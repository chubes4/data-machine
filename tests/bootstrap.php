<?php
/**
 * PHPUnit bootstrap file for Data Machine.
 *
 * @package DataMachine
 */

// Define plugin constants for tests
define( 'TESTS_PLUGIN_DIR', dirname( __DIR__ ) );
define( 'DATAMACHINE_VERSION', '0.8.4' );

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
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL;
	exit( 1 );
}

// Define ABSPATH to prevent libraries from exiting early
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// Minimal mocks for WordPress functions required by libraries during autoloading
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {}
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url() { return ''; }
}
if ( ! function_exists( 'get_template_directory' ) ) {
	function get_template_directory() { return ''; }
}
if ( ! function_exists( 'get_stylesheet_directory' ) ) {
	function get_stylesheet_directory() { return ''; }
}

// Load Composer autoloader
require_once TESTS_PLUGIN_DIR . '/vendor/autoload.php';

// Define WP_TESTS_PHPUNIT_POLYFILLS_PATH
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', TESTS_PLUGIN_DIR . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php' );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// Load the plugin
	require TESTS_PLUGIN_DIR . '/data-machine.php';

	// Create database tables for testing
	\DataMachine\Core\Database\Pipelines\Pipelines::create_table();
	\DataMachine\Core\Database\Flows\Flows::create_table();
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
