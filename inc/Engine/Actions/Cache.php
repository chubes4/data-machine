<?php
/**
 * Centralized Cache Clearing Actions
 *
 * WordPress actions-based cache clearing system that replaces DatabaseCache.php.
 * Provides centralized cache management through WordPress native action hooks.
 *
 * @package DataMachine\Engine\Actions
 * @since 1.0.0
 */

namespace DataMachine\Engine\Actions;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Cache clearing actions handler
 *
 * Centralized cache management using WordPress transients and action hooks.
 * Eliminates scattered cache clearing throughout the codebase.
 */
class Cache {

    /**
     * Centralized cache key patterns used throughout the Data Machine plugin
     *
     * These constants define ALL cache keys that need to be cleared when
     * pipeline operations occur. Based on actual usage in database classes.
     */

    // Pipeline cache keys
    const PIPELINE_CACHE_KEY = 'dm_pipeline_';
    const ALL_PIPELINES_CACHE_KEY = 'dm_all_pipelines';
    const PIPELINE_CONFIG_CACHE_KEY = 'dm_pipeline_config_';
    const PIPELINE_COUNT_CACHE_KEY = 'dm_pipeline_count';
    const PIPELINE_EXPORT_CACHE_KEY = 'dm_pipeline_export';

    // Flow cache keys
    const FLOW_CONFIG_CACHE_KEY = 'dm_flow_config_';
    const PIPELINE_FLOWS_CACHE_KEY = 'dm_pipeline_flows_';
    const ALL_ACTIVE_FLOWS_CACHE_KEY = 'dm_all_active_flows';
    const FLOW_SCHEDULING_CACHE_KEY = 'dm_flow_scheduling_';
    const MAX_DISPLAY_ORDER_CACHE_KEY = 'dm_max_display_order_';

    // Job cache keys
    const JOB_CACHE_KEY = 'dm_job_';
    const JOB_STATUS_CACHE_KEY = 'dm_job_status_';
    const TOTAL_JOBS_COUNT_CACHE_KEY = 'dm_total_jobs_count';
    const FLOW_JOBS_CACHE_KEY = 'dm_flow_jobs_';

    // Cache patterns for bulk clearing
    const PIPELINE_PATTERN = 'dm_pipeline_*';
    const FLOW_PATTERN = 'dm_flow_*';
    const JOB_PATTERN = 'dm_job_*';
    const RECENT_JOBS_PATTERN = 'dm_recent_jobs*';
    const FLOW_JOBS_PATTERN = 'dm_flow_jobs*';

    /**
     * Register cache clearing action hooks
     */
    public static function register() {
        $instance = new self();

        // Primary cache clearing actions
        add_action('dm_clear_cache', [$instance, 'handle_clear_cache'], 10, 1);
        add_action('dm_clear_all_cache', [$instance, 'handle_clear_all_cache'], 10, 0);

        // Cache clearing is handled explicitly where needed, not on auto-save
    }

    /**
     * Handle cache clearing for specific pipeline
     *
     * Clears all caches related to a specific pipeline including:
     * - Pipeline configuration cache
     * - Flow configuration cache
     * - Pipeline flows cache
     * - Flow step cache
     *
     * @param int $pipeline_id Pipeline ID to clear cache for
     */
    public function handle_clear_cache($pipeline_id) {
        if (empty($pipeline_id)) {
            do_action('dm_log', 'warning', 'Cache clear requested with empty pipeline ID');
            return;
        }

        do_action('dm_log', 'debug', 'Starting cache clear for pipeline', ['pipeline_id' => $pipeline_id]);

        // Clear all related caches for this pipeline
        $this->clear_pipeline_cache($pipeline_id);
        $this->clear_flow_cache($pipeline_id);
        $this->clear_job_cache();

        do_action('dm_log', 'debug', 'Cache clear completed for pipeline', ['pipeline_id' => $pipeline_id]);
    }

    /**
     * Handle complete cache clearing
     *
     * Clears all Data Machine related caches.
     * Use sparingly - only when necessary for complete reset.
     */
    public function handle_clear_all_cache() {
        do_action('dm_log', 'debug', 'Starting complete cache clear');

        // Clear all Data Machine cache patterns using centralized constants
        $cache_patterns = [
            self::PIPELINE_PATTERN,
            self::FLOW_PATTERN,
            self::JOB_PATTERN,
            self::RECENT_JOBS_PATTERN,
            self::FLOW_JOBS_PATTERN
        ];

        foreach ($cache_patterns as $pattern) {
            $this->clear_cache_pattern($pattern);
        }

        // Clear individual global caches that don't match patterns
        delete_transient(self::ALL_PIPELINES_CACHE_KEY);
        delete_transient(self::PIPELINE_COUNT_CACHE_KEY);
        delete_transient(self::PIPELINE_EXPORT_CACHE_KEY);
        delete_transient(self::ALL_ACTIVE_FLOWS_CACHE_KEY);
        delete_transient(self::TOTAL_JOBS_COUNT_CACHE_KEY);

        do_action('dm_log', 'debug', 'Complete cache clear finished');
    }

    /**
     * Clear pipeline configuration cache
     *
     * @param int $pipeline_id Pipeline ID
     */
    private function clear_pipeline_cache($pipeline_id) {
        // Clear all pipeline-specific cache keys
        $cache_keys = [
            self::PIPELINE_CACHE_KEY . $pipeline_id,
            self::PIPELINE_CONFIG_CACHE_KEY . $pipeline_id,
            self::PIPELINE_FLOWS_CACHE_KEY . $pipeline_id,
            self::MAX_DISPLAY_ORDER_CACHE_KEY . $pipeline_id
        ];

        foreach ($cache_keys as $key) {
            delete_transient($key);
        }

        // Clear global pipeline caches
        delete_transient(self::ALL_PIPELINES_CACHE_KEY);
        delete_transient(self::PIPELINE_COUNT_CACHE_KEY);
        delete_transient(self::PIPELINE_EXPORT_CACHE_KEY);
    }

    /**
     * Clear flow configuration cache
     *
     * @param int $pipeline_id Pipeline ID to clear flow caches for
     */
    private function clear_flow_cache($pipeline_id) {
        // Clear pipeline-specific flow cache
        delete_transient(self::PIPELINE_FLOWS_CACHE_KEY . $pipeline_id);

        // Clear global flow caches
        delete_transient(self::ALL_ACTIVE_FLOWS_CACHE_KEY);

        // Clear all flow-specific caches using pattern (flow_config_, flow_scheduling_, etc.)
        $this->clear_cache_pattern(self::FLOW_PATTERN);
    }

    /**
     * Clear flow step cache
     *
     * @param string $flow_step_id Flow step ID
     */
    public function clear_flow_step_cache($flow_step_id) {
        if (empty($flow_step_id)) {
            return;
        }

        $cache_keys = [
            'dm_flow_step_' . $flow_step_id,
            'dm_flow_step_config_' . $flow_step_id
        ];

        foreach ($cache_keys as $key) {
            delete_transient($key);
        }
    }

    /**
     * Clear job-related caches
     *
     * Clears all caches related to job operations including counts,
     * recent jobs lists, and flow-specific job caches.
     */
    private function clear_job_cache() {
        // Clear global job caches
        delete_transient(self::TOTAL_JOBS_COUNT_CACHE_KEY);

        // Clear job patterns (covers recent jobs, flow jobs, etc.)
        $this->clear_cache_pattern(self::JOB_PATTERN);
        $this->clear_cache_pattern(self::RECENT_JOBS_PATTERN);
        $this->clear_cache_pattern(self::FLOW_JOBS_PATTERN);
    }

    /**
     * Clear cache by pattern
     *
     * Clears all transients matching a given pattern.
     * Uses WordPress database queries to find matching transient keys.
     *
     * @param string $pattern Pattern to match (e.g., 'dm_pipeline_*')
     */
    private function clear_cache_pattern($pattern) {
        global $wpdb;

        // Convert pattern to SQL LIKE pattern
        $sql_pattern = str_replace('*', '%', $pattern);

        // Find all transients matching the pattern
        $transient_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $sql_pattern
        ));

        // Delete each matching transient
        foreach ($transient_keys as $transient_key) {
            // Remove '_transient_' prefix to get the actual transient name
            $transient_name = str_replace('_transient_', '', $transient_key);
            delete_transient($transient_name);
        }

        // Also clear timeout transients
        $timeout_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . $sql_pattern
        ));

        foreach ($timeout_keys as $timeout_key) {
            delete_option($timeout_key);
        }
    }
}