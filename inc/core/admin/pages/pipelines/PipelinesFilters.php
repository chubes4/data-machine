<?php
/**
 * Pipelines Admin Page Component Filter Registration
 * 
 * Revolutionary "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as the Pipelines Admin Page's "main plugin file" - the complete
 * interface contract with the engine, demonstrating complete self-containment
 * and zero bootstrap dependencies.
 * 
 * @package DataMachine
 * @subpackage Core\Admin\Pages\Pipelines
 * @since 0.1.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Pipelines Admin Page component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Pipelines Admin Page capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_pipelines_admin_page_filters() {
    
    // Admin page registration - Pipelines declares itself as admin page
    add_filter('dm_register_admin_pages', function($pages) {
        $pages['pipelines'] = [
            'page_title' => __('Pipelines', 'data-machine'),
            'menu_title' => __('Pipelines', 'data-machine'),
            'capability' => 'manage_options',
            'menu_slug' => 'dm-pipelines',
            'callback' => [Pipelines::class, 'render'],
            'position' => 10
        ];
        return $pages;
    }, 10, 1);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_pipelines_admin_page_filters();