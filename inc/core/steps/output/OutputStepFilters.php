<?php
/**
 * Output Step Component Filter Registration
 * 
 * Revolutionary "Plugins Within Plugins" Architecture Implementation
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
    
    // Step registration - Output declares itself as 'output' step type
    add_filter('dm_get_steps', function($step_config, $step_type) {
        if ($step_type === 'output') {
            return [
                'label' => __('Output', 'data-machine'),
                'description' => __('Send processed data to target destinations', 'data-machine'),
                'has_handlers' => true,
                'class' => OutputStep::class
            ];
        }
        return $step_config;
    }, 10, 2);
    
    // DataPacket conversion registration - parameter-based self-registration
    add_filter('dm_create_datapacket', function($datapacket, $source_data, $source_type, $context) {
        if ($source_type === 'output_result') {
            // Create output result DataPacket with proper structure
            $packet = new \DataMachine\Engine\DataPacket(
                'Output Complete',
                $source_data['content'] ?? '',
                'output_result'
            );
            
            // Add output-specific metadata
            if (isset($source_data['metadata'])) {
                $metadata = $source_data['metadata'];
                $packet->metadata['handler_used'] = $metadata['handler_used'] ?? null;
                $packet->metadata['output_success'] = $metadata['output_success'] ?? true;
                $packet->metadata['processing_time'] = $metadata['processing_time'] ?? time();
            }
            
            // Add context information
            if (isset($context['job_id'])) {
                $packet->metadata['job_id'] = $context['job_id'];
            }
            if (isset($context['handler_name'])) {
                $packet->metadata['handler_name'] = $context['handler_name'];
            }
            
            $packet->processing['steps_completed'][] = 'output';
            
            return $packet;
        }
        return $datapacket;
    }, 10, 4);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_output_step_filters();