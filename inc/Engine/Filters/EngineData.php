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
      * Array-based storage for engine data with extensibility for custom data types.
      *
      * Allows extensions to modify/add data before storage via filter.
      * Retrieval should use direct database access: $db_jobs->retrieve_engine_data($job_id)
      */
    add_filter('datamachine_engine_data', function($default, $job_id, $data = null) {
        $job_id = (int) $job_id; // Ensure job_id is int for database operations

        if ($job_id === null || $job_id === '') {
            return $default;
        }

        $db_jobs = new \DataMachine\Core\Database\Jobs\Jobs();

        // Only storage mode: when data array is provided, allow extensions to modify
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

        // No retrieval mode - use direct database access instead
        return $default;

    }, 10, 3);

}

/**
 * Get engine data directly from database.
 *
 * @param int $job_id Job ID
 * @return array Engine data or empty array
 */
function datamachine_get_engine_data(int $job_id): array {
    if ($job_id <= 0) {
        return [];
    }

    $db_jobs = new \DataMachine\Core\Database\Jobs\Jobs();
    return $db_jobs->retrieve_engine_data($job_id);
}

datamachine_register_engine_data_filter();