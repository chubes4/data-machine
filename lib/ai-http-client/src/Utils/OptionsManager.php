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
     * Whether the options manager is properly configured
     */
    private $is_configured = false;

    /**
     * Constructor with plugin context
     *
     * @param string $plugin_context Plugin context for scoped configuration
     */
    public function __construct($plugin_context = null) {
        // Use direct plugin context validation (simplified approach)
        if (empty($plugin_context)) {
            throw new Exception('Plugin context is required for AI_HTTP_Options_Manager');
        }
        
        $this->plugin_context = sanitize_key($plugin_context);
        $this->is_configured = true; // Assume configured if context provided
    }

    /**
     * Get plugin scoped option name
     *
     * @param string $base_name Base option name
     * @return string Scoped option name with plugin context
     */
    private function get_scoped_option_name($base_name) {
        return $base_name . '_' . $this->plugin_context;
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

    // save_provider_settings removed - plugins handle their own configuration

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

    // delete_provider_settings removed - plugins handle their own configuration

    // reset_all_settings removed - plugins handle their own configuration

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

    
    // AJAX handlers removed - plugins handle their own configuration

    /**
     * Check if options manager is properly configured
     *
     * @return bool True if configured, false otherwise
     */
    public function is_configured() {
        return $this->is_configured;
    }

    // All step-aware configuration methods removed - plugins handle their own step configuration
}

// AJAX handlers removed - plugins handle their own configuration