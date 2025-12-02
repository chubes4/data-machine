<?php
/**
 * Backend processing filters for Data Machine engine.
 *
 * Core filter registration for handler services, scheduling,
 * and step configuration discovery.
 *
 * @package DataMachine
 * @subpackage Engine\Filters
 * @since 0.1.0
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Register import/export filters for Data Machine.
 *
 * Registers filters for importer service discovery and initialization.
 *
 * @since 0.1.0
 */
function datamachine_register_importexport_filters() {



    add_filter('datamachine_importer', function($service) {
        if ($service === null) {
            require_once DATAMACHINE_PATH . 'inc/Engine/Actions/ImportExport.php';
            return new \DataMachine\Engine\Actions\ImportExport();
        }
        return $service;
    }, 10, 1);
}

datamachine_register_importexport_filters();

/**
 * Register backend processing filters for engine operations.
 *
 * Registers filters for service discovery, scheduling,
 * and step configuration. Does not handle UI/admin logic.
 *
 * @since 0.1.0
 */
function datamachine_register_utility_filters() {
    
    add_filter('datamachine_auth_providers', function($providers) {
        return $providers;
    }, 5, 1);
    
    add_filter('datamachine_scheduler_intervals', function($intervals) {
        return [
            'every_5_minutes' => [
                'label' => 'Every 5 Minutes',
                'seconds' => 300 // 5 * 60
            ],
            'hourly' => [
                'label' => 'Hourly',
                'seconds' => HOUR_IN_SECONDS
            ],
            'every_2_hours' => [
                'label' => 'Every 2 Hours',
                'seconds' => HOUR_IN_SECONDS * 2
            ],
            'every_4_hours' => [
                'label' => 'Every 4 Hours',
                'seconds' => HOUR_IN_SECONDS * 4
            ],
            'qtrdaily' => [
                'label' => 'Every 6 Hours',
                'seconds' => HOUR_IN_SECONDS * 6
            ],
            'twicedaily' => [
                'label' => 'Twice Daily',
                'seconds' => HOUR_IN_SECONDS * 12
            ],
            'daily' => [
                'label' => 'Daily',
                'seconds' => DAY_IN_SECONDS
            ],
            'weekly' => [
                'label' => 'Weekly',
                'seconds' => WEEK_IN_SECONDS
            ]
        ];
    }, 10);
    
    
    add_filter('datamachine_step_settings', function($configs) {
        return $configs;
    }, 5);
    add_filter('datamachine_generate_flow_step_id', function($existing_id, $pipeline_step_id, $flow_id) {
        if (empty($pipeline_step_id) || empty($flow_id)) {
            do_action('datamachine_log', 'error', 'Invalid flow step ID generation parameters', [
                'pipeline_step_id' => $pipeline_step_id,
                'flow_id' => $flow_id
            ]);
            return '';
        }
        
        return $pipeline_step_id . '_' . $flow_id;
    }, 10, 3);

    add_filter('datamachine_split_pipeline_step_id', function($default, $pipeline_step_id) {
        if (empty($pipeline_step_id) || strpos($pipeline_step_id, '_') === false) {
            return null; // Old UUID4 format or invalid
        }

        $parts = explode('_', $pipeline_step_id, 2);
        if (count($parts) !== 2) {
            return null;
        }

        return [
            'pipeline_id' => $parts[0],
            'uuid' => $parts[1]
        ];
    }, 10, 2);

    // Split composite flow_step_id: {pipeline_step_id}_{flow_id}
    add_filter('datamachine_split_flow_step_id', function($null, $flow_step_id) {
        if (empty($flow_step_id) || !is_string($flow_step_id)) {
            return null;
        }

        // Split on last underscore to handle UUIDs with dashes
        $last_underscore_pos = strrpos($flow_step_id, '_');
        if ($last_underscore_pos === false) {
            return null;
        }

        $pipeline_step_id = substr($flow_step_id, 0, $last_underscore_pos);
        $flow_id = substr($flow_step_id, $last_underscore_pos + 1);

        // Validate flow_id is numeric
        if (!is_numeric($flow_id)) {
            return null;
        }

        return [
            'pipeline_step_id' => $pipeline_step_id,
            'flow_id' => (int)$flow_id
        ];
    }, 10, 2);

    // Global execution context for directives




}
