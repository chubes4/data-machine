<?php
/**
 * Plugin Name:     Data Machine
 * Plugin URI:      https://wordpress.org/plugins/data-machine/
 * Description:     A powerful WordPress plugin that automatically collects data from various sources using OpenAI API, fact-checks it, and publishes the results to multiple platforms including WordPress, Twitter, Facebook, Threads, and Bluesky.
 * Version:         0.1.0
 * Author:          Chris Huber
 * Author URI:      https://chubes.net
 * Text Domain:     data-machine
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'DATA_MACHINE_VERSION', '0.1.0' );

/** Define plugin path constant */
define( 'DATA_MACHINE_PATH', plugin_dir_path( __FILE__ ) );

// Load Composer autoloader and dependencies (includes Action Scheduler)
require_once __DIR__ . '/vendor/autoload.php';

// Load AI HTTP Client library for unified multi-provider AI integration
require_once __DIR__ . '/lib/ai-http-client/ai-http-client.php';

// Load centralized service filter registration
require_once __DIR__ . '/inc/engine/DataMachineFilters.php';

// PSR-4 Autoloading - no manual includes needed

/**
 * Begins execution of the plugin.
 *
 * @since    0.1.0
 */
function run_data_machine() {
    // Initialize pure filter-based service registry
    // All services use ultra-direct filter-based access
    
    // Register ultra-direct service filters for most heavily used services
    dm_register_direct_service_filters();
    
    // Register parameter-based database service system
    dm_register_database_service_system();
    
    // Register remaining core services 
    dm_register_remaining_core_services();
    
    // Register universal handler system with clean parameter-based architecture
    dm_register_universal_handler_system();
    
    // Register utility filters for external handlers
    dm_register_utility_filters();
    
    // Register parameter-based step auto-discovery system
    dm_register_step_auto_discovery_system();
    
    // Admin setup
    if (is_admin()) {
        // API auth page removed - replaced with handler-level configuration via universal modal system
        $logger = apply_filters('dm_get_logger', null);
        add_action('admin_notices', array($logger, 'display_admin_notices'));
    }

    // Auto-load all core handlers so they can self-register
    dm_autoload_core_handlers();
    
    // Auto-load all core admin pages so they can self-register (following handler pattern)
    dm_autoload_core_admin_pages();
    
    // Auto-load all core steps so they can self-register (following same pattern)
    dm_autoload_core_steps();

    // Core steps now self-register via parameter-based filter system
    // External plugins use dm_get_steps filter for extensibility


    // Admin initialization - use filter-based service access
    $admin_page = apply_filters('dm_get_admin_page', null);

    // Import/export functionality migrated to other handlers

    // AJAX functionality migrated to other components during architectural refactor

    // --- Initialize Admin Interface ---
    $admin_menu_assets = apply_filters('dm_get_admin_menu_assets', null);
    if ($admin_menu_assets) {
        $admin_menu_assets->init_hooks();
    }

    // Register hooks for AJAX handlers - dashboard functionality handled by project management AJAX

    // Register single Action Scheduler hook for direct pipeline execution
    // Eliminates 100 WordPress hook registrations in favor of direct Action Scheduler callbacks
    // Use filter-based orchestrator access for consistency with pure architecture
    add_action( 'dm_execute_step', function( $job_id, $step_position ) {
        $orchestrator = apply_filters('dm_get_orchestrator', null);
        if ($orchestrator && method_exists($orchestrator, 'execute_step_callback')) {
            return $orchestrator->execute_step_callback( $job_id, $step_position );
        }
        return false; // Fail gracefully if orchestrator unavailable
    }, 10, 2 );

    // Initialize scheduler hooks
    $scheduler = apply_filters('dm_get_scheduler', null);
    if ($scheduler) {
        $scheduler->init_hooks();
    }

}

/**
 * Auto-load all core handlers so they can self-register via filters.
 * 
 * This function implements the "plugins within plugins" architecture by ensuring
 * all handler files are loaded, allowing their self-registration code to execute.
 */
function dm_autoload_core_handlers(): void {
    $handler_directories = [
        DATA_MACHINE_PATH . 'inc/core/handlers/input/',
        DATA_MACHINE_PATH . 'inc/core/handlers/output/'
    ];
    
    foreach ($handler_directories as $directory) {
        if (!is_dir($directory)) {
            continue;
        }
        
        // Get all subdirectories (each handler type)
        $handler_types = glob($directory . '*', GLOB_ONLYDIR);
        
        foreach ($handler_types as $handler_type_dir) {
            // Load ALL PHP files in the handler directory
            // This enables modular handler architecture with separate directive files
            $php_files = glob($handler_type_dir . '/*.php');
            
            foreach ($php_files as $php_file) {
                require_once $php_file;
            }
        }
    }
}

/**
 * Auto-load core admin pages for self-registration.
 * 
 * Follows the established handler pattern - admin pages self-register
 * via dm_register_admin_pages collection filter using pure self-registration.
 * 
 * @since NEXT_VERSION
 */
function dm_autoload_core_admin_pages(): void {
    $admin_pages_root = DATA_MACHINE_PATH . 'inc/core/admin/pages/';
    
    // Also load the modal system
    $modal_file = DATA_MACHINE_PATH . 'inc/core/admin/modal/Modal.php';
    if (file_exists($modal_file)) {
        require_once $modal_file;
    }
    
    if (!is_dir($admin_pages_root)) {
        return;
    }
    
    // Get all subdirectories (each admin page type) - same pattern as handlers
    $page_directories = glob($admin_pages_root . '*', GLOB_ONLYDIR);
    
    foreach ($page_directories as $page_dir) {
        // Load ALL PHP files in the page directory - same as handlers
        $php_files = glob($page_dir . '/*.php');
        
        foreach ($php_files as $php_file) {
            require_once $php_file;
        }
        
        // Instantiate the main page class for self-registration (same pattern as handlers)
        $directory_name = basename($page_dir);
        $class_name = "\\DataMachine\\Core\\Admin\\Pages\\" . ucfirst($directory_name) . "\\" . ucfirst($directory_name);
        
        if (class_exists($class_name)) {
            new $class_name();
        }
    }
}

/**
 * Auto-load core pipeline steps for self-registration.
 * 
 * Follows the established handler pattern - steps self-register
 * via dm_get_steps parameter-based filter system.
 * 
 * @since NEXT_VERSION
 */
function dm_autoload_core_steps(): void {
    $step_directories = [
        DATA_MACHINE_PATH . 'inc/core/steps/input/',
        DATA_MACHINE_PATH . 'inc/core/steps/ai/',
        DATA_MACHINE_PATH . 'inc/core/steps/output/'
    ];
    
    foreach ($step_directories as $directory) {
        if (!is_dir($directory)) {
            continue;
        }
        
        // Load the main step file directly (follows same pattern as admin pages)
        // Step files are named like InputStep.php, AIStep.php, OutputStep.php
        $step_files = glob($directory . '*Step.php');
        
        foreach ($step_files as $step_file) {
            if (file_exists($step_file)) {
                require_once $step_file;
            }
        }
    }
}


// Initialize after plugins_loaded to ensure Action Scheduler is available
add_action('plugins_loaded', 'run_data_machine', 20);


/**
 * Allows JSON file uploads.
 */
function dm_allow_json_upload($mimes) {
    $mimes['json'] = 'application/json';
    return $mimes;
}
add_filter( 'upload_mimes', 'dm_allow_json_upload' );


// Activation and deactivation hooks (if needed)
register_activation_hook( __FILE__, 'activate_data_machine' );
register_deactivation_hook( __FILE__, 'dm_deactivate_plugin' );

/**
 * Plugin deactivation hook.
 */
function dm_deactivate_plugin() {
    // Add deactivation tasks here if needed
}

/**
 * Plugin activation hook.
 * Creates all required database tables using consistent namespace references.
 */
function activate_data_machine() {
	// Action Scheduler is now bundled as a library - no dependency check needed

	// Initialize database service system for activation process
	dm_register_database_service_system();

	// Create/Update all database tables using filter-based database service access
	// Consistent with the plugin's pure filter-based architecture
	$db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
	if ($db_pipelines) {
		$db_pipelines->create_table();
	}

	$db_flows = apply_filters('dm_get_database_service', null, 'flows');
	if ($db_flows) {
		$db_flows->create_table();
	}

	$db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
	if ($db_jobs) {
		$db_jobs->create_table();
	}

	$db_remote_locations = apply_filters('dm_get_database_service', null, 'remote_locations');
	if ($db_remote_locations) {
		$db_remote_locations->create_table();
	}

	// ProcessedItems table creation via filter-based database service access
	$db_processed_items = apply_filters('dm_get_database_service', null, 'processed_items');
	if ($db_processed_items) {
		$db_processed_items->create_table();
	}

	// Set a transient flag for first-time admin notice or setup wizard (optional)
	set_transient( 'dm_activation_notice', true, 5 * MINUTE_IN_SECONDS );
}

