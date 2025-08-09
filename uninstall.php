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

// Delete global plugin options (standardized storage)
delete_option( 'openai_api_key' ); // Historical option cleanup
delete_option( 'bluesky_username' );
delete_option( 'bluesky_app_password' );
delete_option( 'twitter_api_key' );
delete_option( 'reddit_api_key' );
delete_option( 'threads_app_credentials' );
delete_option( 'facebook_app_credentials' );

// Delete AI HTTP Client library options for this plugin context
delete_option( 'ai_http_client_providers_data-machine' );
delete_option( 'ai_http_client_selected_provider_data-machine' );
// Note: Shared API keys preserved for other plugins using AI HTTP Client library

// Delete historical options from previous storage system
delete_option( 'dm_openai_api_key' );
delete_option( 'dm_openai_user_meta' );
delete_option( 'dm_bluesky_user_meta' );
delete_option( 'dm_twitter_user_meta' );
delete_option( 'dm_reddit_user_meta' );
delete_option( 'dm_threads_user_meta' );
delete_option( 'dm_facebook_user_meta' );

// Delete user meta for all users
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'dm_%'" );

// Drop custom tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dm_pipelines" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dm_flows" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dm_jobs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dm_processed_items" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dm_remote_locations" );


// Clear Action Scheduler jobs if available
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'data-machine' );
}

// Clear transients
delete_transient( 'dm_activation_notice' ); 