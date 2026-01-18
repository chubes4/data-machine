<?php
/**
 * Uninstall Data Machine Plugin
 *
 * @package Data_Machine
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete authentication data (unified datamachine_oauth system)
$datamachine_auth_providers = array( 'twitter', 'facebook', 'threads', 'googlesheets', 'reddit', 'bluesky', 'wordpress_publish', 'wordpress_posts' );
foreach ( $datamachine_auth_providers as $datamachine_provider ) {
	delete_option( "{$datamachine_provider}_auth_data" );
}

// Note: AI HTTP Client library shared API keys preserved for other plugins using the library

// Delete options from different storage system
delete_option( 'datamachine_openai_api_key' );
delete_option( 'datamachine_openai_user_meta' );
delete_option( 'datamachine_bluesky_user_meta' );
delete_option( 'datamachine_twitter_user_meta' );
delete_option( 'datamachine_reddit_user_meta' );
delete_option( 'datamachine_threads_user_meta' );
delete_option( 'datamachine_facebook_user_meta' );

// Delete user meta for all users - WordPress compliant approach
global $wpdb;
// Use prepared statement for security and WordPress compliance
$datamachine_pattern = 'datamachine_%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $datamachine_pattern ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

// Clear any related caches
wp_cache_flush();

// Drop custom tables - WordPress compliant approach
// Check for proper capabilities (should be admin-only in uninstall context)
if ( current_user_can( 'delete_plugins' ) || defined( 'WP_UNINSTALL_PLUGIN' ) ) {

	// Drop tables in reverse dependency order
	$datamachine_tables_to_drop = array(
		$wpdb->prefix . 'datamachine_processed_items',
		$wpdb->prefix . 'datamachine_jobs',
		$wpdb->prefix . 'datamachine_flows',
		$wpdb->prefix . 'datamachine_pipelines',
	);

	foreach ( $datamachine_tables_to_drop as $datamachine_table_name ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $datamachine_table_name ) );
	}

	// Clear all WordPress caches to ensure clean state
	wp_cache_flush();
}

// Delete files repository (uploads/datamachine-files/)
$datamachine_upload_dir = wp_upload_dir();
$datamachine_files_dir  = trailingslashit( $datamachine_upload_dir['basedir'] ) . 'datamachine-files';

if ( is_dir( $datamachine_files_dir ) ) {
	datamachine_recursive_delete( $datamachine_files_dir );
}

// Delete log files (uploads/datamachine-logs/)
$datamachine_logs_dir = trailingslashit( $datamachine_upload_dir['basedir'] ) . 'datamachine-logs';

if ( is_dir( $datamachine_logs_dir ) ) {
	datamachine_recursive_delete( $datamachine_logs_dir );
}

// Clear Action Scheduler jobs if available
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'data-machine' );
}

// Clear transients
delete_transient( 'datamachine_activation_notice' );

/**
 * Recursively delete a directory and its contents.
 *
 * @param string $dir Directory path to delete.
 * @return bool True on success, false on failure.
 */
function datamachine_recursive_delete( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		$path = trailingslashit( $dir ) . $file;

		if ( is_dir( $path ) ) {
			datamachine_recursive_delete( $path );
		} else {
			wp_delete_file( $path );
		}
	}

	return rmdir( $dir );
}
