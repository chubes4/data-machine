<?php
/**
 * Project Pipeline Configuration Service
 *
 * Manages project-specific pipeline step configurations for the extensible pipeline architecture.
 * This service provides methods to store, retrieve, and manage pipeline step configurations at the project level,
 * allowing projects to have custom pipeline configurations with specific step orders and settings.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/helpers
 * @since      NEXT_VERSION
 */

namespace DataMachine\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ProjectPipelineConfigService {

    /**
     * Initialize the service.
     * Uses filter-based architecture - no constructor injection.
     *
     * @since NEXT_VERSION
     */
    public function __construct() {
        // No parameters needed - pure filter-based architecture
    }

    /**
     * Get project pipeline steps configuration as an ordered array.
     *
     * @param int      $project_id The ID of the project.
     * @param int|null $user_id    Optional. The ID of the user for ownership verification.
     * @return array Array of step configurations structured as:
     *               {
     *                 "steps": [
     *                   {"type": "input", "slug": "files", "config": {...}, "position": 0},
     *                   {"type": "ai", "slug": "process", "config": {...}, "position": 1}, 
     *                   {"type": "output", "slug": "twitter", "config": {...}, "position": 2}
     *                 ]
     *               }
     */
    public function get_project_pipeline_steps( int $project_id, int $user_id = null ): array {
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $pipeline_config = $db_projects->get_project_pipeline_configuration( $project_id, $user_id );
        
        if ( empty( $pipeline_config ) || ! isset( $pipeline_config['steps'] ) ) {
            return [];
        }

        // Ensure steps are sorted by position
        $steps = $pipeline_config['steps'];
        if ( is_array( $steps ) ) {
            usort( $steps, function( $a, $b ) {
                $pos_a = isset( $a['position'] ) ? (int) $a['position'] : 0;
                $pos_b = isset( $b['position'] ) ? (int) $b['position'] : 0;
                return $pos_a <=> $pos_b;
            });
        }

        return [
            'steps' => $steps
        ];
    }

    /**
     * Update project pipeline steps configuration.
     *
     * @param int   $project_id    The ID of the project.
     * @param array $steps_config  Array of step configurations to store.
     * @param int   $user_id       The ID of the user making the change.
     * @return bool True on success, false on failure.
     */
    public function update_project_pipeline_steps( int $project_id, array $steps_config, int $user_id ): bool {
        // Validate the configuration structure
        $validation = $this->validate_pipeline_configuration( $steps_config );
        if ( ! $validation['valid'] ) {
            $logger = apply_filters('dm_get_service', null, 'logger');
            if ( $logger ) {
                $logger->error('Invalid pipeline configuration provided', [
                    'project_id' => $project_id,
                    'errors' => $validation['errors']
                ]);
            }
            return false;
        }

        // Normalize step positions
        $normalized_config = $this->normalize_step_positions( $steps_config );
        
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $result = $db_projects->update_project_pipeline_configuration( $project_id, $normalized_config, $user_id );
        
        return $result !== false;
    }

    /**
     * Add a new step to a project pipeline at a specific position.
     *
     * @param int    $project_id  The ID of the project.
     * @param string $step_type   The type of step (e.g., 'input', 'ai', 'output').
     * @param array  $step_config Configuration array for the step.
     * @param int    $position    Position to insert the step at (0-based).
     * @param int    $user_id     The ID of the user making the change.
     * @return bool True on success, false on failure.
     */
    public function add_step_to_project( int $project_id, string $step_type, array $step_config, int $position, int $user_id ): bool {
        $current_config = $this->get_project_pipeline_steps( $project_id, $user_id );
        $steps = $current_config['steps'] ?? [];

        // Validate the step type is available
        $available_types = $this->get_available_step_types();
        if ( ! isset( $available_types[$step_type] ) ) {
            $logger = apply_filters('dm_get_service', null, 'logger');
            if ( $logger ) {
                $logger->error('Invalid step type provided', [
                    'project_id' => $project_id,
                    'step_type' => $step_type,
                    'available_types' => array_keys( $available_types )
                ]);
            }
            return false;
        }

        // Create new step configuration
        $new_step = [
            'type' => $step_type,
            'slug' => $step_config['slug'] ?? $step_type,
            'config' => $step_config,
            'position' => $position
        ];

        // Shift existing steps down if needed
        foreach ( $steps as &$step ) {
            if ( $step['position'] >= $position ) {
                $step['position']++;
            }
        }

        // Add the new step
        $steps[] = $new_step;

        return $this->update_project_pipeline_steps( $project_id, ['steps' => $steps], $user_id );
    }

    /**
     * Remove a step from a project pipeline at a specific position.
     *
     * @param int $project_id     The ID of the project.
     * @param int $step_position  Position of the step to remove (0-based).
     * @param int $user_id        The ID of the user making the change.
     * @return bool True on success, false on failure.
     */
    public function remove_step_from_project( int $project_id, int $step_position, int $user_id ): bool {
        $current_config = $this->get_project_pipeline_steps( $project_id, $user_id );
        $steps = $current_config['steps'] ?? [];

        // Find and remove the step at the specified position
        $steps = array_filter( $steps, function( $step ) use ( $step_position ) {
            return $step['position'] !== $step_position;
        });

        // Shift remaining steps up
        foreach ( $steps as &$step ) {
            if ( $step['position'] > $step_position ) {
                $step['position']--;
            }
        }

        // Re-index array to remove gaps
        $steps = array_values( $steps );

        return $this->update_project_pipeline_steps( $project_id, ['steps' => $steps], $user_id );
    }

    /**
     * Reorder project pipeline steps to a new sequence.
     *
     * @param int   $project_id The ID of the project.
     * @param array $new_order  Array of step positions in new order (e.g., [2, 0, 1]).
     * @param int   $user_id    The ID of the user making the change.
     * @return bool True on success, false on failure.
     */
    public function reorder_project_steps( int $project_id, array $new_order, int $user_id ): bool {
        $current_config = $this->get_project_pipeline_steps( $project_id, $user_id );
        $steps = $current_config['steps'] ?? [];

        if ( count( $new_order ) !== count( $steps ) ) {
            $logger = apply_filters('dm_get_service', null, 'logger');
            if ( $logger ) {
                $logger->error('New order array length does not match step count', [
                    'project_id' => $project_id,
                    'expected_count' => count( $steps ),
                    'provided_count' => count( $new_order )
                ]);
            }
            return false;
        }

        // Create array mapping old position to step data
        $steps_by_position = [];
        foreach ( $steps as $step ) {
            $steps_by_position[$step['position']] = $step;
        }

        // Reorder steps according to new order
        $reordered_steps = [];
        foreach ( $new_order as $new_pos => $old_pos ) {
            if ( ! isset( $steps_by_position[$old_pos] ) ) {
                $logger = apply_filters('dm_get_service', null, 'logger');
                if ( $logger ) {
                    $logger->error('Invalid old position in reorder array', [
                        'project_id' => $project_id,
                        'old_position' => $old_pos
                    ]);
                }
                return false;
            }
            
            $step = $steps_by_position[$old_pos];
            $step['position'] = $new_pos;
            $reordered_steps[] = $step;
        }

        return $this->update_project_pipeline_steps( $project_id, ['steps' => $reordered_steps], $user_id );
    }

    /**
     * Get available step types from the pipeline step registry.
     *
     * @return array Array of available step types with their configurations.
     */
    public function get_available_step_types(): array {
        $pipeline_step_registry = apply_filters('dm_get_service', null, 'pipeline_step_registry');
        if ( ! $pipeline_step_registry ) {
            return [];
        }

        return $pipeline_step_registry->get_registered_steps();
    }

    /**
     * Validate pipeline configuration structure and content.
     *
     * @param array $steps_config The step configuration array to validate.
     * @return array Array with 'valid' boolean and 'errors' array.
     */
    public function validate_pipeline_configuration( array $steps_config ): array {
        $errors = [];

        // Check if steps array exists
        if ( ! isset( $steps_config['steps'] ) ) {
            $errors[] = "Configuration must contain a 'steps' array";
            return ['valid' => false, 'errors' => $errors];
        }

        $steps = $steps_config['steps'];
        if ( ! is_array( $steps ) ) {
            $errors[] = "Steps must be an array";
            return ['valid' => false, 'errors' => $errors];
        }

        // Get available step types for validation
        $available_types = $this->get_available_step_types();
        $positions_used = [];

        foreach ( $steps as $index => $step ) {
            if ( ! is_array( $step ) ) {
                $errors[] = "Step at index {$index} must be an array";
                continue;
            }

            // Validate required fields
            $required_fields = ['type', 'slug', 'config', 'position'];
            foreach ( $required_fields as $field ) {
                if ( ! isset( $step[$field] ) ) {
                    $errors[] = "Step at index {$index} is missing required field: {$field}";
                }
            }

            // Validate step type
            if ( isset( $step['type'] ) && ! isset( $available_types[$step['type']] ) ) {
                $errors[] = "Step at index {$index} has invalid type: {$step['type']}";
            }

            // Validate position is unique and numeric
            if ( isset( $step['position'] ) ) {
                $position = $step['position'];
                if ( ! is_numeric( $position ) ) {
                    $errors[] = "Step at index {$index} has non-numeric position: {$position}";
                } else {
                    $position = (int) $position;
                    if ( in_array( $position, $positions_used ) ) {
                        $errors[] = "Position {$position} is used by multiple steps";
                    }
                    $positions_used[] = $position;
                }
            }

            // Validate config is an array
            if ( isset( $step['config'] ) && ! is_array( $step['config'] ) ) {
                $errors[] = "Step at index {$index} config must be an array";
            }

            // Validate slug is a string
            if ( isset( $step['slug'] ) && ! is_string( $step['slug'] ) ) {
                $errors[] = "Step at index {$index} slug must be a string";
            }
        }

        // Validate pipeline has required step types (at minimum: input and output)
        $step_types = array_column( $steps, 'type' );
        $required_types = ['input', 'output'];
        foreach ( $required_types as $required_type ) {
            if ( ! in_array( $required_type, $step_types ) ) {
                $errors[] = "Pipeline must contain at least one step of type: {$required_type}";
            }
        }

        return [
            'valid' => empty( $errors ),
            'errors' => $errors
        ];
    }

    /**
     * Normalize step positions to ensure sequential ordering (0, 1, 2, ...).
     *
     * @param array $steps_config The step configuration array to normalize.
     * @return array Normalized configuration with sequential positions.
     */
    private function normalize_step_positions( array $steps_config ): array {
        if ( ! isset( $steps_config['steps'] ) || ! is_array( $steps_config['steps'] ) ) {
            return $steps_config;
        }

        $steps = $steps_config['steps'];
        
        // Sort by current position
        usort( $steps, function( $a, $b ) {
            $pos_a = isset( $a['position'] ) ? (int) $a['position'] : 0;
            $pos_b = isset( $b['position'] ) ? (int) $b['position'] : 0;
            return $pos_a <=> $pos_b;
        });

        // Reassign sequential positions
        foreach ( $steps as $index => &$step ) {
            $step['position'] = $index;
        }

        $steps_config['steps'] = $steps;
        return $steps_config;
    }

    /**
     * Create default pipeline configuration for a project based on available step types.
     *
     * @param int $project_id The ID of the project.
     * @param int $user_id    The ID of the user (for ownership verification).
     * @return bool True on success, false on failure.
     */
    public function create_default_pipeline_configuration( int $project_id, int $user_id ): bool {
        $available_types = $this->get_available_step_types();
        
        // Create a basic 3-step pipeline: input → ai → output
        $default_steps = [];
        $position = 0;

        // Add input step if available
        foreach ( $available_types as $type_name => $type_config ) {
            if ( strpos( $type_name, 'input' ) !== false ) {
                $default_steps[] = [
                    'type' => $type_name,
                    'slug' => $type_name,
                    'config' => [],
                    'position' => $position++
                ];
                break;
            }
        }

        // Add AI processing step if available
        foreach ( $available_types as $type_name => $type_config ) {
            if ( strpos( $type_name, 'process' ) !== false || strpos( $type_name, 'ai' ) !== false ) {
                $default_steps[] = [
                    'type' => $type_name,
                    'slug' => $type_name,
                    'config' => [],
                    'position' => $position++
                ];
                break;
            }
        }

        // Add output step if available
        foreach ( $available_types as $type_name => $type_config ) {
            if ( strpos( $type_name, 'output' ) !== false ) {
                $default_steps[] = [
                    'type' => $type_name,
                    'slug' => $type_name,
                    'config' => [],
                    'position' => $position++
                ];
                break;
            }
        }

        $default_config = [
            'steps' => $default_steps
        ];

        return $this->update_project_pipeline_steps( $project_id, $default_config, $user_id );
    }
}