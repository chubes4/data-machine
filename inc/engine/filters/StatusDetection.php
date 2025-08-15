<?php
/**
 * Status Detection Filter System
 *
 * Centralized status detection for UI components using filter-based architecture.
 * Provides red/yellow/green status indicators for step cards and other UI elements.
 *
 * @package DataMachine\Core\Admin\Filters
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Filters;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all status detection filters
 *
 * Implements the dm_detect_status filter system for UI status indicators.
 * Returns simple 'red', 'yellow', 'green' status strings.
 *
 * @since 1.0.0
 */
function dm_register_status_detection_filters() {
    
    /**
     * AI Step Status Detection
     *
     * Context: 'ai_step'
     * Data: ['pipeline_step_id' => string]
     * Returns: 'red' if not configured, 'green' if configured
     */
    add_filter('dm_detect_status', function($default_status, $context, $data) {
        if ($context !== 'ai_step') {
            return $default_status;
        }
        
        $pipeline_step_id = $data['pipeline_step_id'] ?? null;
        if (!$pipeline_step_id) {
            return 'red'; // No pipeline step ID
        }
        
        // Get AI configuration for this pipeline step
        $ai_config = apply_filters('dm_ai_config', [], $pipeline_step_id);
        
        // Check if basic AI configuration exists
        if (empty($ai_config)) {
            return 'red'; // No AI config at all
        }
        
        // Check for required provider and model - look for both 'provider' and 'selected_provider'
        $has_provider = !empty($ai_config['selected_provider']) || !empty($ai_config['provider']);
        $has_model = !empty($ai_config['model']);
        
        if (!$has_provider || !$has_model) {
            return 'red'; // Missing critical configuration
        }
        
        // AI step is properly configured
        return 'green';
        
    }, 10, 3);
    
    /**
     * Handler Authentication Status Detection
     *
     * Context: 'handler_auth'
     * Data: ['handler_slug' => string]
     * Returns: 'red' if not authenticated, 'green' if authenticated
     */
    add_filter('dm_detect_status', function($default_status, $context, $data) {
        if ($context !== 'handler_auth') {
            return $default_status;
        }
        
        $handler_slug = $data['handler_slug'] ?? null;
        if (!$handler_slug) {
            return 'red'; // No handler slug provided
        }
        
        // Check if handler has authentication data
        $auth_data = apply_filters('dm_oauth', [], 'retrieve', $handler_slug);
        
        if (empty($auth_data)) {
            return 'red'; // No authentication data
        }
        
        // Handler is authenticated
        return 'green';
        
    }, 10, 3);
    
    /**
     * WordPress Draft Mode Detection
     *
     * Context: 'wordpress_draft'
     * Data: ['flow_step_id' => string]
     * Returns: 'yellow' if set to draft, 'green' if set to publish
     */
    add_filter('dm_detect_status', function($default_status, $context, $data) {
        if ($context !== 'wordpress_draft') {
            return $default_status;
        }
        
        $flow_step_id = $data['flow_step_id'] ?? null;
        if (!$flow_step_id) {
            return 'green'; // No flow step ID, can't check
        }
        
        // Get handler configuration
        $step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
        $handler_settings = $step_config['handler']['settings'] ?? [];
        
        // Check post status setting
        $post_status = $handler_settings['post_status'] ?? 'publish';
        
        if ($post_status === 'draft') {
            return 'yellow'; // Warning: set to draft mode
        }
        
        return 'green';
        
    }, 10, 3);
    
    /**
     * Files Handler Status Detection
     *
     * Context: 'files_status'
     * Data: ['flow_step_id' => string]
     * Returns: 'red' if no files or all processed, 'green' if has unprocessed files
     */
    add_filter('dm_detect_status', function($default_status, $context, $data) {
        if ($context !== 'files_status') {
            return $default_status;
        }
        
        $flow_step_id = $data['flow_step_id'] ?? null;
        if (!$flow_step_id) {
            return 'red'; // No flow step ID
        }
        
        // Get files repository
        $files_repo = apply_filters('dm_files_repository', [])['files'] ?? null;
        if (!$files_repo) {
            return 'red'; // No files repository available
        }
        
        // Get all files for this flow step
        $files = $files_repo->get_all_files($flow_step_id);
        
        if (empty($files)) {
            return 'red'; // No files uploaded
        }
        
        // Check if all files are processed
        $unprocessed_count = 0;
        foreach ($files as $file) {
            $file_identifier = $file['path'] ?? $file['filename'] ?? '';
            if ($file_identifier) {
                $is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, 'files', $file_identifier);
                if (!$is_processed) {
                    $unprocessed_count++;
                }
            }
        }
        
        if ($unprocessed_count === 0) {
            return 'red'; // All files processed, no work to do
        }
        
        return 'green'; // Has unprocessed files
        
    }, 10, 3);
    
}

// Auto-register filters when file loads
dm_register_status_detection_filters();