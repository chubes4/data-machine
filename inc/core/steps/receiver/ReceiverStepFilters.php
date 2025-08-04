<?php
/**
 * Receiver Step Component Filter Registration
 * 
 * Modular Component System Implementation
 * 
 * This file serves as Receiver Step's complete interface contract with the engine,
 * demonstrating systematic self-containment and comprehensive organization
 * for AI workflow webhook reception.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Receiver
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Receiver;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Receiver Step component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Receiver Step capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_receiver_step_filters() {
    
    // Step registration - Receiver declares itself as 'receiver' step type
    add_filter('dm_get_steps', function($step_config, $step_type = null) {
        // Discovery mode: return all steps when no type specified
        if (empty($step_type)) {
            return array_merge($step_config ?: [], [
                'receiver' => [
                    'label' => __('Receiver', 'data-machine'),
                    'description' => __('Accept webhooks from external platforms (framework implementation - coming soon)', 'data-machine'),
                    'class' => ReceiverStep::class,
                    'position' => 40
                ]
            ]);
        }
        
        // Specific mode: return step config for matching type
        if ($step_type === 'receiver') {
            return [
                'label' => __('Receiver', 'data-machine'),
                'description' => __('Accept webhooks from external platforms (framework implementation - coming soon)', 'data-machine'),
                'class' => ReceiverStep::class
            ];
        }
        return $step_config;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_receiver_step_filters();