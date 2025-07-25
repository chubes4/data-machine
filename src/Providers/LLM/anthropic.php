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
}