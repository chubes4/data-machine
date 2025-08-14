<?php
/**
 * AI HTTP Client - OpenRouter Provider
 * 
 * Single Responsibility: Pure OpenRouter API communication only
 * No normalization logic - just sends/receives raw data
 * This is a "dumb" API client that the unified normalizers use
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Self-register OpenRouter provider with complete configuration
 * Self-contained provider architecture - no external normalizers needed
 */
add_filter('ai_providers', function($providers) {
    $providers['openrouter'] = [
        'class' => 'AI_HTTP_OpenRouter_Provider',
        'type' => 'llm',
        'name' => 'OpenRouter'
    ];
    return $providers;
});

class AI_HTTP_OpenRouter_Provider {

    private $api_key;
    private $base_url;

    private $http_referer;
    private $app_title;

    /**
     * Constructor
     *
     * @param array $config Provider configuration
     */
    public function __construct($config = array()) {
        $this->api_key = isset($config['api_key']) ? $config['api_key'] : '';
        $this->http_referer = isset($config['http_referer']) ? $config['http_referer'] : '';
        $this->app_title = isset($config['app_title']) ? $config['app_title'] : 'AI HTTP Client';
        
        if (isset($config['base_url']) && !empty($config['base_url'])) {
            $this->base_url = rtrim($config['base_url'], '/');
        } else {
            $this->base_url = 'https://openrouter.ai/api/v1';
        }
    }

    /**
     * Check if provider is configured
     *
     * @return bool True if configured
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Get authentication headers for OpenRouter API
     * Includes optional HTTP-Referer and X-Title headers
     *
     * @return array Headers array
     */
    private function get_auth_headers() {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key
        );

        if (!empty($this->http_referer)) {
            $headers['HTTP-Referer'] = $this->http_referer;
        }

        if (!empty($this->app_title)) {
            $headers['X-Title'] = $this->app_title;
        }

        return $headers;
    }

    /**
     * Send request to OpenRouter API
     * Handles all format conversion internally - receives and returns standard format
     *
     * @param array $standard_request Standard request format
     * @return array Standard response format
     * @throws Exception If request fails
     */
    public function request($standard_request) {
        if (!$this->is_configured()) {
            throw new Exception('OpenRouter provider not configured - missing API key');
        }

        // Convert standard format to OpenRouter format internally
        $provider_request = $this->format_request($standard_request);
        
        $url = $this->base_url . '/chat/completions';
        
        // Use centralized ai_http filter
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($provider_request)
        ], 'OpenRouter');
        
        if (!$result['success']) {
            throw new Exception('OpenRouter API request failed: ' . $result['error']);
        }
        
        $raw_response = json_decode($result['data'], true);
        
        // Convert OpenRouter format to standard format
        return $this->format_response($raw_response);
    }

    /**
     * Send streaming request to OpenRouter API
     * Handles all format conversion internally - receives and returns standard format
     *
     * @param array $standard_request Standard request format
     * @param callable $callback Optional callback for each chunk
     * @return array Standard response format
     * @throws Exception If request fails
     */
    public function streaming_request($standard_request, $callback = null) {
        if (!$this->is_configured()) {
            throw new Exception('OpenRouter provider not configured - missing API key');
        }

        // Convert standard format to OpenRouter format internally
        $provider_request = $this->format_request($standard_request);
        
        $url = $this->base_url . '/chat/completions';
        
        // Use centralized ai_http filter with streaming=true
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($provider_request)
        ], 'OpenRouter Streaming', true, $callback);
        
        if (!$result['success']) {
            throw new Exception('OpenRouter streaming request failed: ' . $result['error']);
        }

        // Return standardized streaming response
        return [
            'success' => true,
            'data' => [
                'content' => '',
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'model' => $standard_request['model'] ?? '',
                'finish_reason' => 'stop',
                'tool_calls' => null
            ],
            'error' => null,
            'provider' => 'openrouter'
        ];
    }

    /**
     * Get available models from OpenRouter API
     *
     * @return array Raw models response
     * @throws Exception If request fails
     */
    public function get_raw_models() {
        if (!$this->is_configured()) {
            return array();
        }

        $url = $this->base_url . '/models';
        
        // Use centralized ai_http filter
        $result = apply_filters('ai_http', [], 'GET', $url, [
            'headers' => $this->get_auth_headers()
        ], 'OpenRouter');

        if (!$result['success']) {
            throw new Exception('OpenRouter API request failed: ' . $result['error']);
        }

        return json_decode($result['data'], true);
    }

    /**
     * Upload file to OpenRouter API (OpenAI-compatible)
     * 
     * @param string $file_path Path to file to upload
     * @param string $purpose Purpose for upload (default: 'user_data')
     * @return string File ID from OpenRouter
     * @throws Exception If upload fails
     */
    public function upload_file($file_path, $purpose = 'user_data') {
        if (!$this->is_configured()) {
            throw new Exception('OpenRouter provider not configured');
        }

        if (!file_exists($file_path)) {
            throw new Exception("File not found: {$file_path}");
        }

        // OpenRouter uses OpenAI-compatible file upload endpoint
        $url = $this->base_url . '/files';
        
        // Prepare multipart form data (same as OpenAI)
        $boundary = wp_generate_uuid4();
        $headers = array_merge($this->get_auth_headers(), [
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary
        ]);

        // Build multipart body
        $body = '';
        
        // Purpose field
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"purpose\"\r\n\r\n";
        $body .= $purpose . "\r\n";
        
        // File field
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file_path) . "\"\r\n";
        $body .= "Content-Type: " . mime_content_type($file_path) . "\r\n\r\n";
        $body .= file_get_contents($file_path) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        // Send request using centralized ai_http filter
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => $body
        ], 'OpenRouter File Upload');

        if (!$result['success']) {
            throw new Exception('OpenRouter file upload failed: ' . $result['error']);
        }

        $response_body = $result['data'];

        $data = json_decode($response_body, true);
        if (!isset($data['id'])) {
            throw new Exception('OpenRouter file upload response missing file ID');
        }

        return $data['id'];
    }

    /**
     * Delete file from OpenRouter API (OpenAI-compatible)
     * 
     * @param string $file_id OpenRouter file ID to delete
     * @return bool Success status
     * @throws Exception If delete fails
     */
    public function delete_file($file_id) {
        if (!$this->is_configured()) {
            throw new Exception('OpenRouter provider not configured');
        }

        $url = $this->base_url . "/files/{$file_id}";
        
        // Send request using centralized ai_http filter
        $result = apply_filters('ai_http', [], 'DELETE', $url, [
            'headers' => $this->get_auth_headers()
        ], 'OpenRouter File Delete');

        if (!$result['success']) {
            throw new Exception('OpenRouter file delete failed: ' . $result['error']);
        }

        return $result['status_code'] === 200;
    }

    /**
     * Get normalized models for UI components
     * 
     * @return array Key-value array of model_id => display_name
     * @throws Exception If API call fails
     */
    public function get_normalized_models() {
        $raw_models = $this->get_raw_models();
        return $this->normalize_models_response($raw_models);
    }
    
    /**
     * Normalize OpenRouter models API response
     * 
     * @param array $raw_models Raw API response
     * @return array Normalized models array
     */
    private function normalize_models_response($raw_models) {
        $models = array();
        
        // OpenRouter returns: { "data": [{"id": "model-name", "name": "Display Name", ...}, ...] }
        $data = isset($raw_models['data']) ? $raw_models['data'] : $raw_models;
        if (is_array($data)) {
            foreach ($data as $model) {
                if (isset($model['id'])) {
                    $display_name = isset($model['name']) ? $model['name'] : $model['id'];
                    $models[$model['id']] = $display_name;
                }
            }
        }
        
        return $models;
    }
    
    /**
     * Format unified request to OpenRouter API format (OpenAI-compatible)
     *
     * @param array $unified_request Standard request format
     * @return array OpenRouter-formatted request
     * @throws Exception If validation fails
     */
    private function format_request($unified_request) {
        $this->validate_unified_request($unified_request);
        
        $request = $this->sanitize_common_fields($unified_request);
        
        // OpenRouter uses OpenAI-compatible format
        if (isset($request['temperature'])) {
            $request['temperature'] = max(0, min(1, floatval($request['temperature'])));
        }

        if (isset($request['max_tokens'])) {
            $request['max_tokens'] = max(1, intval($request['max_tokens']));
        }

        return $request;
    }
    
    /**
     * Format OpenRouter response to unified standard format (OpenAI-compatible)
     *
     * @param array $openrouter_response Raw OpenRouter response
     * @return array Standard response format
     */
    private function format_response($openrouter_response) {
        // OpenRouter uses OpenAI-compatible format, so use standard Chat Completions parsing
        if (empty($openrouter_response['choices'])) {
            throw new Exception('Invalid OpenRouter response: missing choices');
        }

        $choice = $openrouter_response['choices'][0];
        $message = $choice['message'];

        // Extract content and tool calls
        $content = isset($message['content']) ? $message['content'] : '';
        $tool_calls = isset($message['tool_calls']) ? $message['tool_calls'] : null;

        // Extract usage
        $usage = array(
            'prompt_tokens' => isset($openrouter_response['usage']['prompt_tokens']) ? $openrouter_response['usage']['prompt_tokens'] : 0,
            'completion_tokens' => isset($openrouter_response['usage']['completion_tokens']) ? $openrouter_response['usage']['completion_tokens'] : 0,
            'total_tokens' => isset($openrouter_response['usage']['total_tokens']) ? $openrouter_response['usage']['total_tokens'] : 0
        );

        return array(
            'success' => true,
            'data' => array(
                'content' => $content,
                'usage' => $usage,
                'model' => isset($openrouter_response['model']) ? $openrouter_response['model'] : '',
                'finish_reason' => isset($choice['finish_reason']) ? $choice['finish_reason'] : 'unknown',
                'tool_calls' => $tool_calls
            ),
            'error' => null,
            'provider' => 'openrouter',
            'raw_response' => $openrouter_response
        );
    }
    
    /**
     * Validate unified request format
     *
     * @param array $request Request to validate
     * @throws Exception If invalid
     */
    private function validate_unified_request($request) {
        if (!is_array($request)) {
            throw new Exception('Request must be an array');
        }

        if (!isset($request['messages']) || !is_array($request['messages'])) {
            throw new Exception('Request must include messages array');
        }

        if (empty($request['messages'])) {
            throw new Exception('Messages array cannot be empty');
        }
    }
    
    /**
     * Sanitize common fields
     *
     * @param array $request Request to sanitize
     * @return array Sanitized request
     */
    private function sanitize_common_fields($request) {
        // Sanitize messages
        if (isset($request['messages'])) {
            foreach ($request['messages'] as &$message) {
                if (isset($message['role'])) {
                    $message['role'] = sanitize_text_field($message['role']);
                }
                if (isset($message['content']) && is_string($message['content'])) {
                    $message['content'] = sanitize_textarea_field($message['content']);
                }
            }
        }

        // Sanitize other common fields
        if (isset($request['model'])) {
            $request['model'] = sanitize_text_field($request['model']);
        }

        return $request;
    }
    
}