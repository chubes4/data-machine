<?php
/**
 * AI HTTP Client - Gemini Provider
 * 
 * Single Responsibility: Pure Google Gemini API communication only
 * No normalization logic - just sends/receives raw data
 * This is a "dumb" API client that the unified normalizers use
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Self-register Gemini provider with complete configuration
 * Self-contained provider architecture - no external normalizers needed
 */
add_filter('ai_providers', function($providers) {
    $providers['gemini'] = [
        'class' => 'AI_HTTP_Gemini_Provider',
        'type' => 'llm',
        'name' => 'Google Gemini'
    ];
    return $providers;
});

class AI_HTTP_Gemini_Provider {

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
            $this->base_url = 'https://generativelanguage.googleapis.com/v1beta';
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
     * Get authentication headers for Gemini API
     *
     * @return array Headers array
     */
    private function get_auth_headers() {
        return array(
            'x-goog-api-key' => $this->api_key
        );
    }

    /**
     * Build Gemini URL with model in path and prepare request data
     *
     * @param array $provider_request Request data
     * @param string $endpoint_suffix Endpoint suffix (e.g., ':generateContent')
     * @return array [url, modified_request]
     */
    private function build_gemini_url_and_request($provider_request, $endpoint_suffix) {
        $model = isset($provider_request['model']) ? $provider_request['model'] : 'gemini-pro';
        $url = $this->base_url . '/models/' . $model . $endpoint_suffix;
        
        // Remove model from request body (it's in the URL)
        unset($provider_request['model']);
        
        return array($url, $provider_request);
    }

    /**
     * Send request to Gemini API
     * Handles all format conversion internally - receives and returns standard format
     *
     * @param array $standard_request Standard request format
     * @return array Standard response format
     * @throws Exception If request fails
     */
    public function request($standard_request) {
        if (!$this->is_configured()) {
            throw new Exception('Gemini provider not configured - missing API key');
        }

        // Convert standard format to Gemini format internally
        $provider_request = $this->format_request($standard_request);
        
        list($url, $modified_request) = $this->build_gemini_url_and_request($provider_request, ':generateContent');
        
        // Use centralized ai_http filter
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($modified_request)
        ], 'Gemini');
        
        if (!$result['success']) {
            throw new Exception('Gemini API request failed: ' . $result['error']);
        }
        
        $raw_response = json_decode($result['data'], true);
        
        // Convert Gemini format to standard format
        return $this->format_response($raw_response);
    }

    /**
     * Send streaming request to Gemini API
     * Handles all format conversion internally - receives and returns standard format
     *
     * @param array $standard_request Standard request format
     * @param callable $callback Optional callback for each chunk
     * @return array Standard response format
     * @throws Exception If request fails
     */
    public function streaming_request($standard_request, $callback = null) {
        if (!$this->is_configured()) {
            throw new Exception('Gemini provider not configured - missing API key');
        }

        // Convert standard format to Gemini format internally
        $provider_request = $this->format_request($standard_request);
        
        list($url, $modified_request) = $this->build_gemini_url_and_request($provider_request, ':streamGenerateContent');
        
        // Use centralized ai_http filter with streaming=true
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($modified_request)
        ], 'Gemini Streaming', true, $callback);
        
        if (!$result['success']) {
            throw new Exception('Gemini streaming request failed: ' . $result['error']);
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
            'provider' => 'gemini'
        ];
    }

    /**
     * Get available models from Gemini API
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
        ], 'Gemini');

        if (!$result['success']) {
            throw new Exception('Gemini API request failed: ' . $result['error']);
        }

        return json_decode($result['data'], true);
    }

    /**
     * Upload file to Google Gemini File API
     * 
     * @param string $file_path Path to file to upload
     * @param string $purpose Purpose for upload (default: 'user_data')
     * @return string File URI from Google
     * @throws Exception If upload fails
     */
    public function upload_file($file_path, $purpose = 'user_data') {
        if (!$this->is_configured()) {
            throw new Exception('Gemini provider not configured');
        }

        if (!file_exists($file_path)) {
            throw new Exception("File not found: {$file_path}");
        }

        // Google Gemini file upload endpoint
        $url = 'https://generativelanguage.googleapis.com/upload/v1beta/files?uploadType=multipart&key=' . $this->api_key;
        
        // Prepare multipart form data
        $boundary = wp_generate_uuid4();
        $headers = [
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary
        ];

        // Build multipart body with metadata and file
        $body = '';
        
        // Metadata part
        $metadata = json_encode([
            'file' => [
                'display_name' => basename($file_path)
            ]
        ]);
        
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"metadata\"\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= $metadata . "\r\n";
        
        // File part
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="data"; filename="' . basename($file_path) . "\"\r\n";
        $body .= "Content-Type: " . mime_content_type($file_path) . "\r\n\r\n";
        $body .= file_get_contents($file_path) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        // Send request using centralized ai_http filter
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => $body
        ], 'Gemini File Upload');

        if (!$result['success']) {
            throw new Exception('Gemini file upload failed: ' . $result['error']);
        }

        $response_body = $result['data'];

        $data = json_decode($response_body, true);
        if (!isset($data['file']['uri'])) {
            throw new Exception('Gemini file upload response missing file URI');
        }

        return $data['file']['uri'];
    }

    /**
     * Delete file from Google Gemini File API
     * 
     * @param string $file_uri Gemini file URI to delete
     * @return bool Success status
     * @throws Exception If delete fails
     */
    public function delete_file($file_uri) {
        if (!$this->is_configured()) {
            throw new Exception('Gemini provider not configured');
        }

        // Extract file name from URI
        $file_name = basename(parse_url($file_uri, PHP_URL_PATH));
        $url = "https://generativelanguage.googleapis.com/v1beta/files/{$file_name}?key=" . $this->api_key;
        
        // Send request using centralized ai_http filter
        $result = apply_filters('ai_http', [], 'DELETE', $url, [], 'Gemini File Delete');

        if (!$result['success']) {
            throw new Exception('Gemini file delete failed: ' . $result['error']);
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
     * Normalize Gemini models API response
     * 
     * @param array $raw_models Raw API response
     * @return array Normalized models array
     */
    private function normalize_models_response($raw_models) {
        $models = array();
        
        // Gemini returns: { "models": [{"name": "models/gemini-pro", "displayName": "Gemini Pro", ...}, ...] }
        $data = isset($raw_models['models']) ? $raw_models['models'] : $raw_models;
        if (is_array($data)) {
            foreach ($data as $model) {
                if (isset($model['name'])) {
                    $model_id = str_replace('models/', '', $model['name']);
                    $display_name = isset($model['displayName']) ? $model['displayName'] : $model_id;
                    $models[$model_id] = $display_name;
                }
            }
        }
        
        return $models;
    }
    
    /**
     * Format unified request to Gemini API format
     *
     * @param array $unified_request Standard request format
     * @return array Gemini-formatted request
     * @throws Exception If validation fails
     */
    private function format_request($unified_request) {
        $this->validate_unified_request($unified_request);
        
        $request = $this->sanitize_common_fields($unified_request);
        
        // Convert messages to Gemini contents format
        if (isset($request['messages'])) {
            $request['contents'] = $this->convert_to_gemini_contents($request['messages']);
            unset($request['messages']);
        }

        // Gemini uses maxOutputTokens
        if (isset($request['max_tokens'])) {
            $request['generationConfig']['maxOutputTokens'] = max(1, intval($request['max_tokens']));
            unset($request['max_tokens']);
        }

        // Gemini temperature in generationConfig
        if (isset($request['temperature'])) {
            $request['generationConfig']['temperature'] = max(0, min(1, floatval($request['temperature'])));
            unset($request['temperature']);
        }

        return $request;
    }
    
    /**
     * Format Gemini response to unified standard format
     *
     * @param array $gemini_response Raw Gemini response
     * @return array Standard response format
     */
    private function format_response($gemini_response) {
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
    
    /**
     * Convert messages to Gemini contents format
     *
     * @param array $messages Standard messages
     * @return array Gemini contents format
     */
    private function convert_to_gemini_contents($messages) {
        $contents = array();

        foreach ($messages as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                continue;
            }

            // Map roles
            $role = $message['role'] === 'assistant' ? 'model' : 'user';
            
            // Skip system messages for now (Gemini handles differently)
            if ($message['role'] === 'system') {
                continue;
            }

            $contents[] = array(
                'role' => $role,
                'parts' => array(
                    array('text' => $message['content'])
                )
            );
        }

        return $contents;
    }
    
}