<?php
/**
 * AI HTTP Client - Simplified Grok Provider
 * 
 * Single Responsibility: Pure Grok/X.AI API communication only
 * No normalization logic - just sends/receives raw data
 * This is a "dumb" API client that the unified normalizers use
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Grok_Provider {

    private $api_key;
    private $base_url = 'https://api.x.ai/v1';
    private $timeout = 30;

    /**
     * Constructor
     *
     * @param array $config Provider configuration
     */
    public function __construct($config = array()) {
        $this->api_key = isset($config['api_key']) ? $config['api_key'] : '';
        $this->timeout = isset($config['timeout']) ? intval($config['timeout']) : 30;
        
        if (isset($config['base_url']) && !empty($config['base_url'])) {
            $this->base_url = rtrim($config['base_url'], '/');
        }
    }

    /**
     * Send raw request to Grok API
     *
     * @param array $provider_request Already normalized for Grok
     * @return array Raw Grok response
     * @throws Exception If request fails
     */
    public function send_raw_request($provider_request) {
        if (!$this->is_configured()) {
            throw new Exception('Grok provider not configured - missing API key');
        }

        $url = $this->base_url . '/chat/completions';
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';

        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => wp_json_encode($provider_request),
            'timeout' => $this->timeout,
            'method' => 'POST'
        ));

        if (is_wp_error($response)) {
            throw new Exception('Grok API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = 'Grok API error (HTTP ' . $status_code . ')';
            if (isset($decoded_response['error']['message'])) {
                $error_message .= ': ' . $decoded_response['error']['message'];
            }
            throw new Exception($error_message);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Grok API');
        }

        return $decoded_response;
    }

    /**
     * Send raw streaming request to Grok API
     *
     * @param array $provider_request Already normalized for Grok
     * @param callable $callback Optional callback for each chunk
     * @return string Full response content
     * @throws Exception If request fails
     */
    public function send_raw_streaming_request($provider_request, $callback = null) {
        if (!$this->is_configured()) {
            throw new Exception('Grok provider not configured - missing API key');
        }

        $url = $this->base_url . '/chat/completions';
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';

        $provider_request['stream'] = true;

        $response_body = '';
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => wp_json_encode($provider_request),
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
            throw new Exception('Grok streaming request failed: ' . $error);
        }

        if ($http_code !== 200) {
            throw new Exception('Grok streaming request failed with HTTP ' . $http_code);
        }

        return '';
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
        $headers = $this->get_auth_headers();

        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => $this->timeout
        ));

        if (is_wp_error($response)) {
            throw new Exception('Grok models request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($body, true);

        if ($status_code !== 200) {
            throw new Exception('Grok models request failed with HTTP ' . $status_code);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Grok models API');
        }

        return $decoded_response;
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
     * Format headers for cURL
     *
     * @param array $headers Associative headers array
     * @return array Indexed headers array for cURL
     */
    private function format_curl_headers($headers) {
        $formatted = array();
        foreach ($headers as $key => $value) {
            $formatted[] = $key . ': ' . $value;
        }
        return $formatted;
    }
}