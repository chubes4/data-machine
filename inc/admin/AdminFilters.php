<?php

namespace DataMachine\Admin;

use DataMachine\Admin\AdminMenuAssets;
use DataMachine\Admin\Logger;

/**
 * Universal admin page callback function.
 * 
 * Simple callback that WordPress menus can use to display any registered admin page.
 * Handles capability checking, slug extraction, and calls the render filter.
 */
function dm_admin_page_callback() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Permission denied.', 'data-machine'));
    }
    
    // Get current page slug
    $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    
    // Extract page slug from WordPress menu format (remove 'dm-' prefix)
    $page_slug = $current_page;
    if (strpos($page_slug, 'dm-') === 0) {
        $page_slug = substr($page_slug, 3); // Remove 'dm-' prefix
    }
    
    // Get page configuration for existence check
    $page_config = apply_filters('dm_get_admin_page', null, $page_slug);
    
    // If no page config found, show error
    if (!$page_config) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Page configuration not found.', 'data-machine') . '</p></div>';
        return;
    }
    
    // Render page content via filter system
    $content = apply_filters('dm_render_admin_page', '', $page_slug);
    
    if (empty($content)) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Page content not available.', 'data-machine') . '</p></div>';
        return;
    }
    
    echo wp_kses_post($content);
}

function dm_register_admin_filters() {
    add_filter('dm_get_admin_menu_assets', function($admin_menu_assets) {
        if ($admin_menu_assets === null) {
            return new AdminMenuAssets();
        }
        return $admin_menu_assets;
    }, 10);

    add_filter('dm_get_logger', function($logger) {
        if ($logger === null) {
            return new Logger();
        }
        return $logger;
    }, 10);
    
    // Content rendering filter for admin pages
    add_filter('dm_render_admin_page', function($content, $page_slug) {
        // Admin page components register their content via this filter
        return $content;
    }, 5, 2);
    
    // Step configuration discovery filter - infrastructure hook for components
    add_filter('dm_get_step_config', function($config, $step_type, $context) {
        // Admin engine provides the filter hook infrastructure
        // Step components self-register their configuration capabilities via this same filter
        return $config;
    }, 5, 3);
}

dm_register_admin_filters();