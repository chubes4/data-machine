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
    add_filter('dm_get_handlers', function($handlers) {
        $handlers['files'] = [
            'type' => 'fetch',
            'class' => Files::class,
            'label' => __('Files', 'data-machine'),
            'description' => __('Process local files and uploads', 'data-machine')
        ];
        return $handlers;
    });
    
    // Settings registration - parameter-matched to 'files' handler
    add_filter('dm_get_handler_settings', function($all_settings) {
        $all_settings['files'] = new FilesSettings();
        return $all_settings;
    });
    
    // Files custom template registration - provides specialized upload interface
    add_filter('dm_render_template', function($content, $template_name, $data = []) {
        if ($template_name === 'modal/handler-settings/files') {
            // Files handler provides its own template with upload interface
            // Use the existing files.php template temporarily
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
    
    // FilesRepository service registration - singleton pattern for consistency
    add_filter('dm_get_files_repository', function($repository) {
        static $instance = null;
        if ($instance === null) {
            $instance = new FilesRepository();
        }
        return $instance;
    }, 10, 1);
    
    // Action Scheduler cleanup integration
    add_action('dm_cleanup_old_files', function() {
        $repository = apply_filters('dm_get_files_repository', null);
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
    add_action('wp_ajax_dm_upload_file', function() {
        dm_handle_file_upload();
    });
    
    // AJAX handler for getting files with processing status
    add_action('wp_ajax_dm_get_handler_files', function() {
        dm_get_handler_files();
    });
}

/**
 * Handle AJAX file upload with handler context support
 */
function dm_handle_file_upload() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_upload_file')) {
        wp_send_json_error(['message' => __('Security check failed.', 'data-machine')]);
        return;
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => __('File upload failed.', 'data-machine')]);
        return;
    }
    
    $file = $_FILES['file'];
    
    // Extract flow_step_id from request for proper file isolation
    // Use the flow_step_id provided by the frontend form
    $flow_step_id = sanitize_text_field($_POST['flow_step_id'] ?? '');
    
    if (empty($flow_step_id)) {
        wp_send_json_error(['message' => __('Missing flow step ID from form data.', 'data-machine')]);
        return;
    }
    
    try {
        // Basic validation - file size and dangerous extensions
        $file_size = filesize($file['tmp_name']);
        if ($file_size === false) {
            throw new Exception(__('Cannot determine file size.', 'data-machine'));
        }
        
        // 32MB limit
        $max_file_size = 32 * 1024 * 1024;
        if ($file_size > $max_file_size) {
            throw new Exception(sprintf(
                __('File too large: %1$s. Maximum allowed size: %2$s', 'data-machine'),
                size_format($file_size),
                size_format($max_file_size)
            ));
        }

        // Block dangerous extensions
        $dangerous_extensions = ['php', 'exe', 'bat', 'cmd', 'scr'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $dangerous_extensions)) {
            throw new Exception(__('File type not allowed for security reasons.', 'data-machine'));
        }
        
        // Use repository to store file with handler context
        $repository = apply_filters('dm_get_files_repository', null);
        if (!$repository) {
            wp_send_json_error(['message' => __('File repository service not available.', 'data-machine')]);
            return;
        }
        
        $stored_path = $repository->store_file($file['tmp_name'], $file['name'], $flow_step_id);
        
        if (!$stored_path) {
            wp_send_json_error(['message' => __('Failed to store file.', 'data-machine')]);
            return;
        }
        
        // Get file info for response
        $file_info = $repository->get_file_info(basename($stored_path));
        
        do_action('dm_log', 'debug', 'File uploaded successfully via AJAX.', [
            'filename' => $file['name'],
            'stored_path' => $stored_path,
            'flow_step_id' => $flow_step_id
        ]);
        
        wp_send_json_success([
            'file_info' => $file_info,
            'message' => sprintf(__('File "%s" uploaded successfully.', 'data-machine'), $file['name'])
        ]);
        
    } catch (Exception $e) {
        do_action('dm_log', 'error', 'File upload failed.', [
            'filename' => $file['name'],
            'error' => $e->getMessage(),
            'flow_step_id' => $flow_step_id
        ]);
        
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Handle AJAX request to get files with processing status for a specific handler
 */
function dm_get_handler_files() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_upload_file')) {
        wp_send_json_error(['message' => __('Security check failed.', 'data-machine')]);
        return;
    }
    
    // Use the flow_step_id provided by the frontend
    $flow_step_id = sanitize_text_field($_POST['flow_step_id'] ?? '');
    
    if (empty($flow_step_id)) {
        wp_send_json_error(['message' => __('Missing flow step ID from request.', 'data-machine')]);
        return;
    }
    
    try {
        // Get files repository service
        $repository = apply_filters('dm_get_files_repository', null);
        if (!$repository) {
            wp_send_json_error(['message' => __('File repository service not available.', 'data-machine')]);
            return;
        }
        
        // Get all files for this flow step
        $files = $repository->get_all_files($flow_step_id);
        
        // Get processed items service to check processing status
        $all_databases = apply_filters('dm_get_database_services', []);
        $processed_items_service = $all_databases['processed_items'] ?? null;
        if (!$processed_items_service) {
            wp_send_json_error(['message' => __('Processed items service not available.', 'data-machine')]);
            return;
        }
        
        // Enhance files with processing status
        $files_with_status = [];
        foreach ($files as $file) {
            $is_processed = false;
            do_action('dm_is_item_processed', $flow_id, 'files', $file['path'], &$is_processed);
            
            $files_with_status[] = [
                'filename' => $file['filename'],
                'size' => $file['size'],
                'size_formatted' => size_format($file['size']),
                'modified' => $file['modified'],
                'modified_formatted' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $file['modified']),
                'is_processed' => $is_processed,
                'status' => $is_processed ? __('Processed', 'data-machine') : __('Pending', 'data-machine'),
                'path' => $file['path']
            ];
        }
        
        wp_send_json_success([
            'files' => $files_with_status,
            'total_files' => count($files_with_status),
            'pending_files' => count(array_filter($files_with_status, fn($f) => !$f['is_processed']))
        ]);
        
    } catch (Exception $e) {
        do_action('dm_log', 'error', 'Failed to get handler files.', [
            'error' => $e->getMessage(),
            'flow_step_id' => $flow_step_id
        ]);
        
        wp_send_json_error(['message' => __('Failed to retrieve files.', 'data-machine')]);
    }
}


/**
 * Check if cleanup should be scheduled based on settings
 *
 * @return bool True if cleanup should be scheduled
 */
function dm_files_should_schedule_cleanup(): bool {
    // Get default settings - cleanup enabled by default
    $settings_class = new FilesSettings();
    $defaults = $settings_class->get_defaults();
    
    // For now, use defaults. In the future, this could check actual handler configurations
    // across all flows to see if any have auto_cleanup_enabled set to true
    return $defaults['auto_cleanup_enabled'] ?? true;
}

// Auto-register when file loads - achieving complete self-containment
dm_register_files_fetch_filters();