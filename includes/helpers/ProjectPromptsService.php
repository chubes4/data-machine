<?php
/**
 * Project Prompts Service
 *
 * Manages project-level stepwise prompts for the extensible pipeline architecture.
 * This service provides methods to store, retrieve, and manage prompts at the project level,
 * allowing all modules within a project to inherit the same step-specific prompts.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/helpers
 * @since      NEXT_VERSION
 */

namespace DataMachine\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ProjectPromptsService {

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
     * Get project step prompts as an associative array.
     *
     * @param int $project_id The ID of the project.
     * @return array Array of step prompts structured as:
     *               {
     *                 "process": {"process_data_prompt": "..."},
     *                 "factcheck": {"fact_check_prompt": "..."},
     *                 "finalize": {"finalize_response_prompt": "..."}
     *               }
     */
    public function get_project_step_prompts( int $project_id ): array {
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $project = $db_projects->get_project( $project_id );
        
        if ( ! $project || empty( $project->step_prompts ) ) {
            return [];
        }

        $prompts = json_decode( $project->step_prompts, true );
        return is_array( $prompts ) ? $prompts : [];
    }

    /**
     * Update project step prompts.
     *
     * @param int   $project_id   The ID of the project.
     * @param array $step_prompts Array of step prompts to store.
     * @return bool True on success, false on failure.
     */
    public function update_project_step_prompts( int $project_id, array $step_prompts ): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dm_projects';
        $json_data = wp_json_encode( $step_prompts );
        
        $updated = $wpdb->update(
            $table_name,
            ['step_prompts' => $json_data],
            ['project_id' => $project_id],
            ['%s'],
            ['%d']
        );

        if ( $updated === false ) {
            $logger = apply_filters('dm_get_service', null, 'logger');
            if ( $logger ) {
                $logger->error('Failed to update project step prompts', [
                    'project_id' => $project_id,
                    'db_error' => $wpdb->last_error
                ]);
            }
            return false;
        }

        return true;
    }

    /**
     * Get a specific prompt for a specific step from a project.
     *
     * @param int    $project_id  The ID of the project.
     * @param string $step_name   The name of the pipeline step (e.g., 'process', 'factcheck').
     * @param string $prompt_field The name of the prompt field (e.g., 'process_data_prompt').
     * @return string The prompt content, or empty string if not found.
     */
    public function get_step_prompt( int $project_id, string $step_name, string $prompt_field ): string {
        $step_prompts = $this->get_project_step_prompts( $project_id );
        
        if ( isset( $step_prompts[$step_name][$prompt_field] ) ) {
            return (string) $step_prompts[$step_name][$prompt_field];
        }
        
        return '';
    }

    /**
     * Set a specific prompt for a specific step in a project.
     *
     * @param int    $project_id   The ID of the project.
     * @param string $step_name    The name of the pipeline step.
     * @param string $prompt_field The name of the prompt field.
     * @param string $prompt_value The prompt content.
     * @return bool True on success, false on failure.
     */
    public function set_step_prompt( int $project_id, string $step_name, string $prompt_field, string $prompt_value ): bool {
        $step_prompts = $this->get_project_step_prompts( $project_id );
        
        // Initialize step array if it doesn't exist
        if ( ! isset( $step_prompts[$step_name] ) ) {
            $step_prompts[$step_name] = [];
        }
        
        // Set the specific prompt field
        $step_prompts[$step_name][$prompt_field] = $prompt_value;
        
        return $this->update_project_step_prompts( $project_id, $step_prompts );
    }

    /**
     * Get all prompt fields for a project organized by pipeline steps.
     *
     * @param int $project_id The ID of the project.
     * @return array Array of prompt fields organized by step, with step metadata:
     *               {
     *                 "process": {
     *                   "step_config": {...},
     *                   "prompts": {"process_data_prompt": "..."}
     *                 }
     *               }
     */
    public function get_project_prompts_with_step_info( int $project_id ): array {
        $pipeline_step_registry = apply_filters('dm_get_service', null, 'pipeline_step_registry');
        $step_prompts = $this->get_project_step_prompts( $project_id );
        $prompt_steps = $pipeline_step_registry->get_prompt_steps_in_order();
        
        $result = [];
        
        foreach ( $prompt_steps as $step_name => $step_data ) {
            $result[$step_name] = [
                'step_config' => $step_data['step_config'] ?? [],
                'prompt_fields' => $step_data['prompt_fields'] ?? [],
                'prompts' => $step_prompts[$step_name] ?? []
            ];
        }
        
        return $result;
    }

    /**
     * Validate project step prompts structure.
     *
     * @param array $step_prompts The step prompts array to validate.
     * @return array Array with 'valid' boolean and 'errors' array.
     */
    public function validate_step_prompts( array $step_prompts ): array {
        $errors = [];
        
        // Get valid pipeline steps
        $pipeline_step_registry = apply_filters('dm_get_service', null, 'pipeline_step_registry');
        $valid_steps = $pipeline_step_registry->get_registered_steps();
        
        foreach ( $step_prompts as $step_name => $prompts ) {
            // Check if step is valid
            if ( ! isset( $valid_steps[$step_name] ) ) {
                $errors[] = "Invalid pipeline step: {$step_name}";
                continue;
            }
            
            // Check if prompts is an array
            if ( ! is_array( $prompts ) ) {
                $errors[] = "Prompts for step '{$step_name}' must be an array";
                continue;
            }
            
            // Get expected prompt fields for this step
            $expected_fields = $pipeline_step_registry->get_step_prompt_fields( $step_name );
            
            foreach ( $prompts as $field_name => $field_value ) {
                // Check if field is expected for this step
                if ( ! isset( $expected_fields[$field_name] ) ) {
                    $errors[] = "Unexpected prompt field '{$field_name}' for step '{$step_name}'";
                }
                
                // Check if required fields are present and not empty
                if ( isset( $expected_fields[$field_name]['required'] ) && 
                     $expected_fields[$field_name]['required'] && 
                     empty( $field_value ) ) {
                    $errors[] = "Required prompt field '{$field_name}' is empty for step '{$step_name}'";
                }
            }
        }
        
        return [
            'valid' => empty( $errors ),
            'errors' => $errors
        ];
    }

    /**
     * Create default step prompts for a project based on current pipeline configuration.
     *
     * @param int $project_id The ID of the project.
     * @return bool True on success, false on failure.
     */
    public function create_default_step_prompts( int $project_id ): bool {
        $pipeline_step_registry = apply_filters('dm_get_service', null, 'pipeline_step_registry');
        $prompt_steps = $pipeline_step_registry->get_prompt_steps_in_order();
        
        $default_prompts = [];
        
        foreach ( $prompt_steps as $step_name => $step_data ) {
            $prompt_fields = $step_data['prompt_fields'] ?? [];
            $step_prompts = [];
            
            foreach ( $prompt_fields as $field_name => $field_config ) {
                // Set default values based on field configuration
                $step_prompts[$field_name] = $field_config['default'] ?? '';
            }
            
            if ( ! empty( $step_prompts ) ) {
                $default_prompts[$step_name] = $step_prompts;
            }
        }
        
        return $this->update_project_step_prompts( $project_id, $default_prompts );
    }
}