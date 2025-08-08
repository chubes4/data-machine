<?php
/**
 * Pure execution engine for data processing pipelines.
 *
 * LEAN ARCHITECTURE:
 * - JobCreator builds complete pipeline configuration
 * - Orchestrator receives pre-built config and executes steps
 * - Zero database dependencies or redundant data preparation
 * - Pure pipeline execution with DataPacket flow
 *
 * EXECUTION FLOW:
 * - Receives pre-built configuration from JobCreator via Action Scheduler
 * - Gets step data directly from provided pipeline_config array
 * - Instantiates step classes via filter-based discovery
 * - Passes complete job config to steps for self-configuration
 * - Schedules next step with output DataPackets
 *
 * STEP REQUIREMENTS:
 * - Parameter-less constructor only
 * - execute(int $job_id, array $data_packets = [], array $job_config = []): array method required
 * - Must return array of DataPackets for next step
 * - Steps extract needed configuration from job_config themselves
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
	 * @param array|null $job_config Pre-built job configuration.
	 * @param array|null $previous_data_packets Previous step data packets.
	 * @return bool True on success, false on failure.
	 */
	public static function execute_step_callback( $job_id, $step_position, $pipeline_id = null, $flow_id = null, $job_config = null, $previous_data_packets = null ): bool {
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
		if ( $pipeline_id && $flow_id && $job_config ) {
			return $orchestrator->execute_step( $step_position, $job_id, $pipeline_id, $flow_id, $job_config, $previous_data_packets ?: [] );
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
	 * Execute a pipeline step using pre-built configuration from JobCreator.
	 * 
	 * PURE EXECUTION ENGINE:
	 * - Uses pre-built pipeline configuration (no database lookups)
	 * - Gets step data directly from pipeline_config array
	 * - Instantiates step and passes complete job configuration
	 * - Steps handle their own configuration extraction
	 *
	 * @param int $step_position The step position in pipeline_config array (0-based index).
	 * @param int $job_id The job ID.
	 * @param int $pipeline_id The pipeline ID.
	 * @param int $flow_id The flow ID.
	 * @param array $job_config Pre-built job configuration from JobCreator.
	 * @param array $previous_data_packets Previous step data packets for execution.
	 * @return bool True on success, false on failure.
	 */
	public function execute_step( int $step_position, int $job_id, int $pipeline_id, int $flow_id, array $job_config, array $previous_data_packets = [] ): bool {
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
			
			// Use pre-built job configuration from JobCreator
			if ( empty( $job_config ) ) {
				$logger->error( 'Job configuration is empty', [
					'pipeline_id' => $pipeline_id,
					'job_id' => $job_id,
					'step_position' => $step_position
				] );
				return false;
			}
			
			// Use pre-built job configuration from JobCreator (no database lookups)
			$pipeline_steps = $job_config['pipeline_step_config'] ?? [];
			
			// Validate we have steps in pre-built configuration
			if ( empty( $pipeline_steps ) ) {
				$logger->error( 'Job configuration contains no steps', [
					'step_position' => $step_position,
					'job_id' => $job_id,
					'pipeline_id' => $pipeline_id
				] );
				return false;
			}
			
			// Get step directly from pre-built array by position index
			if ( ! isset( $pipeline_steps[$step_position] ) ) {
				$logger->error( 'Step not found at position in pre-built configuration', [
					'step_position' => $step_position,
					'available_steps' => count( $pipeline_steps ),
					'job_id' => $job_id
				] );
				return false;
			}
			
			$step_config = $pipeline_steps[$step_position];
			$next_step_exists = isset( $pipeline_steps[$step_position + 1] );

			// Get step class via direct discovery - no mapping needed
			$step_type = $step_config['step_type'] ?? '';
			$all_steps = apply_filters('dm_get_steps', []);
			$step_definition = $all_steps[$step_type] ?? null;
			
			if ( ! $step_definition ) {
				$logger->error( 'Step type not found in registry', [
					'step_position' => $step_position,
					'step_type' => $step_type,
					'job_id' => $job_id
				] );
				return false;
			}
			
			$step_class = $step_definition['class'] ?? '';
			if ( ! class_exists( $step_class ) ) {
				$logger->error( 'Pipeline step class not found', [
					'step_position' => $step_position,
					'step_type' => $step_type,
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

			// Add current step position to job config for step access
			$job_config['current_step_position'] = $step_position;
			
			// Execute step with job configuration - steps access data directly from job_config
			$output_data_packets = $step_instance->execute( $job_id, $previous_data_packets, $job_config );
			
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
				if ( $next_step_exists ) {
					$next_step_position = $step_position + 1;
					// Pass output DataPackets to next step (pure engine flow)
					if ( ! $this->schedule_next_step( $job_id, $next_step_position, $pipeline_id, $flow_id, $job_config, $output_data_packets ) ) {
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
	 * @param array $job_config Pre-built job configuration.
	 * @param array $previous_data_packets Previous step data for next execution.
	 * @return bool True on success, false on failure.
	 */
	private function schedule_next_step( int $job_id, int $step_position, int $pipeline_id, int $flow_id, array $job_config, array $previous_data_packets = [] ): bool {
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
				'pipeline_config' => $job_config,
				'previous_data_packets' => $previous_data_packets
			],
			'data-machine'
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

} // End class