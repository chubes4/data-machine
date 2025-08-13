<?php
/**
 * AI HTTP Client - AJAX Handler
 * 
 * Provides WordPress AJAX endpoints for dynamic component interactions.
 * Handles provider settings and model fetching.
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Ajax_Handler {
    
    /**
     * Save API key via AJAX
     */
    public static function save_api_key() {
        // Security verification
        if (!check_ajax_referer('ai_http_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'ai-http-client')]);
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'ai-http-client')]);
            return;
        }
        
        // Get required parameters
        $provider = sanitize_text_field(wp_unslash($_POST['provider'] ?? ''));
        $api_key = sanitize_text_field(wp_unslash($_POST['api_key'] ?? ''));
        
        if (empty($provider)) {
            wp_send_json_error(['message' => __('Provider is required', 'ai-http-client')]);
            return;
        }
        
        try {
            // Save API key to shared storage
            $option_name = $provider . '_api_key';
            update_option($option_name, $api_key);
            
            wp_send_json_success([
                'message' => __('API key saved successfully', 'ai-http-client'),
                'provider' => $provider
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Load provider settings via AJAX - simplified to only return API keys
     */
    public static function load_provider_settings() {
        // Security verification
        if (!check_ajax_referer('ai_http_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'ai-http-client')]);
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'ai-http-client')]);
            return;
        }
        
        // Get required parameters
        $provider = sanitize_text_field(wp_unslash($_POST['provider'] ?? ''));
        
        if (empty($provider)) {
            wp_send_json_error(['message' => __('Provider is required', 'ai-http-client')]);
            return;
        }
        
        try {
            // Get API key from shared storage
            $api_key = get_option($provider . '_api_key', '');
            
            $settings = [
                'provider' => $provider,
                'api_key' => $api_key
            ];
            
            wp_send_json_success($settings);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Get available models for a provider via AJAX
     */
    public static function get_models() {
        // Security verification
        if (!check_ajax_referer('ai_http_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'ai-http-client')]);
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'ai-http-client')]);
            return;
        }
        
        // Get required parameters
        $plugin_context = sanitize_text_field(wp_unslash($_POST['plugin_context'] ?? ''));
        $provider = sanitize_text_field(wp_unslash($_POST['provider'] ?? ''));
        
        if (empty($plugin_context) || empty($provider)) {
            wp_send_json_error(['message' => __('Plugin context and provider are required', 'ai-http-client')]);
            return;
        }
        
        try {
            // Use ai_models filter for direct model fetching
            $models = apply_filters('ai_models', $provider);
            
            if (empty($models)) {
                wp_send_json_error(['message' => 'No API key configured for ' . $provider . '. Enter API key to load models.']);
                return;
            }
            
            wp_send_json_success($models);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
}