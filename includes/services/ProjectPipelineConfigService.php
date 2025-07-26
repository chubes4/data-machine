<?php
/**
 * Project Pipeline Configuration Service
 *
 * Manages pipeline configuration for individual projects, including step order,
 * handler assignments, and step-specific settings.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/services
 * @since      NEXT_VERSION
 */

namespace DataMachine\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ProjectPipelineConfigService {

    /**
     * Get pipeline configuration for a project.
     *
     * @param int $project_id The project ID.
     * @return array Pipeline configuration.
     */
    public function get_project_pipeline_config( int $project_id ): array {
        $config = get_option( "dm_project_pipeline_{$project_id}", [] );
        
        // If no config exists, return default 3-step pipeline
        if ( empty( $config ) ) {
            return $this->get_default_pipeline_config();
        }
        
        return $config;
    }

    /**
     * Set pipeline configuration for a project.
     *
     * @param int   $project_id The project ID.
     * @param array $config     The pipeline configuration.
     * @return bool True on success, false on failure.
     */
    public function set_project_pipeline_config( int $project_id, array $config ): bool {
        return update_option( "dm_project_pipeline_{$project_id}", $config );
    }

    /**
     * Add a pipeline step to a project.
     *
     * @param int    $project_id The project ID.
     * @param string $step_type  The step type (input, ai, output).
     * @param string $handler_id The handler ID.
     * @param int    $position   The position to insert at.
     * @return bool True on success, false on failure.
     */
    public function add_pipeline_step( int $project_id, string $step_type, string $handler_id = '', int $position = -1 ): bool {
        $config = $this->get_project_pipeline_config( $project_id );
        
        $new_step = [
            'id' => uniqid( $step_type . '_' ),
            'type' => $step_type,
            'handler' => $handler_id,
            'config' => [],
            'order' => count( $config )
        ];
        
        if ( $position >= 0 && $position < count( $config ) ) {
            // Insert at specific position
            array_splice( $config, $position, 0, [ $new_step ] );
            // Reorder subsequent steps
            for ( $i = $position + 1; $i < count( $config ); $i++ ) {
                $config[ $i ]['order'] = $i;
            }
        } else {
            // Add to end
            $config[] = $new_step;
        }
        
        return $this->set_project_pipeline_config( $project_id, $config );
    }

    /**
     * Remove a pipeline step from a project.
     *
     * @param int    $project_id The project ID.
     * @param string $step_id    The step ID to remove.
     * @return bool True on success, false on failure.
     */
    public function remove_pipeline_step( int $project_id, string $step_id ): bool {
        $config = $this->get_project_pipeline_config( $project_id );
        
        $found_index = -1;
        foreach ( $config as $index => $step ) {
            if ( $step['id'] === $step_id ) {
                $found_index = $index;
                break;
            }
        }
        
        if ( $found_index === -1 ) {
            return false; // Step not found
        }
        
        // Remove the step
        array_splice( $config, $found_index, 1 );
        
        // Reorder remaining steps
        foreach ( $config as $index => $step ) {
            $config[ $index ]['order'] = $index;
        }
        
        return $this->set_project_pipeline_config( $project_id, $config );
    }

    /**
     * Reorder pipeline steps for a project.
     *
     * @param int   $project_id The project ID.
     * @param array $step_order Array of step IDs in new order.
     * @return bool True on success, false on failure.
     */
    public function reorder_pipeline_steps( int $project_id, array $step_order ): bool {
        $config = $this->get_project_pipeline_config( $project_id );
        
        // Create a lookup map of current steps
        $step_map = [];
        foreach ( $config as $step ) {
            $step_map[ $step['id'] ] = $step;
        }
        
        // Reorder according to the provided order
        $reordered_config = [];
        foreach ( $step_order as $index => $step_id ) {
            if ( isset( $step_map[ $step_id ] ) ) {
                $step_map[ $step_id ]['order'] = $index;
                $reordered_config[] = $step_map[ $step_id ];
            }
        }
        
        return $this->set_project_pipeline_config( $project_id, $reordered_config );
    }

    /**
     * Update step configuration.
     *
     * @param int    $project_id The project ID.
     * @param string $step_id    The step ID.
     * @param string $handler_id The handler ID.
     * @param array  $config     The step configuration.
     * @return bool True on success, false on failure.
     */
    public function update_step_config( int $project_id, string $step_id, string $handler_id, array $config ): bool {
        $pipeline_config = $this->get_project_pipeline_config( $project_id );
        
        $found_index = -1;
        foreach ( $pipeline_config as $index => $step ) {
            if ( $step['id'] === $step_id ) {
                $found_index = $index;
                break;
            }
        }
        
        if ( $found_index === -1 ) {
            return false; // Step not found
        }
        
        // Update the step
        $pipeline_config[ $found_index ]['handler'] = $handler_id;
        $pipeline_config[ $found_index ]['config'] = $config;
        
        return $this->set_project_pipeline_config( $project_id, $pipeline_config );
    }

    /**
     * Get default pipeline configuration.
     *
     * @return array Default 3-step pipeline configuration.
     */
    private function get_default_pipeline_config(): array {
        return [
            [
                'id' => 'input_default',
                'type' => 'input',
                'handler' => '',
                'config' => [],
                'order' => 0
            ],
            [
                'id' => 'ai_default',
                'type' => 'ai',
                'handler' => 'ai_processing',
                'config' => [],
                'order' => 1
            ],
            [
                'id' => 'output_default',
                'type' => 'output',
                'handler' => '',
                'config' => [],
                'order' => 2
            ]
        ];
    }

    /**
     * Validate pipeline configuration.
     *
     * @param array $config The pipeline configuration.
     * @return array Validation result with 'valid' and 'errors' keys.
     */
    public function validate_pipeline_config( array $config ): array {
        $errors = [];
        $has_input = false;
        $has_ai = false;
        $has_output = false;
        
        foreach ( $config as $step ) {
            if ( ! isset( $step['type'] ) ) {
                $errors[] = __( 'Step missing type.', 'data-machine' );
                continue;
            }
            
            switch ( $step['type'] ) {
                case 'input':
                    $has_input = true;
                    break;
                case 'ai':
                    $has_ai = true;
                    break;
                case 'output':
                    $has_output = true;
                    break;
                default:
                    $errors[] = sprintf( 
                        /* translators: %s: step type */
                        __( 'Invalid step type: %s', 'data-machine' ), 
                        $step['type'] 
                    );
            }
        }
        
        if ( ! $has_input ) {
            $errors[] = __( 'Pipeline must have at least one input step.', 'data-machine' );
        }
        
        if ( ! $has_ai ) {
            $errors[] = __( 'Pipeline must have at least one AI processing step.', 'data-machine' );
        }
        
        if ( ! $has_output ) {
            $errors[] = __( 'Pipeline must have at least one output step.', 'data-machine' );
        }
        
        return [
            'valid' => empty( $errors ),
            'errors' => $errors
        ];
    }

    /**
     * Delete pipeline configuration for a project.
     *
     * @param int $project_id The project ID.
     * @return bool True on success, false on failure.
     */
    public function delete_project_pipeline_config( int $project_id ): bool {
        return delete_option( "dm_project_pipeline_{$project_id}" );
    }
}