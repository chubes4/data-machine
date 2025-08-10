<?php
/**
 * AI HTTP Client - OpenAI Provider
 * 
 * Single Responsibility: Pure OpenAI API communication only
 * No normalization logic - just sends/receives raw data
 * This is a "dumb" API client that the unified normalizers use
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_OpenAI_Provider extends Base_LLM_Provider {

    private $organization;

    /**
     * Constructor
     *
     * @param array $config Provider configuration
     */
    public function __construct($config = array()) {
        parent::__construct($config);
        $this->organization = isset($config['organization']) ? $config['organization'] : '';
    }

    /**
     * Get default base URL for OpenAI
     *
     * @return string Default base URL
     */
    protected function get_default_base_url() {
        return 'https://api.openai.com/v1';
    }

    /**
     * Get provider name for error messages
     *
     * @return string Provider name
     */
    protected function get_provider_name() {
        return 'OpenAI';
    }

    /**
     * Get authentication headers for OpenAI API
     * Includes organization header if configured
     *
     * @return array Headers array
     */
    protected function get_auth_headers() {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key
        );

        if (!empty($this->organization)) {
            $headers['OpenAI-Organization'] = $this->organization;
        }

        return $headers;
    }

    /**
     * Send raw request to OpenAI API
     *
     * @param array $provider_request Already normalized for OpenAI
     * @return array Raw OpenAI response
     * @throws Exception If request fails
     */
    public function send_raw_request($provider_request) {
        if (!$this->is_configured()) {
            throw new Exception('OpenAI provider not configured - missing API key');
        }

        $url = $this->base_url . '/responses';
        // Debug logging in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI HTTP Client DEBUG: OpenAI request to ' . $url . ' with payload: ' . wp_json_encode($provider_request));
        }
        
        return $this->execute_post_request($url, $provider_request);
    }

    /**
     * Send raw streaming request to OpenAI API
     *
     * @param array $provider_request Already normalized for OpenAI
     * @param callable $callback Optional callback for each chunk
     * @return string Full response content
     * @throws Exception If request fails
     */
    public function send_raw_streaming_request($provider_request, $callback = null) {
        if (!$this->is_configured()) {
            throw new Exception('OpenAI provider not configured - missing API key');
        }

        $url = $this->base_url . '/responses';
        // Debug logging in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI HTTP Client DEBUG: OpenAI streaming request to ' . $url . ' with payload: ' . wp_json_encode($provider_request));
        }

        return $this->execute_streaming_curl($url, $provider_request, $callback);
    }

    /**
     * Get available models from OpenAI API
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
     * Upload file to OpenAI Files API
     * 
     * @param string $file_path Path to file to upload
     * @param string $purpose Purpose for upload (default: 'user_data')
     * @return string File ID from OpenAI
     * @throws Exception If upload fails
     */
    public function upload_file($file_path, $purpose = 'user_data') {
        if (!$this->is_configured()) {
            throw new Exception('OpenAI provider not configured');
        }

        if (!file_exists($file_path)) {
            throw new Exception("File not found: {$file_path}");
        }

        // OpenAI file upload endpoint
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

        // Send request
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            throw new Exception('OpenAI file upload failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Debug logging in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[OpenAI Debug] File Upload Response Status: {$response_code}");
            error_log("[OpenAI Debug] File Upload Response Body: {$response_body}");
        }

        if ($response_code !== 200) {
            throw new Exception("OpenAI file upload failed with status {$response_code}: {$response_body}");
        }

        $data = json_decode($response_body, true);
        if (!isset($data['id'])) {
            throw new Exception('OpenAI file upload response missing file ID');
        }

        return $data['id'];
    }

    /**
     * Delete file from OpenAI Files API
     * 
     * @param string $file_id OpenAI file ID to delete
     * @return bool Success status
     * @throws Exception If delete fails
     */
    public function delete_file($file_id) {
        if (!$this->is_configured()) {
            throw new Exception('OpenAI provider not configured');
        }

        $url = $this->base_url . "/files/{$file_id}";
        
        $response = wp_remote_request($url, [
            'method' => 'DELETE',
            'headers' => $this->get_auth_headers(),
        ]);

        if (is_wp_error($response)) {
            throw new Exception('OpenAI file delete failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        
        // Debug logging in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[OpenAI Debug] File Delete Response Status: {$response_code}");
        }

        return $response_code === 200;
    }

}