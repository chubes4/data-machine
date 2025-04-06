<?php
/**
 * Plugin Name:     Data Processing Machine
 * Plugin URI:      PLUGIN_URL
 * Description:     A plugin to automatically collect data from files using OpenAI API, fact-check it, and return a final output.
 * Version:         0.1.0
 * Author:          Your Name
 * Author URI:      YOUR_URL
 * Text Domain:     auto-data-collection
 * Domain Path:     /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 0.1.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'AUTO_DATA_COLLECTION_VERSION', '0.1.0' );

// Include the main plugin class - IMPORTANT: Include this BEFORE using the class
require plugin_dir_path( __FILE__ ) . 'includes/class-auto-data-collection.php';

/**
 * Begins execution of the plugin.
 *
 * @since    0.1.0
 */
function run_auto_data_collection() {

	$plugin = new Auto_Data_Collection(); // Instantiate the main plugin class
	$plugin->run(); // Run the plugin

}
run_auto_data_collection();


// Activation and deactivation hooks (if needed)
register_activation_hook( __FILE__, array( 'Auto_Data_Collection', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Auto_Data_Collection', 'deactivate' ) );