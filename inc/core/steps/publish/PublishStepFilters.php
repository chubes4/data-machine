<?php
/**
 * Publish Step Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Publish Step's "main plugin file" - the complete interface
 * contract with the engine, demonstrating complete self-containment and
 * zero bootstrap dependencies.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Publish
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Publish;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Publish Step component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Publish Step capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_publish_step_filters() {
    
    // Step registration - Publish declares itself as 'publish' step type (pure discovery mode)
    add_filter('dm_get_steps', function($steps) {
        $steps['publish'] = [
            'label' => __('Publish', 'data-machine'),
            'description' => __('Publish to target destinations', 'data-machine'),
            'class' => PublishStep::class,
            'position' => 30
        ];
        return $steps;
    });
    
    // DataPacket creation removed - engine uses universal DataPacket constructor
    // Publish step returns properly formatted data for direct constructor usage
}

// Auto-register when file loads - achieving complete self-containment
dm_register_publish_step_filters();