<?php
/**
 * AI HTTP Client - Component Interface
 * 
 * Single Responsibility: Define contract for all UI components
 * Ensures consistent rendering and configuration across all components
 *
 * @package AIHttpClient\Components
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

interface AI_HTTP_Component_Interface {
    
    /**
     * Render the component HTML
     *
     * @param string $unique_id Unique component identifier
     * @param array $config Component configuration
     * @param array $current_values Current saved values
     * @return string Rendered HTML
     */
    public static function render($unique_id, $config = [], $current_values = []);
    
    /**
     * Get component configuration schema
     *
     * @return array Configuration schema
     */
    public static function get_config_schema();
    
    /**
     * Get component default values
     *
     * @return array Default values
     */
    public static function get_defaults();
    
    /**
     * Validate component configuration
     *
     * @param array $config Configuration to validate
     * @return bool True if valid
     */
    public static function validate_config($config);
}