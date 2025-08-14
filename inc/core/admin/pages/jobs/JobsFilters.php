<?php
/**
 * Jobs Admin Page Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as the Jobs Admin Page's "main plugin file" - the complete
 * interface contract with the engine, demonstrating complete self-containment
 * and zero bootstrap dependencies.
 * 
 * @package DataMachine
 * @subpackage Core\Admin\Pages\Jobs
 * @since 0.1.0
 */

namespace DataMachine\Core\Admin\Pages\Jobs;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Jobs Admin Page component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Jobs Admin Page capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_jobs_admin_page_filters() {
    
    // Pure discovery mode - matches actual system usage
    add_filter('dm_admin_pages', function($pages) {
        $pages['jobs'] = [
            'page_title' => __('Jobs', 'data-machine'),
            'menu_title' => __('Jobs', 'data-machine'),  
            'capability' => 'manage_options',
            'position' => 20,
            'templates' => __DIR__ . '/templates/',
            'assets' => [
                'css' => [
                    'dm-core-modal' => [
                        'file' => 'inc/core/admin/modal/assets/css/core-modal.css',
                        'deps' => [],
                        'media' => 'all'
                    ],
                    'dm-admin-jobs' => [
                        'file' => 'inc/core/admin/pages/jobs/assets/css/admin-jobs.css',
                        'deps' => ['dm-core-modal'],
                        'media' => 'all'
                    ]
                ],
                'js' => [
                    'dm-core-modal' => [
                        'file' => 'inc/core/admin/modal/assets/js/core-modal.js',
                        'deps' => ['jquery'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dmCoreModal',
                            'data' => [
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'get_modal_content_nonce' => wp_create_nonce('dm_get_modal_content'),
                                'strings' => [
                                    'loading' => __('Loading...', 'data-machine'),
                                    'error' => __('Error', 'data-machine'),
                                    'close' => __('Close', 'data-machine')
                                ]
                            ]
                        ]
                    ],
                    'dm-jobs-admin' => [
                        'file' => 'inc/core/admin/pages/jobs/assets/js/data-machine-jobs.js',
                        'deps' => ['jquery'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dmJobsAdmin',
                            'data' => [
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'strings' => [
                                    'loading' => __('Loading...', 'data-machine'),
                                    'error' => __('An error occurred', 'data-machine')
                                ]
                            ]
                        ]
                    ],
                    'dm-jobs-modal' => [
                        'file' => 'inc/core/admin/pages/jobs/assets/js/jobs-modal.js',
                        'deps' => ['jquery', 'dm-core-modal'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dmJobsModal',
                            'data' => [
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'clear_processed_items_nonce' => wp_create_nonce('dm_clear_processed_items_manual'),
                                'clear_jobs_nonce' => wp_create_nonce('dm_clear_jobs_manual'),
                                'get_pipeline_flows_nonce' => wp_create_nonce('dm_get_pipeline_flows_for_select'),
                                'strings' => [
                                    'loading' => __('Loading...', 'data-machine'),
                                    'error' => __('An error occurred', 'data-machine')
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        return $pages;
    });
}

// Auto-register when file loads - achieving complete self-containment
dm_register_jobs_admin_page_filters();

/**
 * Register Jobs Admin modal
 * 
 * @since NEXT_VERSION
 */
add_filter('dm_modals', function($modals) {
    $modals['jobs-admin'] = [
        'title' => __('Jobs Administration', 'data-machine'),
        'template' => 'modal/jobs-admin'
    ];
    return $modals;
});

/**
 * AJAX handler for manual processed items deletion
 * Uses the existing dm_delete action infrastructure
 * 
 * @since NEXT_VERSION
 */
add_action('wp_ajax_dm_clear_processed_items_manual', function() {
    // Security checks
    if (!check_ajax_referer('dm_clear_processed_items_manual', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
    }
    
    // Get parameters
    $clear_type = sanitize_text_field(wp_unslash($_POST['clear_type'] ?? ''));
    $target_id = isset($_POST['target_id']) ? intval($_POST['target_id']) : 0;
    
    if (!in_array($clear_type, ['pipeline', 'flow'])) {
        wp_send_json_error(['message' => __('Invalid clear type', 'data-machine')]);
    }
    
    if (!$target_id) {
        wp_send_json_error(['message' => __('Invalid target ID', 'data-machine')]);
    }
    
    // Use the existing dm_delete action to clear processed items
    // This will trigger the handle_processed_items_deletion method in Delete.php
    if ($clear_type === 'pipeline') {
        // For pipeline: Delete all processed items for flows in this pipeline
        // We need to implement 'pipeline_id' deletion in Delete.php
        do_action('dm_delete', 'processed_items', $target_id, ['delete_by' => 'pipeline_id']);
    } else {
        // For flow: Use existing flow_id deletion 
        do_action('dm_delete', 'processed_items', $target_id, ['delete_by' => 'flow_id']);
    }
    
    // The dm_delete action handles the response via wp_send_json_success/error
    // If we get here without a response sent, send a generic success
    // (This shouldn't happen as Delete.php sends the response)
});

/**
 * AJAX handler to get flows for a specific pipeline
 * 
 * @since NEXT_VERSION
 */
add_action('wp_ajax_dm_get_pipeline_flows_for_select', function() {
    // Security checks
    if (!check_ajax_referer('dm_get_pipeline_flows_for_select', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
    }
    
    $pipeline_id = isset($_POST['pipeline_id']) ? intval($_POST['pipeline_id']) : 0;
    
    if (!$pipeline_id) {
        wp_send_json_error(['message' => __('Invalid pipeline ID', 'data-machine')]);
    }
    
    // Use the existing filter to get flows for the pipeline
    $pipeline_flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
    
    $flows = [];
    foreach ($pipeline_flows as $flow) {
        $flows[] = [
            'flow_id' => $flow['flow_id'],
            'flow_name' => $flow['flow_name']
        ];
    }
    
    wp_send_json_success(['flows' => $flows]);
});

/**
 * AJAX handler for manual jobs deletion
 * Uses the existing dm_delete action infrastructure
 * 
 * @since NEXT_VERSION
 */
add_action('wp_ajax_dm_clear_jobs_manual', function() {
    // Security checks
    if (!check_ajax_referer('dm_clear_jobs_manual', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
    }
    
    // Get parameters
    $clear_type = sanitize_text_field(wp_unslash($_POST['clear_jobs_type'] ?? ''));
    $cleanup_processed = !empty($_POST['cleanup_processed']);
    
    if (!in_array($clear_type, ['all', 'failed'])) {
        wp_send_json_error(['message' => __('Invalid clear type', 'data-machine')]);
    }
    
    // Use the existing dm_delete action to clear jobs
    // This will trigger the handle_jobs_deletion method in Delete.php
    do_action('dm_delete', 'jobs', $clear_type, ['cleanup_processed' => $cleanup_processed]);
    
    // The dm_delete action handles the response via wp_send_json_success/error
    // If we get here without a response sent, send a generic success
    // (This shouldn't happen as Delete.php sends the response)
});