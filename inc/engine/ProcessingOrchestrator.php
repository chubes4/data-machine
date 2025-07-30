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
		
		// Fix parameter order: method expects (step_position, job_id)
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
			$db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
			$db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
			
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
			
			$logger->info( 'Executing pipeline step', [
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

			// Natural data flow: Get latest DataPacket to pass directly to step
			$latest_data_packet = $this->get_latest_data_packet( $job_id, $step_position, $logger );
			
			// Set up context filter before step execution for full pipeline context access
			$this->setup_context_filter( $job_id, $step_position, $logger );
			
			try {
				// Execute step with natural data flow
				$result = $step_instance->execute( $job_id, $latest_data_packet );
				$logger->info( 'Step executed with natural data flow', [
					'job_id' => $job_id,
					'step_position' => $step_position,
					'class' => $step_class,
					'data_packet_available' => $latest_data_packet !== null
				] );
			} finally {
				// Always clean up context filter after step execution
				$this->cleanup_context_filter();
			}

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
	 * Schedule the next step in the async pipeline using direct Action Scheduler execution.
	 *
	 * @param int $job_id The job ID.
	 * @param int $step_position The next step position.
	 * @return bool True on success, false on failure.
	 */
	private function schedule_next_step( int $job_id, int $step_position ): bool {
		$logger = apply_filters('dm_get_logger', null);
		
		$action_id = as_schedule_single_action(
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
			$logger && $logger->info( 'Using pipeline_id from job', [
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
			$logger && $logger->info( 'Using flow_id from job', [
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
			$config = isset($pipeline_config['steps']) ? $pipeline_config['steps'] : [];
			
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
		// Get global registry for class mapping
		$pipeline_step_types = apply_filters( 'dm_get_steps', [] );
		
		foreach ( $pipeline_steps as $step ) {
			if ( isset( $step['position'] ) && (int) $step['position'] === $step_position ) {
				// Map pipeline step config to expected format
				return $this->map_pipeline_step_to_execution_format( $step, $pipeline_step_types, $logger );
			}
		}
		
		return null;
	}

	/**
	 * Map pipeline step configuration to execution format expected by orchestrator.
	 *
	 * @param array $pipeline_step Pipeline-specific step configuration.
	 * @param array $pipeline_step_types Global step registry from dm_get_steps filter for class mapping.
	 * @param object $logger The logger instance.
	 * @return array|null Mapped step configuration or null if mapping fails.
	 */
	private function map_pipeline_step_to_execution_format( array $pipeline_step, array $pipeline_step_types, object $logger ): ?array {
		$step_type = $pipeline_step['type'] ?? '';
		
		// Get the class from global registry based on step type
		if ( isset( $pipeline_step_types[ $step_type ] ) ) {
			return [
				'class' => $pipeline_step_types[ $step_type ]['class'],
				'type' => $step_type,
				'handler' => $pipeline_step['slug'] ?? '',
				'config' => $pipeline_step['config'] ?? [],
				'position' => $pipeline_step['position'] ?? 0
			];
		}
		
		$logger->warning( 'Unable to map pipeline step to execution format - step type not found in global registry', [
			'step_type' => $step_type,
			'pipeline_step' => $pipeline_step,
			'available_types' => array_keys( $pipeline_step_types )
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
	 * Get latest DataPacket from previous step for natural data flow.
	 *
	 * @param int $job_id The job ID.
	 * @param int $step_position The current step position.
	 * @param object $logger The logger instance.
	 * @return \DataMachine\Engine\DataPacket|null Latest DataPacket or null if not found.
	 */
	private function get_latest_data_packet( int $job_id, int $step_position, object $logger ): ?\DataMachine\Engine\DataPacket {
		// First step gets null DataPacket (input steps create their own)
		if ( $step_position === 0 ) {
			$logger->info( 'First step - no previous DataPacket available', [
				'job_id' => $job_id,
				'step_position' => $step_position
			] );
			return null;
		}

		// Get pipeline context service
		$pipeline_context = apply_filters( 'dm_get_pipeline_context', null );
		if ( ! $pipeline_context ) {
			$logger->warning( 'Pipeline context service unavailable for DataPacket retrieval', [
				'job_id' => $job_id,
				'step_position' => $step_position
			] );
			return null;
		}

		// Get previous step name
		$previous_step_name = $pipeline_context->get_previous_step_name( $job_id );
		if ( ! $previous_step_name ) {
			$logger->warning( 'No previous step name found for DataPacket retrieval', [
				'job_id' => $job_id,
				'step_position' => $step_position
			] );
			return null;
		}

		// Get database jobs service
		$db_jobs = apply_filters( 'dm_get_database_service', null, 'jobs' );
		if ( ! $db_jobs ) {
			$logger->error( 'Database jobs service unavailable for DataPacket retrieval', [
				'job_id' => $job_id,
				'step_position' => $step_position
			] );
			return null;
		}

		// Retrieve DataPacket JSON from database
		$json_data = $db_jobs->get_step_data_by_name( $job_id, $previous_step_name );
		if ( ! $json_data ) {
			$logger->warning( 'No DataPacket found from previous step', [
				'job_id' => $job_id,
				'step_position' => $step_position,
				'previous_step' => $previous_step_name
			] );
			return null;
		}

		// Parse DataPacket from JSON
		try {
			$data_packet = \DataMachine\Engine\DataPacket::fromJson( $json_data );
			$logger->info( 'Successfully retrieved latest DataPacket for natural flow', [
				'job_id' => $job_id,
				'step_position' => $step_position,
				'previous_step' => $previous_step_name,
				'content_length' => $data_packet->getContentLength(),
				'source_type' => $data_packet->metadata['source_type'] ?? 'unknown'
			] );
			return $data_packet;
		} catch ( \Exception $e ) {
			$logger->error( 'Failed to parse DataPacket from previous step', [
				'job_id' => $job_id,
				'step_position' => $step_position,
				'previous_step' => $previous_step_name,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			return null;
		}
	}


	/**
	 * Set up context filter before step execution for full pipeline context access.
	 *
	 * @param int $job_id The job ID.
	 * @param int $step_position The current step position.
	 * @param object $logger The logger instance.
	 */
	private function setup_context_filter( int $job_id, int $step_position, object $logger ): void {
		// Get all required services
		$pipeline_context = apply_filters( 'dm_get_pipeline_context', null );
		$db_jobs = apply_filters( 'dm_get_database_service', null, 'jobs' );

		if ( ! $pipeline_context || ! $db_jobs ) {
			$logger->warning( 'Required services unavailable for context filter setup', [
				'job_id' => $job_id,
				'step_position' => $step_position,
				'pipeline_context_available' => $pipeline_context !== null,
				'db_jobs_available' => $db_jobs !== null
			] );
			return;
		}

		// Build comprehensive context
		$context = [
			'job_id' => $job_id,
			'current_step_position' => $step_position,
			'pipeline_summary' => $pipeline_context->get_pipeline_context_summary( $job_id ),
			'all_previous_packets' => $this->get_all_previous_data_packets_for_context( $job_id, $pipeline_context, $db_jobs, $logger )
		];

		// Register context filter with high priority to ensure availability
		add_filter( 'dm_get_context', function( $existing_context ) use ( $context ) {
			return $context;
		}, 10 );

		$logger->info( 'Context filter established for step execution', [
			'job_id' => $job_id,
			'step_position' => $step_position,
			'previous_packets_count' => count( $context['all_previous_packets'] ),
			'context_keys' => array_keys( $context )
		] );
	}

	/**
	 * Clean up context filter after step execution.
	 */
	private function cleanup_context_filter(): void {
		// Remove the context filter to prevent leakage to subsequent operations
		remove_all_filters( 'dm_get_context' );
	}

	/**
	 * Get all previous DataPackets for context filter.
	 *
	 * @param int $job_id The job ID.
	 * @param object $pipeline_context The pipeline context service.
	 * @param object $db_jobs The database jobs service.
	 * @param object $logger The logger instance.
	 * @return array Array of DataPacket objects from all previous steps.
	 */
	private function get_all_previous_data_packets_for_context( int $job_id, object $pipeline_context, object $db_jobs, object $logger ): array {
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
				$logger->warning( 'Skipping malformed DataPacket in context', [
					'job_id' => $job_id,
					'step_name' => $step_name,
					'error' => $e->getMessage()
				] );
				// Skip malformed packets
				continue;
			}
		}

		return $data_packets;
	}


} // End class