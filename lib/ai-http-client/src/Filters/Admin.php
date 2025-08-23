<?php
/**
 * AI HTTP Client - Admin Filters
 * 
 * Centralized admin interface functionality via WordPress filter system.
 * All admin-related filters including API key management, component rendering, 
 * template system, and AJAX handlers organized in this file.
 *
 * @package AIHttpClient\Filters
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

// Filter to get/set all provider API keys.
// Usage: apply_filters('ai_provider_api_keys', null) to get all keys.
//        apply_filters('ai_provider_api_keys', $keys) to update all keys.
add_filter('ai_provider_api_keys', function($keys = null) {
    $option_name = 'ai_http_shared_api_keys';
    if (is_null($keys)) {
        // Get all keys
        return get_option($option_name, []);
    } else {
        // Set all keys
        update_option($option_name, $keys);
        return $keys;
    }
});


/**
 * Render template with proper variable scoping
 *
 * @param string $template_name Template filename (without .php extension)
 * @param array $data Variables to make available in template
 * @return string Rendered template HTML
 */
function ai_http_render_template($template_name, $data = []) {
    $template_path = dirname(__FILE__) . '/../templates/' . $template_name . '.php';
    
    if (!file_exists($template_path)) {
        return '<div class="notice notice-error"><p>Template not found: ' . esc_html($template_name) . '</p></div>';
    }
    
    // Extract template data array into individual variables for template scope
    // Validate required variables exist to prevent template errors
    if (is_array($data)) {
        extract($data);
    }
    
    // Start output buffering to capture template output
    ob_start();
    include $template_path;
    return ob_get_clean();
}

// AI Component Rendering filter - simplified to return only table rows
// Usage: echo apply_filters('ai_render_component', '');
// Usage: echo apply_filters('ai_render_component', '', ['selected_provider' => 'anthropic', 'selected_model' => 'claude-3-sonnet']);
add_filter('ai_render_component', function($html, $config = []) {
    
    // Use provided configuration or defaults
    $selected_provider = $config['selected_provider'] ?? 'openai';
    $selected_model = $config['selected_model'] ?? '';
    
    // Generate unique ID for form elements
    $unique_id = 'ai_' . uniqid();
    
    // Render core components template (always rendered) - returns table rows only
    $template_data = [
        'unique_id' => $unique_id,
        'selected_provider' => $selected_provider,
        'provider_config' => [
            'model' => $selected_model
        ]
    ];
    
    $html = ai_http_render_template('core', $template_data);
    
    
    // Add nonce for AJAX operations
    $html .= '<tr class="ai-hidden"><td colspan="2">' . wp_nonce_field('ai_http_nonce', 'ai_http_nonce_field', true, false) . '</td></tr>';
    
    return $html;
}, 10, 2);

/**
 * Save API key via AJAX
 */
function ai_http_ajax_save_api_key() {
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
        // Save API key using ai_provider_api_keys filter
        $all_keys = apply_filters('ai_provider_api_keys', null);
        $all_keys[$provider] = $api_key;
        apply_filters('ai_provider_api_keys', $all_keys);
        wp_send_json_success([
            'message' => __('API key saved successfully', 'ai-http-client'),
            'provider' => $provider
        ]);
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Load API key for provider via AJAX
 */
function ai_http_ajax_load_api_key() {
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
        // Get API key using ai_provider_api_keys filter
        $all_keys = apply_filters('ai_provider_api_keys', null);
        $api_key = $all_keys[$provider] ?? '';
        $data = [
            'provider' => $provider,
            'api_key' => $api_key
        ];
        wp_send_json_success($data);
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Get available models for a provider via AJAX
 */
function ai_http_ajax_get_models() {
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
        // Always get API key using ai_provider_api_keys filter
        $all_keys = apply_filters('ai_provider_api_keys', null);
        $api_key = $all_keys[$provider] ?? '';
        $models = apply_filters('ai_models', $provider, ['api_key' => $api_key]);
        wp_send_json_success($models);
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// Register AJAX actions for dynamic component interactions
// Only registers in admin context to avoid unnecessary overhead
if (is_admin()) {
    add_action('wp_ajax_ai_http_save_api_key', 'ai_http_ajax_save_api_key');
    add_action('wp_ajax_ai_http_load_provider_settings', 'ai_http_ajax_load_api_key');
    add_action('wp_ajax_ai_http_get_models', 'ai_http_ajax_get_models');
}