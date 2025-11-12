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
function datamachine_register_logs_admin_page_filters() {
    
    // Pure discovery mode - matches actual system usage
    add_filter('datamachine_admin_pages', function($pages) {
        $pages['logs'] = [
            'page_title' => __('Logs', 'datamachine'),
            'menu_title' => __('Logs', 'datamachine'),
            'capability' => 'manage_options',
            'position' => 30,
            'templates' => __DIR__ . '/templates/',
            'assets' => [
                'css' => [
                    'datamachine-admin-logs' => [
                        'file' => 'inc/Core/Admin/Pages/Logs/assets/css/admin-logs.css',
                        'deps' => [],
                        'media' => 'all'
                    ]
                ],
                'js' => [
                    'datamachine-admin-logs' => [
                        'file' => 'inc/Core/Admin/Pages/Logs/assets/js/admin-logs.js',
                        'deps' => ['jquery', 'wp-api-fetch'],
                        'in_footer' => true
                    ]
                ]
            ]
        ];
        return $pages;
    });
}

// Auto-register when file loads - achieving complete self-containment
datamachine_register_logs_admin_page_filters();