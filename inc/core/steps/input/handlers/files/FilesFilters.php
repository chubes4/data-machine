<?php
/**
 * Files Input Handler Component Filter Registration
 * 
 * Modular Component System Implementation
 * 
 * This file serves as Files Input Handler's complete interface contract with the engine,
 * demonstrating comprehensive self-containment and systematic organization.
 * Each handler component manages its own filter registration for AI workflow integration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Input\Files
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Input\Files;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}


/**
 * Register all Files Input Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Files Input Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_files_input_filters() {
    // Handler registration - Files declares itself as input handler
    add_filter('dm_get_handlers', function($handlers, $type) {
        if ($type === 'input') {
            // Initialize handlers array if null
            if ($handlers === null) {
                $handlers = [];
            }
            
            $handlers['files'] = [
                'class' => Files::class,
                'label' => __('Files', 'data-machine'),
                'description' => __('Process local files and uploads', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);
    
    // Settings registration - parameter-matched to 'files' handler
    add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
        if ($handler_slug === 'files') {
            return new FilesSettings();
        }
        return $settings;
    }, 10, 2);
    
    // Modal content registration - Files owns its handler-settings modal content
    add_filter('dm_get_modal', function($content, $template) {
        if ($template === 'handler-settings') {
            // Return early if content already provided by another handler
            if ($content !== null) {
                return $content;
            }
            
            $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
            $handler_slug = $context['handler_slug'] ?? '';
            
            // Only handle files handler
            if ($handler_slug !== 'files') {
                return $content;
            }
            
            // Use proper filter-based template rendering
            $pipelines_instance = new \DataMachine\Core\Admin\Pages\Pipelines\Pipelines();
            $settings_instance = apply_filters('dm_get_handler_settings', null, 'files');
            
            return $pipelines_instance->render_template('modal/handler-settings-form', [
                'handler_slug' => 'files',
                'handler_config' => [
                    'label' => __('Files', 'data-machine'),
                    'description' => __('Process uploaded files and documents', 'data-machine')
                ],
                'step_type' => $context['step_type'] ?? 'input',
                'settings_available' => ($settings_instance !== null),
                'handler_settings' => $settings_instance
            ]);
        }
        return $content;
    }, 10, 2);
    
    // DataPacket conversion registration - Files handler uses dedicated DataPacket class
    add_filter('dm_create_datapacket', function($datapacket, $source_data, $source_type, $context) {
        if ($source_type === 'files') {
            return FilesDataPacket::create($source_data, $context);
        }
        return $datapacket;
    }, 10, 4);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_files_input_filters();