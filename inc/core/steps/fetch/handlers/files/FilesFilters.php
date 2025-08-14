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
 * @subpackage Core\Handlers\Fetch\Files
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Fetch\Files;

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
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
    
    // DataPacket creation removed - engine uses universal DataPacket constructor
    // Files handler returns properly formatted data for direct constructor usage
    
    // Register Files handler repository implementation via engine filter
    add_filter('dm_files_repository', function($repositories) {
        $repositories['files'] = new FilesRepository();
        return $repositories;
    });
    
    // Action Scheduler cleanup integration
    add_action('dm_cleanup_old_files', function() {
        $repositories = apply_filters('dm_files_repository', []);
        $repository = $repositories['files'] ?? null;
        if ($repository) {
            $deleted_count = $repository->cleanup_old_files(7); // Delete files older than 7 days
            
            do_action('dm_log', 'debug', 'FilesRepository: Scheduled cleanup completed.', [
                'deleted_files' => $deleted_count
            ]);
        }
    });
    
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
    
    // AJAX file upload handler
    add_action('wp_ajax_dm_upload_file', fn() => do_action('dm_ajax_route', 'dm_upload_file', 'modal'));
    
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
        // Fallback to default if settings not available
        return true;
    }
    
    $defaults = $files_settings->get_defaults();
    
    // For now, use defaults. In the future, this could check actual handler configurations
    // across all flows to see if any have auto_cleanup_enabled set to true
    return $defaults['auto_cleanup_enabled'] ?? true;
}

// Auto-register when file loads - achieving complete self-containment
dm_register_files_fetch_filters();