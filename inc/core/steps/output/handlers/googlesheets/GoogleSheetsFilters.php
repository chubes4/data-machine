<?php
/**
 * Google Sheets Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Google Sheets's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Output\GoogleSheets
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Output\GoogleSheets;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Google Sheets component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Google Sheets capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_googlesheets_filters() {
    
    // Handler registration - Google Sheets declares itself as output handler (pure discovery mode)
    add_filter('dm_get_handlers', function($handlers) {
        $handlers['googlesheets_output'] = [
            'type' => 'output',
            'class' => GoogleSheets::class,
            'label' => __('Google Sheets', 'data-machine'),
            'description' => __('Append structured data to Google Sheets for analytics, reporting, and team collaboration', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_get_auth_providers', function($providers) {
        $providers['googlesheets_output'] = new GoogleSheetsAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_get_handler_settings', function($all_settings) {
        $all_settings['googlesheets_output'] = new GoogleSheetsSettings();
        return $all_settings;
    });
    
    // Modal content registration - Pure discovery mode
    add_filter('dm_get_modals', function($modals) {
        // Get GoogleSheets settings for modal content
        $all_settings = apply_filters('dm_get_handler_settings', []);
        $settings_instance = $all_settings['googlesheets_output'] ?? null;
        
        // Handler-specific modal removed - core modal handles generic 'handler-settings'
        
        // Handler authentication modal
        $modals['googlesheets_output-handler-auth'] = [
            'content' => apply_filters('dm_render_template', '', 'modal/handler-auth-form', [
                'handler_slug' => 'googlesheets_output',
                'handler_config' => [
                    'label' => __('Google Sheets', 'data-machine'),
                    'description' => __('Append structured data to Google Sheets for analytics, reporting, and team collaboration', 'data-machine')
                ],
                'step_type' => 'output'
            ]),
            'title' => __('Google Sheets Authentication', 'data-machine')
        ];
        
        return $modals;
    });
}

// Auto-register when file loads - achieving complete self-containment
dm_register_googlesheets_filters();