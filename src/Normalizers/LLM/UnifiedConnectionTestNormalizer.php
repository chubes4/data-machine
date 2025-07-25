<?php
/**
 * AI HTTP Client - Unified Connection Test Normalizer
 * 
 * Single Responsibility: Standardize connection testing across providers
 * Creates test requests and normalizes test responses
 *
 * @package AIHttpClient\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Unified_Connection_Test_Normalizer {

    /**
     * Create test request for any provider
     *
     * @param string $provider_name Target provider
     * @param array $config Provider configuration
     * @return array Provider-specific test request
     */
    public function create_test_request($provider_name, $config = array()) {
        switch (strtolower($provider_name)) {
            case 'openai':
                return $this->create_openai_test_request($config);
            
            case 'anthropic':
                return $this->create_anthropic_test_request($config);
            
            case 'gemini':
                return $this->create_gemini_test_request($config);
            
            case 'grok':
                return $this->create_grok_test_request($config);
            
            case 'openrouter':
                return $this->create_openrouter_test_request($config);
            
            default:
                throw new Exception("Connection test not supported for provider: {$provider_name}");
        }
    }

    /**
     * Normalize test response from any provider
     *
     * @param array $raw_response Raw provider response
     * @param string $provider_name Source provider
     * @return array Standardized test result
     */
    public function normalize_test_response($raw_response, $provider_name) {
        switch (strtolower($provider_name)) {
            case 'openai':
                return $this->normalize_openai_test_response($raw_response);
            
            case 'anthropic':
                return $this->normalize_anthropic_test_response($raw_response);
            
            case 'gemini':
                return $this->normalize_gemini_test_response($raw_response);
            
            case 'grok':
                return $this->normalize_grok_test_response($raw_response);
            
            case 'openrouter':
                return $this->normalize_openrouter_test_response($raw_response);
            
            default:
                return array(
                    'success' => false,
                    'message' => "Connection test not supported for provider: {$provider_name}"
                );
        }
    }

    /**
     * Create OpenAI test request
     */
    private function create_openai_test_request($config) {
        return array(
            'model' => $config['test_model'] ?? 'gpt-3.5-turbo',
            'input' => array(
                array(
                    'role' => 'user',
                    'content' => 'Test connection - respond with "OK"'
                )
            ),
            'max_output_tokens' => 10
        );
    }

    /**
     * Create Anthropic test request
     */
    private function create_anthropic_test_request($config) {
        return array(
            'model' => $config['test_model'] ?? 'claude-3-haiku-20240307',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Test connection - respond with "OK"'
                )
            ),
            'max_tokens' => 10
        );
    }

    /**
     * Create Gemini test request
     */
    private function create_gemini_test_request($config) {
        return array(
            'contents' => array(
                array(
                    'role' => 'user',
                    'parts' => array(
                        array('text' => 'Test connection - respond with "OK"')
                    )
                )
            ),
            'generationConfig' => array(
                'maxOutputTokens' => 10
            )
        );
    }

    /**
     * Create Grok test request
     */
    private function create_grok_test_request($config) {
        return array(
            'model' => $config['test_model'] ?? 'grok-beta',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Test connection - respond with "OK"'
                )
            ),
            'max_tokens' => 10
        );
    }

    /**
     * Create OpenRouter test request
     */
    private function create_openrouter_test_request($config) {
        return array(
            'model' => $config['test_model'] ?? 'openai/gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Test connection - respond with "OK"'
                )
            ),
            'max_tokens' => 10
        );
    }

    /**
     * Normalize OpenAI test response
     */
    private function normalize_openai_test_response($response) {
        if (isset($response['error'])) {
            return array(
                'success' => false,
                'message' => 'OpenAI API error: ' . ($response['error']['message'] ?? 'Unknown error'),
                'provider' => 'openai'
            );
        }

        // Handle Responses API format
        if (isset($response['response'])) {
            $content = '';
            if (isset($response['response']['output'])) {
                foreach ($response['response']['output'] as $output_item) {
                    if ($output_item['type'] === 'content') {
                        $content .= $output_item['text'] ?? '';
                    }
                }
            }

            return array(
                'success' => true,
                'message' => 'Successfully connected to OpenAI API',
                'model_used' => $response['response']['model'] ?? 'unknown',
                'response_content' => $content,
                'provider' => 'openai'
            );
        }

        // Handle Chat Completions format
        if (isset($response['choices'][0]['message']['content'])) {
            return array(
                'success' => true,
                'message' => 'Successfully connected to OpenAI API',
                'model_used' => $response['model'] ?? 'unknown',
                'response_content' => $response['choices'][0]['message']['content'],
                'provider' => 'openai'
            );
        }

        return array(
            'success' => false,
            'message' => 'Invalid OpenAI response format',
            'provider' => 'openai'
        );
    }

    /**
     * Normalize Anthropic test response
     */
    private function normalize_anthropic_test_response($response) {
        if (isset($response['error'])) {
            return array(
                'success' => false,
                'message' => 'Anthropic API error: ' . ($response['error']['message'] ?? 'Unknown error'),
                'provider' => 'anthropic'
            );
        }

        $content = '';
        if (isset($response['content']) && is_array($response['content'])) {
            foreach ($response['content'] as $content_block) {
                if ($content_block['type'] === 'text') {
                    $content .= $content_block['text'] ?? '';
                }
            }
        }

        if (!empty($content)) {
            return array(
                'success' => true,
                'message' => 'Successfully connected to Anthropic API',
                'model_used' => $response['model'] ?? 'unknown',
                'response_content' => $content,
                'provider' => 'anthropic'
            );
        }

        return array(
            'success' => false,
            'message' => 'Invalid Anthropic response format',
            'provider' => 'anthropic'
        );
    }

    /**
     * Normalize Gemini test response
     */
    private function normalize_gemini_test_response($response) {
        if (isset($response['error'])) {
            return array(
                'success' => false,
                'message' => 'Gemini API error: ' . ($response['error']['message'] ?? 'Unknown error'),
                'provider' => 'gemini'
            );
        }

        $content = '';
        if (isset($response['candidates'][0]['content']['parts'])) {
            foreach ($response['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $content .= $part['text'];
                }
            }
        }

        if (!empty($content)) {
            return array(
                'success' => true,
                'message' => 'Successfully connected to Gemini API',
                'model_used' => $response['modelVersion'] ?? 'unknown',
                'response_content' => $content,
                'provider' => 'gemini'
            );
        }

        return array(
            'success' => false,
            'message' => 'Invalid Gemini response format',
            'provider' => 'gemini'
        );
    }

    /**
     * Normalize Grok test response (OpenAI-compatible)
     */
    private function normalize_grok_test_response($response) {
        $result = $this->normalize_openai_test_response($response);
        $result['provider'] = 'grok';
        $result['message'] = str_replace('OpenAI', 'Grok', $result['message']);
        return $result;
    }

    /**
     * Normalize OpenRouter test response (OpenAI-compatible)
     */
    private function normalize_openrouter_test_response($response) {
        $result = $this->normalize_openai_test_response($response);
        $result['provider'] = 'openrouter';
        $result['message'] = str_replace('OpenAI', 'OpenRouter', $result['message']);
        return $result;
    }

    /**
     * Create error response for connection test
     *
     * @param string $error_message Error message
     * @param string $provider_name Provider name
     * @return array Standardized error response
     */
    public function create_error_response($error_message, $provider_name) {
        return array(
            'success' => false,
            'message' => $error_message,
            'provider' => $provider_name
        );
    }

    /**
     * Validate provider configuration for connection test
     *
     * @param string $provider_name Provider name
     * @param array $config Provider configuration
     * @return array Validation result
     */
    public function validate_provider_config($provider_name, $config) {
        switch (strtolower($provider_name)) {
            case 'openai':
                return $this->validate_openai_config($config);
            
            case 'anthropic':
                return $this->validate_anthropic_config($config);
            
            case 'gemini':
                return $this->validate_gemini_config($config);
            
            case 'grok':
                return $this->validate_grok_config($config);
            
            case 'openrouter':
                return $this->validate_openrouter_config($config);
            
            default:
                return array(
                    'valid' => false,
                    'message' => "Unknown provider: {$provider_name}"
                );
        }
    }

    /**
     * Validate OpenAI configuration
     */
    private function validate_openai_config($config) {
        if (empty($config['api_key'])) {
            return array(
                'valid' => false,
                'message' => 'OpenAI API key is required'
            );
        }

        return array('valid' => true);
    }

    /**
     * Validate Anthropic configuration
     */
    private function validate_anthropic_config($config) {
        if (empty($config['api_key'])) {
            return array(
                'valid' => false,
                'message' => 'Anthropic API key is required'
            );
        }

        return array('valid' => true);
    }

    /**
     * Validate Gemini configuration
     */
    private function validate_gemini_config($config) {
        if (empty($config['api_key'])) {
            return array(
                'valid' => false,
                'message' => 'Gemini API key is required'
            );
        }

        return array('valid' => true);
    }

    /**
     * Validate Grok configuration
     */
    private function validate_grok_config($config) {
        if (empty($config['api_key'])) {
            return array(
                'valid' => false,
                'message' => 'Grok API key is required'
            );
        }

        return array('valid' => true);
    }

    /**
     * Validate OpenRouter configuration
     */
    private function validate_openrouter_config($config) {
        if (empty($config['api_key'])) {
            return array(
                'valid' => false,
                'message' => 'OpenRouter API key is required'
            );
        }

        return array('valid' => true);
    }
}