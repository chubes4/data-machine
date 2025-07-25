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
}