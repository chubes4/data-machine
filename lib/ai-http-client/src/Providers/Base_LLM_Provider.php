<?php
/**
 * AI HTTP Client - Base LLM Provider
 * 
 * Single Responsibility: Common functionality for all LLM providers
 * Eliminates code duplication across OpenAI, Anthropic, Gemini, Grok, OpenRouter
 * 
 * Provides concrete implementations for:
 * - Configuration handling (api_key, timeout, base_url)
 * - Authentication patterns
 * - cURL streaming setup
 * - JSON response validation
 * - Common error handling
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class Base_LLM_Provider {

    protected $api_key;
    protected $base_url;
    protected $timeout = 30;

    /**
     * Constructor - handles common configuration
     *
     * @param array $config Provider configuration
     */
    public function __construct($config = array()) {
        $this->api_key = isset($config['api_key']) ? $config['api_key'] : '';
        $this->timeout = isset($config['timeout']) ? intval($config['timeout']) : 30;
        
        if (isset($config['base_url']) && !empty($config['base_url'])) {
            $this->base_url = rtrim($config['base_url'], '/');
        } else {
            $this->base_url = $this->get_default_base_url();
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
     * Get available models - default implementation returns empty array
     * Providers can override if they support model fetching
     *
     * @return array Raw models response
     */
    public function get_raw_models() {
        if (!$this->is_configured()) {
            return array();
        }
        
        return array();
    }

    /**
     * Get default base URL for this provider
     * Child classes must implement this
     *
     * @return string Default base URL
     */
    protected function get_default_base_url() {
        return '';
    }

    /**
     * Get authentication headers - default Bearer token implementation
     * Child classes can override for different auth patterns
     *
     * @return array Headers array
     */
    protected function get_auth_headers() {
        return array(
            'Authorization' => 'Bearer ' . $this->api_key
        );
    }

    /**
     * Format headers for cURL - converts associative to indexed array
     *
     * @param array $headers Associative headers array
     * @return array Indexed headers array for cURL
     */
    protected function format_curl_headers($headers) {
        $formatted = array();
        foreach ($headers as $key => $value) {
            $formatted[] = $key . ': ' . $value;
        }
        return $formatted;
    }

    /**
     * Execute streaming cURL request with common setup and error handling
     *
     * @param string $url Request URL
     * @param array $request_data Request payload
     * @param callable $callback Optional callback for each chunk
     * @return string Empty string (streaming outputs directly)
     * @throws Exception If request fails
     */
    protected function execute_streaming_curl($url, $request_data, $callback = null) {
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';

        $request_data['stream'] = true;

        $response_body = '';
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => wp_json_encode($request_data),
            CURLOPT_HTTPHEADER => $this->format_curl_headers($headers),
            CURLOPT_WRITEFUNCTION => function($ch, $data) use ($callback, &$response_body) {
                $response_body .= $data; // Capture response for error logging
                if ($callback && is_callable($callback)) {
                    call_user_func($callback, $data);
                } else {
                    echo $data;
                    flush();
                }
                return strlen($data);
            },
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_RETURNTRANSFER => false
        ));

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            $provider_name = $this->get_provider_name();
            throw new Exception($provider_name . ' streaming request failed: ' . $error);
        }

        if ($http_code !== 200) {
            $provider_name = $this->get_provider_name();
            throw new Exception($provider_name . ' streaming request failed with HTTP ' . $http_code);
        }

        return '';
    }

    /**
     * Execute standard POST request with common error handling
     *
     * @param string $url Request URL
     * @param array $request_data Request payload
     * @return array Decoded JSON response
     * @throws Exception If request fails
     */
    protected function execute_post_request($url, $request_data) {
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';

        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => wp_json_encode($request_data),
            'timeout' => $this->timeout,
            'method' => 'POST'
        ));

        if (is_wp_error($response)) {
            $provider_name = $this->get_provider_name();
            throw new Exception($provider_name . ' API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        return $this->validate_json_response($body, $status_code);
    }

    /**
     * Execute GET request with common error handling
     *
     * @param string $url Request URL
     * @return array Decoded JSON response
     * @throws Exception If request fails
     */
    protected function execute_get_request($url) {
        $headers = $this->get_auth_headers();

        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => $this->timeout
        ));

        if (is_wp_error($response)) {
            $provider_name = $this->get_provider_name();
            throw new Exception($provider_name . ' API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        return $this->validate_json_response($body, $status_code);
    }

    /**
     * Validate JSON response and handle common errors
     *
     * @param string $body Response body
     * @param int $status_code HTTP status code
     * @return array Decoded JSON response
     * @throws Exception If response is invalid
     */
    protected function validate_json_response($body, $status_code) {
        $decoded_response = json_decode($body, true);

        if ($status_code !== 200) {
            $provider_name = $this->get_provider_name();
            $error_message = $provider_name . ' API error (HTTP ' . $status_code . ')';
            if (isset($decoded_response['error']['message'])) {
                $error_message .= ': ' . $decoded_response['error']['message'];
            }
            throw new Exception($error_message);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $provider_name = $this->get_provider_name();
            throw new Exception('Invalid JSON response from ' . $provider_name . ' API');
        }

        return $decoded_response;
    }

    /**
     * Get provider name for error messages
     * Child classes can override for custom naming
     *
     * @return string Provider name
     */
    protected function get_provider_name() {
        return 'Provider';
    }
}