<?php
/**
 * AI HTTP Client - Anthropic Provider
 * 
 * Single Responsibility: Pure Anthropic API communication only
 * No normalization logic - just sends/receives raw data
 * This is a "dumb" API client that the unified normalizers use
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Self-register Anthropic provider with complete configuration
 * Includes normalizer specifications for self-contained provider architecture
 */
add_filter('ai_providers', function($providers) {
    $providers['anthropic'] = [
        'class' => 'AI_HTTP_Anthropic_Provider', 
        'type' => 'llm',
        'name' => 'Anthropic',
        'normalizers' => [
            'request' => 'AI_HTTP_Unified_Request_Normalizer',
            'response' => 'AI_HTTP_Unified_Response_Normalizer',
            'streaming' => 'AI_HTTP_Unified_Streaming_Normalizer',
            'tool_results' => 'AI_HTTP_Unified_Tool_Results_Normalizer'
        ],
        'tool_format' => [
            'id_field' => 'tool_use_id',
            'content_field' => 'content'
        ]
    ];
    return $providers;
});

class AI_HTTP_Anthropic_Provider {

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
            $this->base_url = 'https://api.anthropic.com/v1';
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
     * Get authentication headers for Anthropic API
     *
     * @return array Headers array
     */
    private function get_auth_headers() {
        return array(
            'x-api-key' => $this->api_key,
            'anthropic-version' => '2023-06-01'
        );
    }

    /**
     * Send raw request to Anthropic API
     *
     * @param array $provider_request Already normalized for Anthropic
     * @return array Raw Anthropic response
     * @throws Exception If request fails
     */
    public function send_raw_request($provider_request) {
        if (!$this->is_configured()) {
            throw new Exception('Anthropic provider not configured - missing API key');
        }

        $url = $this->base_url . '/messages';
        
        // Use centralized ai_http filter
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($provider_request)
        ], 'Anthropic');
        
        if (!$result['success']) {
            throw new Exception('Anthropic API request failed: ' . $result['error']);
        }
        
        return json_decode($result['data'], true);
    }

    /**
     * Send raw streaming request to Anthropic API
     *
     * @param array $provider_request Already normalized for Anthropic
     * @param callable $callback Optional callback for each chunk
     * @return string Full response content
     * @throws Exception If request fails
     */
    public function send_raw_streaming_request($provider_request, $callback = null) {
        if (!$this->is_configured()) {
            throw new Exception('Anthropic provider not configured - missing API key');
        }

        $url = $this->base_url . '/messages';
        
        // Use centralized ai_http filter with streaming=true
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($provider_request)
        ], 'Anthropic Streaming', true, $callback);
        
        if (!$result['success']) {
            throw new Exception('Anthropic streaming request failed: ' . $result['error']);
        }

        return '';
    }

    /**
     * Get available models from Anthropic API
     * Note: Anthropic doesn't have a models endpoint, so return empty array
     *
     * @return array Empty array (Anthropic doesn't have a models endpoint)
     */
    public function get_raw_models() {
        if (!$this->is_configured()) {
            return array();
        }

        // Anthropic doesn't have a models endpoint
        // Model names are hardcoded: claude-3-5-sonnet-20241022, claude-3-haiku-20240307, etc.
        return array();
    }

    /**
     * Upload file to Anthropic API
     * 
     * @param string $file_path Path to file to upload
     * @param string $purpose Purpose for upload (default: 'user_data')
     * @return string File ID from Anthropic
     * @throws Exception If upload fails
     */
    public function upload_file($file_path, $purpose = 'user_data') {
        if (!$this->is_configured()) {
            throw new Exception('Anthropic provider not configured');
        }

        if (!file_exists($file_path)) {
            throw new Exception("File not found: {$file_path}");
        }

        // Anthropic file upload endpoint
        $url = $this->base_url . '/files';
        
        // Prepare multipart form data
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
        ], 'Anthropic File Upload');

        if (!$result['success']) {
            throw new Exception('Anthropic file upload failed: ' . $result['error']);
        }

        $response_body = $result['data'];

        $data = json_decode($response_body, true);
        if (!isset($data['id'])) {
            throw new Exception('Anthropic file upload response missing file ID');
        }

        return $data['id'];
    }

    /**
     * Delete file from Anthropic API
     * 
     * @param string $file_id Anthropic file ID to delete
     * @return bool Success status
     * @throws Exception If delete fails
     */
    public function delete_file($file_id) {
        if (!$this->is_configured()) {
            throw new Exception('Anthropic provider not configured');
        }

        $url = $this->base_url . "/files/{$file_id}";
        
        // Send request using centralized ai_http filter
        $result = apply_filters('ai_http', [], 'DELETE', $url, [
            'headers' => $this->get_auth_headers()
        ], 'Anthropic File Delete');

        if (!$result['success']) {
            throw new Exception('Anthropic file delete failed: ' . $result['error']);
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
     * Normalize Anthropic models API response
     * 
     * @param array $raw_models Raw API response
     * @return array Normalized models array
     */
    private function normalize_models_response($raw_models) {
        $models = array();
        
        // Anthropic returns: { "data": [{"id": "claude-3-5-sonnet-20241022", "display_name": "Claude 3.5 Sonnet", ...}, ...] }
        $data = isset($raw_models['data']) ? $raw_models['data'] : $raw_models;
        if (is_array($data)) {
            foreach ($data as $model) {
                if (isset($model['id'])) {
                    $display_name = isset($model['display_name']) ? $model['display_name'] : $model['id'];
                    $models[$model['id']] = $display_name;
                }
            }
        }
        
        return $models;
    }
    
}