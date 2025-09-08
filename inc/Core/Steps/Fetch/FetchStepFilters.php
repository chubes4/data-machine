<?php
/**
 * Fetch Step Component Filter Registration
 * 
 * Modular Component System Implementation
 * 
 * This file serves as Fetch Step's complete interface contract with the engine,
 * demonstrating systematic self-containment and comprehensive organization
 * for AI workflow data collection.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Fetch
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Fetch;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Fetch Step component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Fetch Step capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_fetch_step_filters() {
    
    // Step registration - Fetch declares itself as 'fetch' step type (pure discovery mode)
    add_filter('dm_steps', function($steps) {
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
dm_register_fetch_step_filters();