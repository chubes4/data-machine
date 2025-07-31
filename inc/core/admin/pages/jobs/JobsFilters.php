<?php
/**
 * Jobs Admin Page Component Filter Registration
 * 
 * Revolutionary "Plugins Within Plugins" Architecture Implementation
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
    
    // Admin page registration - Jobs declares itself as admin page
    add_filter('dm_register_admin_pages', function($pages) {
        $pages['jobs'] = [
            'page_title' => __('Jobs', 'data-machine'),
            'menu_title' => __('Jobs', 'data-machine'),  
            'capability' => 'manage_options',
            'menu_slug' => 'dm-jobs',
            'callback' => [Jobs::class, 'render'],
            'position' => 20
        ];
        return $pages;
    }, 10, 1);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_jobs_admin_page_filters();