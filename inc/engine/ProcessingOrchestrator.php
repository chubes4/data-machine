<?php
/**
 * Orchestrates the extensible multi-step data processing pipeline.
 *
 * Uses pure position-based execution with project pipeline configuration as the single source of truth.
 * Supports drag-and-drop pipeline ordering with multiple steps of the same type.
 *
 * POSITION-BASED EXECUTION:
 * - Linear pipeline execution using project pipeline configuration
 * - User-controlled step ordering through visual pipeline builder
 * - Multiple steps of the same type supported (multiple AI steps, etc.)
 *
 * STEP REQUIREMENTS:
 * - Parameter-less constructor only
 * - Single execute(int $job_id): bool method required
 * - No interface or inheritance requirements
 * - Pure filter-based service access
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes
 * @since      0.6.0
 */

namespace DataMachine\Engine;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ProcessingOrchestrator {

	/**
	 * Constructor with filter-based service access.
	 * Uses pure filter-based architecture for dependency resolution.
	 */
	public function __construct() {
		// Parameter-less constructor - all services accessed via filters
	}
	/**
	 * Execute a pipeline step at the specified position using pure position-based execution.
	 * 
	 * Uses project pipeline configuration as the single source of truth, enabling 
	 * user-controlled pipeline order through drag-and-drop interface. Multiple steps 
	 * of the same type can exist in a single pipeline.
	 *
	 * @param int $step_position The step position in pipeline (0-based).
	 * @param int $job_id The job ID.
	 * @return bool True on success, false on failure.
	 */
	public function execute_step( int $step_position, int $job_id ): bool {
		try {
			// Get services via filters
			$logger = apply_filters('dm_get_logger', null);
			$db_jobs = apply_filters('dm_get_db_jobs', null);
			$db_projects = apply_filters('dm_get_db_projects', null);
			
			if (!$logger || !$db_jobs) {
				return false;
			}
			
			// Get job details to determine project_id
			$job = $db_jobs->get_job( $job_id );
			if ( ! $job ) {
				$logger->error( 'Job not found', [
					'job_id' => $job_id,
					'step_position' => $step_position
				] );
				return false;
			}
			
			// Get project_id for pipeline configuration
			$project_id = $this->get_project_id_from_job( $job );
			if ( ! $project_id ) {
				$logger->error( 'Project ID not found for job', [
					'job_id' => $job_id,
					'module_id' => $job->module_id,
					'step_position' => $step_position
				] );
				return false;
			}
			
			$logger->info( 'Executing pipeline step', [
				'job_id' => $job_id,
				'project_id' => $project_id,
				'step_position' => $step_position
			] );
			
			// Get project pipeline configuration as single source of truth
			$pipeline_config = $this->get_project_pipeline_steps( $project_id, $db_projects, $logger );
			if ( empty( $pipeline_config ) ) {
				$logger->error( 'Project pipeline configuration not found or invalid', [
					'project_id' => $project_id,
					'job_id' => $job_id
				] );
				return false;
			}
			
			// Find step configuration by position
			$step_config = $this->find_step_config_by_position( $step_position, $pipeline_config, $logger );
			if ( ! $step_config ) {
				$logger->error( 'Pipeline step configuration not found at position', [
					'step_position' => $step_position,
					'job_id' => $job_id,
					'project_id' => $project_id
				] );
				return false;
			}
			
			$next_step_position = $this->get_next_step_position( $step_position, $pipeline_config );

			$step_class = $step_config['class'];
			if ( ! class_exists( $step_class ) ) {
				$logger->error( 'Pipeline step class not found', [
					'step_position' => $step_position,
					'class' => $step_class,
					'job_id' => $job_id
				] );
				return false;
			}

			// Update job's current step before execution (use step type for backward compatibility)
			$step_type = $step_config['type'] ?? 'unknown';
			$db_jobs->update_current_step_name( $job_id, $step_type );

			// Create step instance (parameter-less constructor)
			$step_instance = new $step_class();
			
			// Enforce standard execute method requirement
			if ( ! method_exists( $step_instance, 'execute' ) ) {
				$logger->error( 'Pipeline step must have execute method', [
					'step_position' => $step_position,
					'class' => $step_class,
					'job_id' => $job_id,
					'available_methods' => get_class_methods( $step_instance )
				] );
				return false;
			}

			// Execute step using standard signature
			$result = $step_instance->execute( $job_id );

			// Schedule next step using position-based routing
			if ( $result ) {
				if ( $next_step_position !== null ) {
					$next_hook = 'dm_step_position_' . $next_step_position . '_job_event';
					
					if ( ! $this->schedule_next_step( $job_id, $next_hook ) ) {
						$logger->error( 'Failed to schedule next step', [
							'current_position' => $step_position,
							'next_position' => $next_step_position,
							'job_id' => $job_id
						] );
						return false;
					}
					
					$logger->info( 'Scheduled next step', [
						'current_position' => $step_position,
						'next_position' => $next_step_position,
						'job_id' => $job_id
					] );
				} else {
					// Final step completed successfully
					$logger->info( 'Pipeline completed successfully', [
						'final_position' => $step_position,
						'job_id' => $job_id
					] );
				}
			}

			return $result;

		} catch ( \Exception $e ) {
			$logger = apply_filters('dm_get_logger', null);
			if ($logger) {
				$logger->error( 'Exception in pipeline step execution', [
					'step_position' => $step_position,
					'job_id' => $job_id,
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				] );
			}
			return false;
		}
	}


	/**
	 * Schedule the next step in the async pipeline.
	 *
	 * @param int    $job_id The job ID.
	 * @param string $hook   The hook name for the next step.
	 * @return bool True on success, false on failure.
	 */
	private function schedule_next_step( int $job_id, string $hook ): bool {
		$logger = apply_filters('dm_get_logger', null);
		
		$action_id = as_schedule_single_action(
			time() + 1, // Schedule immediately
			$hook,
			['job_id' => $job_id],
			\DataMachine\Core\Constants::ACTION_GROUP
		);

		$success = $action_id !== false && $action_id !== 0;
		
		if ( ! $success && $logger ) {
			$logger->error( 'Failed to schedule next step', [
				'job_id' => $job_id,
				'hook' => $hook,
				'action_id' => $action_id
			] );
		}
		
		return $success;
	}

	/**
	 * Get project_id from job by querying the modules table.
	 *
	 * @param object $job The job object.
	 * @return int|null The project ID or null if not found.
	 */
	private function get_project_id_from_job( object $job ): ?int {
		global $wpdb;
		
		if ( empty( $job->module_id ) ) {
			return null;
		}
		
		$project_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT project_id FROM {$wpdb->prefix}dm_modules WHERE module_id = %d",
			$job->module_id
		) );
		
		return $project_id ? (int) $project_id : null;
	}

	/**
	 * Get project pipeline steps configuration as single source of truth.
	 *
	 * @param int $project_id The project ID.
	 * @param object|null $db_projects The database projects service.
	 * @param object $logger The logger instance.
	 * @return array Pipeline steps configuration array.
	 */
	private function get_project_pipeline_steps( int $project_id, ?object $db_projects, object $logger ): array {
		if ( ! $db_projects ) {
			$logger->error( 'Database projects service not available', [
				'project_id' => $project_id
			] );
			return [];
		}
		
		try {
			$pipeline_config = $db_projects->get_project_pipeline_configuration( $project_id );
			$config = isset($pipeline_config['steps']) ? $pipeline_config['steps'] : [];
			
			// Basic validation - ensure we have steps
			$validation = ['valid' => !empty($config), 'errors' => empty($config) ? ['No pipeline steps configured'] : []];
			if ( ! $validation['valid'] ) {
				$logger->error( 'Invalid project pipeline configuration', [
					'project_id' => $project_id,
					'errors' => $validation['errors']
				] );
				return [];
			}
			
			return $config['steps'] ?? [];
		} catch ( \Exception $e ) {
			$logger->error( 'Error loading project pipeline configuration', [
				'project_id' => $project_id,
				'error' => $e->getMessage()
			] );
			return [];
		}
	}

	/**
	 * Find step configuration by position in project configuration.
	 *
	 * @param int $step_position The step position to find.
	 * @param array $pipeline_steps Project pipeline steps array.
	 * @param object $logger The logger instance.
	 * @return array|null Step configuration or null if not found.
	 */
	private function find_step_config_by_position( int $step_position, array $pipeline_steps, object $logger ): ?array {
		// Get global registry for class mapping
		$pipeline_step_types = apply_filters( 'dm_register_pipeline_step_types', [] );
		
		foreach ( $pipeline_steps as $step ) {
			if ( isset( $step['position'] ) && (int) $step['position'] === $step_position ) {
				// Map project step config to expected format
				return $this->map_project_step_to_pipeline_format( $step, $pipeline_step_types, $logger );
			}
		}
		
		return null;
	}

	/**
	 * Map project step configuration to pipeline format expected by orchestrator.
	 *
	 * @param array $project_step Project-specific step configuration.
	 * @param array $pipeline_step_types Global pipeline step types registry for class mapping.
	 * @param object $logger The logger instance.
	 * @return array|null Mapped step configuration or null if mapping fails.
	 */
	private function map_project_step_to_pipeline_format( array $project_step, array $pipeline_step_types, object $logger ): ?array {
		$step_type = $project_step['type'] ?? '';
		
		// Get the class from global registry based on step type
		if ( isset( $pipeline_step_types[ $step_type ] ) ) {
			return [
				'class' => $pipeline_step_types[ $step_type ]['class'],
				'type' => $step_type,
				'handler' => $project_step['slug'] ?? '',
				'config' => $project_step['config'] ?? [],
				'position' => $project_step['position'] ?? 0
			];
		}
		
		$logger->warning( 'Unable to map project step to pipeline format - step type not found in global registry', [
			'step_type' => $step_type,
			'project_step' => $project_step,
			'available_types' => array_keys( $pipeline_step_types )
		] );
		
		return null;
	}

	/**
	 * Get the next step position from project configuration.
	 *
	 * @param int $current_position The current step position.
	 * @param array $pipeline_steps Project pipeline steps array.
	 * @return int|null The next step position or null if this is the final step.
	 */
	private function get_next_step_position( int $current_position, array $pipeline_steps ): ?int {
		$next_position = $current_position + 1;
		
		// Check if next position exists in pipeline
		foreach ( $pipeline_steps as $step ) {
			if ( isset( $step['position'] ) && (int) $step['position'] === $next_position ) {
				return $next_position;
			}
		}
		
		return null; // No next step - pipeline complete
	}


} // End class