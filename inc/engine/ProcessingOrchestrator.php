<?php
/**
 * Orchestrates the extensible multi-step data processing pipeline.
 *
 * Uses pure position-based execution with pipeline configuration as the single source of truth.
 * Supports drag-and-drop pipeline ordering with multiple steps of the same type.
 *
 * POSITION-BASED EXECUTION:
 * - Linear pipeline execution using pipeline configuration
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
	 * Static callback method for Action Scheduler direct execution.
	 * Eliminates the need for WordPress hook registration layers.
	 *
	 * @param int $job_id The job ID.
	 * @param int $step_position The step position in pipeline (0-based).
	 * @return bool True on success, false on failure.
	 */
	public static function execute_step_callback( $job_id, $step_position ): bool {
		$orchestrator = apply_filters('dm_get_orchestrator', null);
		if ( ! $orchestrator ) {
			$logger = apply_filters('dm_get_logger', null);
			if ( $logger ) {
				$logger->error( 'Orchestrator service not available for Action Scheduler callback', [
					'job_id' => $job_id,
					'step_position' => $step_position
				] );
			}
			return false;
		}
		
		// Call execute_step with correct parameter order
		return $orchestrator->execute_step( $step_position, $job_id );
	}
	/**
	 * Execute a pipeline step at the specified position using pure position-based execution.
	 * 
	 * Uses pipeline configuration as the single source of truth, enabling 
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
			$all_databases = apply_filters('dm_get_database_services', []);
			$db_jobs = $all_databases['jobs'] ?? null;
			$db_pipelines = $all_databases['pipelines'] ?? null;
			
			if (!$logger || !$db_jobs) {
				return false;
			}
			
			// Get job details to determine pipeline_id and flow_id
			$job = $db_jobs->get_job( $job_id );
			if ( ! $job ) {
				$logger->error( 'Job not found', [
					'job_id' => $job_id,
					'step_position' => $step_position
				] );
				return false;
			}
			
			// Get pipeline_id for pipeline configuration
			$pipeline_id = $this->get_pipeline_id_from_job( $job );
			if ( ! $pipeline_id ) {
				$logger->error( 'Pipeline ID not found for job', [
					'job_id' => $job_id,
					'step_position' => $step_position
				] );
				return false;
			}
			
			// Get flow_id for flow context
			$flow_id = $this->get_flow_id_from_job( $job );
			if ( ! $flow_id ) {
				$logger->error( 'Flow ID not found for job', [
					'job_id' => $job_id,
					'step_position' => $step_position
				] );
				return false;
			}
			
			$logger->debug( 'Executing pipeline step', [
				'job_id' => $job_id,
				'pipeline_id' => $pipeline_id,
				'flow_id' => $flow_id,
				'step_position' => $step_position
			] );
			
			// Get pipeline configuration as single source of truth
			$pipeline_config = $this->get_pipeline_steps( $pipeline_id, $db_pipelines, $logger );
			if ( empty( $pipeline_config ) ) {
				$logger->error( 'Pipeline configuration not found or invalid', [
					'pipeline_id' => $pipeline_id,
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
					'pipeline_id' => $pipeline_id
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

			// Update job's current step before execution
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

			// Always pass full array of DataPackets (most recent first)
			// Steps self-select what they need based on their consume_all_packets flag
			$all_data_packets = $this->get_all_previous_data_packets( $job_id, $step_position, $logger );
			$result = $step_instance->execute( $job_id, $all_data_packets );
			
			$logger->debug( 'Step executed with data packets array', [
				'job_id' => $job_id,
				'step_position' => $step_position,
				'class' => $step_class,
				'packets_count' => count( $all_data_packets )
			] );

			// Schedule next step using direct Action Scheduler execution
			if ( $result ) {
				if ( $next_step_position !== null ) {
					if ( ! $this->schedule_next_step( $job_id, $next_step_position ) ) {
						$logger->error( 'Failed to schedule next step', [
							'current_position' => $step_position,
							'next_position' => $next_step_position,
							'job_id' => $job_id
						] );
						return false;
					}
					
					$logger->debug( 'Scheduled next step', [
						'current_position' => $step_position,
						'next_position' => $next_step_position,
						'job_id' => $job_id
					] );
				} else {
					// Final step completed successfully
					$logger->debug( 'Pipeline completed successfully', [
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
	 * Schedule the next step in the async pipeline using direct Action Scheduler execution.
	 *
	 * @param int $job_id The job ID.
	 * @param int $step_position The next step position.
	 * @return bool True on success, false on failure.
	 */
	private function schedule_next_step( int $job_id, int $step_position ): bool {
		$logger = apply_filters('dm_get_logger', null);
		
		$scheduler = apply_filters('dm_get_action_scheduler', null);
		if (!$scheduler) {
			$logger && $logger->error('ActionScheduler service not available');
			return false;
		}
		
		$action_id = $scheduler->schedule_single_action(
			time() + 1, // Schedule immediately
			'dm_execute_step',
			['job_id' => $job_id, 'step_position' => $step_position],
			\DataMachine\Engine\Constants::ACTION_GROUP
		);

		$success = $action_id !== false && $action_id !== 0;
		
		if ( ! $success && $logger ) {
			$logger->error( 'Failed to schedule next step', [
				'job_id' => $job_id,
				'step_position' => $step_position,
				'action_id' => $action_id
			] );
		}
		
		return $success;
	}

	/**
	 * Get pipeline_id from job.
	 *
	 * @param object $job The job object.
	 * @return int|null The pipeline ID or null if not found.
	 */
	private function get_pipeline_id_from_job( object $job ): ?int {
		$logger = apply_filters('dm_get_logger', null);
		
		if ( ! empty( $job->pipeline_id ) ) {
			$logger && $logger->debug( 'Using pipeline_id from job', [
				'job_id' => $job->job_id ?? 'unknown',
				'pipeline_id' => $job->pipeline_id
			] );
			return (int) $job->pipeline_id;
		}
		
		$logger && $logger->error( 'Job missing pipeline_id', [
			'job_id' => $job->job_id ?? 'unknown',
			'available_fields' => array_keys( (array) $job )
		] );
		return null;
	}

	/**
	 * Get flow_id from job.
	 *
	 * @param object $job The job object.
	 * @return int|null The flow ID or null if not found.
	 */
	private function get_flow_id_from_job( object $job ): ?int {
		$logger = apply_filters('dm_get_logger', null);
		
		if ( ! empty( $job->flow_id ) ) {
			$logger && $logger->debug( 'Using flow_id from job', [
				'job_id' => $job->job_id ?? 'unknown',
				'flow_id' => $job->flow_id
			] );
			return (int) $job->flow_id;
		}
		
		$logger && $logger->error( 'Job missing flow_id', [
			'job_id' => $job->job_id ?? 'unknown',
			'available_fields' => array_keys( (array) $job )
		] );
		return null;
	}

	/**
	 * Get pipeline steps configuration as single source of truth.
	 *
	 * @param int $pipeline_id The pipeline ID.
	 * @param object|null $db_pipelines The database pipelines service.
	 * @param object $logger The logger instance.
	 * @return array Pipeline steps configuration array.
	 */
	private function get_pipeline_steps( int $pipeline_id, ?object $db_pipelines, object $logger ): array {
		if ( ! $db_pipelines ) {
			$logger->error( 'Database pipelines service not available', [
				'pipeline_id' => $pipeline_id
			] );
			return [];
		}
		
		try {
			$pipeline_config = $db_pipelines->get_pipeline_configuration( $pipeline_id );
			
			// Fail fast - no fallbacks
			if ( !isset($pipeline_config['steps']) || empty($pipeline_config['steps']) ) {
				$logger->error( 'Pipeline configuration missing or has no steps', [
					'pipeline_id' => $pipeline_id,
					'config_keys' => array_keys($pipeline_config ?? [])
				] );
				throw new \RuntimeException( "Pipeline {$pipeline_id} has no configured steps - cannot process" );
			}
			
			$config = $pipeline_config['steps'];
			
			// Basic validation - ensure we have steps
			$validation = ['valid' => !empty($config), 'errors' => empty($config) ? ['No pipeline steps configured'] : []];
			if ( ! $validation['valid'] ) {
				$logger->error( 'Invalid pipeline configuration', [
					'pipeline_id' => $pipeline_id,
					'errors' => $validation['errors']
				] );
				return [];
			}
			
			return $config;
		} catch ( \Exception $e ) {
			$logger = apply_filters('dm_get_logger', null);
			$logger && $logger->error( 'Error loading pipeline configuration', [
				'pipeline_id' => $pipeline_id,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'method' => __METHOD__
			] );
			return [];
		}
	}

	/**
	 * Find step configuration by position in pipeline configuration.
	 *
	 * @param int $step_position The step position to find.
	 * @param array $pipeline_steps Pipeline steps array.
	 * @param object $logger The logger instance.
	 * @return array|null Step configuration or null if not found.
	 */
	private function find_step_config_by_position( int $step_position, array $pipeline_steps, object $logger ): ?array {
		foreach ( $pipeline_steps as $step ) {
			if ( isset( $step['position'] ) && (int) $step['position'] === $step_position ) {
				// Map pipeline step config to expected format using parameter-based discovery
				return $this->map_pipeline_step_to_execution_format( $step, $logger );
			}
		}
		
		return null;
	}

	/**
	 * Get step configuration by type using pure discovery mode.
	 *
	 * @param string $step_type The step type to discover ('input', 'output', 'ai', etc.)
	 * @return array|null Step configuration array or null if not found.
	 */
	private function get_step_config_by_type( string $step_type ): ?array {
		// Use pure discovery mode - get all steps and find matching type
		$all_steps = apply_filters('dm_get_steps', []);
		return $all_steps[$step_type] ?? null;
	}

	/**
	 * Map pipeline step configuration to execution format expected by orchestrator.
	 *
	 * @param array $pipeline_step Pipeline-specific step configuration.
	 * @param object $logger The logger instance.
	 * @return array|null Mapped step configuration or null if mapping fails.
	 */
	private function map_pipeline_step_to_execution_format( array $pipeline_step, object $logger ): ?array {
		$step_type = $pipeline_step['type'] ?? '';
		
		// Get step config via parameter-based discovery
		$step_config = $this->get_step_config_by_type( $step_type );
		
		if ( $step_config ) {
			return [
				'class' => $step_config['class'],
				'type' => $step_type,
				'handler' => $pipeline_step['slug'] ?? '',
				'config' => $pipeline_step['config'] ?? [],
				'position' => $pipeline_step['position'] ?? 0
			];
		}
		
		$logger->warning( 'Unable to map pipeline step to execution format - step type not found via parameter-based discovery', [
			'step_type' => $step_type,
			'pipeline_step' => $pipeline_step
		] );
		
		return null;
	}

	/**
	 * Get the next step position from pipeline configuration.
	 *
	 * @param int $current_position The current step position.
	 * @param array $pipeline_steps Pipeline steps array.
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



	/**
	 * Get all previous DataPackets for steps that consume all packets.
	 *
	 * @param int $job_id The job ID.
	 * @param int $step_position The current step position.
	 * @param object $logger The logger instance.
	 * @return array Array of DataPacket objects from all previous steps.
	 */
	private function get_all_previous_data_packets( int $job_id, int $step_position, object $logger ): array {
		// First step gets empty array (input steps generate their own data)
		if ( $step_position === 0 ) {
			$logger->debug( 'First step - no previous DataPackets available', [
				'job_id' => $job_id,
				'step_position' => $step_position
			] );
			return [];
		}

		// Get required services
		$pipeline_context = apply_filters( 'dm_get_pipeline_context', null );
		$all_databases = apply_filters('dm_get_database_services', []);
		$db_jobs = $all_databases['jobs'] ?? null;

		if ( ! $pipeline_context || ! $db_jobs ) {
			$logger->warning( 'Required services unavailable for all DataPackets retrieval', [
				'job_id' => $job_id,
				'step_position' => $step_position,
				'pipeline_context_available' => $pipeline_context !== null,
				'db_jobs_available' => $db_jobs !== null
			] );
			return [];
		}

		// Get previous step names and retrieve DataPackets
		$previous_step_names = $pipeline_context->get_all_previous_step_names( $job_id );
		if ( empty( $previous_step_names ) ) {
			return []; // No previous steps
		}

		$data_packets = [];

		// Get DataPackets from all previous steps
		foreach ( $previous_step_names as $step_name ) {
			if ( ! $step_name ) {
				continue;
			}

			$json_data = $db_jobs->get_step_data_by_name( $job_id, $step_name );
			if ( ! $json_data ) {
				continue;
			}

			try {
				$packet = \DataMachine\Engine\DataPacket::fromJson( $json_data );
				if ( $packet ) {
					$data_packets[] = $packet;
				}
			} catch ( \Exception $e ) {
				$logger->warning( 'Skipping malformed DataPacket', [
					'job_id' => $job_id,
					'step_name' => $step_name,
					'error' => $e->getMessage()
				] );
				// Skip malformed packets
				continue;
			}
		}

		// Reverse array to ensure most recent packets are first
		return array_reverse( $data_packets );
	}


} // End class