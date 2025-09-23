<?php
/**
 * Centralized cache management using WordPress transients and action hooks.
 *
 * @package DataMachine\Engine\Actions
 */

namespace DataMachine\Engine\Actions;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Cache {

    /**
     * Cache key constants for WordPress transient management.
     */
    const PIPELINE_CACHE_KEY = 'dm_pipeline_';
    const ALL_PIPELINES_CACHE_KEY = 'dm_all_pipelines';
    const PIPELINES_LIST_CACHE_KEY = 'dm_pipelines_list';
    const PIPELINE_CONFIG_CACHE_KEY = 'dm_pipeline_config_';
    const PIPELINE_COUNT_CACHE_KEY = 'dm_pipeline_count';
    const PIPELINE_EXPORT_CACHE_KEY = 'dm_pipeline_export';

    const FLOW_CONFIG_CACHE_KEY = 'dm_flow_config_';
    const PIPELINE_FLOWS_CACHE_KEY = 'dm_pipeline_flows_';
    const FLOW_SCHEDULING_CACHE_KEY = 'dm_flow_scheduling_';
    const MAX_DISPLAY_ORDER_CACHE_KEY = 'dm_max_display_order_';

    const JOB_CACHE_KEY = 'dm_job_';
    const JOB_STATUS_CACHE_KEY = 'dm_job_status_';
    const TOTAL_JOBS_COUNT_CACHE_KEY = 'dm_total_jobs_count';
    const FLOW_JOBS_CACHE_KEY = 'dm_flow_jobs_';
    const RECENT_JOBS_CACHE_KEY = 'dm_recent_jobs_';

    const DUE_FLOWS_CACHE_KEY = 'dm_due_flows_';

    const PIPELINE_PATTERN = 'dm_pipeline_*';
    const FLOW_PATTERN = 'dm_flow_*';
    const JOB_PATTERN = 'dm_job_*';
    const RECENT_JOBS_PATTERN = 'dm_recent_jobs*';
    const FLOW_JOBS_PATTERN = 'dm_flow_jobs*';

    /**
     * Register cache clearing action hooks.
     */
    public static function register() {
        $instance = new self();

        add_action('dm_clear_flow_cache', [$instance, 'handle_clear_flow_cache'], 10, 1);
        add_action('dm_clear_flow_step_cache', [$instance, 'clear_flow_step_cache'], 10, 1);
        add_action('dm_clear_pipeline_cache', [$instance, 'handle_clear_pipeline_cache'], 10, 1);
        add_action('dm_clear_pipelines_list_cache', [$instance, 'handle_clear_pipelines_list_cache'], 10, 0);
        add_action('dm_clear_jobs_cache', [$instance, 'handle_clear_jobs_cache'], 10, 0);
        add_action('dm_clear_all_cache', [$instance, 'handle_clear_all_cache'], 10, 0);

        add_action('dm_cache_set', [$instance, 'handle_cache_set'], 10, 4);

        // Listen for AI HTTP Client cache events for logging (optional integration)
        add_action('ai_model_cache_cleared', [$instance, 'handle_ai_cache_cleared'], 10, 1);
        add_action('ai_all_model_cache_cleared', [$instance, 'handle_ai_all_cache_cleared'], 10, 0);
    }

    /**
     * Clear pipeline-related caches including flows and jobs.
     */
    public function handle_clear_pipeline_cache($pipeline_id) {
        if (empty($pipeline_id)) {
            do_action('dm_log', 'warning', 'Cache clear requested with empty pipeline ID');
            return;
        }

        $this->clear_pipeline_cache($pipeline_id);
        $this->clear_flow_cache($pipeline_id);
        $this->clear_job_cache();

        // Clear object cache to ensure fresh data in all contexts (web + cron)
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Clear flow-specific caches and associated pipeline caches.
     */
    public function handle_clear_flow_cache($flow_id) {
        if (empty($flow_id)) {
            do_action('dm_log', 'warning', 'Flow cache clear requested with empty flow ID');
            return;
        }

        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;

        if ($db_flows) {
            // Get flow data FIRST before clearing any caches to avoid recursion
            $flow = apply_filters('dm_get_flow', null, $flow_id);
            $pipeline_id = $flow['pipeline_id'] ?? null;
            $flow_config = $flow['flow_config'] ?? [];

            // Clear main flow caches (CRITICAL: dm_get_flow_step_config depends on FLOW_CONFIG_CACHE_KEY)
            $flow_config_key = self::FLOW_CONFIG_CACHE_KEY . $flow_id;
            $flow_scheduling_key = self::FLOW_SCHEDULING_CACHE_KEY . $flow_id;

            $flow_config_cleared = delete_transient($flow_config_key);
            $flow_scheduling_cleared = delete_transient($flow_scheduling_key);

            do_action('dm_log', 'debug', 'Flow cache clearing executed', [
                'flow_id' => $flow_id,
                'flow_config_cache_key' => $flow_config_key,
                'flow_config_cleared' => $flow_config_cleared,
                'flow_scheduling_cache_key' => $flow_scheduling_key,
                'flow_scheduling_cleared' => $flow_scheduling_cleared,
                'pipeline_id' => $pipeline_id,
                'flow_steps_count' => count($flow_config)
            ]);

            // Clear flow step caches for all steps in this flow using pre-retrieved data
            foreach ($flow_config as $flow_step_id => $step_config) {
                $this->clear_flow_step_cache($flow_step_id);
            }

            // Clear pipeline-related caches if we have a pipeline_id
            if ($pipeline_id) {
                delete_transient(self::PIPELINE_FLOWS_CACHE_KEY . $pipeline_id);
                delete_transient(self::MAX_DISPLAY_ORDER_CACHE_KEY . $pipeline_id);
            }

        } else {
            do_action('dm_log', 'warning', 'Could not clear flow cache - flows database not available', [
                'flow_id' => $flow_id
            ]);
        }

        // Clear object cache to ensure fresh data in all contexts (web + cron)
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Clear job-related caches system-wide.
     */
    public function handle_clear_jobs_cache() {
        $this->clear_job_cache();

        // Clear object cache to ensure fresh data in all contexts (web + cron)
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Clear lightweight pipelines list cache for UI dropdowns.
     */
    public function handle_clear_pipelines_list_cache() {
        delete_transient(self::PIPELINES_LIST_CACHE_KEY);

        // Clear object cache to ensure fresh data in all contexts (web + cron)
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Clear all Data Machine caches including object cache.
     */
    public function handle_clear_all_cache() {
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

        delete_transient(self::ALL_PIPELINES_CACHE_KEY);
        delete_transient(self::PIPELINES_LIST_CACHE_KEY);
        delete_transient(self::PIPELINE_COUNT_CACHE_KEY);
        delete_transient(self::PIPELINE_EXPORT_CACHE_KEY);
        delete_transient(self::TOTAL_JOBS_COUNT_CACHE_KEY);

        // Signal AI HTTP Client library to clear model caches (if present)
        do_action('ai_clear_all_cache');

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Store cache data with comprehensive logging.
     */
    public function handle_cache_set($key, $data, $timeout = 0, $group = null) {
        if (empty($key)) {
            do_action('dm_log', 'warning', 'Cache set requested with empty key');
            return false;
        }


        $result = set_transient($key, $data, $timeout);

        if (!$result) {
            do_action('dm_log', 'warning', 'Cache set failed', ['cache_key' => $key]);
        }

        return $result;
    }

    /**
     * Clear pipeline-specific cache entries.
     */
    private function clear_pipeline_cache($pipeline_id) {
        $cache_keys = [
            self::PIPELINE_CACHE_KEY . $pipeline_id,
            self::PIPELINE_CONFIG_CACHE_KEY . $pipeline_id,
            self::PIPELINE_FLOWS_CACHE_KEY . $pipeline_id,
            self::MAX_DISPLAY_ORDER_CACHE_KEY . $pipeline_id
        ];

        foreach ($cache_keys as $key) {
            delete_transient($key);
        }

    }

    /**
     * Clear flow configuration and pattern-based caches.
     */
    private function clear_flow_cache($pipeline_id) {
        delete_transient(self::PIPELINE_FLOWS_CACHE_KEY . $pipeline_id);

        $this->clear_cache_pattern(self::FLOW_PATTERN);
    }

    /**
     * Clear flow step configuration cache.
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

        // Clear object cache to ensure fresh data in all contexts (web + cron)
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Clear job patterns and count caches.
     */
    private function clear_job_cache() {
        delete_transient(self::TOTAL_JOBS_COUNT_CACHE_KEY);

        $this->clear_cache_pattern(self::JOB_PATTERN);
        $this->clear_cache_pattern(self::RECENT_JOBS_PATTERN);
        $this->clear_cache_pattern(self::FLOW_JOBS_PATTERN);
    }

    /**
     * Clear caches matching wildcard patterns via database queries.
     */
    private function clear_cache_pattern($pattern) {
        global $wpdb;

        $sql_pattern = str_replace('*', '%', $pattern);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $transient_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $sql_pattern
        ));

        foreach ($transient_keys as $transient_key) {
            $transient_name = str_replace('_transient_', '', $transient_key);
            delete_transient($transient_name);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $timeout_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . $sql_pattern
        ));

        foreach ($timeout_keys as $timeout_key) {
            delete_option($timeout_key);
        }
    }

    /**
     * Handle AI model cache cleared event for logging.
     */
    public function handle_ai_cache_cleared($provider) {
        do_action('dm_log', 'debug', 'AI model cache cleared via action hook', [
            'provider' => $provider,
            'integration' => 'ai-http-client'
        ]);
    }

    /**
     * Handle AI all model cache cleared event for logging.
     */
    public function handle_ai_all_cache_cleared() {
        do_action('dm_log', 'debug', 'All AI model caches cleared via action hook', [
            'integration' => 'ai-http-client'
        ]);
    }
}