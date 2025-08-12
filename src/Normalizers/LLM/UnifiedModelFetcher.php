<?php
/**
 * AI HTTP Client - Unified Model Fetcher
 * 
 * Single Responsibility: Fetch ALL available models from ANY provider
 * Pure API fetching - NO defaults, NO fallbacks, NO filtering, NO sorting
 * Let it error out if it fails - that's the intention
 *
 * @package AIHttpClient\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Unified_Model_Fetcher {

    /**
     * Fetch models for any provider
     * Returns normalized key-value format: model_id => display_name
     *
     * @param string $provider_name Provider name (openai, anthropic, gemini, etc.)
     * @param array $provider_config Provider configuration (API keys, etc.)
     * @return array Normalized models array (model_id => display_name)
     * @throws Exception If provider not supported or API fails
     */
    public static function fetch_models($provider_name, $provider_config = array()) {
        // Use filter-based provider discovery and instantiation
        $all_providers = apply_filters('ai_providers', []);
        $provider_info = $all_providers[strtolower($provider_name)] ?? null;
        
        if (!$provider_info || $provider_info['type'] !== 'llm') {
            throw new Exception('Unsupported provider for model fetching');
        }
        
        $provider_class = $provider_info['class'];
        $provider = new $provider_class($provider_config);
        
        // Each provider handles its own model normalization
        return $provider->get_normalized_models();
    }

    /**
     * Fetch raw models for any provider
     * Pure API fetching - will throw exception if it fails
     *
     * @param string $provider_name Provider name (openai, anthropic, gemini, etc.)
     * @param array $provider_config Provider configuration (API keys, etc.)
     * @return array Raw models response from API
     * @throws Exception If provider not supported or API fails
     */
    public static function fetch_raw_models($provider_name, $provider_config = array()) {
        // Use filter-based provider discovery and instantiation
        $all_providers = apply_filters('ai_providers', []);
        $provider_info = $all_providers[strtolower($provider_name)] ?? null;
        
        if (!$provider_info || $provider_info['type'] !== 'llm') {
            throw new Exception('Unsupported provider for model fetching');
        }
        
        $provider_class = $provider_info['class'];
        $provider = new $provider_class($provider_config);
        
        // Each provider handles its own raw model fetching
        return $provider->get_raw_models();
    }

}