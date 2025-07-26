<?php
/**
 * Orchestrates the extensible multi-step data processing pipeline.
 *
 * Uses WordPress filters to discover and execute pipeline steps dynamically,
 * enabling third-party plugins to add, remove, or modify pipeline steps
 * without touching core code.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes
 * @since      0.6.0
 */

namespace DataMachine\Engine;

use DataMachine\Engine\Interfaces\PipelineStepInterface;

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
	 * Execute a pipeline step with closed-door philosophy.
	 * 
	 * Each step operates on DataPacket from previous step only,
	 * with no backward looking or complex data retrieval.
	 * Uses project pipeline configuration as single source of truth.
	 *
	 * @param string $step_name The step name (e.g., 'input', 'ai', 'output').
	 * @param int $job_id The job ID.
	 * @return bool True on success, false on failure.
	 */
	public function execute_step( string $step_name, int $job_id ): bool {
		try {
			// Get services via filters
			$logger = apply_filters('dm_get_service', null, 'logger');
			$db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
			$project_pipeline_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
			
			if (!$logger || !$db_jobs) {
				return false;
			}
			
			// Get job details to determine project_id
			$job = $db_jobs->get_job( $job_id );
			if ( ! $job ) {
				$logger->error( 'Job not found', [
					'job_id' => $job_id,
					'step_name' => $step_name
				] );
				return false;
			}
			
			// Get project_id from module_id
			$project_id = $this->get_project_id_from_job( $job );
			if ( ! $project_id ) {
				$logger->error( 'Project ID not found for job', [
					'job_id' => $job_id,
					'module_id' => $job->module_id,
					'step_name' => $step_name
				] );
				return false;
			}
			
			// Get project pipeline configuration as single source of truth
			$pipeline_config = $this->get_project_pipeline_config( $project_id, $project_pipeline_service, $logger );
			if ( empty( $pipeline_config ) ) {
				$logger->error( 'Project pipeline configuration not found or invalid', [
					'project_id' => $project_id,
					'job_id' => $job_id
				] );
				return false;
			}
			
			// Find step configuration in project config
			$step_config = $this->find_step_config_in_project( $step_name, $pipeline_config, $logger );
			if ( ! $step_config ) {
				$logger->error( 'Pipeline step configuration not found in project config', [
					'step_name' => $step_name,
					'job_id' => $job_id,
					'project_id' => $project_id
				] );
				return false;
			}

			$step_class = $step_config['class'];
			if ( ! class_exists( $step_class ) ) {
				$logger->error( 'Pipeline step class not found', [
					'step_name' => $step_name,
					'class' => $step_class,
					'job_id' => $job_id
				] );
				return false;
			}

			// Update job's current step before execution
			$db_jobs->update_current_step_name( $job_id, $step_name );

			// Create step instance (parameter-less constructor)
			$step_instance = $this->create_step_instance( $step_class );
			if ( ! $step_instance instanceof PipelineStepInterface ) {
				$logger->error( 'Pipeline step must implement PipelineStepInterface', [
					'step_name' => $step_name,
					'class' => $step_class,
					'job_id' => $job_id
				] );
				return false;
			}

			// Execute step (closed-door: step handles own data flow)
			$result = $step_instance->execute( $job_id );

			// Schedule next step using project configuration
			if ( $result ) {
				$next_step = $this->get_next_step_from_project( $step_name, $pipeline_config );
				if ( $next_step ) {
					$next_hook = 'dm_' . $next_step . '_job_event';
					
					if ( ! $this->schedule_next_step( $job_id, $next_hook ) ) {
						$logger->error( 'Failed to schedule next step', [
							'current_step' => $step_name,
							'next_step' => $next_step,
							'job_id' => $job_id,
							'project_id' => $project_id
						] );
						return false;
					}
				} else {
					// Final step completed successfully
					$logger->info( 'Pipeline completed successfully', [
						'final_step' => $step_name,
						'job_id' => $job_id,
						'project_id' => $project_id
					] );
				}
			}

			return $result;

		} catch ( \Exception $e ) {
			$logger = apply_filters('dm_get_service', null, 'logger');
			if ($logger) {
				$logger->error( 'Exception in pipeline step execution', [
					'step_name' => $step_name,
					'job_id' => $job_id,
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				] );
			}
			return false;
		}
	}

	/**
	 * Create a pipeline step instance using simple instantiation.
	 *
	 * @param string $step_class The step class name.
	 * @return object The step instance.
	 */
	private function create_step_instance( string $step_class ): object {
		// Simple instantiation - services accessed via filters within the step
		return new $step_class();
	}

	/**
	 * Schedule the next step in the async pipeline.
	 *
	 * @param int    $job_id The job ID.
	 * @param string $hook   The hook name for the next step.
	 * @return bool True on success, false on failure.
	 */
	private function schedule_next_step( int $job_id, string $hook ): bool {
		$action_scheduler = apply_filters('dm_get_service', null, 'action_scheduler');
		$logger = apply_filters('dm_get_service', null, 'logger');
		
		if (!$action_scheduler) {
			if ($logger) {
				$logger->error( 'Action scheduler service not available', [
					'job_id' => $job_id,
					'hook' => $hook
				] );
			}
			return false;
		}
		
		$action_id = $action_scheduler->schedule_single_job(
			$hook,
			['job_id' => $job_id],
			time() + 1 // Schedule immediately
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
	 * Get project pipeline configuration as single source of truth.
	 *
	 * @param int $project_id The project ID.
	 * @param object|null $project_pipeline_service The pipeline config service.
	 * @param object $logger The logger instance.
	 * @return array Pipeline configuration array.
	 */
	private function get_project_pipeline_config( int $project_id, ?object $project_pipeline_service, object $logger ): array {
		if ( ! $project_pipeline_service ) {
			$logger->error( 'Project pipeline config service not available', [
				'project_id' => $project_id
			] );
			return [];
		}
		
		try {
			$config = $project_pipeline_service->get_project_pipeline_config( $project_id );
			
			// Validate pipeline configuration
			$validation = $project_pipeline_service->validate_pipeline_config( $config );
			if ( ! $validation['valid'] ) {
				$logger->error( 'Invalid project pipeline configuration', [
					'project_id' => $project_id,
					'errors' => $validation['errors']
				] );
				return [];
			}
			
			return $config;
		} catch ( \Exception $e ) {
			$logger->error( 'Error loading project pipeline configuration', [
				'project_id' => $project_id,
				'error' => $e->getMessage()
			] );
			return [];
		}
	}

	/**
	 * Find step configuration from project configuration only.
	 *
	 * @param string $step_name The step name to find.
	 * @param array $pipeline_config Project pipeline configuration.
	 * @param object $logger The logger instance.
	 * @return array|null Step configuration or null if not found.
	 */
	private function find_step_config_in_project( string $step_name, array $pipeline_config, object $logger ): ?array {
		// Get global registry for class mapping
		$pipeline_steps = apply_filters( 'dm_register_pipeline_steps', [] );
		
		foreach ( $pipeline_config as $step ) {
			if ( isset( $step['type'] ) && $step['type'] === $step_name ) {
				// Map project step config to expected format
				return $this->map_project_step_to_pipeline_format( $step, $pipeline_steps, $logger );
			}
		}
		
		return null;
	}

	/**
	 * Map project step configuration to pipeline format expected by orchestrator.
	 *
	 * @param array $project_step Project-specific step configuration.
	 * @param array $pipeline_steps Global pipeline steps registry for class mapping.
	 * @param object $logger The logger instance.
	 * @return array|null Mapped step configuration or null if mapping fails.
	 */
	private function map_project_step_to_pipeline_format( array $project_step, array $pipeline_steps, object $logger ): ?array {
		$step_type = $project_step['type'] ?? '';
		
		// Get the class from global registry based on step type
		if ( isset( $pipeline_steps[ $step_type ] ) ) {
			return [
				'class' => $pipeline_steps[ $step_type ]['class'],
				'handler' => $project_step['handler'] ?? '',
				'config' => $project_step['config'] ?? []
			];
		}
		
		$logger->warning( 'Unable to map project step to pipeline format - step type not found in global registry', [
			'step_type' => $step_type,
			'project_step' => $project_step,
			'available_types' => array_keys( $pipeline_steps )
		] );
		
		return null;
	}

	/**
	 * Get the next step from project configuration only.
	 *
	 * @param string $current_step The current step name.
	 * @param array $pipeline_config Project pipeline configuration.
	 * @return string|null The next step name or null if this is the final step.
	 */
	private function get_next_step_from_project( string $current_step, array $pipeline_config ): ?string {
		$current_order = null;
		
		// Find current step's order
		foreach ( $pipeline_config as $step ) {
			if ( isset( $step['type'] ) && $step['type'] === $current_step ) {
				$current_order = $step['order'] ?? null;
				break;
			}
		}
		
		if ( $current_order === null ) {
			return null;
		}
		
		// Find next step by order
		$next_order = $current_order + 1;
		foreach ( $pipeline_config as $step ) {
			if ( isset( $step['order'] ) && $step['order'] === $next_order ) {
				return $step['type'] ?? null;
			}
		}
		
		return null; // No next step found - this is the final step
	}

} // End class