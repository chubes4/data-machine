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
}