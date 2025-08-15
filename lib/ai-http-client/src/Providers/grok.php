<?php
/**
 * AI HTTP Client - Grok Provider
 * 
 * Single Responsibility: Pure Grok/X.AI API communication only
 * No normalization logic - just sends/receives raw data
 * This is a "dumb" API client that the unified normalizers use
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Self-register Grok provider with complete configuration
 * Self-contained provider architecture - no external normalizers needed
 */
add_filter('ai_providers', function($providers) {
    $providers['grok'] = [
        'class' => 'AI_HTTP_Grok_Provider',
        'type' => 'llm',
        'name' => 'Grok'
    ];
    return $providers;
});

class AI_HTTP_Grok_Provider {

    private $api_key;
    private $base_url;

    /**
     * Constructor
     *
     * @param array $config Provider configuration
     */
    public function __construct($config = array()) {
        $this->api_key = isset($config['api_key']) ? $config['api_key'] : '';
        
        if (isset($config['base_url']) && !empty($config['base_url'])) {
            $this->base_url = rtrim($config['base_url'], '/');
        } else {
            $this->base_url = 'https://api.x.ai/v1';
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
     * Get authentication headers for Grok API
     *
     * @return array Headers array
     */
    private function get_auth_headers() {
        return array(
            'Authorization' => 'Bearer ' . $this->api_key
        );
    }

    /**
     * Get provider name for error messages
     *
     * @return string Provider name
     */
    protected function get_provider_name() {
        return 'Grok';
    }

    /**
     * Send request to Grok API
     * Handles all format conversion internally - receives and returns standard format
     *
     * @param array $standard_request Standard request format
     * @return array Standard response format
     * @throws Exception If request fails
     */
    public function request($standard_request) {
        if (!$this->is_configured()) {
            throw new Exception('Grok provider not configured - missing API key');
        }

        // Convert standard format to Grok format internally
        $provider_request = $this->format_request($standard_request);
        
        $url = $this->base_url . '/chat/completions';
        
        // Use centralized ai_http filter
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($provider_request)
        ], 'Grok');
        
        if (!$result['success']) {
            throw new Exception('Grok API request failed: ' . $result['error']);
        }
        
        $raw_response = json_decode($result['data'], true);
        
        // Convert Grok format to standard format
        return $this->format_response($raw_response);
    }

    /**
     * Send streaming request to Grok API
     * Handles all format conversion internally - receives and returns standard format
     *
     * @param array $standard_request Standard request format
     * @param callable $callback Optional callback for each chunk
     * @return array Standard response format
     * @throws Exception If request fails
     */
    public function streaming_request($standard_request, $callback = null) {
        if (!$this->is_configured()) {
            throw new Exception('Grok provider not configured - missing API key');
        }

        // Convert standard format to Grok format internally
        $provider_request = $this->format_request($standard_request);
        
        $url = $this->base_url . '/chat/completions';
        
        // Use centralized ai_http filter with streaming=true
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($provider_request)
        ], 'Grok Streaming', true, $callback);
        
        if (!$result['success']) {
            throw new Exception('Grok streaming request failed: ' . $result['error']);
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
            'provider' => 'grok'
        ];
    }

    /**
     * Get available models from Grok API
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
        ], 'Grok');

        if (!$result['success']) {
            throw new Exception('Grok API request failed: ' . $result['error']);
        }

        return json_decode($result['data'], true);
    }

    /**
     * Upload file to Grok API (OpenAI-compatible)
     * 
     * @param string $file_path Path to file to upload
     * @param string $purpose Purpose for upload (default: 'user_data')
     * @return string File ID from Grok
     * @throws Exception If upload fails
     */
    public function upload_file($file_path, $purpose = 'user_data') {
        if (!$this->is_configured()) {
            throw new Exception('Grok provider not configured');
        }

        if (!file_exists($file_path)) {
            throw new Exception("File not found: {$file_path}");
        }

        // Grok uses OpenAI-compatible file upload endpoint
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
        ], 'Grok File Upload');

        if (!$result['success']) {
            throw new Exception('Grok file upload failed: ' . $result['error']);
        }

        $response_body = $result['data'];

        $data = json_decode($response_body, true);
        if (!isset($data['id'])) {
            throw new Exception('Grok file upload response missing file ID');
        }

        return $data['id'];
    }

    /**
     * Delete file from Grok API (OpenAI-compatible)
     * 
     * @param string $file_id Grok file ID to delete
     * @return bool Success status
     * @throws Exception If delete fails
     */
    public function delete_file($file_id) {
        if (!$this->is_configured()) {
            throw new Exception('Grok provider not configured');
        }

        $url = $this->base_url . "/files/{$file_id}";
        
        // Send request using centralized ai_http filter
        $result = apply_filters('ai_http', [], 'DELETE', $url, [
            'headers' => $this->get_auth_headers()
        ], 'Grok File Delete');

        if (!$result['success']) {
            throw new Exception('Grok file delete failed: ' . $result['error']);
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
     * Normalize Grok models API response (OpenAI-compatible format)
     * 
     * @param array $raw_models Raw API response
     * @return array Normalized models array
     */
    private function normalize_models_response($raw_models) {
        $models = array();
        
        // Grok uses OpenAI-compatible format: { "data": [{"id": "grok-beta", "object": "model", ...}, ...] }
        $data = isset($raw_models['data']) ? $raw_models['data'] : $raw_models;
        if (is_array($data)) {
            foreach ($data as $model) {
                if (isset($model['id'])) {
                    $models[$model['id']] = $model['id'];
                }
            }
        }
        
        return $models;
    }
    
    /**
     * Format unified request to Grok API format (OpenAI-compatible with additions)
     *
     * @param array $unified_request Standard request format
     * @return array Grok-formatted request
     * @throws Exception If validation fails
     */
    private function format_request($unified_request) {
        $this->validate_unified_request($unified_request);
        
        $request = $this->sanitize_common_fields($unified_request);
        
        // Grok uses OpenAI-compatible format, just add reasoning_effort if supported
        if (isset($request['reasoning_effort'])) {
            $request['reasoning_effort'] = sanitize_text_field($request['reasoning_effort']);
        }

        // Process optional parameters (only if explicitly provided)
        // Temperature parameter (OPTIONAL - only if explicitly provided)
        if (isset($request['temperature']) && !empty($request['temperature'])) {
            $request['temperature'] = max(0, min(1, floatval($request['temperature'])));
        }

        // Max tokens parameter (OPTIONAL - only if explicitly provided)
        if (isset($request['max_tokens']) && !empty($request['max_tokens'])) {
            $request['max_tokens'] = max(1, intval($request['max_tokens']));
        }

        return $request;
    }
    
    /**
     * Format Grok response to unified standard format (OpenAI-compatible)
     *
     * @param array $grok_response Raw Grok response
     * @return array Standard response format
     */
    private function format_response($grok_response) {
        // Grok uses OpenAI-compatible format, so use standard Chat Completions parsing
        if (empty($grok_response['choices'])) {
            throw new Exception('Invalid Grok response: missing choices');
        }

        $choice = $grok_response['choices'][0];
        $message = $choice['message'];

        // Extract content and tool calls
        $content = isset($message['content']) ? $message['content'] : '';
        $tool_calls = isset($message['tool_calls']) ? $message['tool_calls'] : null;

        // Extract usage
        $usage = array(
            'prompt_tokens' => isset($grok_response['usage']['prompt_tokens']) ? $grok_response['usage']['prompt_tokens'] : 0,
            'completion_tokens' => isset($grok_response['usage']['completion_tokens']) ? $grok_response['usage']['completion_tokens'] : 0,
            'total_tokens' => isset($grok_response['usage']['total_tokens']) ? $grok_response['usage']['total_tokens'] : 0
        );

        return array(
            'success' => true,
            'data' => array(
                'content' => $content,
                'usage' => $usage,
                'model' => isset($grok_response['model']) ? $grok_response['model'] : '',
                'finish_reason' => isset($choice['finish_reason']) ? $choice['finish_reason'] : 'unknown',
                'tool_calls' => $tool_calls
            ),
            'error' => null,
            'provider' => 'grok',
            'raw_response' => $grok_response
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