<?php
/**
 * Engine Parameter Injection System
 *
 * Centralized database retrieval and parameter injection for engine_data.
 * Implements explicit data separation between AI data packets and engine parameters.
 *
 * @package DataMachine
 * @subpackage Engine\Filters
 * @since 1.0.0
 */

namespace DataMachine\Engine\Filters;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register engine parameter injection filter.
 *
 * Implements centralized engine_data retrieval from database storage.
 * Fetch handlers store source_url/image_url; Engine.php injects via filter.
 *
 * @since 1.0.0
 */
function dm_register_engine_filters() {

    /**
     * Inject engine parameters from database storage.
     *
     * Retrieves source_url, image_url stored by fetch handlers via store_engine_data().
     * Enables explicit data separation: clean AI packets vs engine parameters.
     *
     * @param array $parameters Current parameters
     * @param array $data Data packet array
     * @param array $flow_step_config Step configuration
     * @param string $step_type Step type identifier
     * @param string $flow_step_id Flow step identifier
     * @return array Enhanced parameters with engine data
     */
    add_filter('dm_engine_parameters', function($parameters, $data, $flow_step_config, $step_type, $flow_step_id) {
        $job_id = $parameters['job_id'] ?? null;
        if (!$job_id) {
            return $parameters;
        }

        // Get database service via filter discovery
        $all_databases = apply_filters('dm_db', []);
        $db_jobs = $all_databases['jobs'] ?? null;
        if (!$db_jobs) {
            return $parameters;
        }

        // Retrieve engine_data for this job
        $engine_data = $db_jobs->retrieve_engine_data($job_id);
        if (empty($engine_data)) {
            do_action('dm_log', 'debug', 'Engine Parameters: No engine_data found for job', [
                'job_id' => $job_id,
                'step_type' => $step_type,
                'flow_step_id' => $flow_step_id
            ]);
            return $parameters;
        }

        do_action('dm_log', 'debug', 'Engine Parameters: Retrieved engine_data from database', [
            'job_id' => $job_id,
            'step_type' => $step_type,
            'flow_step_id' => $flow_step_id,
            'has_source_url' => isset($engine_data['source_url']),
            'source_url' => $engine_data['source_url'] ?? 'NOT_SET',
            'has_image_url' => isset($engine_data['image_url']),
            'image_url' => $engine_data['image_url'] ?? 'NOT_SET',
            'engine_data_keys' => array_keys($engine_data)
        ]);

        // Inject engine parameters for handler consumption
        $parameters = array_merge($parameters, $engine_data);

        // Inject file metadata from data packet for AI multimodal processing
        if ($step_type === 'ai' && !empty($data)) {
            $first_item = $data[0] ?? [];
            $metadata = $first_item['metadata'] ?? [];

            // Check for file metadata from fetch handlers
            if (isset($metadata['file_path']) && file_exists($metadata['file_path'])) {
                $parameters['file_path'] = $metadata['file_path'];
                $parameters['mime_type'] = $metadata['mime_type'] ?? '';

                do_action('dm_log', 'debug', 'Engine Parameters: Injected file metadata for AI step', [
                    'job_id' => $job_id,
                    'flow_step_id' => $flow_step_id,
                    'file_path' => $parameters['file_path'],
                    'mime_type' => $parameters['mime_type']
                ]);
            }
        }

        return $parameters;

    }, 5, 5);

}

// Auto-register when file loads - following established pattern
dm_register_engine_filters();