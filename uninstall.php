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

// Delete authentication data (unified dm_oauth system)
$auth_providers = ['twitter', 'facebook', 'threads', 'googlesheets', 'reddit', 'bluesky', 'wordpress_publish', 'wordpress_fetch'];
foreach ($auth_providers as $provider) {
    delete_option("{$provider}_auth_data");
}

// Delete AI HTTP Client library options for this plugin context
delete_option( 'ai_http_client_providers_data-machine' );
delete_option( 'ai_http_client_selected_provider_data-machine' );
// Note: Shared API keys preserved for other plugins using AI HTTP Client library

// Delete options from different storage system
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


// Clear Action Scheduler jobs if available
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'data-machine' );
}

// Clear transients
delete_transient( 'dm_activation_notice' ); 