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
        $handlers['googlesheets_input'] = [
            'type' => 'input',
            'class' => GoogleSheetsInput::class,
            'label' => __('Google Sheets', 'data-machine'),
            'description' => __('Read data from Google Sheets spreadsheets', 'data-machine')
        ];
        return $handlers;
    });
    
    // Settings registration - parameter-matched to 'googlesheets_input' handler
    add_filter('dm_get_handler_settings', function($all_settings) {
        $all_settings['googlesheets_input'] = new GoogleSheetsInputSettings();
        return $all_settings;
    });
    
    // Modal content registration - Google Sheets Input owns its handler-settings and handler-auth modal content
    add_filter('dm_get_modal', function($content, $template) {
        // Return early if content already provided by another handler
        if ($content !== null) {
            return $content;
        }
        
        // Properly sanitize context data following WordPress security standards
        $raw_context = wp_unslash($_POST['context'] ?? '');
        $context = is_string($raw_context) ? json_decode($raw_context, true) : [];
        $context = is_array($context) ? $context : [];
        $handler_slug = sanitize_text_field($context['handler_slug'] ?? '');
        
        // Only handle googlesheets_input handler when step_type is input
        if ($handler_slug !== 'googlesheets_input' || ($context['step_type'] ?? '') !== 'input') {
            return $content;
        }
        
        if ($template === 'handler-settings') {
            // Settings modal template
            $all_settings = apply_filters('dm_get_handler_settings', []);
            $settings_instance = $all_settings['googlesheets'] ?? null;
            
            return apply_filters('dm_render_template', '', 'modal/handler-settings-form', [
                'handler_slug' => 'googlesheets',
                'handler_config' => [
                    'label' => __('Google Sheets', 'data-machine'),
                    'description' => __('Read data from Google Sheets spreadsheets', 'data-machine')
                ],
                'step_type' => sanitize_text_field($context['step_type'] ?? 'input'),
                'flow_id' => sanitize_text_field($context['flow_id'] ?? ''),
                'pipeline_id' => sanitize_text_field($context['pipeline_id'] ?? ''),
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
                'step_type' => sanitize_text_field($context['step_type'] ?? 'input')
            ]);
        }
        
        return $content;
    }, 10, 2);
    
    // Authentication registration - pure discovery mode
    // This creates bi-directional Google Sheets integration by sharing auth with output handler
    add_filter('dm_get_auth_providers', function($providers) {
        // Use shared authentication for both input and output Google Sheets handlers
        $providers['googlesheets'] = new \DataMachine\Core\Handlers\Output\GoogleSheets\GoogleSheetsAuth();
        return $providers;
    });
    
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