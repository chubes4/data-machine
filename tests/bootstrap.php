<?php
/**
 * PHPUnit bootstrap file for Data Machine.
 *
 * @package DataMachine
 */

// Define plugin constants for tests
define( 'TESTS_PLUGIN_DIR', dirname( __DIR__ ) );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Define WP_CORE_DIR if not already defined
if ( ! defined( 'WP_CORE_DIR' ) ) {
	$_wp_core_dir = getenv( 'WP_CORE_DIR' );
	if ( ! $_wp_core_dir ) {
		$_wp_core_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress';
	}
	define( 'WP_CORE_DIR', $_wp_core_dir );
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
} elseif ( file_exists( TESTS_PLUGIN_DIR . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', TESTS_PLUGIN_DIR . '/vendor/yoast/phpunit-polyfills' );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// Load Composer autoloader
	require_once TESTS_PLUGIN_DIR . '/vendor/autoload.php';

	// Load the plugin
	require TESTS_PLUGIN_DIR . '/data-machine.php';

	// Load Data Machine Events extension for integration tests
	if ( file_exists( TESTS_PLUGIN_DIR . '/../datamachine-events/datamachine-events.php' ) ) {
		require TESTS_PLUGIN_DIR . '/../datamachine-events/datamachine-events.php';
	}

	// Create database tables for testing
	\DataMachine\Core\Database\Pipelines\Pipelines::create_table();
	\DataMachine\Core\Database\Flows\Flows::create_table();
	\DataMachine\Core\Database\Jobs\Jobs::create_table();

	$processed_items = new \DataMachine\Core\Database\ProcessedItems\ProcessedItems();
	$processed_items->create_table();

	// Ensure ToolManager translation readiness tracking is enabled.
	\DataMachine\Engine\AI\Tools\ToolManager::init();
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
