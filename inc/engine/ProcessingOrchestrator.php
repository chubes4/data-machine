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
 * - Single execute(int $job_id, array $data_packets = []): array method required
 * - Must return array of DataPackets for next step
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
	 * @param int|null $pipeline_id The pipeline ID.
	 * @param int|null $flow_id The flow ID.
	 * @param array|null $pipeline_config Pre-built pipeline configuration.
	 * @param array|null $previous_data_packets Previous step data packets.
	 * @return bool True on success, false on failure.
	 */
	public static function execute_step_callback( $job_id, $step_position, $pipeline_id = null, $flow_id = null, $pipeline_config = null, $previous_data_packets = null ): bool {
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
		
		// Call execute_step with complete parameter set
		if ( $pipeline_id && $flow_id && $pipeline_config ) {
			return $orchestrator->execute_step( $step_position, $job_id, $pipeline_id, $flow_id, $pipeline_config, $previous_data_packets ?: [] );
		} else {
			// Legacy fallback - should not occur with new JobCreator
			$logger = apply_filters('dm_get_logger', null);
			if ( $logger ) {
				$logger->error( 'Legacy execute_step_callback invocation - missing pipeline data', [
					'job_id' => $job_id,
					'step_position' => $step_position
				] );
			}
			return false;
		}
	}
	/**
	 * Execute a pipeline step at the specified position using pure stateless execution.
	 * 
	 * Ultra-lean execution engine with zero database dependencies. All required data
	 * is passed as parameters from JobCreator/ActionScheduler.
	 *
	 * @param int $step_position The step position in pipeline (0-based).
	 * @param int $job_id The job ID.
	 * @param int $pipeline_id The pipeline ID.
	 * @param int $flow_id The flow ID.
	 * @param array $pipeline_config Pre-built pipeline configuration from JobCreator.
	 * @param array $previous_data_packets Previous step data packets for execution.
	 * @return bool True on success, false on failure.
	 */
	public function execute_step( int $step_position, int $job_id, int $pipeline_id, int $flow_id, array $pipeline_config, array $previous_data_packets = [] ): bool {
		try {
			// Get services via filters
			$logger = apply_filters('dm_get_logger', null);
			
			if (!$logger) {
				return false;
			}
			
			$logger->debug( 'Executing pipeline step with pre-built configuration', [
				'job_id' => $job_id,
				'pipeline_id' => $pipeline_id,
				'flow_id' => $flow_id,
				'step_position' => $step_position
			] );
			
			// Use pre-built pipeline configuration from JobCreator
			if ( empty( $pipeline_config ) ) {
				$logger->error( 'Pipeline configuration is empty', [
					'pipeline_id' => $pipeline_id,
					'job_id' => $job_id,
					'step_position' => $step_position
				] );
				return false;
			}
			
			// Find step configuration by position
			$pipeline_steps = $pipeline_config['pipeline_step_config'] ?? [];
			$step_config = $this->find_step_config_by_position( $step_position, $pipeline_steps, $logger );
			if ( ! $step_config ) {
				$logger->error( 'Pipeline step configuration not found at position', [
					'step_position' => $step_position,
					'job_id' => $job_id,
					'pipeline_id' => $pipeline_id
				] );
				return false;
			}
			
			$next_step_position = $this->get_next_step_position( $step_position, $pipeline_steps );

			$step_class = $step_config['class'];
			if ( ! class_exists( $step_class ) ) {
				$logger->error( 'Pipeline step class not found', [
					'step_position' => $step_position,
					'class' => $step_class,
					'job_id' => $job_id
				] );
				return false;
			}

			// ProcessingOrchestrator is now lean - job tracking moved to ActionScheduler

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

			// Execute step and capture DataPacket returns (pure engine flow)
			$output_data_packets = $step_instance->execute( $job_id, $previous_data_packets );
			
			// Validate step return - must be array of DataPackets
			if ( ! is_array( $output_data_packets ) ) {
				$logger->error( 'Step must return array of DataPackets', [
					'job_id' => $job_id,
					'step_position' => $step_position,
					'class' => $step_class,
					'returned_type' => gettype( $output_data_packets )
				] );
				return false;
			}
			
			$logger->debug( 'Step executed with DataPacket flow', [
				'job_id' => $job_id,
				'step_position' => $step_position,
				'class' => $step_class,
				'input_packets' => count( $previous_data_packets ),
				'output_packets' => count( $output_data_packets )
			] );

			// Success = non-empty DataPacket array, failure = empty array
			$step_success = ! empty( $output_data_packets );
			
			// Handle pipeline flow based on step success
			if ( $step_success ) {
				if ( $next_step_position !== null ) {
					// Pass output DataPackets to next step (pure engine flow)
					if ( ! $this->schedule_next_step( $job_id, $next_step_position, $pipeline_id, $flow_id, $pipeline_config, $output_data_packets ) ) {
						$logger->error( 'Failed to schedule next step', [
							'current_position' => $step_position,
							'next_position' => $next_step_position,
							'job_id' => $job_id
						] );
						return false;
					}
					
					$logger->debug( 'Scheduled next step with DataPackets', [
						'current_position' => $step_position,
						'next_position' => $next_step_position,
						'job_id' => $job_id,
						'packets_passed' => count( $output_data_packets )
					] );
				} else {
					// Final step completed successfully with output
					$logger->debug( 'Pipeline execution completed successfully', [
						'final_position' => $step_position,
						'job_id' => $job_id,
						'final_packets' => count( $output_data_packets )
					] );
				}
			} else {
				// Step failed - returned empty DataPackets
				$logger->error( 'Step execution failed - no output DataPackets', [
					'job_id' => $job_id,
					'failed_step' => $step_position,
					'class' => $step_class
				] );
			}

			return $step_success;

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
	 * @param int $pipeline_id The pipeline ID.
	 * @param int $flow_id The flow ID.
	 * @param array $pipeline_config Pre-built pipeline configuration.
	 * @param array $previous_data_packets Previous step data for next execution.
	 * @return bool True on success, false on failure.
	 */
	private function schedule_next_step( int $job_id, int $step_position, int $pipeline_id, int $flow_id, array $pipeline_config, array $previous_data_packets = [] ): bool {
		$logger = apply_filters('dm_get_logger', null);
		
		$scheduler = apply_filters('dm_get_action_scheduler', null);
		if (!$scheduler) {
			$logger && $logger->error('ActionScheduler service not available');
			return false;
		}
		
		$action_id = $scheduler->schedule_single_action(
			time(), // Action Scheduler handles immediate execution precisely
			'dm_execute_step',
			[
				'job_id' => $job_id,
				'step_position' => $step_position,
				'pipeline_id' => $pipeline_id,
				'flow_id' => $flow_id,
				'pipeline_config' => $pipeline_config,
				'previous_data_packets' => $previous_data_packets
			],
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
		$step_type = $pipeline_step['step_type'] ?? '';
		
		// Get step config via pure discovery
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
		
		$logger->warning( 'Unable to map pipeline step to execution format - step type not found', [
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





} // End class