<?php
/**
 * Flow Validation Engine
 *
 * Validates pipeline configurations and step flows to ensure data integrity
 * and proper pipeline execution.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine
 * @since      NEXT_VERSION
 */

namespace DataMachine\Engine;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class FlowValidationEngine {

    /**
     * Validate a complete pipeline configuration.
     *
     * @param array $pipeline_config The pipeline configuration to validate.
     * @return array Validation result with 'valid', 'errors', and 'warnings' keys.
     */
    public function validate_pipeline( array $pipeline_config ): array {
        $errors = [];
        $warnings = [];
        
        // Check if pipeline has steps
        if ( empty( $pipeline_config ) ) {
            $errors[] = __( 'Pipeline must have at least one step.', 'data-machine' );
            return [
                'valid' => false,
                'errors' => $errors,
                'warnings' => $warnings
            ];
        }
        
        // Check for required step types
        $step_types = array_column( $pipeline_config, 'type' );
        $has_input = in_array( 'input', $step_types, true );
        $has_ai = in_array( 'ai', $step_types, true );
        $has_output = in_array( 'output', $step_types, true );
        
        if ( ! $has_input ) {
            $errors[] = __( 'Pipeline must have at least one input step.', 'data-machine' );
        }
        
        if ( ! $has_ai ) {
            $warnings[] = __( 'Pipeline without AI processing steps may have limited functionality.', 'data-machine' );
        }
        
        if ( ! $has_output ) {
            $errors[] = __( 'Pipeline must have at least one output step.', 'data-machine' );
        }
        
        // Validate step order
        $order_errors = $this->validate_step_order( $pipeline_config );
        $errors = array_merge( $errors, $order_errors );
        
        // Validate individual steps
        foreach ( $pipeline_config as $index => $step ) {
            $step_errors = $this->validate_individual_step( $step, $index );
            $errors = array_merge( $errors, $step_errors );
        }
        
        return [
            'valid' => empty( $errors ),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Validate step ordering and sequence.
     *
     * @param array $pipeline_config The pipeline configuration.
     * @return array Array of validation errors.
     */
    private function validate_step_order( array $pipeline_config ): array {
        $errors = [];
        
        // Check that steps have proper order values
        foreach ( $pipeline_config as $index => $step ) {
            if ( ! isset( $step['order'] ) ) {
                $errors[] = sprintf(
                    /* translators: %d: step index */
                    __( 'Step at position %d is missing order value.', 'data-machine' ),
                    $index + 1
                );
            }
        }
        
        // Check for duplicate orders
        $orders = array_column( $pipeline_config, 'order' );
        $unique_orders = array_unique( $orders );
        if ( count( $orders ) !== count( $unique_orders ) ) {
            $errors[] = __( 'Duplicate step order values detected.', 'data-machine' );
        }
        
        return $errors;
    }
    
    /**
     * Validate an individual pipeline step.
     *
     * @param array $step The step configuration.
     * @param int   $index The step index.
     * @return array Array of validation errors.
     */
    private function validate_individual_step( array $step, int $index ): array {
        $errors = [];
        $step_position = $index + 1;
        
        // Check required fields
        if ( ! isset( $step['type'] ) ) {
            $errors[] = sprintf(
                /* translators: %d: step position */
                __( 'Step %d is missing type.', 'data-machine' ),
                $step_position
            );
            return $errors; // Can't continue validation without type
        }
        
        if ( ! isset( $step['id'] ) ) {
            $errors[] = sprintf(
                /* translators: %d: step position */
                __( 'Step %d is missing ID.', 'data-machine' ),
                $step_position
            );
        }
        
        // Validate step type using dynamic filter system
        $registered_step_types = apply_filters('dm_register_step_types', []);
        $valid_types = array_keys($registered_step_types);
        if ( ! in_array( $step['type'], $valid_types, true ) ) {
            $errors[] = sprintf(
                /* translators: %1$d: step position, %2$s: invalid type */
                __( 'Step %1$d has invalid type: %2$s', 'data-machine' ),
                $step_position,
                $step['type']
            );
        }
        
        // Type-specific validation
        switch ( $step['type'] ) {
            case 'input':
            case 'output':
                $handler_errors = $this->validate_handler_step( $step, $step_position );
                $errors = array_merge( $errors, $handler_errors );
                break;
                
            case 'ai':
                $ai_errors = $this->validate_ai_step( $step, $step_position );
                $errors = array_merge( $errors, $ai_errors );
                break;
        }
        
        return $errors;
    }
    
    /**
     * Validate a handler-based step (input/output).
     *
     * @param array $step The step configuration.
     * @param int   $step_position The step position.
     * @return array Array of validation errors.
     */
    private function validate_handler_step( array $step, int $step_position ): array {
        $errors = [];
        
        // Handler validation can be added here when needed
        // For now, handler is optional as it can be configured later
        
        return $errors;
    }
    
    /**
     * Validate an AI processing step.
     *
     * @param array $step The step configuration.
     * @param int   $step_position The step position.
     * @return array Array of validation errors.
     */
    private function validate_ai_step( array $step, int $step_position ): array {
        $errors = [];
        
        // AI step validation can be enhanced as needed
        // For now, config is optional as it can be configured later
        
        return $errors;
    }
    
    /**
     * Validate pipeline flow connections.
     *
     * @param array $pipeline_config The pipeline configuration.
     * @return array Validation result with flow-specific checks.
     */
    public function validate_flow_connections( array $pipeline_config ): array {
        $errors = [];
        $warnings = [];
        
        if ( empty( $pipeline_config ) ) {
            return [
                'valid' => true,
                'errors' => [],
                'warnings' => []
            ];
        }
        
        // Sort by order to check logical flow
        usort( $pipeline_config, function( $a, $b ) {
            return ( $a['order'] ?? 0 ) <=> ( $b['order'] ?? 0 );
        });
        
        // Check logical step progression
        $step_types = array_column( $pipeline_config, 'type' );
        
        // Input should generally come before output
        $first_input = array_search( 'input', $step_types );
        $last_output = array_search( 'output', array_reverse( $step_types, true ) );
        
        if ( $first_input !== false && $last_output !== false ) {
            $last_output = count( $step_types ) - 1 - $last_output; // Adjust for reverse
            if ( $first_input > $last_output ) {
                $warnings[] = __( 'Input step appears after output step - this may cause unexpected behavior.', 'data-machine' );
            }
        }
        
        return [
            'valid' => empty( $errors ),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Validate step configuration for a specific step type.
     *
     * @param array  $step_config The step configuration.
     * @param string $step_type   The step type.
     * @return array Validation result.
     */
    public function validate_step_config( array $step_config, string $step_type ): array {
        $errors = [];
        $warnings = [];
        
        switch ( $step_type ) {
            case 'ai':
                if ( empty( $step_config['prompt'] ) ) {
                    $warnings[] = __( 'AI step has no prompt configured.', 'data-machine' );
                }
                if ( empty( $step_config['model'] ) ) {
                    $warnings[] = __( 'AI step has no model specified.', 'data-machine' );
                }
                break;
                
            case 'input':
                if ( empty( $step_config['handler'] ) ) {
                    $warnings[] = __( 'Input step has no handler configured.', 'data-machine' );
                }
                break;
                
            case 'output':
                if ( empty( $step_config['handler'] ) ) {
                    $warnings[] = __( 'Output step has no handler configured.', 'data-machine' );
                }
                break;
        }
        
        return [
            'valid' => empty( $errors ),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
}