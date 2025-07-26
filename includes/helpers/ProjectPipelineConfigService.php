<?php
/**
 * Project Pipeline Configuration Service
 *
 * Manages project-level pipeline configurations for the dynamic step system.
 * This service handles creation, validation, and management of custom pipeline
 * configurations, allowing each project to define its own processing workflow.
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
     * Get project pipeline configuration.
     *
     * @param int $project_id The ID of the project.
     * @return array Array containing pipeline configuration or default if not set.
     */
    public function get_project_pipeline_config( int $project_id ): array {
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $config = $db_projects->get_project_pipeline_configuration( $project_id );
        
        // If no configuration exists, return default
        if ( empty( $config ) ) {
            return $this->get_default_pipeline_config();
        }
        
        return $config;
    }

    /**
     * Update project pipeline configuration.
     *
     * @param int   $project_id      The ID of the project.
     * @param array $pipeline_config The pipeline configuration to set.
     * @param int   $user_id         The user ID for ownership verification.
     * @return bool True on success, false on failure.
     */
    public function update_project_pipeline_config( int $project_id, array $pipeline_config, int $user_id ): bool {
        // Validate configuration first
        $validation = $this->validate_pipeline_config( $pipeline_config );
        if ( ! $validation['valid'] ) {
            return false;
        }

        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        return $db_projects->update_project_pipeline_configuration( $project_id, $pipeline_config, $user_id );
    }

    /**
     * Get the default pipeline configuration.
     *
     * @return array Default 5-step pipeline configuration.
     */
    public function get_default_pipeline_config(): array {
        return [
            'steps' => ['input', 'process', 'factcheck', 'finalize', 'output'],
            'ai_steps' => ['process', 'factcheck', 'finalize'],
            'step_configs' => [
                'input' => [
                    'class' => 'DataMachine\\Engine\\Steps\\InputStep',
                    'required' => true,
                    'type' => 'input'
                ],
                'process' => [
                    'class' => 'DataMachine\\Engine\\Steps\\ProcessStep',
                    'required' => true,
                    'type' => 'ai'
                ],
                'factcheck' => [
                    'class' => 'DataMachine\\Engine\\Steps\\FactCheckStep',
                    'required' => false,
                    'type' => 'ai'
                ],
                'finalize' => [
                    'class' => 'DataMachine\\Engine\\Steps\\FinalizeStep',
                    'required' => true,
                    'type' => 'ai'
                ],
                'output' => [
                    'class' => 'DataMachine\\Engine\\Steps\\OutputStep',
                    'required' => true,
                    'type' => 'output'
                ]
            ]
        ];
    }

    /**
     * Get available step types for pipeline building.
     *
     * @return array Array of available step types with their configurations.
     */
    public function get_available_step_types(): array {
        // Get globally registered pipeline steps
        $pipeline_registry = apply_filters('dm_get_service', null, 'pipeline_step_registry');
        $registered_steps = $pipeline_registry->get_registered_steps();
        
        $available_types = [];
        
        foreach ( $registered_steps as $step_name => $step_config ) {
            $available_types[$step_name] = [
                'class' => $step_config['class'] ?? '',
                'label' => $this->get_step_display_name( $step_name ),
                'type' => $this->get_step_type( $step_name ),
                'description' => $this->get_step_description( $step_name )
            ];
        }
        
        return $available_types;
    }

    /**
     * Validate a pipeline configuration.
     *
     * @param array $config The pipeline configuration to validate.
     * @return array Array with 'valid' boolean and 'errors' array.
     */
    public function validate_pipeline_config( array $config ): array {
        $errors = [];
        
        // Check required keys
        $required_keys = ['steps', 'ai_steps', 'step_configs'];
        foreach ( $required_keys as $key ) {
            if ( ! isset( $config[$key] ) ) {
                $errors[] = "Missing required configuration key: {$key}";
            }
        }
        
        if ( ! empty( $errors ) ) {
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Validate steps array
        if ( ! is_array( $config['steps'] ) || empty( $config['steps'] ) ) {
            $errors[] = "Steps must be a non-empty array";
        }
        
        // Validate ai_steps array
        if ( ! is_array( $config['ai_steps'] ) ) {
            $errors[] = "AI steps must be an array";
        }
        
        // Validate step_configs
        if ( ! is_array( $config['step_configs'] ) ) {
            $errors[] = "Step configs must be an array";
        }
        
        // Check that all steps have configurations
        foreach ( $config['steps'] as $step_name ) {
            if ( ! isset( $config['step_configs'][$step_name] ) ) {
                $errors[] = "Missing configuration for step: {$step_name}";
            }
        }
        
        // Check that ai_steps are subset of steps
        foreach ( $config['ai_steps'] as $ai_step ) {
            if ( ! in_array( $ai_step, $config['steps'] ) ) {
                $errors[] = "AI step '{$ai_step}' not found in steps array";
            }
        }
        
        // Validate step sequence (must have input and output)
        if ( ! in_array( 'input', $config['steps'] ) ) {
            $errors[] = "Pipeline must include an 'input' step";
        }
        
        if ( ! in_array( 'output', $config['steps'] ) ) {
            $errors[] = "Pipeline must include an 'output' step";
        }
        
        // Validate step classes exist
        foreach ( $config['step_configs'] as $step_name => $step_config ) {
            if ( isset( $step_config['class'] ) && ! class_exists( $step_config['class'] ) ) {
                $errors[] = "Step class does not exist: {$step_config['class']} for step '{$step_name}'";
            }
        }
        
        return [
            'valid' => empty( $errors ),
            'errors' => $errors
        ];
    }

    /**
     * Create a custom pipeline configuration.
     *
     * @param array $step_names Array of step names in execution order.
     * @param array $ai_steps   Array of step names that are AI processing steps.
     * @return array Valid pipeline configuration.
     */
    public function create_custom_pipeline_config( array $step_names, array $ai_steps ): array {
        $available_types = $this->get_available_step_types();
        $step_configs = [];
        
        foreach ( $step_names as $step_name ) {
            if ( isset( $available_types[$step_name] ) ) {
                $step_configs[$step_name] = [
                    'class' => $available_types[$step_name]['class'],
                    'required' => in_array( $step_name, ['input', 'output'] ), // Input and output always required
                    'type' => $available_types[$step_name]['type']
                ];
            }
        }
        
        return [
            'steps' => $step_names,
            'ai_steps' => array_intersect( $ai_steps, $step_names ), // Only AI steps that exist in steps
            'step_configs' => $step_configs
        ];
    }

    /**
     * Get step sequence for a specific job.
     *
     * @param int $job_id The job ID.
     * @return array Array of step names in execution order.
     */
    public function get_job_step_sequence( int $job_id ): array {
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
        $job = $db_jobs->get_job( $job_id );
        
        if ( ! $job || empty( $job->step_sequence ) ) {
            // Fallback: get from project configuration
            if ( ! empty( $job->module_id ) ) {
                $db_modules = apply_filters('dm_get_service', null, 'db_modules');
                $module = $db_modules->get_module( $job->module_id );
                
                if ( $module && ! empty( $module->project_id ) ) {
                    $config = $this->get_project_pipeline_config( $module->project_id );
                    return $config['steps'] ?? [];
                }
            }
            
            // Ultimate fallback: default pipeline
            $default_config = $this->get_default_pipeline_config();
            return $default_config['steps'];
        }
        
        $sequence = json_decode( $job->step_sequence, true );
        return is_array( $sequence ) ? $sequence : [];
    }

    /**
     * Get display name for a step.
     *
     * @param string $step_name The internal step name.
     * @return string Human-readable step name.
     */
    private function get_step_display_name( string $step_name ): string {
        $display_names = [
            'input' => 'Data Collection',
            'process' => 'AI Processing',
            'factcheck' => 'Fact Checking',
            'finalize' => 'Content Finalization',
            'output' => 'Publishing'
        ];
        
        return $display_names[$step_name] ?? ucfirst( str_replace( '_', ' ', $step_name ) );
    }

    /**
     * Get step type (input, ai, output).
     *
     * @param string $step_name The step name.
     * @return string The step type.
     */
    private function get_step_type( string $step_name ): string {
        if ( $step_name === 'input' ) return 'input';
        if ( $step_name === 'output' ) return 'output';
        return 'ai'; // Default to AI step
    }

    /**
     * Get step description.
     *
     * @param string $step_name The step name.
     * @return string Step description.
     */
    private function get_step_description( string $step_name ): string {
        $descriptions = [
            'input' => 'Collects data from configured sources',
            'process' => 'Processes data using AI models',
            'factcheck' => 'Validates content accuracy using AI',
            'finalize' => 'Finalizes and formats content',
            'output' => 'Publishes content to configured destinations'
        ];
        
        return $descriptions[$step_name] ?? 'Custom processing step';
    }
}