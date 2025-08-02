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
}

dm_register_admin_filters();