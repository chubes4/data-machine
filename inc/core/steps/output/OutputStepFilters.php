<?php
/**
 * Output Step Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Output Step's "main plugin file" - the complete interface
 * contract with the engine, demonstrating complete self-containment and
 * zero bootstrap dependencies.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Output
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Output;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Output Step component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Output Step capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_output_step_filters() {
    
    // Step registration - Output declares itself as 'output' step type (pure discovery mode)
    add_filter('dm_get_steps', function($steps) {
        $steps['output'] = [
            'label' => __('Output', 'data-machine'),
            'description' => __('Publish to target destinations', 'data-machine'),
            'class' => OutputStep::class,
            'position' => 30
        ];
        return $steps;
    });
    
    // DataPacket creation removed - engine uses universal DataPacket constructor
    // Output step returns properly formatted data for direct constructor usage
}

// Auto-register when file loads - achieving complete self-containment
dm_register_output_step_filters();