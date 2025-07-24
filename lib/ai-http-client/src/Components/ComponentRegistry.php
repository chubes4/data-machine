<?php
/**
 * AI HTTP Client - Component Registry
 * 
 * Single Responsibility: Manage available UI components
 * Handles component registration, discovery, and instantiation
 *
 * @package AIHttpClient\Components
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Component_Registry {
    
    /**
     * Core components (always required)
     */
    private static $core_components = [
        'provider_selector' => 'AI_HTTP_Core_ProviderSelector',
        'api_key_input' => 'AI_HTTP_Core_ApiKeyInput',
        'model_selector' => 'AI_HTTP_Core_ModelSelector'
    ];
    
    /**
     * Extended components (optional)
     */
    private static $extended_components = [
        'temperature_slider' => 'AI_HTTP_Extended_TemperatureSlider',
        'system_prompt_field' => 'AI_HTTP_Extended_SystemPromptField',
        'max_tokens_input' => 'AI_HTTP_Extended_MaxTokensInput',
        'streaming_toggle' => 'AI_HTTP_Extended_StreamingToggle',
        'test_connection' => 'AI_HTTP_Extended_TestConnection'
    ];
    
    /**
     * Custom components registered by plugins
     */
    private static $custom_components = [];
    
    /**
     * Whether components have been auto-discovered
     */
    private static $components_discovered = false;
    
    /**
     * Get all available components
     *
     * @return array Available components
     */
    public static function get_all_components() {
        // Auto-discover components if not already done
        if (!self::$components_discovered) {
            self::discover_components();
        }
        
        return array_merge(
            self::$core_components,
            self::$extended_components,
            self::$custom_components
        );
    }
    
    /**
     * Discover and register components via WordPress filters
     */
    private static function discover_components() {
        if (self::$components_discovered) {
            return;
        }
        
        // Allow plugins to register components
        $registered_components = apply_filters('ai_http_client_register_components', []);
        
        foreach ($registered_components as $name => $class) {
            try {
                self::register_component($name, $class);
            } catch (Exception $e) {
                // Log error but don't break the system
                if (function_exists('error_log')) {
                    error_log('AI HTTP Client: Failed to register component ' . $name . ': ' . $e->getMessage());
                }
            }
        }
        
        self::$components_discovered = true;
    }
    
    /**
     * Get core components
     *
     * @return array Core components
     */
    public static function get_core_components() {
        return self::$core_components;
    }
    
    /**
     * Get extended components
     *
     * @return array Extended components
     */
    public static function get_extended_components() {
        return self::$extended_components;
    }
    
    /**
     * Register a custom component
     *
     * @param string $name Component name
     * @param string $class Component class
     * @param bool $allow_override Whether to allow overriding existing components
     * @throws Exception If component already exists and override not allowed
     */
    public static function register_component($name, $class, $allow_override = false) {
        if (self::component_exists($name) && !$allow_override) {
            throw new Exception("Component '" . esc_html($name) . "' already exists");
        }
        
        if (!class_exists($class)) {
            throw new Exception("Component class '" . esc_html($class) . "' does not exist");
        }
        
        if (!is_subclass_of($class, 'AI_HTTP_Component_Interface')) {
            throw new Exception("Component class '" . esc_html($class) . "' must implement AI_HTTP_Component_Interface");
        }
        
        self::$custom_components[$name] = $class;
    }
    
    /**
     * Force component discovery (useful for testing)
     */
    public static function force_discovery() {
        self::$components_discovered = false;
        self::discover_components();
    }
    
    /**
     * Check if component exists
     *
     * @param string $name Component name
     * @return bool True if exists
     */
    public static function component_exists($name) {
        $all_components = self::get_all_components();
        return isset($all_components[$name]);
    }
    
    /**
     * Get component class
     *
     * @param string $name Component name
     * @return string|null Component class or null if not found
     */
    public static function get_component_class($name) {
        $all_components = self::get_all_components();
        return isset($all_components[$name]) ? $all_components[$name] : null;
    }
    
    /**
     * Render component
     *
     * @param string $name Component name
     * @param string $unique_id Unique component identifier
     * @param array $config Component configuration
     * @param array $current_values Current saved values
     * @return string Rendered HTML
     * @throws Exception If component not found
     */
    public static function render_component($name, $unique_id, $config = [], $current_values = []) {
        $class = self::get_component_class($name);
        
        if (!$class) {
            throw new Exception("Component '" . esc_html($name) . "' not found");
        }
        
        return call_user_func([$class, 'render'], $unique_id, $config, $current_values);
    }
    
    /**
     * Get component default configuration
     *
     * @param string $name Component name
     * @return array Default configuration
     * @throws Exception If component not found
     */
    public static function get_component_defaults($name) {
        $class = self::get_component_class($name);
        
        if (!$class) {
            throw new Exception("Component '" . esc_html($name) . "' not found");
        }
        
        return call_user_func([$class, 'get_defaults']);
    }
    
    /**
     * Validate component configuration
     *
     * @param string $name Component name
     * @param array $config Configuration to validate
     * @return bool True if valid
     * @throws Exception If component not found
     */
    public static function validate_component_config($name, $config) {
        $class = self::get_component_class($name);
        
        if (!$class) {
            throw new Exception("Component '" . esc_html($name) . "' not found");
        }
        
        return call_user_func([$class, 'validate_config'], $config);
    }
}