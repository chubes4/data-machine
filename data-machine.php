<?php
/**
 * Plugin Name:     Data Machine
 * Plugin URI:      https://wordpress.org/plugins/data-machine/
 * Description:     AI-first WordPress plugin for content processing workflows. Visual pipeline builder with multi-provider AI integration and agentic tool calling.
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

// Filter-based architecture with service discovery patterns

require_once __DIR__ . '/vendor/autoload.php';

if ( ! class_exists( 'ActionScheduler' ) ) {
    require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}

require_once __DIR__ . '/lib/ai-http-client/ai-http-client.php';
require_once __DIR__ . '/inc/engine/filters/DataMachineFilters.php';
require_once __DIR__ . '/inc/engine/filters/Database.php';
require_once __DIR__ . '/inc/engine/filters/Admin.php';
require_once __DIR__ . '/inc/engine/filters/Logger.php';
require_once __DIR__ . '/inc/engine/filters/AI.php';
require_once __DIR__ . '/inc/engine/filters/OAuth.php';
require_once __DIR__ . '/inc/engine/actions/DataMachineActions.php';
require_once __DIR__ . '/inc/engine/filters/StatusDetection.php';

function run_data_machine() {
    dm_register_database_service_system();
    dm_register_database_filters();
    dm_register_utility_filters();
    dm_register_admin_filters();
    dm_register_logger_filters();
    dm_register_oauth_system();
    dm_register_status_detection_filters();
    dm_register_core_actions();
    
    dm_autoload_core_component_directory('inc/core/admin/');
    dm_autoload_core_component_directory('inc/core/steps/');
    dm_autoload_core_component_directory('inc/core/database/');
}

function dm_autoload_core_component_directory(string $relative_path): void {
    $component_root = DATA_MACHINE_PATH . $relative_path;
    
    if (!is_dir($component_root)) {
        return;
    }
    
    $component_directories = glob($component_root . '*', GLOB_ONLYDIR);
    
    foreach ($component_directories as $component_dir) {
        $php_files = glob($component_dir . '/*.php');
        
        foreach ($php_files as $php_file) {
            require_once $php_file;
        }
        
        if (strpos($relative_path, 'handlers/') !== false || strpos($relative_path, 'steps/') !== false) {
            $handler_subdirs = glob($component_dir . '/*', GLOB_ONLYDIR);
            
            foreach ($handler_subdirs as $handler_subdir) {
                $handler_php_files = glob($handler_subdir . '/*.php');
                
                foreach ($handler_php_files as $handler_php_file) {
                    require_once $handler_php_file;
                }
                
                if (basename($handler_subdir) === 'handlers') {
                    $individual_handlers = glob($handler_subdir . '/*', GLOB_ONLYDIR);
                    
                    foreach ($individual_handlers as $individual_handler_dir) {
                        $handler_files = glob($individual_handler_dir . '/*.php');
                        
                        foreach ($handler_files as $handler_file) {
                            require_once $handler_file;
                        }
                    }
                }
            }
        }
        
        if (basename($component_dir) === 'pages') {
            $page_subdirectories = glob($component_dir . '/*', GLOB_ONLYDIR);
            
            foreach ($page_subdirectories as $page_dir) {
                $page_php_files = glob($page_dir . '/*.php');
                
                foreach ($page_php_files as $page_php_file) {
                    require_once $page_php_file;
                }
            }
        }
    }
    
    $root_php_files = glob($component_root . '*.php');
    foreach ($root_php_files as $php_file) {
        require_once $php_file;
    }
}

add_action('plugins_loaded', 'run_data_machine', 20);


function dm_allow_json_upload($mimes) {
    $mimes['json'] = 'application/json';
    return $mimes;
}
add_filter( 'upload_mimes', 'dm_allow_json_upload' );

register_activation_hook( __FILE__, 'activate_data_machine' );
register_deactivation_hook( __FILE__, 'dm_deactivate_plugin' );

function dm_deactivate_plugin() {
    // Plugin deactivation cleanup would go here if needed
}

function activate_data_machine() {
	dm_autoload_core_component_directory('inc/core/database/');
	dm_register_database_service_system();

	$all_databases = apply_filters('dm_db', []);
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

	$db_processed_items = $all_databases['processed_items'] ?? null;
	if ($db_processed_items) {
		$db_processed_items->create_table();
	}

	$timeout = defined( 'MINUTE_IN_SECONDS' ) ? 5 * MINUTE_IN_SECONDS : 5 * 60;
	set_transient( 'dm_activation_notice', true, $timeout );
}
function dm_check_requirements() {
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
	
	global $wp_version;
	if ( version_compare( $wp_version, '6.0', '<' ) ) {
		add_action( 'admin_notices', function() use ( $wp_version ) {
			echo '<div class="notice notice-error"><p>';
			printf( 
				/* translators: %1$s: current WordPress version, %2$s: required WordPress version */
				esc_html__( 'Data Machine requires WordPress %2$s or higher. You are running WordPress %1$s.', 'data-machine' ),
				esc_html( $wp_version ),
				'6.0'
			);
			echo '</p></div>';
		});
		return false;
	}
	
	if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Data Machine: Composer dependencies are missing. Please run "composer install" or contact Chubes to report a bug.', 'data-machine' );
			echo '</p></div>';
		});
		return false;
	}
	
	return true;
}

