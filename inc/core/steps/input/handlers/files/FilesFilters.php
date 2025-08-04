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
            $settings_instance = apply_filters('dm_get_handler_settings', null, 'files');
            
            return apply_filters('dm_render_template', '', 'modal/handler-settings-form', [
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
            
            $logger = apply_filters('dm_get_logger', null);
            $logger?->debug('FilesRepository: Scheduled cleanup completed.', [
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
            
            $logger = apply_filters('dm_get_logger', null);
            $logger?->debug('FilesRepository: Weekly cleanup scheduled.');
        }
    });
    
    // AJAX file upload handler
    add_action('wp_ajax_dm_upload_file', function() {
        dm_handle_file_upload();
    });
}

/**
 * Handle AJAX file upload
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
    $logger = apply_filters('dm_get_logger', null);
    
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
        
        // Use repository to store file
        $repository = apply_filters('dm_get_files_repository', null);
        if (!$repository) {
            wp_send_json_error(['message' => __('File repository service not available.', 'data-machine')]);
            return;
        }
        
        $stored_path = $repository->store_file($file['tmp_name'], $file['name']);
        
        if (!$stored_path) {
            wp_send_json_error(['message' => __('Failed to store file.', 'data-machine')]);
            return;
        }
        
        // Get file info for response
        $file_info = $repository->get_file_info(basename($stored_path));
        
        $logger?->debug('File uploaded successfully via AJAX.', [
            'filename' => $file['name'],
            'stored_path' => $stored_path
        ]);
        
        wp_send_json_success([
            'file_info' => $file_info,
            'message' => sprintf(__('File "%s" uploaded successfully.', 'data-machine'), $file['name'])
        ]);
        
    } catch (Exception $e) {
        $logger?->error('File upload failed.', [
            'filename' => $file['name'],
            'error' => $e->getMessage()
        ]);
        
        wp_send_json_error(['message' => $e->getMessage()]);
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
dm_register_files_input_filters();