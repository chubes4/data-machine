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

class AI_HTTP_Gemini_Provider extends Base_LLM_Provider {

    /**
     * Get default base URL for Gemini
     *
     * @return string Default base URL
     */
    protected function get_default_base_url() {
        return 'https://generativelanguage.googleapis.com/v1beta';
    }

    /**
     * Get provider name for error messages
     *
     * @return string Provider name
     */
    protected function get_provider_name() {
        return 'Gemini';
    }

    /**
     * Get authentication headers for Gemini API
     *
     * @return array Headers array
     */
    protected function get_auth_headers() {
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
        return $this->execute_post_request($url, $modified_request);
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
        return $this->execute_streaming_curl($url, $modified_request, $callback);
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
        return $this->execute_get_request($url);
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

        // Send request
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Gemini file upload failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Debug logging in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[Gemini Debug] File Upload Response Status: {$response_code}");
            error_log("[Gemini Debug] File Upload Response Body: {$response_body}");
        }

        if ($response_code !== 200) {
            throw new Exception("Gemini file upload failed with status {$response_code}: {$response_body}");
        }

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
        
        $response = wp_remote_request($url, [
            'method' => 'DELETE',
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Gemini file delete failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        
        // Debug logging in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[Gemini Debug] File Delete Response Status: {$response_code}");
        }

        return $response_code === 200;
    }
}