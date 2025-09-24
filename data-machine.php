<?php
/**
 * Plugin Name:     Data Machine
 * Plugin URI:      https://wordpress.org/plugins/data-machine/
 * Description:     AI-powered WordPress plugin for automated content workflows with visual pipeline builder and multi-provider AI integration.
 * Version:         0.1.1
 * Author:          Chris Huber
 * Author URI:      https://chubes.net
 * Text Domain:     data-machine
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! dm_check_requirements() ) {
	return;
}

define( 'DATA_MACHINE_VERSION', '0.1.1' );

define( 'DATA_MACHINE_PATH', plugin_dir_path( __FILE__ ) );
define( 'DATA_MACHINE_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/vendor/autoload.php';

if ( ! class_exists( 'ActionScheduler' ) ) {
    require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}


function run_data_machine() {

    dm_register_database_service_system();
    dm_register_database_filters();
    dm_register_utility_filters();
    dm_register_admin_filters();
    dm_register_logger_filters();
    dm_register_oauth_system();
    dm_register_status_detection_filters();
    dm_register_core_actions();
    
    \DataMachine\Engine\Filters\Create::register();
    
    \DataMachine\Core\Admin\Pages\Pipelines\Ajax\PipelinePageAjax::register();
    \DataMachine\Core\Admin\Pages\Pipelines\Ajax\PipelineModalAjax::register();
    \DataMachine\Core\Admin\Pages\Pipelines\Ajax\PipelineSwitcherAjax::register();
    \DataMachine\Core\Admin\Pages\Pipelines\Ajax\PipelineDeleteAjax::register();
    \DataMachine\Core\Admin\Pages\Pipelines\Ajax\PipelineImportExportAjax::register();
    \DataMachine\Core\Admin\Pages\Pipelines\Ajax\PipelineAutoSaveAjax::register();
    \DataMachine\Core\Admin\Pages\Pipelines\Ajax\PipelineFlowCreateAjax::register();
    \DataMachine\Core\Admin\Pages\Pipelines\Ajax\PipelineFileUploadAjax::register();
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
}

function activate_data_machine() {
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

function dm_check_database_cleanup() {
	// Only check on admin pages
	if (!is_admin()) {
		return;
	}

	global $wpdb;
	$flows_table = $wpdb->prefix . 'dm_flows';

	// Check if table exists and has display_order column
	$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $flows_table));
	if (!$table_exists) {
		return;
	}

	$column_exists = $wpdb->get_results($wpdb->prepare(
		"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
		 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'display_order'",
		DB_NAME,
		$flows_table
	));

	if (!empty($column_exists)) {
		add_action('admin_notices', function() {
			echo '<div class="notice notice-info is-dismissible">';
			echo '<p>';
			esc_html_e('Data Machine: Database cleanup available. Click to remove unused display order column.', 'data-machine');
			echo ' <button type="button" class="button button-secondary" id="dm-cleanup-database">';
			esc_html_e('Clean Database', 'data-machine');
			echo '</button>';
			echo '</p>';
			echo '</div>';

			// Add inline JavaScript following existing patterns
			echo '<script>
			jQuery(document).ready(function($) {
				$("#dm-cleanup-database").on("click", function() {
					var button = $(this);
					button.prop("disabled", true).text("' . esc_js(__('Cleaning...', 'data-machine')) . '");

					$.post(ajaxurl, {
						action: "dm_cleanup_database",
						nonce: "' . wp_create_nonce('dm_ajax_actions') . '"
					}, function(response) {
						if (response.success) {
							button.closest(".notice").html("<p>' . esc_js(__('Database cleanup completed successfully!', 'data-machine')) . '</p>");
							setTimeout(function() {
								button.closest(".notice").fadeOut();
							}, 3000);
						} else {
							button.prop("disabled", false).text("' . esc_js(__('Clean Database', 'data-machine')) . '");
							alert("Error: " + response.data.message);
						}
					});
				});
			});
			</script>';
		});
	}
}

// Add AJAX handler for database cleanup
add_action('wp_ajax_dm_cleanup_database', 'dm_handle_cleanup_database');

// Add admin_init hook to check for database cleanup
add_action('admin_init', 'dm_check_database_cleanup');

function dm_handle_cleanup_database() {
	// Security check - exact same pattern as existing AJAX handlers
	check_ajax_referer('dm_ajax_actions', 'nonce');

	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => __('You do not have sufficient permissions to access this page.', 'data-machine')]);
	}

	global $wpdb;
	$flows_table = $wpdb->prefix . 'dm_flows';

	// Check if index exists before dropping (MySQL compatible)
	$index_exists = $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
		 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'display_order'",
		DB_NAME,
		$flows_table
	));

	if ($index_exists) {
		$wpdb->query("ALTER TABLE {$flows_table} DROP INDEX display_order");
	}

	// Drop the column (we already verified it exists in dm_check_database_cleanup)
	$wpdb->query("ALTER TABLE {$flows_table} DROP COLUMN display_order");

	do_action('dm_log', 'info', 'Database cleanup: Removed display_order column via admin notice', [
		'table' => $flows_table,
		'trigger' => 'admin_notice',
		'index_existed' => (bool)$index_exists
	]);

	// Send success response - exact same pattern as existing handlers
	wp_send_json_success([
		'message' => __('Database cleanup completed successfully.', 'data-machine')
	]);
}

