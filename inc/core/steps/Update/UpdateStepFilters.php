<?php
/**
 * Update Step Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Update Step's complete interface contract with the engine,
 * demonstrating systematic self-containment and comprehensive organization
 * for AI workflow content updates.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Update
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Update;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Update Step component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Update Step capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_update_step_filters() {
    
    // Step registration - Update Step declares itself (pure discovery mode)
    add_filter('dm_steps', function($steps) {
        $steps['update'] = [
            'label' => __('Update', 'data-machine'),
            'description' => __('Update existing content with processed data', 'data-machine'),
            'class' => UpdateStep::class,
            'position' => 25  // Between AI (20) and Publish (30)
        ];
        return $steps;
    });
}

// Auto-register when file loads - achieving complete self-containment
dm_register_update_step_filters();