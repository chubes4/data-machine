<?php
/**
 * AI HTTP Client - Options Manager
 * 
 * Single Responsibility: Handle WordPress options storage for AI provider settings
 * Manages the nested array structure in wp_options table
 *
 * @package AIHttpClient
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Options_Manager {

    /**
     * Base option name for plugin-scoped provider settings
     */
    const OPTION_NAME_BASE = 'ai_http_client_providers';

    /**
     * Base option name for plugin-scoped selected provider
     */
    const SELECTED_PROVIDER_OPTION_BASE = 'ai_http_client_selected_provider';

    /**
     * Option name for shared API keys across all plugins
     */
    const SHARED_API_KEYS_OPTION = 'ai_http_client_shared_api_keys';

    /**
     * Plugin context for scoped configuration
     */
    private $plugin_context;

    /**
     * AI type for scoped configuration
     */
    private $ai_type;

    /**
     * Whether the options manager is properly configured
     */
    private $is_configured = false;

    /**
     * Constructor with plugin context and AI type
     *
     * @param string $plugin_context Plugin context for scoped configuration
     * @param string $ai_type AI type for scoped configuration ('llm', 'upscaling', 'generative')
     */
    public function __construct($plugin_context = null, $ai_type = null) {
        // Require ai_type parameter - no defaults
        if (empty($ai_type)) {
            $this->is_configured = false;
            return;
        }
        
        // Validate ai_type using filter-based discovery
        $ai_types = apply_filters('ai_types', []);
        $valid_types = array_keys($ai_types);
        if (!in_array($ai_type, $valid_types)) {
            $this->is_configured = false;
            return;
        }
        
        $this->ai_type = $ai_type;
        
        // Use direct plugin context validation (simplified approach)
        if (empty($plugin_context)) {
            throw new Exception('Plugin context is required for AI_HTTP_Options_Manager');
        }
        
        $this->plugin_context = sanitize_key($plugin_context);
        $this->is_configured = true; // Assume configured if context provided
    }

    /**
     * Get plugin and AI type scoped option name
     *
     * @param string $base_name Base option name
     * @return string Scoped option name with plugin context and AI type
     */
    private function get_scoped_option_name($base_name) {
        return $base_name . '_' . $this->plugin_context . '_' . $this->ai_type;
    }

    /**
     * Get all provider settings for this plugin context
     *
     * @return array All provider settings
     */
    public function get_all_providers() {
        $settings = get_option($this->get_scoped_option_name(self::OPTION_NAME_BASE), array());
        $selected_provider = get_option($this->get_scoped_option_name(self::SELECTED_PROVIDER_OPTION_BASE), 'openai');
        
        // CRITICAL FIX: Ensure selected provider exists in settings
        // This allows API key merging to work even if no provider config was explicitly saved
        if (!isset($settings[$selected_provider])) {
            $settings[$selected_provider] = array(); // Create empty config for selected provider
        }
        
        // Merge with shared API keys
        $shared_api_keys = get_option(self::SHARED_API_KEYS_OPTION, array());
        foreach ($settings as $provider => &$config) {
            if (isset($shared_api_keys[$provider])) {
                $config['api_key'] = $shared_api_keys[$provider];
            }
        }
        
        // Add selected provider to the settings array
        $settings['selected_provider'] = $selected_provider;
        
        return $settings;
    }

    /**
     * Get settings for a specific provider in this plugin context
     *
     * @param string $provider_name Provider name
     * @return array Provider settings with merged API key
     */
    public function get_provider_settings($provider_name) {
        // Get plugin-specific settings
        $scoped_option_name = $this->get_scoped_option_name(self::OPTION_NAME_BASE);
        $all_settings = get_option($scoped_option_name, array());
        $provider_settings = isset($all_settings[$provider_name]) ? $all_settings[$provider_name] : array();
        
        // Merge with shared API key
        $shared_api_keys = get_option(self::SHARED_API_KEYS_OPTION, array());
        
        if (isset($shared_api_keys[$provider_name])) {
            $api_key = $this->get_api_key($provider_name);
            $provider_settings['api_key'] = $api_key;
        }
        
        return $provider_settings;
    }

    /**
     * Save settings for a specific provider in this plugin context
     *
     * @param string $provider_name Provider name
     * @param array $settings Provider settings
     * @return bool True on success
     */
    public function save_provider_settings($provider_name, $settings) {
        $sanitized_settings = $this->sanitize_provider_settings($settings);
        
        // Separate API key for shared storage
        $api_key = null;
        if (isset($sanitized_settings['api_key'])) {
            $api_key = $sanitized_settings['api_key'];
            unset($sanitized_settings['api_key']); // Remove from plugin-specific storage
        }
        
        // Save plugin-specific settings (without API key)
        $all_settings = get_option($this->get_scoped_option_name(self::OPTION_NAME_BASE), array());
        $all_settings[$provider_name] = array_merge(
            isset($all_settings[$provider_name]) ? $all_settings[$provider_name] : array(),
            $sanitized_settings
        );
        $success_settings = update_option($this->get_scoped_option_name(self::OPTION_NAME_BASE), $all_settings);
        
        // Save API key to shared storage if provided
        $success_api_key = true;
        if (!empty($api_key)) {
            $success_api_key = $this->set_shared_api_key($provider_name, $api_key);
        }
        
        return $success_settings && $success_api_key;
    }

    /**
     * Get specific setting for a provider
     *
     * @param string $provider_name Provider name
     * @param string $setting_key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get_provider_setting($provider_name, $setting_key, $default = null) {
        $provider_settings = $this->get_provider_settings($provider_name);
        return isset($provider_settings[$setting_key]) ? $provider_settings[$setting_key] : $default;
    }

    /**
     * Set specific setting for a provider
     *
     * @param string $provider_name Provider name
     * @param string $setting_key Setting key
     * @param mixed $value Setting value
     * @return bool True on success
     */
    public function set_provider_setting($provider_name, $setting_key, $value) {
        $provider_settings = $this->get_provider_settings($provider_name);
        $provider_settings[$setting_key] = $value;
        
        return $this->save_provider_settings($provider_name, $provider_settings);
    }

    /**
     * Check if provider is configured (has API key)
     *
     * @param string $provider_name Provider name
     * @return bool True if configured
     */
    public function is_provider_configured($provider_name) {
        $api_key = $this->get_provider_setting($provider_name, 'api_key');
        return !empty($api_key);
    }

    /**
     * Get selected provider for this plugin context
     *
     * @return string Selected provider name
     */
    public function get_selected_provider() {
        return get_option($this->get_scoped_option_name(self::SELECTED_PROVIDER_OPTION_BASE), 'openai');
    }

    /**
     * Set selected provider for this plugin context
     *
     * @param string $provider_name Provider name
     * @return bool True on success
     */
    public function set_selected_provider($provider_name) {
        return update_option($this->get_scoped_option_name(self::SELECTED_PROVIDER_OPTION_BASE), sanitize_text_field($provider_name));
    }

    /**
     * Delete provider settings for this plugin context
     *
     * @param string $provider_name Provider name
     * @return bool True on success
     */
    public function delete_provider_settings($provider_name) {
        $all_settings = get_option($this->get_scoped_option_name(self::OPTION_NAME_BASE), array());
        
        if (isset($all_settings[$provider_name])) {
            unset($all_settings[$provider_name]);
            return update_option($this->get_scoped_option_name(self::OPTION_NAME_BASE), $all_settings);
        }
        
        return true;
    }

    /**
     * Reset all settings for this plugin context
     *
     * @return bool True on success
     */
    public function reset_all_settings() {
        $deleted_main = delete_option($this->get_scoped_option_name(self::OPTION_NAME_BASE));
        $deleted_selected = delete_option($this->get_scoped_option_name(self::SELECTED_PROVIDER_OPTION_BASE));
        
        return $deleted_main && $deleted_selected;
    }

    /**
     * Get API key for provider from shared storage
     *
     * @param string $provider_name Provider name
     * @return string API key
     */
    public function get_api_key($provider_name) {
        $shared_api_keys = get_option(self::SHARED_API_KEYS_OPTION, array());
        return isset($shared_api_keys[$provider_name]) ? $shared_api_keys[$provider_name] : '';
    }

    /**
     * Set API key for provider in shared storage
     *
     * @param string $provider_name Provider name
     * @param string $api_key API key
     * @return bool True on success
     */
    public function set_api_key($provider_name, $api_key) {
        return $this->set_shared_api_key($provider_name, $api_key);
    }

    /**
     * Set shared API key for provider
     *
     * @param string $provider_name Provider name
     * @param string $api_key API key
     * @return bool True on success
     */
    private function set_shared_api_key($provider_name, $api_key) {
        $shared_api_keys = get_option(self::SHARED_API_KEYS_OPTION, array());
        
        // Check if API key already exists with same value
        $existing_api_key = isset($shared_api_keys[$provider_name]) ? $shared_api_keys[$provider_name] : null;
        $keys_identical = ($existing_api_key && $existing_api_key === $api_key);
        
        $shared_api_keys[$provider_name] = $api_key;
        
        $update_result = update_option(self::SHARED_API_KEYS_OPTION, $shared_api_keys);
        
        // CRITICAL FIX: WordPress update_option() returns false if no change is needed
        // If API keys are identical, that's actually success (no update needed)
        return $update_result || $keys_identical;
    }

    /**
     * Export all settings for this plugin context (for backup/migration)
     *
     * @return array All settings
     */
    public function export_settings() {
        return array(
            'plugin_context' => $this->plugin_context,
            'providers' => get_option($this->get_scoped_option_name(self::OPTION_NAME_BASE), array()),
            'selected_provider' => get_option($this->get_scoped_option_name(self::SELECTED_PROVIDER_OPTION_BASE), 'openai'),
            'shared_api_keys' => get_option(self::SHARED_API_KEYS_OPTION, array()),
            'export_date' => current_time('mysql'),
            'version' => AI_HTTP_CLIENT_VERSION
        );
    }

    /**
     * Import settings for this plugin context (for backup/migration)
     *
     * @param array $settings Settings to import
     * @return bool True on success
     */
    public function import_settings($settings) {
        if (!is_array($settings) || !isset($settings['providers'])) {
            return false;
        }
        
        $success_main = update_option($this->get_scoped_option_name(self::OPTION_NAME_BASE), $settings['providers']);
        $success_selected = true;
        $success_api_keys = true;
        
        if (isset($settings['selected_provider'])) {
            $success_selected = update_option($this->get_scoped_option_name(self::SELECTED_PROVIDER_OPTION_BASE), $settings['selected_provider']);
        }
        
        if (isset($settings['shared_api_keys'])) {
            $success_api_keys = update_option(self::SHARED_API_KEYS_OPTION, $settings['shared_api_keys']);
        }
        
        return $success_main && $success_selected && $success_api_keys;
    }

    /**
     * Get configuration array for AI filters
     *
     * @return array Configuration ready for ai_request filter
     */
    public function get_client_config() {
        // Return empty config if not properly configured
        if (!$this->is_configured) {
            return array(
                'default_provider' => null,
                'providers' => array()
            );
        }
        
        $selected_provider = $this->get_selected_provider();
        $provider_settings = $this->get_provider_settings($selected_provider);
        
        return array(
            'default_provider' => $selected_provider,
            'providers' => array(
                $selected_provider => $provider_settings
            )
        );
    }

    /**
     * Sanitize provider settings
     *
     * @param array $settings Raw settings
     * @return array Sanitized settings
     */
    private function sanitize_provider_settings($settings) {
        $sanitized = array();
        
        // Sanitize common fields
        if (isset($settings['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($settings['api_key']);
        }
        
        if (isset($settings['model'])) {
            $sanitized['model'] = sanitize_text_field($settings['model']);
        }
        
        if (isset($settings['instructions'])) {
            $sanitized['instructions'] = sanitize_textarea_field($settings['instructions']);
        }
        
        if (isset($settings['base_url'])) {
            $sanitized['base_url'] = esc_url_raw($settings['base_url']);
        }
        
        if (isset($settings['organization'])) {
            $sanitized['organization'] = sanitize_text_field($settings['organization']);
        }
        
        // Sanitize custom fields
        foreach ($settings as $key => $value) {
            if (strpos($key, 'custom_') === 0) {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        // Sanitize numeric fields
        if (isset($settings['temperature'])) {
            $sanitized['temperature'] = floatval($settings['temperature']);
        }
        
        if (isset($settings['max_tokens'])) {
            $sanitized['max_tokens'] = intval($settings['max_tokens']);
        }
        
        return $sanitized;
    }

    
    /**
     * Initialize AJAX handlers for settings management
     */
    public static function init_ajax_handlers() {
        add_action('wp_ajax_ai_http_save_settings', [__CLASS__, 'ajax_save_settings']);
        add_action('wp_ajax_ai_http_load_provider_settings', [__CLASS__, 'ajax_load_provider_settings']);
    }
    
    /**
     * AJAX handler for saving settings with plugin context
     */
    public static function ajax_save_settings() {
        // Enhanced nonce verification - no fallbacks
        if (!isset($_POST['nonce'])) {
            wp_send_json_error('Security nonce is required for settings save.');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'ai_http_nonce')) {
            wp_send_json_error('Security verification failed. Please refresh the page and try again.');
            return;
        }
        
        try {
            $plugin_context = sanitize_key(wp_unslash($_POST['plugin_context']));
            if (empty($plugin_context)) {
                wp_send_json_error('Plugin context is required');
            }
            
            $step_id = isset($_POST['step_id']) ? sanitize_key(wp_unslash($_POST['step_id'])) : null;
            $options_manager = new self($plugin_context, 'llm');
            
            if ($step_id) {
                // Step-aware form processing
                $field_prefix = "ai_step_{$step_id}_";
                
                $provider = sanitize_text_field(wp_unslash($_POST[$field_prefix . 'provider']));
                $step_settings = array(
                    'provider' => $provider,
                    'model' => sanitize_text_field(wp_unslash($_POST[$field_prefix . 'model'])),
                    'temperature' => isset($_POST[$field_prefix . 'temperature']) ? floatval($_POST[$field_prefix . 'temperature']) : null,
                    'system_prompt' => isset($_POST[$field_prefix . 'system_prompt']) ? sanitize_textarea_field($_POST[$field_prefix . 'system_prompt']) : '',
                );
                
                // Handle step-specific custom fields
                foreach ($_POST as $key => $value) {
                    if (strpos($key, $field_prefix) === 0 && strpos($key, 'custom_') !== false) {
                        $clean_key = str_replace($field_prefix, '', $key);
                        $step_settings[$clean_key] = sanitize_text_field($value);
                    }
                }
                
                // Save step configuration using action
                do_action('save_ai_config', [
                    'type' => 'step_config',
                    'step_id' => $step_id,
                    'data' => $step_settings
                ]);
                wp_send_json_success('Step settings saved');
                
            } else {
                // Global form processing (existing behavior)
                $provider = sanitize_text_field(wp_unslash($_POST['ai_provider']));
                $api_key = sanitize_text_field(wp_unslash($_POST['ai_api_key']));
                $settings = array(
                    'model' => sanitize_text_field(wp_unslash($_POST['ai_model'])),
                    'temperature' => isset($_POST['ai_temperature']) ? floatval($_POST['ai_temperature']) : null,
                    'system_prompt' => isset($_POST['ai_system_prompt']) ? sanitize_textarea_field($_POST['ai_system_prompt']) : '',
                    'instructions' => isset($_POST['instructions']) ? sanitize_textarea_field($_POST['instructions']) : ''
                );

                // Handle custom fields
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'custom_') === 0) {
                        $settings[$key] = sanitize_text_field($value);
                    }
                }

                // Save using actions
                do_action('save_ai_config', [
                    'type' => 'api_key',
                    'provider' => $provider,
                    'api_key' => $api_key
                ]);
                
                do_action('save_ai_config', [
                    'type' => 'provider_settings',
                    'provider' => $provider,
                    'data' => $settings
                ]);
                
                do_action('save_ai_config', [
                    'type' => 'selected_provider',
                    'provider' => $provider
                ]);
                
                wp_send_json_success('Settings saved');
            }
            
        } catch (Exception $e) {
            error_log('AI HTTP Client: Save settings AJAX failed: ' . $e->getMessage());
            wp_send_json_error('Save failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for loading provider settings with plugin context and step support
     */
    public static function ajax_load_provider_settings() {
        // Enhanced nonce verification - no fallbacks
        if (!isset($_POST['nonce'])) {
            wp_send_json_error('Security nonce is required for loading provider settings.');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'ai_http_nonce')) {
            wp_send_json_error('Security verification failed. Please refresh the page and try again.');
            return;
        }
        
        try {
            $plugin_context = sanitize_key(wp_unslash($_POST['plugin_context']));
            if (empty($plugin_context)) {
                wp_send_json_error('Plugin context is required');
            }
            
            $provider = sanitize_text_field(wp_unslash($_POST['provider']));
            $step_id = isset($_POST['step_id']) ? sanitize_key(wp_unslash($_POST['step_id'])) : null;
            
            $options_manager = new self($plugin_context, 'llm');
            
            // Use step-aware method if step_id is provided
            if ($step_id) {
                $settings = $options_manager->get_provider_settings_with_step($provider, $step_id);
            } else {
                $settings = $options_manager->get_provider_settings($provider);  
            }
            
            wp_send_json_success($settings);
            
        } catch (Exception $e) {
            error_log('AI HTTP Client: Load provider settings AJAX failed: ' . $e->getMessage());
            wp_send_json_error('Failed to load settings: ' . $e->getMessage());
        }
    }

    /**
     * Check if options manager is properly configured
     *
     * @return bool True if configured, false otherwise
     */
    public function is_configured() {
        return $this->is_configured;
    }

    // === STEP-AWARE CONFIGURATION METHODS ===

    /**
     * Base option name for step-scoped configuration
     */
    const STEP_CONFIG_OPTION_BASE = 'ai_http_client_step_config';

    /**
     * Get configuration for a specific step
     *
     * @param string $step_id Step identifier
     * @return array Step configuration
     */
    public function get_step_configuration($step_id) {
        if (!$this->is_configured) {
            return array();
        }
        
        $option_name = $this->get_scoped_option_name(self::STEP_CONFIG_OPTION_BASE);
        $step_configs = get_option($option_name, array());
        return isset($step_configs[$step_id]) ? $step_configs[$step_id] : array();
    }

    /**
     * Save configuration for a specific step
     *
     * @param string $step_id Step identifier  
     * @param array $config Step configuration
     * @return bool True if saved successfully
     */
    public function save_step_configuration($step_id, $config) {
        if (!$this->is_configured) {
            return false;
        }
        
        $option_name = $this->get_scoped_option_name(self::STEP_CONFIG_OPTION_BASE);
        $step_configs = get_option($option_name, array());
        
        // Check if this step already exists
        $existing_config = isset($step_configs[$step_id]) ? $step_configs[$step_id] : null;
        
        $sanitized_config = $this->sanitize_step_settings($config);
        
        // Check if configs are identical
        $configs_identical = ($existing_config && json_encode($existing_config) === json_encode($sanitized_config));
        
        $step_configs[$step_id] = $sanitized_config;
        
        $update_result = update_option($option_name, $step_configs);
        
        // CRITICAL FIX: WordPress update_option() returns false if no change is needed
        // If configs are identical, that's actually success (no update needed)
        return $update_result || $configs_identical;
    }

    /**
     * Get all step configurations for this plugin context
     *
     * @return array All step configurations
     */
    public function get_all_step_configurations() {
        if (!$this->is_configured) {
            return array();
        }
        
        return get_option($this->get_scoped_option_name(self::STEP_CONFIG_OPTION_BASE), array());
    }

    /**
     * Get provider settings with step context (step-specific settings take priority)
     *
     * @param string $provider_name Provider name
     * @param string $step_id Optional step identifier for step-specific settings
     * @return array Provider settings with merged API key and step-specific overrides
     */
    public function get_provider_settings_with_step($provider_name, $step_id = null) {
        // Start with global provider settings
        $provider_settings = $this->get_provider_settings($provider_name);
        
        // If step_id provided, merge with step-specific configuration
        if ($step_id) {
            $step_config = $this->get_step_configuration($step_id);
            
            // If this step is configured for the specified provider, merge step settings
            if (isset($step_config['provider']) && $step_config['provider'] === $provider_name) {
                $provider_settings = array_merge($provider_settings, $step_config);
            }
        }
        
        return $provider_settings;
    }

    /**
     * Delete configuration for a specific step
     *
     * @param string $step_id Step identifier
     * @return bool True if deleted successfully
     */
    public function delete_step_configuration($step_id) {
        if (!$this->is_configured) {
            return false;
        }
        
        $step_configs = get_option($this->get_scoped_option_name(self::STEP_CONFIG_OPTION_BASE), array());
        
        if (isset($step_configs[$step_id])) {
            unset($step_configs[$step_id]);
            return update_option($this->get_scoped_option_name(self::STEP_CONFIG_OPTION_BASE), $step_configs);
        }
        
        return true; // Already doesn't exist
    }

    /**
     * Check if a step has configuration
     *
     * @param string $step_id Step identifier
     * @return bool True if step has configuration
     */
    public function has_step_configuration($step_id) {
        $step_config = $this->get_step_configuration($step_id);
        return !empty($step_config);
    }

    /**
     * Sanitize step settings
     *
     * @param array $settings Raw step settings
     * @return array Sanitized step settings
     */
    private function sanitize_step_settings($settings) {
        $allowed_fields = array(
            'provider' => 'sanitize_text_field',
            'model' => 'sanitize_text_field', 
            'temperature' => 'floatval',
            'max_tokens' => 'intval',
            'system_prompt' => 'wp_kses_post',
            'tools_enabled' => 'sanitize_tools_array'
        );
        
        $sanitized = array();
        
        foreach ($settings as $key => $value) {
            if (isset($allowed_fields[$key])) {
                $sanitizer = $allowed_fields[$key];
                
                if ($sanitizer === 'sanitize_tools_array') {
                    $sanitized[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : array();
                } else {
                    $sanitized[$key] = call_user_func($sanitizer, $value);
                }
            }
        }
        
        return $sanitized;
    }
}

// Initialize AJAX handlers
add_action('plugins_loaded', ['AI_HTTP_Options_Manager', 'init_ajax_handlers']);