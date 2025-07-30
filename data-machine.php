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

    // Register core steps with explicit configuration
    add_filter('dm_get_steps', function($steps) {
        $steps['input'] = [
            'label' => __('Input', 'data-machine'),
            'has_handlers' => true,
            'description' => __('Collect data from external sources', 'data-machine'),
            'class' => 'DataMachine\\Core\\Steps\\Input\\InputStep'
        ];
        
        $steps['ai'] = [
            'label' => __('AI Processing', 'data-machine'),
            'has_handlers' => false,
            'description' => __('Process content using AI models', 'data-machine'),
            'class' => 'DataMachine\\Core\\Steps\\AI\\AIStep'
        ];
        
        $steps['output'] = [
            'label' => __('Output', 'data-machine'),
            'has_handlers' => true,
            'description' => __('Publish to external platforms', 'data-machine'),
            'class' => 'DataMachine\\Core\\Steps\\Output\\OutputStep'
        ];
        
        return $steps;
    }, 5);

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
    // Use closure to ensure class loading in async execution contexts
    add_action( 'dm_execute_step', function( $job_id, $step_position ) {
        return \DataMachine\Engine\ProcessingOrchestrator::execute_step_callback( $job_id, $step_position );
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
 * via dm_register_admin_pages filter just like handlers do.
 * 
 * @since NEXT_VERSION
 */
function dm_autoload_core_admin_pages(): void {
    $admin_page_directories = [
        DATA_MACHINE_PATH . 'inc/core/admin/pages/projects/',
        DATA_MACHINE_PATH . 'inc/core/admin/pages/jobs/',
        DATA_MACHINE_PATH . 'inc/core/admin/pages/remote-locations/'
    ];
    
    foreach ($admin_page_directories as $directory) {
        if (!is_dir($directory)) {
            continue;
        }
        
        // Load the main page file (follows naming convention: DirectoryNamePage.php)
        $directory_name = basename(rtrim($directory, '/'));
        $page_class_map = [
            'projects' => 'ProjectsPage.php',
            'jobs' => 'JobsPage.php', 
            'remote-locations' => 'RemoteLocationsPage.php'
        ];
        
        if (isset($page_class_map[$directory_name])) {
            $page_file = $directory . $page_class_map[$directory_name];
            if (file_exists($page_file)) {
                require_once $page_file;
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

	// Create/Update all database tables using \DataMachine\Core\Database\ namespace
	// All references use fully qualified namespaces for maximum consistency
	\DataMachine\Core\Database\Pipelines::create_table();
	\DataMachine\Core\Database\Flows::create_table();
	\DataMachine\Core\Database\Jobs\Jobs::create_table();
	\DataMachine\Core\Database\RemoteLocations\RemoteLocations::create_table();

	// ProcessedItems table creation via filter-based database service access
	$db_processed_items = apply_filters('dm_get_database_service', null, 'processed_items');
	$db_processed_items->create_table();

	// Set a transient flag for first-time admin notice or setup wizard (optional)
	set_transient( 'dm_activation_notice', true, 5 * MINUTE_IN_SECONDS );
}

