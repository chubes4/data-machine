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
     * Send raw request to Gemini API
     *
     * @param array $provider_request Already normalized for Gemini
     * @return array Raw Gemini response
     * @throws Exception If request fails
     */
    public function send_raw_request($provider_request) {
        if (!$this->is_configured()) {
            throw new Exception('Gemini provider not configured - missing API key');
        }

        list($url, $modified_request) = $this->build_gemini_url_and_request($provider_request, ':generateContent');
        
        // Use centralized ai_request filter
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_request', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($modified_request)
        ], 'Gemini');
        
        if (!$result['success']) {
            throw new Exception('Gemini API request failed: ' . $result['error']);
        }
        
        return json_decode($result['data'], true);
    }

    /**
     * Send raw streaming request to Gemini API
     *
     * @param array $provider_request Already normalized for Gemini
     * @param callable $callback Optional callback for each chunk
     * @return string Full response content
     * @throws Exception If request fails
     */
    public function send_raw_streaming_request($provider_request, $callback = null) {
        if (!$this->is_configured()) {
            throw new Exception('Gemini provider not configured - missing API key');
        }

        list($url, $modified_request) = $this->build_gemini_url_and_request($provider_request, ':streamGenerateContent');
        
        // Use centralized ai_request filter with streaming=true
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_request', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($modified_request)
        ], 'Gemini Streaming', true, $callback);
        
        if (!$result['success']) {
            throw new Exception('Gemini streaming request failed: ' . $result['error']);
        }

        return '';
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
        
        // Use centralized ai_request filter
        $result = apply_filters('ai_request', [], 'GET', $url, [
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

        // Send request using centralized ai_request filter
        $result = apply_filters('ai_request', [], 'POST', $url, [
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
        
        // Send request using centralized ai_request filter
        $result = apply_filters('ai_request', [], 'DELETE', $url, [], 'Gemini File Delete');

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
}