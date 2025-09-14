<?php

namespace DataMachine\Core\Database;

/**
 * WordPress-compliant database caching service for Data Machine plugin
 *
 * Provides caching wrapper methods for database operations to resolve WordPress
 * coding standard warnings while maintaining existing functionality.
 */
class DatabaseCache {

    /**
     * Cached version of $wpdb->get_var() using WordPress transients
     *
     * @param string $query Prepared SQL query
     * @param string $cache_key Unique cache key
     * @param int $cache_duration Cache duration in seconds (0 = indefinite until cleared)
     * @return mixed Query result or null
     */
    public static function cached_get_var( $query, $cache_key, $cache_duration = 0 ) {
        $cached_result = get_transient( $cache_key );

        if ( false !== $cached_result ) {
            return $cached_result;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_var( $query );

        // Store result in cache (0 duration = indefinite until manually cleared)
        set_transient( $cache_key, $result, $cache_duration );

        return $result;
    }

    /**
     * Cached version of $wpdb->get_row() using WordPress transients
     *
     * @param string $query Prepared SQL query
     * @param string $cache_key Unique cache key
     * @param string $output_type Output type (OBJECT, ARRAY_A, ARRAY_N)
     * @param int $cache_duration Cache duration in seconds (0 = indefinite until cleared)
     * @return mixed Query result or null
     */
    public static function cached_get_row( $query, $cache_key, $output_type = 'OBJECT', $cache_duration = 0 ) {
        $cached_result = get_transient( $cache_key );

        if ( false !== $cached_result ) {
            return $cached_result;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_row( $query, $output_type );

        set_transient( $cache_key, $result, $cache_duration );

        return $result;
    }

    /**
     * Cached version of $wpdb->get_results() using WordPress transients
     *
     * @param string $query Prepared SQL query
     * @param string $cache_key Unique cache key
     * @param string $output_type Output type (OBJECT, ARRAY_A, ARRAY_N)
     * @param int $cache_duration Cache duration in seconds (0 = indefinite until cleared)
     * @return array Query results
     */
    public static function cached_get_results( $query, $cache_key, $output_type = 'OBJECT', $cache_duration = 0 ) {
        $cached_result = get_transient( $cache_key );

        if ( false !== $cached_result ) {
            return $cached_result;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_results( $query, $output_type );

        set_transient( $cache_key, $result, $cache_duration );

        return $result;
    }

    /**
     * Clear a specific cache entry
     *
     * @param string $cache_key Cache key to clear
     * @return bool True on successful deletion, false on failure
     */
    public static function clear_cache( $cache_key ) {
        return delete_transient( $cache_key );
    }

    /**
     * Clear multiple cache entries matching a pattern
     *
     * @param string $pattern Pattern to match (using SQL LIKE syntax with %)
     * @return int Number of cache entries cleared
     */
    public static function clear_cache_pattern( $pattern ) {
        global $wpdb;

        // Get all transient keys matching the pattern
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $transient_keys = $wpdb->get_col( $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $pattern
        ) );

        $cleared_count = 0;
        foreach ( $transient_keys as $transient_key ) {
            // Remove the '_transient_' prefix to get the actual cache key
            $cache_key = str_replace( '_transient_', '', $transient_key );
            if ( delete_transient( $cache_key ) ) {
                $cleared_count++;
            }
        }

        return $cleared_count;
    }

    /**
     * Generate cache key for processed items
     *
     * @param string $flow_step_id Flow step ID
     * @param string $source_type Source type
     * @param string $item_identifier Item identifier
     * @return string Cache key
     */
    public static function get_processed_item_cache_key( $flow_step_id, $source_type, $item_identifier ) {
        return 'dm_processed_' . $flow_step_id . '_' . $source_type . '_' . md5( $item_identifier );
    }

    /**
     * Generate cache key for flow configuration
     *
     * @param int $flow_id Flow ID
     * @return string Cache key
     */
    public static function get_flow_cache_key( $flow_id ) {
        return 'dm_flow_config_' . $flow_id;
    }

    /**
     * Generate cache key for pipeline configuration
     *
     * @param int $pipeline_id Pipeline ID
     * @return string Cache key
     */
    public static function get_pipeline_cache_key( $pipeline_id ) {
        return 'dm_pipeline_' . $pipeline_id;
    }

    /**
     * Clear processed items cache when items are marked as processed
     *
     * @param string $flow_step_id Flow step ID
     * @param string $source_type Source type
     * @param string $item_identifier Item identifier
     */
    public static function clear_processed_item_cache( $flow_step_id, $source_type, $item_identifier ) {
        $cache_key = self::get_processed_item_cache_key( $flow_step_id, $source_type, $item_identifier );
        self::clear_cache( $cache_key );

        // Also clear any related processed item pattern caches
        self::clear_cache_pattern( 'dm_processed_' . $flow_step_id . '_*' );
    }

    /**
     * Clear flow configuration cache when flows are auto-saved
     *
     * @param int $pipeline_id Pipeline ID
     */
    public static function clear_flow_cache( $pipeline_id ) {
        // Clear all flow caches related to this pipeline
        self::clear_cache_pattern( 'dm_flow_*' );

        // Clear pipeline flows cache specifically (critical for UI updates)
        self::clear_cache( 'dm_pipeline_flows_' . $pipeline_id );

        // Also clear pipeline cache since flow changes might affect pipeline queries
        self::clear_pipeline_cache( $pipeline_id );
    }

    /**
     * Clear pipeline configuration cache when system prompts are updated
     *
     * @param string $pipeline_step_id Pipeline step ID
     */
    public static function clear_pipeline_cache( $pipeline_step_id_or_id ) {
        // If it's a step ID, extract pipeline ID; if it's already pipeline ID, use as-is
        if ( is_string( $pipeline_step_id_or_id ) && strpos( $pipeline_step_id_or_id, '-' ) !== false ) {
            // This looks like a step ID, but we need pipeline ID for cache clearing
            // Clear all pipeline caches since we can't easily extract pipeline ID from step ID
            self::clear_cache_pattern( 'dm_pipeline_*' );
        } else {
            // This is likely a pipeline ID
            $cache_key = self::get_pipeline_cache_key( $pipeline_step_id_or_id );
            self::clear_cache( $cache_key );
        }
    }

    /**
     * Clear flow step cache when user messages are updated
     *
     * @param string $flow_step_id Flow step ID
     */
    public static function clear_flow_step_cache( $flow_step_id ) {
        // Extract flow ID from flow step ID (format: pipeline_step_id_flow_id)
        $parts = explode( '_', $flow_step_id );
        if ( count( $parts ) >= 2 ) {
            $flow_id = end( $parts );
            $cache_key = self::get_flow_cache_key( $flow_id );
            self::clear_cache( $cache_key );
        }

        // Also clear related flow caches
        self::clear_cache_pattern( 'dm_flow_*' );
    }

    /**
     * Clear job-related caches (for dashboard queries with short cache duration)
     */
    public static function clear_job_cache() {
        self::clear_cache_pattern( 'dm_job_*' );
    }

    /**
     * Clear pipeline flows cache for specific pipeline (used by flow duplication)
     *
     * @param int $pipeline_id Pipeline ID
     */
    public static function clear_pipeline_flows_cache( $pipeline_id ) {
        self::clear_cache( 'dm_pipeline_flows_' . $pipeline_id );

        // Also clear general flow caches that might be related
        self::clear_cache_pattern( 'dm_flow_*' );
    }

    /**
     * Clear all caches related to a deleted pipeline (comprehensive cache clearing)
     *
     * @param int $pipeline_id Pipeline ID that was deleted
     */
    public static function clear_pipeline_deletion_cache( $pipeline_id ) {
        // Clear the critical dropdown cache key (FIXES DROPDOWN PERSISTENCE BUG)
        self::clear_cache( 'dm_all_pipelines' );

        // Clear specific pipeline caches
        self::clear_cache( 'dm_pipeline_flows_' . $pipeline_id );
        self::clear_cache( 'dm_pipeline_' . $pipeline_id );

        // Clear all related cache patterns - comprehensive cleanup for deletion
        self::clear_cache_pattern( 'dm_flow_*' );
        self::clear_cache_pattern( 'dm_pipeline_*' );
        self::clear_cache_pattern( 'dm_job_*' );
    }

    /**
     * Clear all caches related to pipeline creation (for pipeline list updates)
     *
     * @param int $pipeline_id Pipeline ID that was created
     */
    public static function clear_pipeline_creation_cache( $pipeline_id ) {
        // Clear the main pipeline list cache (critical for UI updates)
        self::clear_cache( 'dm_all_pipelines' );

        // Clear pipeline-specific caches
        self::clear_cache_pattern( 'dm_pipeline_*' );
    }

    /**
     * Initialize cache invalidation listeners
     * This method sets up automatic cache clearing based on existing plugin actions
     */
    public static function init() {
        // Listen to existing actions for automatic cache invalidation
        add_action( 'dm_mark_item_processed', [ self::class, 'clear_processed_item_cache' ], 10, 3 );
        add_action( 'dm_auto_save', [ self::class, 'clear_flow_cache' ], 10, 1 );
        add_action( 'dm_update_system_prompt', [ self::class, 'clear_pipeline_cache' ], 10, 1 );
        add_action( 'dm_update_flow_user_message', [ self::class, 'clear_flow_step_cache' ], 10, 1 );

        // Clear pipeline flows cache when flows are created/duplicated
        add_action( 'dm_flow_duplicated', [ self::class, 'clear_pipeline_flows_cache' ], 10, 1 );

        // Clear pipeline flows cache when flows are deleted
        add_action( 'dm_flow_deleted', [ self::class, 'clear_pipeline_flows_cache' ], 10, 1 );

        // Clear all related caches when pipelines are deleted
        add_action( 'dm_pipeline_deleted', [ self::class, 'clear_pipeline_deletion_cache' ], 10, 1 );

        // Clear pipeline list cache when pipelines are created
        add_action( 'dm_pipeline_created', [ self::class, 'clear_pipeline_creation_cache' ], 10, 1 );

        // Clear job caches when jobs are created or completed
        add_action( 'dm_execute_step', [ self::class, 'clear_job_cache' ], 10, 0 );
        add_action( 'dm_schedule_next_step', [ self::class, 'clear_job_cache' ], 10, 0 );
    }
}

// Initialize cache invalidation listeners
DatabaseCache::init();