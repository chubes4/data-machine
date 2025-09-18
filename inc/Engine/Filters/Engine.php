<?php
/**
 * Engine Filter Registration
 *
 * Core engine processing filters for parameter injection and execution flow.
 * Centralizes engine-specific filter registrations following established patterns.
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
 * Register engine processing filters.
 *
 * Core engine filters for parameter injection and execution flow management.
 *
 * Key Filters:
 * - dm_engine_parameters: Centralized parameter injection system
 *
 * @since 1.0.0
 */
function dm_register_engine_filters() {

    /**
     * Centralized Engine Data Parameter Injection
     *
     * Single filter-based parameter injection system that reads engine_data from database
     * and injects URLs and engine-specific parameters for discovery pattern.
     *
     * DATA PACKET = AI (clean metadata, no URLs)
     * ENGINE_DATA = ENGINE (source_url, image_url for handlers)
     *
     * Discovery Pattern: Inject URLs from engine_data, steps use what they need.
     * Runs at priority 5 to execute before existing handler filters.
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
            do_action('dm_log', 'debug', 'Engine Parameter Injection: No jobs database service available', [
                'job_id' => $job_id,
                'step_type' => $step_type
            ]);
            return $parameters;
        }

        // Retrieve engine_data for this job
        $engine_data = $db_jobs->retrieve_engine_data($job_id);
        if (empty($engine_data)) {
            do_action('dm_log', 'debug', 'Engine Parameter Injection: No engine_data found for job', [
                'job_id' => $job_id,
                'step_type' => $step_type
            ]);
            return $parameters;
        }

        // Discovery pattern: inject URLs and engine parameters, steps consume what they need
        $merged_parameters = array_merge($parameters, $engine_data);

        do_action('dm_log', 'debug', 'Engine Parameter Injection: Injected URLs from engine_data', [
            'job_id' => $job_id,
            'step_type' => $step_type,
            'injected_keys' => array_keys($engine_data),
            'has_source_url' => !empty($engine_data['source_url']),
            'has_image_url' => !empty($engine_data['image_url'])
        ]);

        return $merged_parameters;

    }, 5, 5); // Priority 5: Execute before existing handler filters

}

// Auto-register when file loads - following established pattern
dm_register_engine_filters();