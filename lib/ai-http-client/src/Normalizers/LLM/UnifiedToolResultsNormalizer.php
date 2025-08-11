<?php
/**
 * AI HTTP Client - Unified Tool Results Normalizer
 * 
 * Single Responsibility: Handle tool calling continuation differences across providers
 * Normalizes tool results for continuation requests
 *
 * @package AIHttpClient\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Unified_Tool_Results_Normalizer {

    /**
     * Normalize tool results for continuation with any provider
     *
     * @param array $tool_results Standard tool results format
     * @param string $provider_name Target provider
     * @param string|array $context_data Provider-specific context (response_id, conversation_history, etc.)
     * @return array Provider-specific continuation request
     */
    public function normalize_for_continuation($tool_results, $provider_name, $context_data) {
        // Use filter-based provider validation and dynamic method dispatch
        $all_providers = apply_filters('ai_providers', []);
        $provider_info = $all_providers[strtolower($provider_name)] ?? null;
        
        if (!$provider_info || $provider_info['type'] !== 'llm') {
            throw new Exception('Tool continuation not supported for specified provider');
        }
        
        // Dynamic method dispatch based on provider name
        $method = "normalize_" . strtolower($provider_name) . "_continuation";
        if (!method_exists($this, $method)) {
            throw new Exception("Tool continuation not implemented for provider: {$provider_name}");
        }
        
        return $this->$method($tool_results, $context_data);
    }

    /**
     * Extract tool calls from provider response for continuation tracking
     *
     * @param array $provider_response Raw provider response
     * @param string $provider_name Source provider
     * @return array Extracted tool calls in standard format
     */
    public function extract_tool_calls($provider_response, $provider_name) {
        // Use filter-based provider validation and dynamic method dispatch
        $all_providers = apply_filters('ai_providers', []);
        $provider_info = $all_providers[strtolower($provider_name)] ?? null;
        
        if (!$provider_info || $provider_info['type'] !== 'llm') {
            return array();
        }
        
        // Dynamic method dispatch based on provider name
        $method = "extract_" . strtolower($provider_name) . "_tool_calls";
        if (!method_exists($this, $method)) {
            return array(); // Graceful fallback if tool extraction not implemented
        }
        
        return $this->$method($provider_response);
    }

    /**
     * OpenAI continuation normalization (uses response_id + function_call_outputs)
     */
    private function normalize_openai_continuation($tool_results, $response_id) {
        if (empty($response_id)) {
            throw new Exception('Response ID required for OpenAI continuation');
        }

        // Format tool results as function_call_outputs for OpenAI Responses API
        $function_call_outputs = array();
        foreach ($tool_results as $result) {
            $function_call_outputs[] = array(
                'type' => 'function_call_output',
                'call_id' => $result['tool_call_id'] ?? $result['id'] ?? uniqid('tool_'),
                'output' => $result['content'] ?? $result['output'] ?? ''
            );
        }

        return array(
            'previous_response_id' => $response_id,
            'input' => $function_call_outputs
        );
    }

    /**
     * Anthropic continuation normalization (uses conversation history)
     */
    private function normalize_anthropic_continuation($tool_results, $conversation_history) {
        if (!is_array($conversation_history)) {
            throw new Exception('Conversation history required for Anthropic continuation');
        }

        // Build new messages array with tool results
        $messages = $conversation_history;
        
        // Add tool results as tool_result messages
        foreach ($tool_results as $result) {
            $messages[] = array(
                'role' => 'user',
                'content' => array(
                    array(
                        'type' => 'tool_result',
                        'tool_use_id' => $result['tool_call_id'] ?? $result['id'] ?? uniqid('tool_'),
                        'content' => $result['content'] ?? $result['output'] ?? ''
                    )
                )
            );
        }

        return array(
            'messages' => $messages
        );
    }

    /**
     * Gemini continuation normalization (uses conversation history with function responses)
     */
    private function normalize_gemini_continuation($tool_results, $conversation_history) {
        if (!is_array($conversation_history)) {
            throw new Exception('Conversation history required for Gemini continuation');
        }

        // Build contents array with function responses
        $contents = $conversation_history;
        
        // Add function responses
        foreach ($tool_results as $result) {
            $contents[] = array(
                'role' => 'function',
                'parts' => array(
                    array(
                        'functionResponse' => array(
                            'name' => $result['function_name'] ?? 'unknown',
                            'response' => array(
                                'result' => $result['content'] ?? $result['output'] ?? ''
                            )
                        )
                    )
                )
            );
        }

        return array(
            'contents' => $contents
        );
    }

    /**
     * Grok continuation normalization (OpenAI-compatible)
     */
    private function normalize_grok_continuation($tool_results, $context_data) {
        // Grok uses OpenAI-compatible format
        return $this->normalize_openai_continuation($tool_results, $context_data);
    }

    /**
     * OpenRouter continuation normalization (OpenAI-compatible)
     */
    private function normalize_openrouter_continuation($tool_results, $context_data) {
        // OpenRouter uses OpenAI-compatible format
        return $this->normalize_openai_continuation($tool_results, $context_data);
    }

    /**
     * Extract tool calls from OpenAI response
     */
    private function extract_openai_tool_calls($response) {
        $tool_calls = array();

        // Handle Responses API format
        if (isset($response['response']['output'])) {
            foreach ($response['response']['output'] as $output_item) {
                if (isset($output_item['type']) && $output_item['type'] === 'function_call') {
                    $tool_calls[] = array(
                        'id' => $output_item['id'] ?? uniqid('tool_'),
                        'function_name' => $output_item['function_call']['name'] ?? '',
                        'arguments' => $output_item['function_call']['arguments'] ?? array()
                    );
                }
            }
        }

        // Handle Chat Completions format
        if (isset($response['choices'][0]['message']['tool_calls'])) {
            foreach ($response['choices'][0]['message']['tool_calls'] as $tool_call) {
                $tool_calls[] = array(
                    'id' => $tool_call['id'] ?? uniqid('tool_'),
                    'function_name' => $tool_call['function']['name'] ?? '',
                    'arguments' => json_decode($tool_call['function']['arguments'] ?? '{}', true)
                );
            }
        }

        return $tool_calls;
    }

    /**
     * Extract tool calls from Anthropic response
     */
    private function extract_anthropic_tool_calls($response) {
        $tool_calls = array();

        if (isset($response['content']) && is_array($response['content'])) {
            foreach ($response['content'] as $content_block) {
                if (isset($content_block['type']) && $content_block['type'] === 'tool_use') {
                    $tool_calls[] = array(
                        'id' => $content_block['id'] ?? uniqid('tool_'),
                        'function_name' => $content_block['name'] ?? '',
                        'arguments' => $content_block['input'] ?? array()
                    );
                }
            }
        }

        return $tool_calls;
    }

    /**
     * Extract tool calls from Gemini response
     */
    private function extract_gemini_tool_calls($response) {
        $tool_calls = array();

        if (isset($response['candidates'][0]['content']['parts'])) {
            foreach ($response['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['functionCall'])) {
                    $tool_calls[] = array(
                        'id' => uniqid('tool_'),
                        'function_name' => $part['functionCall']['name'] ?? '',
                        'arguments' => $part['functionCall']['args'] ?? array()
                    );
                }
            }
        }

        return $tool_calls;
    }

    /**
     * Extract tool calls from Grok response (OpenAI-compatible)
     */
    private function extract_grok_tool_calls($response) {
        return $this->extract_openai_tool_calls($response);
    }

    /**
     * Extract tool calls from OpenRouter response (OpenAI-compatible)
     */
    private function extract_openrouter_tool_calls($response) {
        return $this->extract_openai_tool_calls($response);
    }

    /**
     * Convert standard tool results to provider-specific format
     *
     * @param array $standard_results Standard tool results
     * @param string $provider_name Target provider
     * @return array Provider-specific tool results
     */
    public function format_tool_results($standard_results, $provider_name) {
        // Use existing ai_providers filter for tool formatting configuration
        $all_providers = apply_filters('ai_providers', []);
        $provider_info = $all_providers[strtolower($provider_name)] ?? null;
        
        if (!$provider_info || $provider_info['type'] !== 'llm' || !isset($provider_info['tool_format'])) {
            return array();
        }
        
        $tool_config = $provider_info['tool_format'];
        $formatted = array();
        
        foreach ($standard_results as $result) {
            $formatted[] = array(
                $tool_config['id_field'] => $result['tool_call_id'] ?? $result['id'] ?? uniqid('tool_'),
                $tool_config['content_field'] => $result['content'] ?? $result['output'] ?? ''
            );
        }
        
        return $formatted;
    }
}