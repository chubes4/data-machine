<?php
/**
 * Centralized cache management with granular invalidation patterns.
 *
 * @package DataMachine\Engine\Actions
 */

namespace DataMachine\Engine\Actions;

if (!defined('WPINC')) {
    die;
}

class Cache {

    const PIPELINE_CACHE_KEY = 'datamachine_pipeline_';
    const ALL_PIPELINES_CACHE_KEY = 'datamachine_all_pipelines';
    const PIPELINES_LIST_CACHE_KEY = 'datamachine_pipelines_list';
    const PIPELINE_CONFIG_CACHE_KEY = 'datamachine_pipeline_config_';
    const PIPELINE_COUNT_CACHE_KEY = 'datamachine_pipeline_count';
    const PIPELINE_EXPORT_CACHE_KEY = 'datamachine_pipeline_export';

    const FLOW_CONFIG_CACHE_KEY = 'datamachine_flow_config_';
    const PIPELINE_FLOWS_CACHE_KEY = 'datamachine_pipeline_flows_';
    const FLOW_SCHEDULING_CACHE_KEY = 'datamachine_flow_scheduling_';

    const JOB_CACHE_KEY = 'datamachine_job_';
    const JOB_STATUS_CACHE_KEY = 'datamachine_job_status_';
    const TOTAL_JOBS_COUNT_CACHE_KEY = 'datamachine_total_jobs_count';
    const FLOW_JOBS_CACHE_KEY = 'datamachine_flow_jobs_';
    const RECENT_JOBS_CACHE_KEY = 'datamachine_recent_jobs_';

    const DUE_FLOWS_CACHE_KEY = 'datamachine_due_flows_';

    const PIPELINE_PATTERN = 'datamachine_pipeline_*';
    const FLOW_PATTERN = 'datamachine_flow_*';
    const JOB_PATTERN = 'datamachine_job_*';
    const RECENT_JOBS_PATTERN = 'datamachine_recent_jobs*';
    const FLOW_JOBS_PATTERN = 'datamachine_flow_jobs*';

    public static function register() {
        $instance = new self();

        add_action('datamachine_clear_flow_cache', [$instance, 'handle_clear_flow_cache'], 10, 1);
        add_action('datamachine_clear_flow_step_cache', [$instance, 'clear_flow_step_cache'], 10, 1);
        add_action('datamachine_clear_flow_config_cache', [$instance, 'handle_clear_flow_config_cache'], 10, 1);
        add_action('datamachine_clear_flow_scheduling_cache', [$instance, 'handle_clear_flow_scheduling_cache'], 10, 1);
        add_action('datamachine_clear_flow_steps_cache', [$instance, 'handle_clear_flow_steps_cache'], 10, 1);
        add_action('datamachine_clear_pipeline_cache', [$instance, 'handle_clear_pipeline_cache'], 10, 1);
        add_action('datamachine_clear_pipelines_list_cache', [$instance, 'handle_clear_pipelines_list_cache'], 10, 0);
        add_action('datamachine_clear_jobs_cache', [$instance, 'handle_clear_jobs_cache'], 10, 0);
        add_action('datamachine_clear_all_cache', [$instance, 'handle_clear_all_cache'], 10, 0);

        // Bulk clearing actions for action-based architecture
        add_action('datamachine_clear_all_flows_cache', [$instance, 'handle_clear_all_flows_cache'], 10, 0);
        add_action('datamachine_clear_all_pipelines_cache', [$instance, 'handle_clear_all_pipelines_cache'], 10, 0);

        add_action('datamachine_cache_set', [$instance, 'handle_cache_set'], 10, 4);

        add_action('ai_model_cache_cleared', [$instance, 'handle_ai_cache_cleared'], 10, 1);
        add_action('ai_all_model_cache_cleared', [$instance, 'handle_ai_all_cache_cleared'], 10, 0);
    }

    public function handle_clear_pipeline_cache($pipeline_id) {
        if (empty($pipeline_id)) {
            do_action('datamachine_log', 'warning', 'Cache clear requested with empty pipeline ID');
            return;
        }

        $this->clear_pipeline_cache($pipeline_id);
        $this->clear_flow_cache($pipeline_id);
        $this->clear_job_cache();

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    public function handle_clear_flow_cache($flow_id) {
        if (empty($flow_id)) {
            do_action('datamachine_log', 'warning', 'Flow cache clear requested with empty flow ID');
            return;
        }

        $all_databases = apply_filters('datamachine_db', []);
        $db_flows = $all_databases['flows'] ?? null;

        if ($db_flows) {
            $flow = apply_filters('datamachine_get_flow', null, $flow_id);
            $pipeline_id = $flow['pipeline_id'] ?? null;
            $flow_config = $flow['flow_config'] ?? [];

            $flow_config_key = self::FLOW_CONFIG_CACHE_KEY . $flow_id;
            $flow_scheduling_key = self::FLOW_SCHEDULING_CACHE_KEY . $flow_id;

            $flow_config_cleared = delete_transient($flow_config_key);
            $flow_scheduling_cleared = delete_transient($flow_scheduling_key);

            do_action('datamachine_log', 'debug', 'Flow cache clearing executed', [
                'flow_id' => $flow_id,
                'flow_config_cache_key' => $flow_config_key,
                'flow_config_cleared' => $flow_config_cleared,
                'flow_scheduling_cache_key' => $flow_scheduling_key,
                'flow_scheduling_cleared' => $flow_scheduling_cleared,
                'pipeline_id' => $pipeline_id,
                'flow_steps_count' => count($flow_config)
            ]);

            foreach ($flow_config as $flow_step_id => $step_config) {
                $this->clear_flow_step_cache($flow_step_id);
            }

            if ($pipeline_id) {
                delete_transient(self::PIPELINE_FLOWS_CACHE_KEY . $pipeline_id);
            }

        } else {
            do_action('datamachine_log', 'warning', 'Could not clear flow cache - flows database not available', [
                'flow_id' => $flow_id
            ]);
        }
    }

    public function handle_clear_flow_config_cache($flow_id) {
        if (empty($flow_id)) {
            do_action('datamachine_log', 'warning', 'Flow config cache clear requested with empty flow ID');
            return;
        }

        $flow_config_key = self::FLOW_CONFIG_CACHE_KEY . $flow_id;
        $cleared = delete_transient($flow_config_key);

        do_action('datamachine_log', 'debug', 'Flow config cache cleared', [
            'flow_id' => $flow_id,
            'cache_key' => $flow_config_key,
            'cleared' => $cleared
        ]);
    }

    public function handle_clear_flow_scheduling_cache($flow_id) {
        if (empty($flow_id)) {
            do_action('datamachine_log', 'warning', 'Flow scheduling cache clear requested with empty flow ID');
            return;
        }

        $flow_scheduling_key = self::FLOW_SCHEDULING_CACHE_KEY . $flow_id;
        $cleared = delete_transient($flow_scheduling_key);

        do_action('datamachine_log', 'debug', 'Flow scheduling cache cleared', [
            'flow_id' => $flow_id,
            'cache_key' => $flow_scheduling_key,
            'cleared' => $cleared
        ]);
    }

    public function handle_clear_flow_steps_cache($flow_id) {
        if (empty($flow_id)) {
            do_action('datamachine_log', 'warning', 'Flow steps cache clear requested with empty flow ID');
            return;
        }

        $all_databases = apply_filters('datamachine_db', []);
        $db_flows = $all_databases['flows'] ?? null;

        if ($db_flows) {
            $flow = apply_filters('datamachine_get_flow', null, $flow_id);
            $flow_config = $flow['flow_config'] ?? [];

            foreach ($flow_config as $flow_step_id => $step_config) {
                $this->clear_flow_step_cache($flow_step_id);
            }

            do_action('datamachine_log', 'debug', 'Flow steps caches cleared', [
                'flow_id' => $flow_id,
                'steps_count' => count($flow_config)
            ]);
        } else {
            do_action('datamachine_log', 'warning', 'Could not clear flow steps cache - flows database not available', [
                'flow_id' => $flow_id
            ]);
        }
    }

    public function handle_clear_jobs_cache() {
        $this->clear_job_cache();

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    public function handle_clear_pipelines_list_cache() {
        delete_transient(self::PIPELINES_LIST_CACHE_KEY);

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Complete cache invalidation across all Data Machine components.
     *
     * Uses action-based architecture where each database component responds to
     * datamachine_clear_all_cache action to clear its own cache patterns. This ensures
     * extensibility and follows the "plugins within plugins" architecture.
     */
    public function handle_clear_all_cache() {
        delete_transient(self::ALL_PIPELINES_CACHE_KEY);
        delete_transient(self::PIPELINES_LIST_CACHE_KEY);
        delete_transient(self::PIPELINE_COUNT_CACHE_KEY);
        delete_transient(self::PIPELINE_EXPORT_CACHE_KEY);
        delete_transient(self::TOTAL_JOBS_COUNT_CACHE_KEY);

        // CRITICAL: Fire the action so database components can respond with their own cache clearing
        do_action('datamachine_clear_all_cache');

        do_action('ai_clear_all_cache');

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Clear all flows cache patterns for action-based architecture.
     */
    public function handle_clear_all_flows_cache() {
        $this->clear_cache_pattern(self::FLOW_PATTERN);

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Clear all pipelines cache patterns for action-based architecture.
     */
    public function handle_clear_all_pipelines_cache() {
        $this->clear_cache_pattern(self::PIPELINE_PATTERN);

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    public function handle_cache_set($key, $data, $timeout = 0, $group = null) {
        if (empty($key)) {
            do_action('datamachine_log', 'warning', 'Cache set requested with empty key');
            return false;
        }


        $result = set_transient($key, $data, $timeout);

        if (!$result) {
            do_action('datamachine_log', 'warning', 'Cache set failed', ['cache_key' => $key]);
        }

        return $result;
    }

    private function clear_pipeline_cache($pipeline_id) {
        $cache_keys = [
            self::PIPELINE_CACHE_KEY . $pipeline_id,
            self::PIPELINE_CONFIG_CACHE_KEY . $pipeline_id,
            self::PIPELINE_FLOWS_CACHE_KEY . $pipeline_id
        ];

        foreach ($cache_keys as $key) {
            delete_transient($key);
        }

    }

    private function clear_flow_cache($pipeline_id) {
        delete_transient(self::PIPELINE_FLOWS_CACHE_KEY . $pipeline_id);

        $this->clear_cache_pattern(self::FLOW_PATTERN);
    }

    public function clear_flow_step_cache($flow_step_id) {
        if (empty($flow_step_id)) {
            return;
        }

        $cache_keys = [
            'datamachine_flow_step_' . $flow_step_id,
            'datamachine_flow_step_config_' . $flow_step_id
        ];

        foreach ($cache_keys as $key) {
            delete_transient($key);
        }

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    private function clear_job_cache() {
        delete_transient(self::TOTAL_JOBS_COUNT_CACHE_KEY);

        $this->clear_cache_pattern(self::JOB_PATTERN);
        $this->clear_cache_pattern(self::RECENT_JOBS_PATTERN);
        $this->clear_cache_pattern(self::FLOW_JOBS_PATTERN);
    }

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

    public function handle_ai_cache_cleared($provider) {
        do_action('datamachine_log', 'debug', 'AI model cache cleared via action hook', [
            'provider' => $provider,
            'integration' => 'ai-http-client'
        ]);
    }

    public function handle_ai_all_cache_cleared() {
        do_action('datamachine_log', 'debug', 'All AI model caches cleared via action hook', [
            'integration' => 'ai-http-client'
        ]);
    }
}