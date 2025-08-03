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
    
    // Unified admin page registration with embedded asset configuration
    add_filter('dm_get_admin_page', function($config, $page_slug) {
        if ($page_slug === 'jobs') {
            $jobs_instance = new Jobs();
            
            return [
                'page_title' => __('Jobs', 'data-machine'),
                'menu_title' => __('Jobs', 'data-machine'),  
                'capability' => 'manage_options',
                'position' => 20,
                'content_callback' => [$jobs_instance, 'render_content'],
                'assets' => [
                    'css' => [
                        'dm-admin-jobs' => [
                            'file' => 'inc/core/admin/pages/jobs/assets/css/admin-jobs.css',
                            'deps' => [],
                            'media' => 'all'
                        ]
                    ],
                    'js' => [
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
                                        'error' => __('An error occurred', 'data-machine'),
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }
        return $config;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_jobs_admin_page_filters();