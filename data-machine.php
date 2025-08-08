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

// Check minimum requirements and deactivate plugin if not met
if ( ! dm_check_requirements() ) {
	return;
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
 * @filter dm_get_handlers
 * Retrieves all handler instances via pure discovery mode.
 * Usage: $all_handlers = apply_filters('dm_get_handlers', []);
 * Filter by type: array_filter($all_handlers, fn($h) => ($h['type'] ?? '') === 'publish')
 * 
 * @filter dm_get_auth_providers
 * Authentication provider registration and retrieval system.
 * Usage: $all_auth = apply_filters('dm_get_auth_providers', []); $twitter_auth = $all_auth['twitter'] ?? null;
 * 
 
 * @filter dm_get_steps
 * Retrieves all step configurations via pure discovery mode.
 * Usage: $all_steps = apply_filters('dm_get_steps', []);
 * Access specific: $step_config = $all_steps['input'] ?? null;
 * 
 * @filter dm_get_step_configs
 * Retrieves all step configurations via pure discovery mode.
 * Usage: $all_configs = apply_filters('dm_get_step_configs', []); $config = $all_configs[$step_type] ?? null;
 * 
 * TEMPLATE SYSTEM FILTERS:
 * 
 * @filter dm_render_template
 * Universal template rendering system for all UI components.
 * Usage: $html = apply_filters('dm_render_template', '', 'page/pipeline-step-card', $data);
 * 
 * @filter dm_render_template
 * Universal template rendering for all UI components.
 * Usage: $html = apply_filters('dm_render_template', '', 'modal/handler-settings', $data);
 * 
 * @filter dm_get_modals
 * Modal content registration and retrieval system.
 * Usage: $all_modals = apply_filters('dm_get_modals', []); $modal = $all_modals['step-selection'] ?? null;
 * 
 * ADMIN SYSTEM FILTERS:
 * 
 * @filter dm_get_admin_pages
 * Admin page registration and configuration system.
 * Usage: $all_pages = apply_filters('dm_get_admin_pages', []);
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
 * 4. Adding custom modal content via dm_get_modals filter
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

// Initialize Action Scheduler only if not already loaded (prevents conflicts with WooCommerce)
if ( ! class_exists( 'ActionScheduler' ) ) {
    require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}

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
    
    
    // Register utility filters for external handlers
    dm_register_utility_filters();
    
    // DataPacket creation system removed - engine uses universal DataPacket constructor
    
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
    if ($admin_menu_assets && method_exists($admin_menu_assets, 'init_hooks')) {
        $admin_menu_assets->init_hooks();
    }

    // Register hooks for AJAX handlers - dashboard functionality handled by project management AJAX

    // Register single Action Scheduler hook for direct pipeline execution
    // Eliminates 100 WordPress hook registrations in favor of direct Action Scheduler callbacks
    // Use filter-based orchestrator access for consistency with pure architecture
    add_action( 'dm_execute_step', function( $job_id, $step_position, $pipeline_id = null, $flow_id = null, $pipeline_config = null, $previous_data_packets = null ) {
        $logger = apply_filters('dm_get_logger', null);
        
        try {
            // Call static method directly - ProcessingOrchestrator::execute_step_callback is static
            $result = \DataMachine\Engine\ProcessingOrchestrator::execute_step_callback( $job_id, $step_position, $pipeline_id, $flow_id, $pipeline_config, $previous_data_packets );
            
            $logger && $logger->debug('Action Scheduler hook executed step', [
                'job_id' => $job_id,
                'step_position' => $step_position,
                'result' => $result ? 'success' : 'failed'
            ]);
                
                // If step execution failed, mark job as failed
                if (!$result) {
                    $all_databases = apply_filters('dm_get_database_services', []);
                    $db_jobs = $all_databases['jobs'] ?? null;
                    if ($db_jobs) {
                        $db_jobs->update_job_status($job_id, 'failed');
                        $logger && $logger->error('Job marked as failed due to step execution failure', [
                            'job_id' => $job_id,
                            'step_position' => $step_position
                        ]);
                    }
                }
                
                return $result;
        } catch (Exception $e) {
            // Mark job as failed on any exception
            $all_databases = apply_filters('dm_get_database_services', []);
            $db_jobs = $all_databases['jobs'] ?? null;
            if ($db_jobs) {
                $db_jobs->update_job_status($job_id, 'failed');
            }
            
            $logger && $logger->error('Job failed due to exception in Action Scheduler hook', [
                'job_id' => $job_id,
                'step_position' => $step_position,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        } catch (Throwable $e) {
            // Catch any fatal errors or other throwables
            $all_databases = apply_filters('dm_get_database_services', []);
            $db_jobs = $all_databases['jobs'] ?? null;
            if ($db_jobs) {
                $db_jobs->update_job_status($job_id, 'failed');
            }
            
            $logger && $logger->error('Job failed due to fatal error in Action Scheduler hook', [
                'job_id' => $job_id,
                'step_position' => $step_position,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }, 10, 6 );


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
	dm_register_database_service_system();

	// Create/Update all database tables using pure discovery pattern
	// Maintains architectural consistency with filter-based approach
	$all_databases = apply_filters('dm_get_database_services', []);
	$db_pipelines = $all_databases['pipelines'] ?? null;
	if ($db_pipelines) {
		$db_pipelines->create_table();
	}

	$db_flows = $all_databases['flows'] ?? null;
	if ($db_flows) {
		$db_flows->create_table();
	}

	$db_jobs = $all_databases['jobs'] ?? null;
	if ($db_jobs) {
		$db_jobs->create_table();
	}

	$db_remote_locations = $all_databases['remote_locations'] ?? null;
	if ($db_remote_locations) {
		$db_remote_locations->create_table();
	}

	// ProcessedItems table creation via pure discovery pattern
	$db_processed_items = $all_databases['processed_items'] ?? null;
	if ($db_processed_items) {
		$db_processed_items->create_table();
	}

	// Set a transient flag for first-time admin notice or setup wizard (optional)
	set_transient( 'dm_activation_notice', true, 5 * MINUTE_IN_SECONDS );
}

/**
 * Check minimum plugin requirements.
 * 
 * @return bool True if requirements met, false otherwise
 */
function dm_check_requirements() {
	// Check PHP version
	if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			printf( 
				/* translators: %1$s: current PHP version, %2$s: required PHP version */
				esc_html__( 'Data Machine requires PHP %2$s or higher. You are running PHP %1$s.', 'data-machine' ),
				esc_html( PHP_VERSION ),
				'8.0'
			);
			echo '</p></div>';
		});
		return false;
	}
	
	// Check WordPress version
	global $wp_version;
	if ( version_compare( $wp_version, '5.0', '<' ) ) {
		add_action( 'admin_notices', function() use ( $wp_version ) {
			echo '<div class="notice notice-error"><p>';
			printf( 
				/* translators: %1$s: current WordPress version, %2$s: required WordPress version */
				esc_html__( 'Data Machine requires WordPress %2$s or higher. You are running WordPress %1$s.', 'data-machine' ),
				esc_html( $wp_version ),
				'5.0'
			);
			echo '</p></div>';
		});
		return false;
	}
	
	// Check if vendor directory exists
	if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Data Machine: Composer dependencies are missing. Please run "composer install" or contact support.', 'data-machine' );
			echo '</p></div>';
		});
		return false;
	}
	
	return true;
}

