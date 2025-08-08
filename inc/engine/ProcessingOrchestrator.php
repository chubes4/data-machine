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
 * - Schedules next step with updated data packet array
 *
 * STEP REQUIREMENTS:
 * - Parameter-less constructor only
 * - execute(int $job_id, array $data_packet = [], array $job_config = []): array method required
 * - Must return updated data packet array for next step
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
	 * @param array|null $data_packet Previous step data packet array.
	 * @return bool True on success, false on failure.
	 */
	public static function execute_step_callback( $job_id, $step_position, $pipeline_id = null, $flow_id = null, $job_config = null, $data_packet = null ): bool {
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
		
		// Call execute_step with complete parameter set - FAIL IMMEDIATELY if missing data
		if ( $pipeline_id && $flow_id && $job_config ) {
			return $orchestrator->execute_step( $step_position, $job_id, $pipeline_id, $flow_id, $job_config, $data_packet ?: [] );
		}
		
		// No legacy support - fail immediately
		$logger = apply_filters('dm_get_logger', null);
		if ( $logger ) {
			$logger->error( 'execute_step_callback requires complete parameter set - missing pipeline data', [
				'job_id' => $job_id,
				'step_position' => $step_position,
				'pipeline_id' => $pipeline_id,
				'flow_id' => $flow_id,
				'has_job_config' => !empty($job_config)
			] );
		}
		return false;
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
	 * @param array $data_packet Previous step data packet array for execution.
	 * @return bool True on success, false on failure.
	 */
	public function execute_step( int $step_position, int $job_id, int $pipeline_id, int $flow_id, array $job_config, array $data_packet = [] ): bool {
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

			// For steps that need handlers (fetch/publish), merge with flow config
			$flow_config = $job_config['flow_config'] ?? [];
			$flow_step_config = $flow_config[$step_position] ?? [];
			
			// Merge step config with flow step config (handlers, settings)
			$merged_step_config = array_merge($step_config, $flow_step_config);
			
			// Add essential IDs that steps/handlers need for processing
			$merged_step_config['pipeline_id'] = $pipeline_id;
			$merged_step_config['flow_id'] = $flow_id;
			
			// Pass merged step configuration directly - steps should not introspect job config
			// Steps receive only their own configuration, not entire pipeline or flow config
			$data_packet = $step_instance->execute( $job_id, $data_packet, $merged_step_config );
			
			// Validate step return - must be data packet array
			if ( ! is_array( $data_packet ) ) {
				$logger->error( 'Step must return data packet array', [
					'job_id' => $job_id,
					'step_position' => $step_position,
					'class' => $step_class,
					'returned_type' => gettype( $data_packet )
				] );
				return false;
			}
			
			$logger->debug( 'Step executed with data packet array', [
				'job_id' => $job_id,
				'step_position' => $step_position,
				'class' => $step_class,
				'final_items' => count( $data_packet )
			] );

			// Success = non-empty data packet array, failure = empty array
			$step_success = ! empty( $data_packet );
			
			// Handle pipeline flow based on step success
			if ( $step_success ) {
				if ( $next_step_exists ) {
					$next_step_position = $step_position + 1;
					// Pass data packet array to next step (pure engine flow)
					if ( ! $this->schedule_next_step( $job_id, $next_step_position, $pipeline_id, $flow_id, $job_config, $data_packet ) ) {
						$logger->error( 'Failed to schedule next step', [
							'current_position' => $step_position,
							'next_position' => $next_step_position,
							'job_id' => $job_id
						] );
						return false;
					}
					
					$logger->debug( 'Scheduled next step with data packet array', [
						'current_position' => $step_position,
						'next_position' => $next_step_position,
						'job_id' => $job_id,
						'items_passed' => count( $data_packet )
					] );
				} else {
					// Final step completed successfully with output
					$logger->debug( 'Pipeline execution completed successfully', [
						'final_position' => $step_position,
						'job_id' => $job_id,
						'final_items' => count( $data_packet )
					] );
				}
			} else {
				// Step failed - returned empty data packet array
				$logger->error( 'Step execution failed - no output data', [
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
	 * @param array $data_packet Previous step data packet array for next execution.
	 * @return bool True on success, false on failure.
	 */
	private function schedule_next_step( int $job_id, int $step_position, int $pipeline_id, int $flow_id, array $job_config, array $data_packet = [] ): bool {
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
				'previous_data_packets' => $data_packet
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