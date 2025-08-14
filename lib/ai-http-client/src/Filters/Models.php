<?php
/**
 * AI HTTP Client - Model Filters
 * 
 * Centralized model fetching via WordPress filter system.
 * All model-related filters organized in this file.
 *
 * @package AIHttpClient\Filters
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

// AI Models filter - simplified to work with API keys only
// Usage: $models = apply_filters('ai_models', $provider_name);
add_filter('ai_models', function($provider_name = null) {

    $args = func_get_args();
    $provider_config = $args[1] ?? null;
    try {
        // Create provider instance directly, always passing config if present
        $provider = ai_http_create_provider($provider_name, $provider_config);
        if (!$provider) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[AI HTTP Client] ai_models filter: Failed to create provider '{$provider_name}'");
            }
            return [];
        }
        // Get models directly from provider (now returns full array of model objects)
        return $provider->get_normalized_models();
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[AI HTTP Client] ai_models filter: Failed to fetch models - " . $e->getMessage());
        }
        return [];
    }
}, 10, 2);