<?php
/**
 * Input Step Component Filter Registration
 * 
 * Modular Component System Implementation
 * 
 * This file serves as Input Step's complete interface contract with the engine,
 * demonstrating systematic self-containment and comprehensive organization
 * for AI workflow data collection.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Input
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Input;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Input Step component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Input Step capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_input_step_filters() {
    
    // Step registration - Input declares itself as 'input' step type
    add_filter('dm_get_steps', function($step_config, $step_type) {
        if ($step_type === 'input') {
            return [
                'label' => __('Input', 'data-machine'),
                'description' => __('Collect data from external sources', 'data-machine'),
                'has_handlers' => true,
                'class' => InputStep::class
            ];
        }
        return $step_config;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_input_step_filters();