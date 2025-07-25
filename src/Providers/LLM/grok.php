<?php
/**
 * AI HTTP Client - Grok Provider
 * 
 * Single Responsibility: Pure Grok/X.AI API communication only
 * No normalization logic - just sends/receives raw data
 * This is a "dumb" API client that the unified normalizers use
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Grok_Provider extends Base_LLM_Provider {

    /**
     * Get default base URL for Grok
     *
     * @return string Default base URL
     */
    protected function get_default_base_url() {
        return 'https://api.x.ai/v1';
    }

    /**
     * Get provider name for error messages
     *
     * @return string Provider name
     */
    protected function get_provider_name() {
        return 'Grok';
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
        return $this->execute_post_request($url, $provider_request);
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
        return $this->execute_streaming_curl($url, $provider_request, $callback);
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
        return $this->execute_get_request($url);
    }
}