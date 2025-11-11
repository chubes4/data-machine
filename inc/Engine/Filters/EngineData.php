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
     * Flexible key/value storage for engine data.
     *
     * Storage mode: apply_filters('datamachine_engine_data', null, $job_id, $key, $value)
     * Retrieval mode: apply_filters('datamachine_engine_data', [], $job_id)
     */
    add_filter('datamachine_engine_data', function($default, $job_id, $key = null, $value = null) {
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

        // Storage mode: when key and value are provided
        if ($key !== null && $value !== null) {
            $current_data = $db_jobs->retrieve_engine_data($job_id);
            $current_data[$key] = $value;
            $db_jobs->update_job_engine_data($job_id, $current_data);

            do_action('datamachine_log', 'debug', 'Engine Data: Stored key/value', [
                'job_id' => $job_id,
                'key' => $key,
                'value_type' => gettype($value)
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

    }, 10, 4);

}

datamachine_register_engine_data_filter();