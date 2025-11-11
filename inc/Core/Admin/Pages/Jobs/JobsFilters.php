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
function datamachine_register_jobs_admin_page_filters() {
    
    // Pure discovery mode - matches actual system usage
    add_filter('datamachine_admin_pages', function($pages) {
        $pages['jobs'] = [
            'page_title' => __('Jobs', 'data-machine'),
            'menu_title' => __('Jobs', 'data-machine'),  
            'capability' => 'manage_options',
            'position' => 20,
            'templates' => __DIR__ . '/templates/',
            'assets' => [
                'css' => [
                    'datamachine-core-modal' => [
                        'file' => 'inc/Core/Admin/Modal/assets/css/core-modal.css',
                        'deps' => [],
                        'media' => 'all'
                    ],
                    'datamachine-admin-jobs' => [
                        'file' => 'inc/Core/Admin/Pages/Jobs/assets/css/admin-jobs.css',
                        'deps' => ['datamachine-core-modal'],
                        'media' => 'all'
                    ]
                ],
                'js' => [
                    'datamachine-core-modal' => [
                        'file' => 'inc/Core/Admin/Modal/assets/js/core-modal.js',
                        'deps' => ['jquery'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dmCoreModal',
                            'data' => [
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'dm_ajax_nonce' => wp_create_nonce('dm_ajax_actions'),
                                'strings' => [
                                    'loading' => __('Loading...', 'data-machine'),
                                    'error' => __('Error', 'data-machine'),
                                    'close' => __('Close', 'data-machine')
                                ]
                            ]
                        ]
                    ],
                    'datamachine-jobs-admin' => [
                        'file' => 'inc/Core/Admin/Pages/Jobs/assets/js/data-machine-jobs.js',
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
                    'datamachine-jobs-modal' => [
                        'file' => 'inc/Core/Admin/Pages/Jobs/assets/js/jobs-modal.js',
                        'deps' => ['jquery', 'datamachine-core-modal'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dmJobsModal',
                            'data' => [
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'dm_ajax_nonce' => wp_create_nonce('dm_ajax_actions'),
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
datamachine_register_jobs_admin_page_filters();

/**
 * Register Jobs Admin modal
 * 
 * @since NEXT_VERSION
 */
add_filter('datamachine_modals', function($modals) {
    $modals['jobs-admin'] = [
        'title' => __('Jobs Administration', 'data-machine'),
        'template' => 'modal/jobs-admin'
    ];
    return $modals;
});