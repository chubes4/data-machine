<?php
/**
 * Fetch Step Filter Registration
 *
 * @package DataMachine\Core\Steps\Fetch
 */

namespace DataMachine\Core\Steps\Fetch;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function datamachine_register_fetch_step_filters() {
    
    // Step registration - Fetch declares itself as 'fetch' step type (pure discovery mode)
    add_filter('datamachine_step_types', function($steps) {
        $steps['fetch'] = [
            'label' => 'Fetch',
            'description' => 'Collect data from external sources',
            'class' => FetchStep::class,
            'position' => 10,
            'uses_handler' => true,
            'has_pipeline_config' => false
        ];
        return $steps;
    });
}

// Auto-register when file loads - achieving complete self-containment
datamachine_register_fetch_step_filters();