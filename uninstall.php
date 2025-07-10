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

// Delete plugin options
delete_option( 'dm_openai_api_key' );
delete_option( 'dm_openai_user_meta' );
delete_option( 'dm_bluesky_user_meta' );
delete_option( 'dm_twitter_user_meta' );
delete_option( 'dm_reddit_user_meta' );
delete_option( 'dm_threads_user_meta' );
delete_option( 'dm_facebook_user_meta' );
delete_option( 'dm_threads_app_credentials' );
delete_option( 'dm_facebook_app_credentials' );

// Delete user meta for all users
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'dm_%'" );

// Drop custom tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dm_projects" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dm_modules" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dm_jobs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dm_processed_items" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dm_remote_locations" );

// Clear any scheduled cron jobs
wp_clear_scheduled_hook( 'dm_run_job_event' );

// Clear transients
delete_transient( 'dm_activation_notice' ); 