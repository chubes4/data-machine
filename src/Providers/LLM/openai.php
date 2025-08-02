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

}