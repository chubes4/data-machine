<?php
/**
 * Centralized engine data storage and retrieval.
 *
 * @package DataMachine\Engine\Filters
 */

if (!defined('ABSPATH')) {
    exit;
}

function datamachine_register_engine_data_filter() {

    /**
     * Array-based storage for engine data.
     *
     * Storage mode: apply_filters('datamachine_engine_data', null, $job_id, ['source_url' => $url, 'image_url' => $img])
     * Retrieval mode: apply_filters('datamachine_engine_data', [], $job_id)
     */
    add_filter('datamachine_engine_data', function($default, $job_id, $data = null) {
        if (empty($job_id)) {
            return $default;
        }

        $all_databases = apply_filters('datamachine_db', []);
        $db_jobs = $all_databases['jobs'] ?? null;

        if (!$db_jobs) {
            do_action('datamachine_log', 'debug', 'Engine Data: Database service unavailable', [
                'job_id' => $job_id
            ]);
            return $default;
        }

        // Storage mode: when data array is provided
        if ($data !== null && is_array($data)) {
            $current_data = $db_jobs->retrieve_engine_data($job_id);
            $merged_data = array_merge($current_data ?: [], $data);
            $db_jobs->store_engine_data($job_id, $merged_data);

            do_action('datamachine_log', 'debug', 'Engine Data: Stored data array', [
                'job_id' => $job_id,
                'keys' => array_keys($data),
                'total_keys' => count($merged_data)
            ]);

            return null;
        }

        // Retrieval mode: return all engine data
        $retrieved_data = $db_jobs->retrieve_engine_data($job_id);

        if (empty($retrieved_data)) {
            do_action('datamachine_log', 'debug', 'Engine Data: No data found for job', [
                'job_id' => $job_id
            ]);
            return [];
        }

        do_action('datamachine_log', 'debug', 'Engine Data: Retrieved all data', [
            'job_id' => $job_id,
            'data_keys' => array_keys($retrieved_data)
        ]);

        return $retrieved_data;

    }, 10, 3);

}

datamachine_register_engine_data_filter();