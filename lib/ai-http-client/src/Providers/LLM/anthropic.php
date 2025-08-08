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

class AI_HTTP_Anthropic_Provider extends Base_LLM_Provider {

    /**
     * Get default base URL for Anthropic
     *
     * @return string Default base URL
     */
    protected function get_default_base_url() {
        return 'https://api.anthropic.com/v1';
    }

    /**
     * Get provider name for error messages
     *
     * @return string Provider name
     */
    protected function get_provider_name() {
        return 'Anthropic';
    }

    /**
     * Get authentication headers for Anthropic API
     *
     * @return array Headers array
     */
    protected function get_auth_headers() {
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
        return $this->execute_post_request($url, $provider_request);
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
        return $this->execute_streaming_curl($url, $provider_request, $callback);
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

        // Send request
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 120
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Anthropic file upload failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Debug logging in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[Anthropic Debug] File Upload Response Status: {$response_code}");
            error_log("[Anthropic Debug] File Upload Response Body: {$response_body}");
        }

        if ($response_code !== 200) {
            throw new Exception("Anthropic file upload failed with status {$response_code}: {$response_body}");
        }

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
        
        $response = wp_remote_request($url, [
            'method' => 'DELETE',
            'headers' => $this->get_auth_headers(),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Anthropic file delete failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        
        // Debug logging in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[Anthropic Debug] File Delete Response Status: {$response_code}");
        }

        return $response_code === 200;
    }
}