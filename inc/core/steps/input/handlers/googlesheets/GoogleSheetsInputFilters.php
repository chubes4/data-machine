<?php
/**
 * Google Sheets Input Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Google Sheets Input Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * Reuses existing Google Sheets OAuth infrastructure from output handler for
 * seamless bi-directional integration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Input\GoogleSheets
 * @since NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Input\GoogleSheets;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Google Sheets Input Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Google Sheets Input Handler capabilities purely through filter-based discovery.
 * 
 * @since NEXT_VERSION
 */
function dm_register_googlesheets_input_filters() {
    
    // Handler registration - Google Sheets Input declares itself as input handler (pure discovery mode)
    add_filter('dm_get_handlers', function($handlers) {
        $handlers['googlesheets'] = [
            'type' => 'input',
            'class' => GoogleSheetsInput::class,
            'label' => __('Google Sheets', 'data-machine'),
            'description' => __('Read data from Google Sheets spreadsheets', 'data-machine')
        ];
        return $handlers;
    });
    
    // Settings registration - parameter-matched to 'googlesheets' handler
    add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
        if ($handler_slug === 'googlesheets') {
            return new GoogleSheetsInputSettings();
        }
        return $settings;
    }, 10, 2);
    
    // Modal content registration - Google Sheets Input owns its handler-settings and handler-auth modal content
    add_filter('dm_get_modal', function($content, $template) {
        // Return early if content already provided by another handler
        if ($content !== null) {
            return $content;
        }
        
        $context = $_POST['context'] ?? [];
        $handler_slug = $context['handler_slug'] ?? '';
        
        // Only handle googlesheets handler when step_type is input
        if ($handler_slug !== 'googlesheets' || ($context['step_type'] ?? '') !== 'input') {
            return $content;
        }
        
        if ($template === 'handler-settings') {
            // Settings modal template
            $settings_instance = apply_filters('dm_get_handler_settings', null, 'googlesheets');
            
            return apply_filters('dm_render_template', '', 'modal/handler-settings-form', [
                'handler_slug' => 'googlesheets',
                'handler_config' => [
                    'label' => __('Google Sheets', 'data-machine'),
                    'description' => __('Read data from Google Sheets spreadsheets', 'data-machine')
                ],
                'step_type' => $context['step_type'] ?? 'input',
                'flow_id' => $context['flow_id'] ?? '',
                'pipeline_id' => $context['pipeline_id'] ?? '',
                'settings_available' => ($settings_instance !== null),
                'handler_settings' => $settings_instance
            ]);
        }
        
        if ($template === 'handler-auth') {
            // Authentication modal template
            return apply_filters('dm_render_template', '', 'modal/handler-auth-form', [
                'handler_slug' => 'googlesheets',
                'handler_config' => [
                    'label' => __('Google Sheets', 'data-machine'),
                    'description' => __('Read data from Google Sheets spreadsheets', 'data-machine')
                ],
                'step_type' => $context['step_type'] ?? 'input'
            ]);
        }
        
        return $content;
    }, 10, 2);
    
    // Authentication registration - reuse existing Google Sheets OAuth infrastructure
    // This creates bi-directional Google Sheets integration by sharing auth with output handler
    add_filter('dm_get_auth', function($auth, $handler_slug) {
        if ($handler_slug === 'googlesheets') {
            // Return existing Google Sheets auth instance (shared with output handler)
            return new \DataMachine\Core\Handlers\Output\GoogleSheets\GoogleSheetsAuth();
        }
        return $auth;
    }, 10, 2);
    
    // DataPacket conversion registration - Google Sheets Input handler uses dedicated DataPacket class
    add_filter('dm_create_datapacket', function($datapacket, $source_data, $source_type, $context) {
        if ($source_type === 'googlesheets') {
            return GoogleSheetsInputDataPacket::create($source_data, $context);
        }
        return $datapacket;
    }, 10, 4);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_googlesheets_input_filters();