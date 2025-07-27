<?php
/**
 * Orchestrates the extensible multi-step data processing pipeline with flow-based execution.
 *
 * Supports both traditional project-based pipeline execution and new flow-based execution:
 *
 * FLOW-BASED EXECUTION:
 * - Modules can define custom flow paths via flow_config JSON field
 * - Enables flexible step routing, alternative paths, and step skipping
 * - Flow configuration structure:
 *   {
 *     "flow_path": [
 *       {"step_id": "input_1", "type": "input", "handler": "files", "position": 0, "next": "ai_1"},
 *       {"step_id": "ai_1", "type": "ai", "position": 1, "next": "output_1"},
 *       {"step_id": "output_1", "type": "output", "handler": "wordpress", "position": 2, "next": null}
 *     ],
 *     "flow_metadata": {"total_steps": 3, "module_name": "Custom Flow Module"}
 *   }
 *
 * TRADITIONAL EXECUTION:
 * - Linear pipeline execution using project pipeline configuration
 * - Maintains backward compatibility for existing modules
 *
 * PURE CAPABILITY-BASED DETECTION:
 * - No interface or inheritance requirements
 * - External plugins can create completely independent classes
 * - Method detection via method_exists() only
 * - Graceful handling of different method signatures
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes
 * @since      0.6.0
 * @version    NEXT_VERSION Added flow-based execution support
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
	 * Execute a pipeline step with support for both flow-based and traditional execution.
	 * 
	 * Supports two execution modes:
	 * 1. Flow-based: Uses module's flow_config for custom step routing
	 * 2. Traditional: Uses project pipeline configuration for linear execution
	 * 
	 * Each step operates on DataPacket from previous step with backward compatibility.
	 *
	 * @param string $step_name The step name (e.g., 'input', 'ai', 'output').
	 * @param int $job_id The job ID.
	 * @return bool True on success, false on failure.
	 */
	public function execute_step( string $step_name, int $job_id ): bool {
		try {
			// Get services via filters
			$logger = apply_filters('dm_get_logger', null);
			$db_jobs = apply_filters('dm_get_db_jobs', null);
			$project_pipeline_service = apply_filters('dm_get_project_pipeline_config_service', null);
			
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
			
			// Get module details for flow configuration
			$db_modules = apply_filters('dm_get_db_modules', null);
			$module = $db_modules ? $db_modules->get_module( $job->module_id ) : null;
			
			// Check for flow-based execution first
			$flow_config = $this->get_module_flow_config( $module, $logger );
			$step_config = null;
			$next_step_info = null;
			
			if ( $flow_config ) {
				// Flow-based execution
				$logger->info( 'Using flow-based execution', [
					'job_id' => $job_id,
					'module_id' => $job->module_id,
					'step_name' => $step_name
				] );
				
				$step_config = $this->find_step_config_in_flow( $step_name, $flow_config, $logger );
				if ( ! $step_config ) {
					$logger->error( 'Pipeline step configuration not found in flow config', [
						'step_name' => $step_name,
						'job_id' => $job_id,
						'module_id' => $job->module_id
					] );
					return false;
				}
				
				$next_step_info = $this->get_next_step_from_flow( $step_name, $flow_config );
			} else {
				// Traditional project-based execution
				$project_id = $this->get_project_id_from_job( $job );
				if ( ! $project_id ) {
					$logger->error( 'Project ID not found for job', [
						'job_id' => $job_id,
						'module_id' => $job->module_id,
						'step_name' => $step_name
					] );
					return false;
				}
				
				$logger->info( 'Using traditional project-based execution', [
					'job_id' => $job_id,
					'project_id' => $project_id,
					'step_name' => $step_name
				] );
				
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
				
				$next_step_info = $this->get_next_step_from_project( $step_name, $pipeline_config );
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
			
			// Capability-based detection - check for execute method
			if ( ! method_exists( $step_instance, 'execute' ) ) {
				$logger->error( 'Pipeline step must have execute method', [
					'step_name' => $step_name,
					'class' => $step_class,
					'job_id' => $job_id,
					'available_methods' => get_class_methods( $step_instance )
				] );
				return false;
			}

			// Execute step using capability detection
			$result = $this->execute_step_with_capability_detection( $step_instance, $job_id, $logger );

			// Schedule next step using unified routing (flow-based or traditional)
			if ( $result ) {
				if ( $next_step_info ) {
					$next_hook = 'dm_' . $next_step_info . '_job_event';
					
					if ( ! $this->schedule_next_step( $job_id, $next_hook ) ) {
						$logger->error( 'Failed to schedule next step', [
							'current_step' => $step_name,
							'next_step' => $next_step_info,
							'job_id' => $job_id,
							'execution_mode' => $flow_config ? 'flow-based' : 'traditional'
						] );
						return false;
					}
					
					$logger->info( 'Scheduled next step', [
						'current_step' => $step_name,
						'next_step' => $next_step_info,
						'job_id' => $job_id,
						'execution_mode' => $flow_config ? 'flow-based' : 'traditional'
					] );
				} else {
					// Final step completed successfully
					$logger->info( 'Pipeline completed successfully', [
						'final_step' => $step_name,
						'job_id' => $job_id,
						'execution_mode' => $flow_config ? 'flow-based' : 'traditional'
					] );
				}
			}

			return $result;

		} catch ( \Exception $e ) {
			$logger = apply_filters('dm_get_logger', null);
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
	 * Pure capability-based approach - no inheritance requirements.
	 *
	 * @param string $step_class The step class name.
	 * @return object The step instance.
	 */
	private function create_step_instance( string $step_class ): object {
		// Simple instantiation - services accessed via filters within the step
		return new $step_class();
	}

	/**
	 * Execute step using pure capability detection.
	 * Supports different method signatures and graceful degradation.
	 *
	 * @param object $step_instance The step instance.
	 * @param int $job_id The job ID.
	 * @param object $logger The logger instance.
	 * @return bool True on success, false on failure.
	 */
	private function execute_step_with_capability_detection( object $step_instance, int $job_id, object $logger ): bool {
		try {
			// Primary execute method - standard signature
			if ( method_exists( $step_instance, 'execute' ) ) {
				$reflection = new \ReflectionMethod( $step_instance, 'execute' );
				$parameters = $reflection->getParameters();
				
				// Check parameter count and types for flexibility
				if ( count( $parameters ) === 1 ) {
					// Standard signature: execute(int $job_id)
					return $step_instance->execute( $job_id );
				} elseif ( count( $parameters ) === 0 ) {
					// Parameter-less execute method
					$logger->info( 'Using parameter-less execute method', [
						'class' => get_class( $step_instance ),
						'job_id' => $job_id
					] );
					return $step_instance->execute();
				} else {
					// Unexpected signature - try with job_id anyway
					$logger->warning( 'Unexpected execute method signature, attempting with job_id', [
						'class' => get_class( $step_instance ),
						'parameter_count' => count( $parameters ),
						'job_id' => $job_id
					] );
					return $step_instance->execute( $job_id );
				}
			}
			
			// Alternative method names for flexibility
			if ( method_exists( $step_instance, 'run' ) ) {
				$logger->info( 'Using alternative run method', [
					'class' => get_class( $step_instance ),
					'job_id' => $job_id
				] );
				return $step_instance->run( $job_id );
			}
			
			if ( method_exists( $step_instance, 'process' ) ) {
				$logger->info( 'Using alternative process method', [
					'class' => get_class( $step_instance ),
					'job_id' => $job_id
				] );
				return $step_instance->process( $job_id );
			}
			
			$logger->error( 'No suitable execution method found', [
				'class' => get_class( $step_instance ),
				'job_id' => $job_id,
				'available_methods' => get_class_methods( $step_instance )
			] );
			
			return false;
			
		} catch ( \Exception $e ) {
			$logger->error( 'Exception during capability-based execution', [
				'class' => get_class( $step_instance ),
				'job_id' => $job_id,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			
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
		$pipeline_steps = apply_filters( 'dm_register_pipeline_step_types', [] );
		
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

	/**
	 * Get and parse module's flow configuration.
	 *
	 * @param object|null $module The module object from database.
	 * @param object $logger The logger instance.
	 * @return array|null Parsed flow configuration or null if not found/invalid.
	 */
	private function get_module_flow_config( ?object $module, object $logger ): ?array {
		if ( ! $module || empty( $module->flow_config ) ) {
			return null;
		}
		
		try {
			$flow_config = json_decode( $module->flow_config, true );
			
			// Validate flow config structure
			if ( ! $this->validate_flow_config( $flow_config, $logger ) ) {
				$logger->warning( 'Invalid flow configuration structure', [
					'module_id' => $module->module_id ?? null,
					'flow_config' => $module->flow_config
				] );
				return null;
			}
			
			$logger->info( 'Successfully parsed module flow configuration', [
				'module_id' => $module->module_id ?? null,
				'total_steps' => $flow_config['flow_metadata']['total_steps'] ?? 0
			] );
			
			return $flow_config;
		} catch ( \Exception $e ) {
			$logger->error( 'Failed to parse module flow configuration', [
				'module_id' => $module->module_id ?? null,
				'error' => $e->getMessage(),
				'flow_config' => $module->flow_config
			] );
			return null;
		}
	}

	/**
	 * Validate flow configuration structure.
	 *
	 * @param array|null $flow_config The flow configuration to validate.
	 * @param object $logger The logger instance.
	 * @return bool True if valid, false otherwise.
	 */
	private function validate_flow_config( ?array $flow_config, object $logger ): bool {
		if ( ! is_array( $flow_config ) ) {
			$logger->warning( 'Flow config is not an array' );
			return false;
		}
		
		// Check required top-level structure
		if ( ! isset( $flow_config['flow_path'] ) || ! is_array( $flow_config['flow_path'] ) ) {
			$logger->warning( 'Flow config missing or invalid flow_path array' );
			return false;
		}
		
		if ( empty( $flow_config['flow_path'] ) ) {
			$logger->warning( 'Flow config has empty flow_path' );
			return false;
		}
		
		// Validate each step in flow_path
		foreach ( $flow_config['flow_path'] as $index => $step ) {
			if ( ! is_array( $step ) ) {
				$logger->warning( 'Flow step is not an array', ['step_index' => $index] );
				return false;
			}
			
			// Check required step fields
			$required_fields = ['step_id', 'type', 'position'];
			foreach ( $required_fields as $field ) {
				if ( ! isset( $step[ $field ] ) ) {
					$logger->warning( 'Flow step missing required field', [
						'step_index' => $index,
						'missing_field' => $field,
						'step' => $step
					] );
					return false;
				}
			}
		}
		
		return true;
	}

	/**
	 * Find step configuration within flow configuration.
	 *
	 * @param string $step_name The step name/type to find.
	 * @param array $flow_config The flow configuration.
	 * @param object $logger The logger instance.
	 * @return array|null Step configuration or null if not found.
	 */
	private function find_step_config_in_flow( string $step_name, array $flow_config, object $logger ): ?array {
		// Get global registry for class mapping
		$pipeline_steps = apply_filters( 'dm_register_pipeline_step_types', [] );
		
		// Find the step in flow_path by type
		foreach ( $flow_config['flow_path'] as $step ) {
			if ( isset( $step['type'] ) && $step['type'] === $step_name ) {
				return $this->map_flow_step_to_pipeline_format( $step, $pipeline_steps, $logger );
			}
		}
		
		$logger->warning( 'Step not found in flow configuration', [
			'step_name' => $step_name,
			'available_steps' => array_column( $flow_config['flow_path'], 'type' )
		] );
		
		return null;
	}

	/**
	 * Map flow step configuration to pipeline format.
	 *
	 * @param array $flow_step Flow-specific step configuration.
	 * @param array $pipeline_steps Global pipeline steps registry for class mapping.
	 * @param object $logger The logger instance.
	 * @return array|null Mapped step configuration or null if mapping fails.
	 */
	private function map_flow_step_to_pipeline_format( array $flow_step, array $pipeline_steps, object $logger ): ?array {
		$step_type = $flow_step['type'] ?? '';
		
		// Get the class from global registry based on step type
		if ( isset( $pipeline_steps[ $step_type ] ) ) {
			return [
				'class' => $pipeline_steps[ $step_type ]['class'],
				'handler' => $flow_step['handler'] ?? '',
				'config' => $flow_step['config'] ?? [],
				'step_id' => $flow_step['step_id'] ?? '',
				'position' => $flow_step['position'] ?? 0
			];
		}
		
		$logger->warning( 'Unable to map flow step to pipeline format - step type not found in global registry', [
			'step_type' => $step_type,
			'flow_step' => $flow_step,
			'available_types' => array_keys( $pipeline_steps )
		] );
		
		return null;
	}

	/**
	 * Get the next step from flow configuration.
	 *
	 * @param string $current_step The current step name/type.
	 * @param array $flow_config The flow configuration.
	 * @return string|null The next step name/type or null if this is the final step.
	 */
	private function get_next_step_from_flow( string $current_step, array $flow_config ): ?string {
		// Find the current step in flow_path
		foreach ( $flow_config['flow_path'] as $step ) {
			if ( isset( $step['type'] ) && $step['type'] === $current_step ) {
				$next_step_id = $step['next'] ?? null;
				
				// If next is null, this is the final step
				if ( $next_step_id === null ) {
					return null;
				}
				
				// Find the next step by step_id
				foreach ( $flow_config['flow_path'] as $next_step ) {
					if ( isset( $next_step['step_id'] ) && $next_step['step_id'] === $next_step_id ) {
						return $next_step['type'] ?? null;
					}
				}
				
				break; // Current step found but next step not found
			}
		}
		
		return null; // Current step not found or next step not found
	}

} // End class