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
                    'wp-components' => [
                        'file' => null,
                        'deps' => [],
                        'media' => 'all'
                    ],
                    'datamachine-shared-pagination' => [
                        'file' => 'inc/Core/Admin/shared/styles/pagination.css',
                        'deps' => [],
                        'media' => 'all'
                    ],
                    'datamachine-jobs-page' => [
                        'file' => 'inc/Core/Admin/Pages/Jobs/assets/css/jobs-page.css',
                        'deps' => ['datamachine-shared-pagination'],
                        'media' => 'all'
                    ]
                ],
                'js' => [
                    'datamachine-jobs-react' => [
                        'file' => 'inc/Core/Admin/assets/build/jobs-react.js',
                        'deps' => ['wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'wp-dom-ready'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dataMachineJobsConfig',
                            'data' => [
                                'restNamespace' => 'datamachine/v1',
                                'restNonce' => wp_create_nonce('wp_rest'),
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

