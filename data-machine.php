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

/**
 * Data Machine WordPress Hook Documentation
 * 
 * This plugin follows a pure filter-based architecture with extensive hook integration.
 * All services, components, and functionality are accessible through WordPress filters.
 * 
 * CORE SERVICE FILTERS:
 * 
 * @filter dm_get_logger
 * Retrieves the logger service instance for structured logging.
 * Usage: $logger = apply_filters('dm_get_logger', null);
 * 
 * @filter dm_get_orchestrator  
 * Retrieves the processing orchestrator for pipeline execution.
 * Usage: $orchestrator = apply_filters('dm_get_orchestrator', null);
 * 
 * @filter dm_get_ai_http_client
 * Retrieves the AI HTTP client for multi-provider AI integration.
 * Usage: $ai_client = apply_filters('dm_get_ai_http_client', null);
 * 
 * @filter dm_get_encryption_helper
 * Retrieves the encryption helper for secure data handling.
 * Usage: $encryption = apply_filters('dm_get_encryption_helper', null);
 * 
 * @filter dm_get_action_scheduler
 * Retrieves the Action Scheduler service for background job management.
 * Usage: $scheduler = apply_filters('dm_get_action_scheduler', null);
 * 
 * @filter dm_get_http_service
 * Retrieves the HTTP service for external API communications.
 * Usage: $http_service = apply_filters('dm_get_http_service', null);
 * 
 * PARAMETER-BASED SERVICE FILTERS:
 * 
 * @filter dm_get_database_service
 * Retrieves database services by type parameter.
 * Usage: $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
 * Types: 'jobs', 'pipelines', 'flows', 'processed_items', 'remote_locations'
 * 
 * @filter dm_get_handlers
 * Retrieves handler instances by type parameter.
 * Usage: $handlers = apply_filters('dm_get_handlers', null, 'output');
 * Types: 'input', 'output', 'receiver'
 * 
 * @filter dm_get_auth
 * Retrieves authentication instances by handler slug parameter.
 * Usage: $auth = apply_filters('dm_get_auth', null, 'twitter');
 * 
 * @filter dm_get_context
 * Retrieves pipeline context data for job processing.
 * Usage: $context = apply_filters('dm_get_context', null, $job_id);
 * 
 * @filter dm_get_steps
 * Retrieves step configurations by type parameter.
 * Usage: $all_steps = apply_filters('dm_get_steps', []);
 * Usage: $step_config = apply_filters('dm_get_steps', null, 'input');
 * 
 * @filter dm_get_step_config
 * Retrieves detailed step configuration for UI rendering.
 * Usage: $config = apply_filters('dm_get_step_config', null, $step_type, $context);
 * 
 * TEMPLATE SYSTEM FILTERS:
 * 
 * @filter dm_render_template
 * Universal template rendering system for all UI components.
 * Usage: $html = apply_filters('dm_render_template', '', 'page/step-card', $data);
 * 
 * @filter dm_get_template
 * AJAX template requesting for dynamic UI updates.
 * Usage: $html = apply_filters('dm_get_template', '', 'modal/handler-settings', $data);
 * 
 * @filter dm_get_modal
 * Modal content registration and retrieval system.
 * Usage: $content = apply_filters('dm_get_modal', null, 'step-selection');
 * 
 * ADMIN SYSTEM FILTERS:
 * 
 * @filter dm_get_admin_page
 * Admin page registration and configuration system.
 * Usage: $config = apply_filters('dm_get_admin_page', null, 'pipelines');
 * 
 * @filter dm_get_admin_menu_assets
 * Admin menu and asset management system.
 * Usage: $assets = apply_filters('dm_get_admin_menu_assets', null);
 * 
 * EXTENSIBILITY HOOKS:
 * 
 * External plugins can extend Data Machine by:
 * 1. Adding custom handlers via dm_get_handlers filter
 * 2. Adding custom steps via dm_get_steps filter  
 * 3. Adding custom admin pages via dm_get_admin_page filter
 * 4. Adding custom modal content via dm_get_modal filter
 * 5. Overriding core services with higher filter priorities
 * 
 * ARCHITECTURAL PRINCIPLES:
 * - All services accessed via apply_filters() - no direct instantiation
 * - Parameter-based discovery for type-specific services
 * - Self-registering components via *Filters.php files
 * - Universal template rendering through filter system
 * - Complete WordPress integration following plugin standards
 */

// Load Composer autoloader and dependencies (includes Action Scheduler)
require_once __DIR__ . '/vendor/autoload.php';

// Initialize Action Scheduler before plugins_loaded hook (required for API functions)
require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';

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
    
    // Register WPDB service filter for architectural consistency
    dm_register_wpdb_service_filter();
    
    // Register context retrieval service for pipeline DataPackets
    dm_register_context_retrieval_service();
    
    // Register universal handler system with clean parameter-based architecture
    dm_register_universal_handler_system();
    
    // Register utility filters for external handlers
    dm_register_utility_filters();
    
    // Register parameter-based step auto-discovery system
    dm_register_step_auto_discovery_system();
    
    // Register universal DataPacket creation system
    dm_register_datapacket_creation_system();
    
    // Admin setup moved to component self-registration
    // Logger component manages its own admin_notices hook via LoggerFilters.php

    // Auto-load all core components using uniform "plugins within plugins" architecture
    require_once __DIR__ . '/inc/admin/AdminFilters.php';        // Load inc/admin/ service filters - MOVED UP FOR PROPER LOADING ORDER
    dm_autoload_core_component_directory('inc/core/admin/');      // Load inc/core/admin/ components  
    dm_autoload_core_component_directory('inc/core/steps/');      // Load inc/core/steps/ components
    dm_autoload_core_component_directory('inc/core/database/');   // Load inc/core/database/ components

    // Core steps now self-register via parameter-based filter system
    // External plugins use dm_get_steps filter for extensibility


    // Admin initialization handled by AdminFilters.php callback function

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


}

/**
 * Unified core component autoloader for \"plugins within plugins\" architecture.
 * 
 * Loads ALL PHP files in all subdirectories of the specified component directory.
 * This enables complete self-registration via *Filters.php files and ensures
 * all component files are available for the bootstrap system.
 * 
 * @param string $relative_path Relative path from plugin root (e.g., 'inc/core/steps/')
 * @since 0.1.0
 */
function dm_autoload_core_component_directory(string $relative_path): void {
    $component_root = DATA_MACHINE_PATH . $relative_path;
    
    if (!is_dir($component_root)) {
        return;
    }
    
    // Get all subdirectories (each component type)
    $component_directories = glob($component_root . '*', GLOB_ONLYDIR);
    
    foreach ($component_directories as $component_dir) {
        // Load ALL PHP files in the component directory
        // This includes: Handler.php, Auth.php, Settings.php, *Filters.php, etc.
        $php_files = glob($component_dir . '/*.php');
        
        foreach ($php_files as $php_file) {
            if (file_exists($php_file)) {
                require_once $php_file;
            }
        }
        
        // SPECIAL CASE: For steps or handlers, also scan subdirectories (e.g., /input/handlers/files/*.php)
        if (strpos($relative_path, 'handlers/') !== false || strpos($relative_path, 'steps/') !== false) {
            $handler_subdirs = glob($component_dir . '/*', GLOB_ONLYDIR);
            
            foreach ($handler_subdirs as $handler_subdir) {
                // First, load any PHP files directly in this subdirectory
                $handler_php_files = glob($handler_subdir . '/*.php');
                
                foreach ($handler_php_files as $handler_php_file) {
                    if (file_exists($handler_php_file)) {
                        require_once $handler_php_file;
                    }
                }
                
                // ADDITIONAL RECURSION: For handler subdirs like /input/handlers/, scan one more level for specific handlers
                if (basename($handler_subdir) === 'handlers') {
                    $individual_handlers = glob($handler_subdir . '/*', GLOB_ONLYDIR);
                    
                    foreach ($individual_handlers as $individual_handler_dir) {
                        $handler_files = glob($individual_handler_dir . '/*.php');
                        
                        foreach ($handler_files as $handler_file) {
                            if (file_exists($handler_file)) {
                                require_once $handler_file;
                            }
                        }
                    }
                }
            }
        }
        
        // SPECIAL CASE: For admin pages, check one level deeper for nested page components
        if (basename($component_dir) === 'pages') {
            $page_subdirectories = glob($component_dir . '/*', GLOB_ONLYDIR);
            
            foreach ($page_subdirectories as $page_dir) {
                $page_php_files = glob($page_dir . '/*.php');
                
                foreach ($page_php_files as $page_php_file) {
                    if (file_exists($page_php_file)) {
                        require_once $page_php_file;
                    }
                }
            }
        }
    }
    
    // Also load direct PHP files in the root directory (e.g., Modal.php)
    $root_php_files = glob($component_root . '*.php');
    foreach ($root_php_files as $php_file) {
        if (file_exists($php_file)) {
            require_once $php_file;
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

	// Load database components first so services are available during activation
	dm_autoload_core_component_directory('inc/core/database/');

	// Initialize filter systems needed for activation
	dm_register_wpdb_service_filter();
	dm_register_database_service_system();

	// Create/Update all database tables using filter-based database service access
	// Maintains architectural consistency with filter-based approach
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

