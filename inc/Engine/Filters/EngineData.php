<?php
/**
 * Centralized Engine Data Access Filter
 *
 * Provides unified access to engine_data (source_url, image_url) stored by fetch handlers.
 * Maintains architectural consistency with filter-based service discovery pattern.
 *
 * @package DataMachine\Engine\Filters
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register engine data filter.
 *
 * Centralizes engine_data access through filter pattern instead of direct database access.
 * Maintains consistency with established architectural pattern of filter-based service discovery.
 */
function dm_register_engine_data_filter() {

    /**
     * Retrieve engine data for a job.
     *
     * Provides centralized access to source_url, image_url stored by fetch handlers.
     * Uses existing filter-based database service discovery pattern.
     *
     * @param array $engine_data Default empty array
     * @param int $job_id Job ID to retrieve engine data for
     * @return array Engine data containing source_url, image_url, etc.
     */
    add_filter('dm_engine_data', function($engine_data, $job_id) {
        if (empty($job_id)) {
            return [];
        }

        // Use established filter pattern for database service discovery
        $all_databases = apply_filters('dm_db', []);
        $db_jobs = $all_databases['jobs'] ?? null;

        if (!$db_jobs) {
            do_action('dm_log', 'debug', 'Engine Data: Database service unavailable', [
                'job_id' => $job_id
            ]);
            return [];
        }

        $retrieved_data = $db_jobs->retrieve_engine_data($job_id);

        if (empty($retrieved_data)) {
            do_action('dm_log', 'debug', 'Engine Data: No engine_data found for job', [
                'job_id' => $job_id
            ]);
            return [];
        }

        do_action('dm_log', 'debug', 'Engine Data: Retrieved data via filter', [
            'job_id' => $job_id,
            'has_source_url' => isset($retrieved_data['source_url']),
            'source_url' => $retrieved_data['source_url'] ?? 'NOT_SET',
            'has_image_url' => isset($retrieved_data['image_url']),
            'image_url' => $retrieved_data['image_url'] ?? 'NOT_SET',
            'data_keys' => array_keys($retrieved_data)
        ]);

        return $retrieved_data;

    }, 10, 2);

}

// Auto-register when file loads - following established pattern
dm_register_engine_data_filter();