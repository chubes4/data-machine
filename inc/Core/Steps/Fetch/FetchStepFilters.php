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
            'label' => __('Fetch', 'data-machine'),
            'description' => __('Collect data from external sources', 'data-machine'),
            'class' => FetchStep::class,
            'position' => 10
        ];
        return $steps;
    });
}

// Auto-register when file loads - achieving complete self-containment
datamachine_register_fetch_step_filters();