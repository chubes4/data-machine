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

function dm_register_engine_data_filter() {

    /**
     * Retrieve engine data for job via centralized filter.
     *
     * @param array $engine_data Default empty array
     * @param int $job_id Job ID
     * @return array Engine data (source_url, image_url, etc.)
     */
    add_filter('dm_engine_data', function($engine_data, $job_id) {
        if (empty($job_id)) {
            return [];
        }

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

dm_register_engine_data_filter();