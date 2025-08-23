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

// Repository registration and cleanup now handled in Engine/filters/DataMachineFilters.php
    
    // Schedule cleanup on plugin activation or settings change
    add_action('init', function() {
        // Only schedule if auto-cleanup is enabled and not already scheduled
        if (dm_files_should_schedule_cleanup() && !as_next_scheduled_action('dm_cleanup_old_files')) {
            as_schedule_recurring_action(
                time() + WEEK_IN_SECONDS,
                WEEK_IN_SECONDS,
                'dm_cleanup_old_files',
                [],
                'data-machine-files'
            );
            
            do_action('dm_log', 'debug', 'FilesRepository: Weekly cleanup scheduled.');
        }
    });
    
    
}



/**
 * Check if cleanup should be scheduled based on settings
 *
 * @return bool True if cleanup should be scheduled
 */
function dm_files_should_schedule_cleanup(): bool {
    // Get settings via filter discovery pattern
    $all_settings = apply_filters('dm_handler_settings', []);
    $files_settings = $all_settings['files'] ?? null;
    
    if (!$files_settings) {
        return true;
    }
    
    $defaults = $files_settings->get_defaults();
    
    // For now, use defaults. In the future, this could check actual handler configurations
    // across all flows to see if any have auto_cleanup_enabled set to true
    return $defaults['auto_cleanup_enabled'] ?? true;
}

// Auto-register when file loads - achieving complete self-containment
dm_register_files_fetch_filters();