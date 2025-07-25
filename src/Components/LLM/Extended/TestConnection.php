<?php
/**
 * AI HTTP Client - Test Connection Component
 * 
 * Single Responsibility: Provide connection testing functionality
 * Renders test button and handles AJAX testing logic
 *
 * @package AIHttpClient\Components\Extended
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Extended_TestConnection implements AI_HTTP_Component_Interface {
    
    /**
     * Render test connection component
     *
     * @param string $unique_id Unique component identifier
     * @param array $config Component configuration
     * @param array $current_values Current saved values
     * @return string Rendered HTML
     */
    public static function render($unique_id, $config = [], $current_values = []) {
        $config = array_merge(self::get_defaults(), $config);
        
        $html = '<div class="postbox">';
        $html .= '<div class="inside">';
        
        if ($config['show_label']) {
            $html .= '<h4>' . esc_html($config['label']) . '</h4>';
        }
        
        $html .= '<button type="button" class="button button-secondary ai-test-connection" ';
        $html .= 'onclick="aiHttpTestConnection(\'' . esc_attr($unique_id) . '\')">';
        $html .= esc_html($config['button_text']);
        $html .= '</button>';
        
        $html .= '<span class="ai-test-result" id="' . esc_attr($unique_id) . '_test_result"></span>';
        
        if ($config['show_help']) {
            $html .= '<p class="description">' . esc_html($config['help_text']) . '</p>';
        }
        
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get default configuration
     *
     * @return array Default config
     */
    public static function get_defaults() {
        return [
            'label' => 'Test Connection',
            'button_text' => 'Test Connection',
            'show_label' => false,
            'show_help' => false,
            'help_text' => 'Test your API connection to verify credentials.'
        ];
    }
    
    /**
     * Get component configuration schema
     *
     * @return array Configuration schema
     */
    public static function get_config_schema() {
        return [
            'label' => 'string',
            'button_text' => 'string', 
            'show_label' => 'boolean',
            'show_help' => 'boolean',
            'help_text' => 'string'
        ];
    }
    
    /**
     * Validate component configuration
     *
     * @param array $config Configuration to validate
     * @return bool True if valid
     */
    public static function validate_config($config) {
        // Basic validation - could be enhanced
        return is_array($config);
    }
    
    /**
     * Initialize AJAX handlers for connection testing
     */
    public static function init_ajax_handlers() {
        add_action('wp_ajax_ai_http_test_connection', [__CLASS__, 'ajax_test_connection']);
    }
    
    /**
     * AJAX handler for testing connection with plugin context
     */
    public static function ajax_test_connection() {
        check_ajax_referer('ai_http_nonce', 'nonce');
        
        try {
            $plugin_context = sanitize_key($_POST['plugin_context']);
            if (empty($plugin_context)) {
                wp_send_json([
                    'success' => false,
                    'message' => 'Plugin context is required'
                ]);
            }
            
            $provider = sanitize_text_field($_POST['provider']);
            
            $client = new AI_HTTP_Client(['plugin_context' => $plugin_context]);
            $result = $client->test_connection($provider);
            
            wp_send_json($result);
            
        } catch (Exception $e) {
            error_log('AI HTTP Client: Connection test AJAX failed: ' . $e->getMessage());
            wp_send_json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            ]);
        }
    }
}

// Initialize AJAX handlers
add_action('init', ['AI_HTTP_Extended_TestConnection', 'init_ajax_handlers']);