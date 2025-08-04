<?php
/**
 * AI HTTP Client - Model Selector Component
 * 
 * Single Responsibility: Render dynamic model selection dropdown
 * Core component that handles model selection with dynamic loading
 *
 * @package AIHttpClient\Components\Core
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Core_ModelSelector implements AI_HTTP_Component_Interface {
    
    /**
     * Render the model selector component
     *
     * @param string $unique_id Unique component identifier
     * @param array $config Component configuration
     * @param array $current_values Current saved values
     * @return string Rendered HTML
     */
    public static function render($unique_id, $config = [], $current_values = []) {
        $config = array_merge(self::get_defaults(), $config);
        
        $provider = $current_values['provider'] ?? 'openai';
        $selected_model = $current_values['model'] ?? '';
        
        // Generate step-aware field name
        $field_name = 'ai_model';
        if (isset($config['step_key']) && !empty($config['step_key'])) {
            $field_name = 'ai_step_' . sanitize_key($config['step_key']) . '_model';
        }
        
        $html = '<tr class="form-field">';
        $html .= '<th scope="row">';
        $html .= '<label for="' . esc_attr($unique_id) . '_model">' . esc_html($config['label']) . '</label>';
        $html .= '</th>';
        $html .= '<td>';
        $html .= '<div>';
        $html .= '<select id="' . esc_attr($unique_id) . '_model" ';
        $html .= 'name="' . esc_attr($field_name) . '" ';
        $html .= 'data-component-id="' . esc_attr($unique_id) . '" ';
        $html .= 'data-component-type="model_selector" ';
        $html .= 'data-provider="' . esc_attr($provider) . '" ';
        $html .= 'class="regular-text">';
        
        $html .= self::render_model_options($provider, $selected_model);
        
        $html .= '</select>';
        $html .= '</div>';
        
        if ($config['show_help']) {
            $html .= '<br><small class="description">' . esc_html($config['help_text']) . '</small>';
        }
        
        $html .= '</td>';
        $html .= '</tr>';
        
        return $html;
    }
    
    /**
     * Get component configuration schema
     *
     * @return array Configuration schema
     */
    public static function get_config_schema() {
        return [
            'label' => [
                'type' => 'string',
                'default' => 'Model',
                'description' => 'Label for the model selector'
            ],
            'show_help' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Show help text'
            ],
            'help_text' => [
                'type' => 'string',
                'default' => 'Select the AI model to use for requests.',
                'description' => 'Help text displayed below selector'
            ],
            'loading_text' => [
                'type' => 'string',
                'default' => 'Loading models...',
                'description' => 'Text shown while loading models'
            ],
            'error_text' => [
                'type' => 'string',
                'default' => 'No API key configured',
                'description' => 'Text shown when model loading fails'
            ]
        ];
    }
    
    /**
     * Get component default values
     *
     * @return array Default values
     */
    public static function get_defaults() {
        return [
            'label' => 'Model',
            'show_help' => true,
            'help_text' => 'Select the AI model to use for requests.',
            'loading_text' => 'Loading models...',
            'error_text' => 'No API key configured'
        ];
    }
    
    /**
     * Validate component configuration
     *
     * @param array $config Configuration to validate
     * @return bool True if valid
     */
    public static function validate_config($config) {
        $schema = self::get_config_schema();
        
        foreach ($config as $key => $value) {
            if (!isset($schema[$key])) {
                return false;
            }
            
            $expected_type = $schema[$key]['type'];
            
            if ($expected_type === 'string' && !is_string($value)) {
                return false;
            }
            
            if ($expected_type === 'boolean' && !is_bool($value)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Render model options for provider
     *
     * @param string $provider Provider key
     * @param string $selected_model Currently selected model
     * @return string HTML options
     */
    private static function render_model_options($provider, $selected_model) {
        try {
            // Note: This static method cannot access plugin context or ai_type
            // It will use default/fallback behavior for initial render
            // Dynamic loading via AJAX will use proper plugin context
            $options_manager = new AI_HTTP_Options_Manager('default', 'llm');
            $provider_config = $options_manager->get_provider_settings($provider);
            
            // Use unified model fetcher - now returns normalized key-value format
            $models = AI_HTTP_Unified_Model_Fetcher::fetch_models($provider, $provider_config);
            
            $html = '';
            
            if (empty($models)) {
                $html .= '<option value="">Enter API key to load models</option>';
                return $html;
            }
            
            foreach ($models as $model_id => $model_name) {
                $selected = ($selected_model === $model_id) ? 'selected' : '';
                $html .= '<option value="' . esc_attr($model_id) . '" ' . $selected . '>';
                $html .= esc_html($model_name);
                $html .= '</option>';
            }
            
            return $html;
            
        } catch (Exception $e) {
            return '<option value="">No API key configured</option>';
        }
    }
    
    /**
     * Initialize AJAX handlers for model fetching
     */
    public static function init_ajax_handlers() {
        add_action('wp_ajax_ai_http_get_models', [__CLASS__, 'ajax_get_models']);
    }
    
    /**
     * Safety check to ensure AJAX action is registered
     * Called before any AJAX-dependent operations
     */
    public static function ensure_ajax_registered() {
        global $wp_filter;
        
        $action_registered = isset($wp_filter['wp_ajax_ai_http_get_models']) && 
                           !empty($wp_filter['wp_ajax_ai_http_get_models']->callbacks);
        
        if (!$action_registered) {
            // Fallback registration if action is missing
            self::init_ajax_handlers();
        }
        
        return $action_registered;
    }
    
    /**
     * AJAX handler for getting models with plugin context
     */
    public static function ajax_get_models() {
        // Ensure AJAX action is registered (safety check)
        self::ensure_ajax_registered();
        
        // Enhanced nonce verification - no fallbacks
        if (!isset($_POST['nonce'])) {
            wp_send_json_error('Security nonce is required for model requests.');
            return;
        }
        
        $nonce_valid = wp_verify_nonce($_POST['nonce'], 'ai_http_nonce');
        if (!$nonce_valid) {
            // Log nonce verification failure for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI HTTP Client: Nonce verification failed for ai_http_get_models. Nonce: ' . substr($_POST['nonce'], 0, 8) . '...');
            }
            wp_send_json_error('Security verification failed. Please refresh the page and try again.');
            return;
        }
        
        try {
            $plugin_context = sanitize_key($_POST['plugin_context']);
            if (empty($plugin_context)) {
                wp_send_json_error('Plugin context is required');
            }
            
            $provider = sanitize_text_field($_POST['provider']);
            
            // Get provider settings from plugin-scoped WordPress options
            $options_manager = new AI_HTTP_Options_Manager($plugin_context, 'llm');
            $provider_config = $options_manager->get_provider_settings($provider);
            
            // Use unified model fetcher
            $models = AI_HTTP_Unified_Model_Fetcher::fetch_models($provider, $provider_config);
            
            if (empty($models)) {
                wp_send_json_error('No API key configured for ' . $provider . '. Enter API key to load models.');
                return;
            }
            
            wp_send_json_success($models);
            
        } catch (Exception $e) {
            error_log('AI HTTP Client: Model fetch AJAX failed: ' . $e->getMessage());
            wp_send_json_error('Failed to fetch models: ' . $e->getMessage());
        }
    }
}

// Initialize AJAX handlers early to ensure availability
add_action('plugins_loaded', ['AI_HTTP_Core_ModelSelector', 'init_ajax_handlers']);