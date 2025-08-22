<?php
/**
 * Logs Admin Page Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as the Logs Admin Page's "main plugin file" - the complete
 * interface contract with the engine, demonstrating complete self-containment
 * and zero bootstrap dependencies.
 * 
 * @package DataMachine
 * @subpackage Core\Admin\Pages\Logs
 * @since 0.1.0
 */

namespace DataMachine\Core\Admin\Pages\Logs;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Logs Admin Page component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Logs Admin Page capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_logs_admin_page_filters() {
    
    // Pure discovery mode - matches actual system usage
    add_filter('dm_admin_pages', function($pages) {
        $pages['logs'] = [
            'page_title' => __('Logs', 'data-machine'),
            'menu_title' => __('Logs', 'data-machine'),
            'capability' => 'manage_options',
            'position' => 30,
            'templates' => __DIR__ . '/templates/',
            'assets' => [
                'css' => [
                    'dm-admin-logs' => [
                        'file' => 'inc/core/admin/pages/logs/assets/css/admin-logs.css',
                        'deps' => [],
                        'media' => 'all'
                    ]
                ],
                'js' => [
                    'dm-admin-logs' => [
                        'file' => 'inc/core/admin/pages/logs/assets/js/admin-logs.js',
                        'deps' => ['jquery'],
                        'in_footer' => true
                    ]
                ]
            ]
        ];
        return $pages;
    });
    
    
    // AJAX handler registration for logs deletion using central dm_delete action
    add_action('wp_ajax_dm_clear_logs', function() {
        // Route through the existing Logs class ajax_clear_logs method
        // which now delegates to the central dm_delete system
        $logs_instance = new Logs();
        $logs_instance->ajax_clear_logs();
    });
}

// Auto-register when file loads - achieving complete self-containment
dm_register_logs_admin_page_filters();