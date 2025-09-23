<?php
/**
 * Centralized Engine Data Access Filter
 *
 * Provides unified storage and retrieval of engine_data (source_url, image_url)
 * via dm_engine_data filter. Maintains filter-based service discovery consistency.
 *
 * @package DataMachine\Engine\Filters
 */

if (!defined('ABSPATH')) {
    exit;
}

function dm_register_engine_data_filter() {

    /**
     * Engine data storage and retrieval filter.
     *
     * @param array $default Default value for retrieval
     * @param int $job_id Job ID
     * @param string $source_url Source URL (storage mode)
     * @param string $image_url Image URL (storage mode)
     * @return array|null Engine data array or null for storage
     */
    add_filter('dm_engine_data', function($default, $job_id, $source_url = null, $image_url = null) {
        if (empty($job_id)) {
            return [];
        }

        // Storage mode: 4+ parameters indicates storage operation
        if (func_num_args() >= 4) {
            $all_databases = apply_filters('dm_db', []);
            $db_jobs = $all_databases['jobs'] ?? null;

            if (!$db_jobs) {
                do_action('dm_log', 'debug', 'Engine Data Storage: Database service unavailable', [
                    'job_id' => $job_id
                ]);
                return null;
            }

            $engine_data = [
                'source_url' => $source_url ?? '',
                'image_url' => $image_url ?? ''
            ];

            $db_jobs->store_engine_data($job_id, $engine_data);

            do_action('dm_log', 'debug', 'Engine Data: Stored via centralized filter', [
                'job_id' => $job_id,
                'source_url' => $engine_data['source_url'],
                'has_image_url' => !empty($engine_data['image_url']),
                'param_count' => func_num_args()
            ]);

            return null;
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

        do_action('dm_log', 'debug', 'Engine Data: Retrieved via centralized filter', [
            'job_id' => $job_id,
            'has_source_url' => isset($retrieved_data['source_url']),
            'source_url' => $retrieved_data['source_url'] ?? 'NOT_SET',
            'has_image_url' => isset($retrieved_data['image_url']),
            'image_url' => $retrieved_data['image_url'] ?? 'NOT_SET',
            'data_keys' => array_keys($retrieved_data),
            'param_count' => func_num_args()
        ]);

        return $retrieved_data;

    }, 10, 4);

}

dm_register_engine_data_filter();