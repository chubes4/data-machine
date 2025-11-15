<?php
/**
 * Plugin Name:     Data Machine
 * Plugin URI:      https://wordpress.org/plugins/datamachine/
 * Description:     AI-powered WordPress plugin for automated content workflows with visual pipeline builder and multi-provider AI integration.
 * Version:         0.2.0
 * Requires at least: 6.0
 * Requires PHP:     8.0
 * Author:          Chris Huber
 * Author URI:      https://chubes.net
 * Text Domain:     datamachine
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! datamachine_check_requirements() ) {
	return;
}

define( 'DATAMACHINE_VERSION', '0.2.0' );

define( 'DATAMACHINE_PATH', plugin_dir_path( __FILE__ ) );
define( 'DATAMACHINE_URL', plugin_dir_url( __FILE__ ) );

// Log file constants
define( 'DATAMACHINE_LOG_DIR', '/datamachine-logs' );
define( 'DATAMACHINE_LOG_FILE', '/datamachine-logs/datamachine.log' );

require_once __DIR__ . '/vendor/autoload.php';

if ( ! class_exists( 'ActionScheduler' ) ) {
    require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}


function run_datamachine() {

	datamachine_register_database_service_system();
	datamachine_register_database_filters();
	datamachine_register_utility_filters();
	datamachine_register_admin_filters();
	datamachine_register_logger_filters();
	datamachine_register_oauth_system();
	datamachine_register_core_actions();
    
    \DataMachine\Engine\Filters\Create::register();

    \DataMachine\Api\Execute::register();
    \DataMachine\Api\Pipelines::register();
    \DataMachine\Api\Flows::register();
	\DataMachine\Api\Files::register();
	\DataMachine\Api\Users::register();
	\DataMachine\Api\Logs::register();
	\DataMachine\Api\ProcessedItems::register();
	\DataMachine\Api\Jobs::register();
	\DataMachine\Api\Settings::register();
	\DataMachine\Api\Auth::register();
	\DataMachine\Api\Chat\Chat::register();
}


add_action('plugins_loaded', 'run_datamachine', 20);


function datamachine_allow_json_upload($mimes) {
    $mimes['json'] = 'application/json';
    return $mimes;
}
add_filter( 'upload_mimes', 'datamachine_allow_json_upload' );

register_activation_hook( __FILE__, 'activate_datamachine' );
register_deactivation_hook( __FILE__, 'datamachine_deactivate_plugin' );

function datamachine_deactivate_plugin() {
}

function activate_datamachine() {
	datamachine_register_database_service_system();

	$all_databases = apply_filters('datamachine_db', []);
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

	\DataMachine\Core\Database\Chat\Chat::create_table();

	// Create log directory during activation
	$upload_dir = wp_upload_dir();
	$log_dir = $upload_dir['basedir'] . DATAMACHINE_LOG_DIR;
	if (!file_exists($log_dir)) {
		$created = wp_mkdir_p($log_dir);
		if (!$created) {
			error_log('Data Machine: Failed to create log directory during activation: ' . $log_dir);
		}
	}

	$timeout = defined( 'MINUTE_IN_SECONDS' ) ? 5 * MINUTE_IN_SECONDS : 5 * 60;
	set_transient( 'datamachine_activation_notice', true, $timeout );
}
function datamachine_check_requirements() {
	if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			printf( 
				/* translators: %1$s: current PHP version, %2$s: required PHP version */
				esc_html__( 'Data Machine requires PHP %2$s or higher. You are running PHP %1$s.', 'datamachine' ),
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
				esc_html__( 'Data Machine requires WordPress %2$s or higher. You are running WordPress %1$s.', 'datamachine' ),
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
			esc_html_e( 'Data Machine: Composer dependencies are missing. Please run "composer install" or contact Chubes to report a bug.', 'datamachine' );
			echo '</p></div>';
		});
		return false;
	}
	
	return true;
}


