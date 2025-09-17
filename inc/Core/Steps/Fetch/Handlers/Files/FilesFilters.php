<?php
/**
 * Files Fetch Handler Component Filter Registration
 * 
 * Modular Component System Implementation
 * 
 * This file serves as Files Fetch Handler's complete interface contract with the engine,
 * demonstrating comprehensive self-containment and systematic organization.
 * Each handler component manages its own filter registration for AI workflow integration.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\Files
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Files;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}


/**
 * Register all Files Fetch Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Files Fetch Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_files_fetch_filters() {
    // Handler registration - Files declares itself as fetch handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['files'] = [
            'type' => 'fetch',
            'class' => Files::class,
            'label' => __('Files', 'data-machine'),
            'description' => __('Process local files and uploads', 'data-machine')
        ];
        return $handlers;
    });
    
    // Settings registration - parameter-matched to 'files' handler
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['files'] = new FilesSettings();
        return $all_settings;
    });
    
    // Metadata parameter injection - Files specific
    add_filter('dm_engine_parameters', function($parameters, $data, $flow_step_config, $step_type, $flow_step_id) {
        // Only process for steps that come after files fetch
        if (empty($data) || !is_array($data)) {
            return $parameters;
        }

        $latest_entry = $data[0] ?? [];
        $metadata = $latest_entry['metadata'] ?? [];
        $source_type = $metadata['source_type'] ?? '';

        // Only inject Files metadata
        if ($source_type === 'files') {
            // Add Files specific parameters to flat structure
            $parameters['file_path'] = $metadata['file_path'] ?? '';
            $parameters['file_name'] = $metadata['file_name'] ?? '';
            $parameters['mime_type'] = $metadata['mime_type'] ?? '';
            $parameters['file_size'] = $metadata['file_size'] ?? 0;
            $parameters['original_id'] = $metadata['original_id'] ?? '';
            $parameters['original_title'] = $metadata['original_title'] ?? '';
            $parameters['original_date_gmt'] = $metadata['original_date_gmt'] ?? '';

            do_action('dm_log', 'debug', 'Files: Metadata injected into engine parameters', [
                'flow_step_id' => $flow_step_id,
                'file_path' => $parameters['file_path'],
                'file_name' => $parameters['file_name'],
                'mime_type' => $parameters['mime_type']
            ]);
        }

        return $parameters;
    }, 10, 5);
    
    // Files custom template registration - provides specialized upload interface
    add_filter('dm_render_template', function($content, $template_name, $data = []) {
        if ($template_name === 'modal/handler-settings/files') {
            // Files handler provides its own template with upload interface
            $template_path = dirname(__DIR__, 4) . '/admin/pages/pipelines/templates/modal/handler-settings/files.php';
            if (file_exists($template_path)) {
                // Extract data for template scope
                $context = $data;
                
                // Capture template output
                ob_start();
                include $template_path;
                return ob_get_clean();
            }
        }
        return $content;
    }, 10, 3);

// Repository registration and cleanup now handled in Engine/filters/FilesRepository.php
    

}

// Auto-register when file loads - achieving complete self-containment
dm_register_files_fetch_filters();