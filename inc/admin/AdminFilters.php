<?php

namespace DataMachine\Admin;

use DataMachine\Admin\AdminMenuAssets;
use DataMachine\Admin\Logger;

// Legacy admin page callback removed - now using unified system in AdminMenuAssets

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
    
    // Legacy content rendering filter removed - now using unified system
    
    // Step configuration discovery filter - infrastructure hook for components
    add_filter('dm_get_step_config', function($config, $step_type, $context) {
        // Admin engine provides the filter hook infrastructure
        // Step components self-register their configuration capabilities via this same filter
        return $config;
    }, 5, 3);
    
    // Parameter-based admin page registration system  
    add_filter('dm_get_admin_page', function($config, $page_slug) {
        if ($config !== null) {
            return $config; // Component self-registration provided
        }
        
        // Pure parameter-based system - admin pages self-register via this same filter
        // No hardcoded page lists - complete architectural consistency
        return null;
    }, 5, 2);
    
    // Parameter-based modal content system for admin interface
    add_filter('dm_get_modal', function($content, $template) {
        // Pure parameter-based system - admin modal templates register their content generation logic
        // Admin layer returns null to allow UI components to handle their own templates
        return $content;
    }, 5, 2);
    
    // Universal template rendering filter - discovers templates from admin page registration
    add_filter('dm_render_template', function($content, $template_name, $data = []) {
        // Discover all registered admin pages and their template directories
        $page_slugs = ['pipelines', 'jobs', 'logs'];
        
        foreach ($page_slugs as $slug) {
            $page_config = apply_filters('dm_get_admin_page', null, $slug);
            if ($page_config && !empty($page_config['templates'])) {
                $template_path = $page_config['templates'] . $template_name . '.php';
                if (file_exists($template_path)) {
                    // Extract data variables for template use
                    extract($data);
                    ob_start();
                    include $template_path;
                    return ob_get_clean();
                }
            }
        }
        
        return '<div class="dm-error">Template not found: ' . esc_html($template_name) . '</div>';
    }, 10, 3);
}

dm_register_admin_filters();