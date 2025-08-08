<?php
/**
 * Admin Component Filter Registration
 * 
 * Registers core admin services and universal template rendering system.
 * Implements parameter-based discovery patterns for admin pages and modal content.
 * 
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/admin
 * @since      0.15.0
 */

namespace DataMachine\Admin;

use DataMachine\Admin\AdminMenuAssets;
use DataMachine\Admin\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Register all admin component filters.
 * 
 * Implements complete filter-based service registration following the established
 * architectural patterns. Includes universal template rendering and parameter-based
 * discovery systems for admin pages and modal content.
 * 
 * @since 0.15.0
 */
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
    
    // Step configurations collection filter - infrastructure hook for components
    add_filter('dm_get_step_configs', function($configs) {
        // Admin engine provides the filter hook infrastructure
        // Step components self-register their configuration capabilities via this same filter
        return $configs;
    }, 5);
    
    // Obsolete dm_get_modal filter system removed - now using modern dm_get_modals + dynamic rendering
    
    // Universal template rendering filter - discovers templates from admin page registration
    add_filter('dm_render_template', function($content, $template_name, $data = []) {
        // Dynamic discovery of all registered admin pages and their template directories
        $all_pages = apply_filters('dm_get_admin_pages', []);
        
        foreach ($all_pages as $slug => $page_config) {
            if (!empty($page_config['templates'])) {
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
        
        // Fallback: Search core modal templates directory
        // Handle modal/ prefix in template names correctly
        if (strpos($template_name, 'modal/') === 0) {
            $modal_template_name = substr($template_name, 6); // Remove 'modal/' prefix
            $core_modal_template_path = DATA_MACHINE_PATH . 'inc/core/admin/modal/templates/' . $modal_template_name . '.php';
        } else {
            $core_modal_template_path = DATA_MACHINE_PATH . 'inc/core/admin/modal/templates/' . $template_name . '.php';
        }
        
        if (file_exists($core_modal_template_path)) {
            // Extract data variables for template use
            extract($data);
            ob_start();
            include $core_modal_template_path;
            return ob_get_clean();
        }
        
        // Log error and return empty string - no user-facing error display
        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            $logger->error("Template not found: {$template_name}");
        }
        return '';
    }, 10, 3);
}

dm_register_admin_filters();