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

class AI_HTTP_OpenRouter_Provider extends Base_LLM_Provider {

    private $http_referer;
    private $app_title;

    /**
     * Constructor
     *
     * @param array $config Provider configuration
     */
    public function __construct($config = array()) {
        parent::__construct($config);
        $this->http_referer = isset($config['http_referer']) ? $config['http_referer'] : '';
        $this->app_title = isset($config['app_title']) ? $config['app_title'] : 'AI HTTP Client';
    }

    /**
     * Get default base URL for OpenRouter
     *
     * @return string Default base URL
     */
    protected function get_default_base_url() {
        return 'https://openrouter.ai/api/v1';
    }

    /**
     * Get provider name for error messages
     *
     * @return string Provider name
     */
    protected function get_provider_name() {
        return 'OpenRouter';
    }

    /**
     * Get authentication headers for OpenRouter API
     * Includes optional HTTP-Referer and X-Title headers
     *
     * @return array Headers array
     */
    protected function get_auth_headers() {
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
     * Send raw request to OpenRouter API
     *
     * @param array $provider_request Already normalized for OpenRouter
     * @return array Raw OpenRouter response
     * @throws Exception If request fails
     */
    public function send_raw_request($provider_request) {
        if (!$this->is_configured()) {
            throw new Exception('OpenRouter provider not configured - missing API key');
        }

        $url = $this->base_url . '/chat/completions';
        return $this->execute_post_request($url, $provider_request);
    }

    /**
     * Send raw streaming request to OpenRouter API
     *
     * @param array $provider_request Already normalized for OpenRouter
     * @param callable $callback Optional callback for each chunk
     * @return string Full response content
     * @throws Exception If request fails
     */
    public function send_raw_streaming_request($provider_request, $callback = null) {
        if (!$this->is_configured()) {
            throw new Exception('OpenRouter provider not configured - missing API key');
        }

        $url = $this->base_url . '/chat/completions';
        return $this->execute_streaming_curl($url, $provider_request, $callback);
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
        return $this->execute_get_request($url);
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

        // Send request
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            throw new Exception('OpenRouter file upload failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Debug logging in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[OpenRouter Debug] File Upload Response Status: {$response_code}");
            error_log("[OpenRouter Debug] File Upload Response Body: {$response_body}");
        }

        if ($response_code !== 200) {
            throw new Exception("OpenRouter file upload failed with status {$response_code}: {$response_body}");
        }

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
        
        $response = wp_remote_request($url, [
            'method' => 'DELETE',
            'headers' => $this->get_auth_headers(),
        ]);

        if (is_wp_error($response)) {
            throw new Exception('OpenRouter file delete failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        
        // Debug logging in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[OpenRouter Debug] File Delete Response Status: {$response_code}");
        }

        return $response_code === 200;
    }
}