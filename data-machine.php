<?php
/**
 * Plugin Name:     Data Machine
 * Plugin URI:      https://wordpress.org/plugins/data-machine/
 * Description:     AI-powered WordPress plugin for automated content workflows with visual pipeline builder and multi-provider AI integration.
 * Version:           0.6.11
 * Requires at least: 6.2
 * Requires PHP:     8.2
 * Author:          Chris Huber, extrachill
 * Author URI:      https://chubes.net
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     data-machine
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! datamachine_check_requirements() ) {
	return;
}

define( 'DATAMACHINE_VERSION', '0.6.11' );

define( 'DATAMACHINE_PATH', plugin_dir_path( __FILE__ ) );
define( 'DATAMACHINE_URL', plugin_dir_url( __FILE__ ) );

// Log file constants
define( 'DATAMACHINE_LOG_DIR', '/datamachine-logs' );
define( 'DATAMACHINE_LOG_FILE', '/datamachine-logs/datamachine.log' );

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/inc/Engine/AI/Tools/ToolRegistrationTrait.php';

if ( ! class_exists( 'ActionScheduler' ) ) {
    require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}


function datamachine_run_datamachine_plugin() {

	// Set Action Scheduler timeout to 10 minutes (600 seconds) for large tasks
	add_filter('action_scheduler_timeout_period', function() { return 600; });

	// One-time migration: Convert legacy 'datamachine' group to 'data-machine'
	// TODO: Remove in v0.7.0
	datamachine_migrate_scheduler_group();

	datamachine_register_utility_filters();
	datamachine_register_admin_filters();
	datamachine_register_oauth_system();
	datamachine_register_core_actions();

	// Load and instantiate all handlers - they self-register via constructors
	datamachine_load_handlers();

    \DataMachine\Api\Execute::register();
    \DataMachine\Api\Pipelines\Pipelines::register();
    \DataMachine\Api\Pipelines\PipelineSteps::register();
    \DataMachine\Api\Pipelines\PipelineFlows::register();
    \DataMachine\Api\Flows\Flows::register();
    \DataMachine\Api\Flows\FlowSteps::register();
	\DataMachine\Api\Files::register();
	\DataMachine\Api\Users::register();
	\DataMachine\Api\Logs::register();
	\DataMachine\Api\ProcessedItems::register();
	\DataMachine\Api\Jobs::register();
	\DataMachine\Api\Settings::register();
	\DataMachine\Api\Auth::register();
	\DataMachine\Api\Chat\Chat::register();
}


// Plugin activation hook to initialize default settings
register_activation_hook(__FILE__, 'datamachine_activate_plugin_defaults');
function datamachine_activate_plugin_defaults() {
    $tool_manager = new \DataMachine\Engine\AI\Tools\ToolManager();
    $opt_out_defaults = $tool_manager->get_opt_out_defaults();

    $default_settings = [
        'enabled_tools' => array_fill_keys($opt_out_defaults, true),
        'enabled_admin_pages' => ['pipelines', 'jobs', 'logs', 'settings'],
        'site_context_enabled' => true,
        'cleanup_job_data_on_failure' => true,
        'engine_mode' => 'full',
    ];

    add_option('datamachine_settings', $default_settings);
}

add_action('plugins_loaded', 'datamachine_run_datamachine_plugin', 20);

/**
 * Load and instantiate all handlers - they self-register via constructors.
 * Clean, explicit approach using composer PSR-4 autoloading.
 */
function datamachine_load_handlers() {
    // Publish Handlers
    new \DataMachine\Core\Steps\Publish\Handlers\WordPress\WordPress();
    new \DataMachine\Core\Steps\Publish\Handlers\Twitter\Twitter();
    new \DataMachine\Core\Steps\Publish\Handlers\Facebook\Facebook();
    new \DataMachine\Core\Steps\Publish\Handlers\GoogleSheets\GoogleSheets();
    new \DataMachine\Core\Steps\Publish\Handlers\Threads\Threads();
    new \DataMachine\Core\Steps\Publish\Handlers\Bluesky\Bluesky();

    // Fetch Handlers
    new \DataMachine\Core\Steps\Fetch\Handlers\WordPress\WordPress();
    new \DataMachine\Core\Steps\Fetch\Handlers\WordPressAPI\WordPressAPI();
    new \DataMachine\Core\Steps\Fetch\Handlers\WordPressMedia\WordPressMedia();
    new \DataMachine\Core\Steps\Fetch\Handlers\Rss\Rss();
    new \DataMachine\Core\Steps\Fetch\Handlers\GoogleSheets\GoogleSheetsFetch();
    new \DataMachine\Core\Steps\Fetch\Handlers\Reddit\Reddit();
    new \DataMachine\Core\Steps\Fetch\Handlers\Files\Files();

    // Update Handlers
    new \DataMachine\Core\Steps\Update\Handlers\WordPress\WordPress();
}

/**
 * Scan directory for PHP files and instantiate classes.
 * Classes are expected to self-register in their constructors.
 */
function datamachine_scan_and_instantiate($directory) {
    $files = glob($directory . '/*.php');

    foreach ($files as $file) {
        // Skip if it's a *Filters.php file (will be deleted)
        if (strpos(basename($file), 'Filters.php') !== false) {
            continue;
        }

        // Skip if it's a *Settings.php file
        if (strpos(basename($file), 'Settings.php') !== false) {
            continue;
        }

        // Include the file - classes will auto-instantiate
        include_once $file;
    }
}

function datamachine_allow_json_upload($mimes) {
    $mimes['json'] = 'application/json';
    return $mimes;
}
add_filter( 'upload_mimes', 'datamachine_allow_json_upload' );

add_action('update_option_datamachine_settings', [\DataMachine\Core\PluginSettings::class, 'clearCache']);

register_activation_hook( __FILE__, 'datamachine_activate_plugin' );
register_deactivation_hook( __FILE__, 'datamachine_deactivate_plugin' );

function datamachine_deactivate_plugin() {
}

/**
 * Plugin activation handler.
 *
 * Creates database tables, log directory, and re-schedules any flows
 * with non-manual scheduling intervals.
 */
function datamachine_activate_plugin() {

	$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
	$db_pipelines->create_table();

	$db_flows = new \DataMachine\Core\Database\Flows\Flows();
	$db_flows->create_table();

	$db_jobs = new \DataMachine\Core\Database\Jobs\Jobs();
	$db_jobs->create_table();

	$db_processed_items = new \DataMachine\Core\Database\ProcessedItems\ProcessedItems();
	$db_processed_items->create_table();

	\DataMachine\Core\Database\Chat\Chat::create_table();

	// Create log directory during activation
	$upload_dir = wp_upload_dir();
	$log_dir = $upload_dir['basedir'] . DATAMACHINE_LOG_DIR;
	if (!file_exists($log_dir)) {
		wp_mkdir_p($log_dir);
	}

	$timeout = defined( 'MINUTE_IN_SECONDS' ) ? 5 * MINUTE_IN_SECONDS : 5 * 60;
	set_transient( 'datamachine_activation_notice', true, $timeout );

	// Re-schedule any flows with non-manual scheduling
	datamachine_activate_scheduled_flows();
}

/**
 * Re-schedule all flows with non-manual scheduling on plugin activation.
 *
 * Ensures scheduled flows resume after plugin reactivation.
 */
function datamachine_activate_scheduled_flows() {
	if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'datamachine_flows';

	// Check if table exists (fresh install won't have flows yet)
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
		return;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$flows = $wpdb->get_results( $wpdb->prepare( 'SELECT flow_id, scheduling_config FROM %i', $table_name ), ARRAY_A );

	if ( empty( $flows ) ) {
		return;
	}

	$intervals = [
		'every_5_minutes' => 300,
		'hourly'          => HOUR_IN_SECONDS,
		'every_2_hours'   => HOUR_IN_SECONDS * 2,
		'every_4_hours'   => HOUR_IN_SECONDS * 4,
		'qtrdaily'        => HOUR_IN_SECONDS * 6,
		'twicedaily'      => HOUR_IN_SECONDS * 12,
		'daily'           => DAY_IN_SECONDS,
		'weekly'          => WEEK_IN_SECONDS,
	];

	$scheduled_count = 0;

	foreach ( $flows as $flow ) {
		$flow_id = (int) $flow['flow_id'];
		$scheduling_config = json_decode( $flow['scheduling_config'], true );

		if ( empty( $scheduling_config ) || empty( $scheduling_config['interval'] ) ) {
			continue;
		}

		$interval = $scheduling_config['interval'];

		if ( $interval === 'manual' ) {
			continue;
		}

		// Clear any existing scheduled actions for this flow
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'datamachine_run_flow_now', [ $flow_id ], 'data-machine' );
		}

		// Handle one-time scheduling
		if ( $interval === 'one_time' ) {
			$timestamp = $scheduling_config['timestamp'] ?? null;
			if ( $timestamp && $timestamp > time() ) {
				as_schedule_single_action( $timestamp, 'datamachine_run_flow_now', [ $flow_id ], 'data-machine' );
				$scheduled_count++;
			}
			continue;
		}

		// Handle recurring scheduling
		$interval_seconds = $intervals[ $interval ] ?? null;
		if ( ! $interval_seconds ) {
			continue;
		}

		as_schedule_recurring_action(
			time() + $interval_seconds,
			$interval_seconds,
			'datamachine_run_flow_now',
			[ $flow_id ],
			'data-machine'
		);
		$scheduled_count++;
	}

	if ( $scheduled_count > 0 ) {
		do_action( 'datamachine_log', 'info', 'Flows re-scheduled on plugin activation', [
			'scheduled_count' => $scheduled_count,
		] );
	}
}

/**
 * One-time migration to convert legacy 'datamachine' Action Scheduler group to 'data-machine'.
 *
 * This handles flows that were scheduled before the text domain migration in v0.6.9.
 * TODO: Remove in v0.7.0
 */
function datamachine_migrate_scheduler_group() {
	if ( get_option( 'datamachine_scheduler_group_migrated_069' ) ) {
		return;
	}

	if ( ! function_exists( 'as_get_scheduled_actions' ) || ! class_exists( 'ActionScheduler' ) ) {
		return;
	}

	$legacy_actions = as_get_scheduled_actions( [
		'hook'     => 'datamachine_run_flow_now',
		'group'    => 'datamachine',
		'status'   => \ActionScheduler_Store::STATUS_PENDING,
		'per_page' => -1,
	], 'ids' );

	$migrated_count = 0;

	foreach ( $legacy_actions as $action_id ) {
		$action = \ActionScheduler::store()->fetch_action( $action_id );
		if ( ! $action ) {
			continue;
		}

		$schedule = $action->get_schedule();
		$args = $action->get_args();

		// Unschedule old action
		as_unschedule_action( 'datamachine_run_flow_now', $args, 'datamachine' );

		// Reschedule with new group
		if ( $schedule instanceof \ActionScheduler_IntervalSchedule ) {
			$interval_seconds = $schedule->get_recurrence();
			$next_timestamp = $schedule->get_date()->getTimestamp();
			as_schedule_recurring_action( $next_timestamp, $interval_seconds, 'datamachine_run_flow_now', $args, 'data-machine' );
			$migrated_count++;
		} elseif ( $schedule instanceof \ActionScheduler_SimpleSchedule ) {
			$scheduled_date = $schedule->get_date();
			if ( $scheduled_date ) {
				$timestamp = $scheduled_date->getTimestamp();
				if ( $timestamp > time() ) {
					as_schedule_single_action( $timestamp, 'datamachine_run_flow_now', $args, 'data-machine' );
					$migrated_count++;
				}
			}
		}
	}

	update_option( 'datamachine_scheduler_group_migrated_069', true );

	if ( $migrated_count > 0 ) {
		do_action( 'datamachine_log', 'info', 'Migrated scheduled actions from legacy group', [
			'migrated_count' => $migrated_count,
		] );
	}
}

function datamachine_check_requirements() {
	if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			printf(
				esc_html( 'Data Machine requires PHP %2$s or higher. You are running PHP %1$s.' ),
				esc_html( PHP_VERSION ),
				'8.0'
			);
			echo '</p></div>';
		});
		return false;
	}
	
	global $wp_version;
	if ( version_compare( $wp_version, '6.2', '<' ) ) {
		add_action( 'admin_notices', function() use ( $wp_version ) {
			echo '<div class="notice notice-error"><p>';
			printf(
				esc_html( 'Data Machine requires WordPress %2$s or higher. You are running WordPress %1$s.' ),
				esc_html( $wp_version ),
				'6.2'
			);
			echo '</p></div>';
		});
		return false;
	}
	
	if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			echo esc_html( 'Data Machine: Composer dependencies are missing. Please run "composer install" or contact Chubes to report a bug.' );
			echo '</p></div>';
		});
		return false;
	}
	
	return true;
}


