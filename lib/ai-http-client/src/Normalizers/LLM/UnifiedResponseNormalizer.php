<?php
/**
 * AI HTTP Client - Unified Response Normalizer
 * 
 * Single Responsibility: Convert ANY provider response to standard format
 * This is the sole point of response normalization for all providers
 *
 * @package AIHttpClient\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Unified_Response_Normalizer {

    private $provider;

    public function __construct($provider = null) {
        $this->provider = $provider;
    }

    /**
     * Normalize response from any provider to standard format
     *
     * @param array $provider_response Raw provider response
     * @param string $provider_name Source provider (openai, anthropic, gemini, etc.)
     * @return array Standardized response format
     * @throws Exception If provider not supported
     */
    public function normalize($provider_response, $provider_name) {
        // Use filter-based provider validation and dynamic method dispatch
        $all_providers = apply_filters('ai_providers', []);
        $provider_info = $all_providers[strtolower($provider_name)] ?? null;
        
        if (!$provider_info || $provider_info['type'] !== 'llm') {
            throw new Exception('Unsupported provider specified');
        }
        
        // Dynamic method dispatch based on provider name
        $method = "normalize_from_" . strtolower($provider_name);
        if (!method_exists($this, $method)) {
            throw new Exception("Response normalization not implemented for provider: {$provider_name}");
        }
        
        return $this->$method($provider_response);
    }

    /**
     * Normalize OpenAI response (handles both Responses API and Chat Completions)
     *
     * @param array $openai_response Raw OpenAI response
     * @return array Standard format
     */
    private function normalize_from_openai($openai_response) {
        // Handle OpenAI Responses API format (primary) - detect by object type
        if (isset($openai_response['object']) && $openai_response['object'] === 'response') {
            return $this->normalize_openai_responses_api($openai_response);
        }
        
        // Handle Chat Completions API format (fallback)
        if (isset($openai_response['choices'])) {
            return $this->normalize_openai_chat_completions($openai_response);
        }
        
        // Handle streaming format
        if (isset($openai_response['content']) && !isset($openai_response['choices'])) {
            return $this->normalize_openai_streaming($openai_response);
        }
        
        throw new Exception('Invalid OpenAI response format');
    }

    /**
     * Normalize Anthropic response
     *
     * @param array $anthropic_response Raw Anthropic response
     * @return array Standard format
     */
    private function normalize_from_anthropic($anthropic_response) {
        $content = '';
        $tool_calls = array();

        // Extract content
        if (isset($anthropic_response['content']) && is_array($anthropic_response['content'])) {
            foreach ($anthropic_response['content'] as $content_block) {
                if (isset($content_block['type'])) {
                    switch ($content_block['type']) {
                        case 'text':
                            $content .= $content_block['text'] ?? '';
                            break;
                        case 'tool_use':
                            $tool_calls[] = array(
                                'id' => $content_block['id'] ?? uniqid('tool_'),
                                'type' => 'function',
                                'function' => array(
                                    'name' => $content_block['name'] ?? '',
                                    'arguments' => wp_json_encode($content_block['input'] ?? array())
                                )
                            );
                            break;
                    }
                }
            }
        }

        // Extract usage
        $usage = array(
            'prompt_tokens' => isset($anthropic_response['usage']['input_tokens']) ? $anthropic_response['usage']['input_tokens'] : 0,
            'completion_tokens' => isset($anthropic_response['usage']['output_tokens']) ? $anthropic_response['usage']['output_tokens'] : 0,
            'total_tokens' => 0
        );
        $usage['total_tokens'] = $usage['prompt_tokens'] + $usage['completion_tokens'];

        return array(
            'success' => true,
            'data' => array(
                'content' => $content,
                'usage' => $usage,
                'model' => $anthropic_response['model'] ?? '',
                'finish_reason' => $anthropic_response['stop_reason'] ?? 'unknown',
                'tool_calls' => !empty($tool_calls) ? $tool_calls : null
            ),
            'error' => null,
            'provider' => 'anthropic',
            'raw_response' => $anthropic_response
        );
    }

    /**
     * Normalize Google Gemini response
     *
     * @param array $gemini_response Raw Gemini response
     * @return array Standard format
     */
    private function normalize_from_gemini($gemini_response) {
        $content = '';
        $tool_calls = array();

        // Extract content from candidates
        if (isset($gemini_response['candidates']) && is_array($gemini_response['candidates'])) {
            $candidate = $gemini_response['candidates'][0] ?? array();
            
            if (isset($candidate['content']['parts']) && is_array($candidate['content']['parts'])) {
                foreach ($candidate['content']['parts'] as $part) {
                    if (isset($part['text'])) {
                        $content .= $part['text'];
                    }
                    if (isset($part['functionCall'])) {
                        $tool_calls[] = array(
                            'id' => uniqid('tool_'),
                            'type' => 'function',
                            'function' => array(
                                'name' => $part['functionCall']['name'] ?? '',
                                'arguments' => wp_json_encode($part['functionCall']['args'] ?? array())
                            )
                        );
                    }
                }
            }
        }

        // Extract usage (Gemini format)
        $usage = array(
            'prompt_tokens' => isset($gemini_response['usageMetadata']['promptTokenCount']) ? $gemini_response['usageMetadata']['promptTokenCount'] : 0,
            'completion_tokens' => isset($gemini_response['usageMetadata']['candidatesTokenCount']) ? $gemini_response['usageMetadata']['candidatesTokenCount'] : 0,
            'total_tokens' => isset($gemini_response['usageMetadata']['totalTokenCount']) ? $gemini_response['usageMetadata']['totalTokenCount'] : 0
        );

        return array(
            'success' => true,
            'data' => array(
                'content' => $content,
                'usage' => $usage,
                'model' => $gemini_response['modelVersion'] ?? '',
                'finish_reason' => isset($gemini_response['candidates'][0]['finishReason']) ? $gemini_response['candidates'][0]['finishReason'] : 'unknown',
                'tool_calls' => !empty($tool_calls) ? $tool_calls : null
            ),
            'error' => null,
            'provider' => 'gemini',
            'raw_response' => $gemini_response
        );
    }

    /**
     * Normalize Grok response (OpenAI-compatible)
     *
     * @param array $grok_response Raw Grok response
     * @return array Standard format
     */
    private function normalize_from_grok($grok_response) {
        // Grok uses OpenAI-compatible format, so reuse OpenAI logic
        return $this->normalize_from_openai($grok_response);
    }

    /**
     * Normalize OpenRouter response (OpenAI-compatible)
     *
     * @param array $openrouter_response Raw OpenRouter response
     * @return array Standard format
     */
    private function normalize_from_openrouter($openrouter_response) {
        // OpenRouter uses OpenAI-compatible format, so reuse OpenAI logic
        return $this->normalize_from_openai($openrouter_response);
    }

    /**
     * Normalize OpenAI Responses API format
     *
     * @param array $response Raw Responses API response
     * @return array Standard format
     */
    private function normalize_openai_responses_api($response) {
        // Extract response ID for continuation - it's at the top level, not nested
        $response_id = isset($response['id']) ? $response['id'] : null;
        if ($response_id && $this->provider) {
            $this->provider->set_last_response_id($response_id);
        }
        
        // Extract content and tool calls from output
        $content = '';
        $tool_calls = array();
        
        if (isset($response['output']) && is_array($response['output'])) {
            foreach ($response['output'] as $output_item) {
                // Handle message type output items (the actual response format)
                if (isset($output_item['type']) && $output_item['type'] === 'message') {
                    if (isset($output_item['content']) && is_array($output_item['content'])) {
                        foreach ($output_item['content'] as $content_item) {
                            if (isset($content_item['type']) && $content_item['type'] === 'output_text') {
                                $content .= isset($content_item['text']) ? $content_item['text'] : '';
                            }
                        }
                    }
                }
                // Handle direct content types (fallback)
                elseif (isset($output_item['type'])) {
                    switch ($output_item['type']) {
                        case 'content':
                        case 'output_text':
                            $content .= isset($output_item['text']) ? $output_item['text'] : '';
                            break;
                        case 'function_call':
                            if (isset($output_item['status']) && $output_item['status'] === 'completed') {
                                $tool_calls[] = array(
                                    'id' => $output_item['id'] ?? uniqid('tool_'),
                                    'type' => 'function',
                                    'function' => array(
                                        'name' => $output_item['function_call']['name'],
                                        'arguments' => wp_json_encode($output_item['function_call']['arguments'] ?? array())
                                    )
                                );
                            }
                            break;
                    }
                }
            }
        }

        // Extract usage - it's at the top level
        $usage = array(
            'prompt_tokens' => isset($response['usage']['input_tokens']) ? $response['usage']['input_tokens'] : 0,
            'completion_tokens' => isset($response['usage']['output_tokens']) ? $response['usage']['output_tokens'] : 0,
            'total_tokens' => isset($response['usage']['total_tokens']) ? $response['usage']['total_tokens'] : 0
        );

        return array(
            'success' => true,
            'data' => array(
                'content' => $content,
                'usage' => $usage,
                'model' => isset($response['model']) ? $response['model'] : '',
                'finish_reason' => isset($response['status']) ? $response['status'] : 'unknown',
                'tool_calls' => !empty($tool_calls) ? $tool_calls : null,
                'response_id' => $response_id
            ),
            'error' => null,
            'provider' => 'openai',
            'raw_response' => $response
        );
    }

    /**
     * Normalize OpenAI Chat Completions API format
     *
     * @param array $response Raw Chat Completions response
     * @return array Standard format
     */
    private function normalize_openai_chat_completions($response) {
        if (empty($response['choices'])) {
            throw new Exception('Invalid OpenAI response: missing choices');
        }

        $choice = $response['choices'][0];
        $message = $choice['message'];

        // Extract content and tool calls
        $content = isset($message['content']) ? $message['content'] : '';
        $tool_calls = isset($message['tool_calls']) ? $message['tool_calls'] : null;

        // Extract usage
        $usage = array(
            'prompt_tokens' => isset($response['usage']['prompt_tokens']) ? $response['usage']['prompt_tokens'] : 0,
            'completion_tokens' => isset($response['usage']['completion_tokens']) ? $response['usage']['completion_tokens'] : 0,
            'total_tokens' => isset($response['usage']['total_tokens']) ? $response['usage']['total_tokens'] : 0
        );

        return array(
            'success' => true,
            'data' => array(
                'content' => $content,
                'usage' => $usage,
                'model' => isset($response['model']) ? $response['model'] : '',
                'finish_reason' => isset($choice['finish_reason']) ? $choice['finish_reason'] : 'unknown',
                'tool_calls' => $tool_calls
            ),
            'error' => null,
            'provider' => 'openai',
            'raw_response' => $response
        );
    }

    /**
     * Normalize OpenAI streaming response format
     *
     * @param array $response Streaming response
     * @return array Standard format
     */
    private function normalize_openai_streaming($response) {
        $content = isset($response['content']) ? $response['content'] : '';
        
        return array(
            'success' => true,
            'data' => array(
                'content' => $content,
                'usage' => array(
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0
                ),
                'model' => isset($response['model']) ? $response['model'] : '',
                'finish_reason' => 'stop',
                'tool_calls' => null
            ),
            'error' => null,
            'provider' => 'openai',
            'raw_response' => $response
        );
    }

    /**
     * Create error response in standard format
     *
     * @param string $error_message Error message
     * @param string $provider_name Provider name
     * @param array $raw_response Raw response for debugging
     * @return array Standard error response
     */
    public function create_error_response($error_message, $provider_name = 'unknown', $raw_response = null) {
        return array(
            'success' => false,
            'data' => null,
            'error' => $error_message,
            'provider' => $provider_name,
            'raw_response' => $raw_response
        );
    }
}