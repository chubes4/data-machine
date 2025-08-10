<?php
/**
 * Pure execution engine for data processing pipelines.
 *
 * RUNTIME LOADING ARCHITECTURE:
 * - Ultra-simple job creation (job_id with pipeline_id + flow_id)
 * - ProcessingOrchestrator loads all configuration at runtime
 * - Database queries performed only when needed for step execution
 * - Pure pipeline execution with DataPacket flow
 *
 * EXECUTION FLOW:
 * - Receives job_id and execution_order from Action Scheduler
 * - Loads job data to get pipeline_id and flow_id
 * - Loads pipeline steps from database
 * - Loads flow configuration from database
 * - Merges step and flow configuration
 * - Instantiates and executes step with merged configuration
 * - Schedules next step with updated DataPacket
 *
 * STEP REQUIREMENTS:
 * - Parameter-less constructor only
 * - execute(int $job_id, array $data = [], array $job_config = []): array method required
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
	 * Static callback method for Action Scheduler direct execution.
	 * Ultra-simple flow_step_id based execution.
	 *
	 * @param string $flow_step_id The flow step ID (format: pipeline_step_id_flow_id).
	 * @param array|null $data Previous step data packet array.
	 * @return bool True on success, false on failure.
	 */
	public static function execute_step_callback( string $flow_step_id, $data = null ): bool {
		// Direct instantiation - ProcessingOrchestrator is the core engine
		$orchestrator = new self();
		
		// Ultra-simple flow_step_id execution
		return $orchestrator->execute_step( $flow_step_id, $data ?: [] );
	}

	/**
	 * Split flow_step_id using clean filter-based parsing.
	 * 
	 * @param string $flow_step_id The flow step ID.
	 * @return array Parsed components with 'pipeline_step_id' and 'flow_id' keys.
	 */
	private function split_flow_step_id( string $flow_step_id ) {
		return apply_filters('dm_split_flow_step_id', [], $flow_step_id);
	}

	/**
	 * Load complete flow step configuration from flow_step_id.
	 * 
	 * @param string $flow_step_id The flow step ID.
	 * @return array|null Complete step configuration or null if not found.
	 */
	private function load_flow_step_config( string $flow_step_id ) {
		$parts = $this->split_flow_step_id($flow_step_id);
		$flow_id = $parts['flow_id'] ?? null;
		if (!$flow_id) {
			do_action('dm_log', 'error', 'Invalid flow_step_id format', ['flow_step_id' => $flow_step_id]);
			return null;
		}

		// Single database query
		$all_databases = apply_filters('dm_db', []);
		$db_flows = $all_databases['flows'] ?? null;
		if (!$db_flows) {
			do_action('dm_log', 'error', 'Flows database service unavailable', ['flow_step_id' => $flow_step_id]);
			return null;
		}

		$flow = $db_flows->get_flow($flow_id);
		if (!$flow || empty($flow['flow_config'])) {
			do_action('dm_log', 'error', 'Flow not found or empty config', ['flow_step_id' => $flow_step_id, 'flow_id' => $flow_id]);
			return null;
		}

		$flow_config = is_string($flow['flow_config']) ? json_decode($flow['flow_config'], true) : $flow['flow_config'];
		if (!isset($flow_config[$flow_step_id])) {
			do_action('dm_log', 'error', 'Flow step not found in flow config', ['flow_step_id' => $flow_step_id]);
			return null;
		}

		return $flow_config[$flow_step_id];
	}

	/**
	 * Find next flow step ID by execution order.
	 * 
	 * @param string $current_flow_step_id Current flow step ID.
	 * @return string|null Next flow step ID or null if no next step.
	 */
	private function find_next_flow_step_id( string $current_flow_step_id ) {
		$current_config = $this->load_flow_step_config($current_flow_step_id);
		if (!$current_config) {
			return null;
		}

		$flow_id = $current_config['flow_id'];
		$current_execution_order = $current_config['execution_order'];
		$next_execution_order = $current_execution_order + 1;

		// Load flow to search all steps
		$all_databases = apply_filters('dm_db', []);
		$db_flows = $all_databases['flows'] ?? null;
		$flow = $db_flows->get_flow($flow_id);
		$flow_config = is_string($flow['flow_config']) ? json_decode($flow['flow_config'], true) : $flow['flow_config'];

		// Find step with next execution order
		foreach ($flow_config as $flow_step_id => $config) {
			if (($config['execution_order'] ?? -1) === $next_execution_order) {
				return $flow_step_id;
			}
		}

		return null; // No next step
	}
	/**
	 * Execute a pipeline step using flow_step_id based execution.
	 * 
	 * ULTRA-SIMPLE FLOW-STEP-ID ENGINE:
	 * - Single parameter contains all execution context
	 * - Flow config has execution_order + complete step configuration
	 * - Single database query for complete step execution
	 *
	 * @param string $flow_step_id The flow step ID (format: pipeline_step_id_flow_id).
	 * @param array $data Previous step data packet array for execution.
	 * @return bool True on success, false on failure.
	 */
	public function execute_step( string $flow_step_id, array $data = [] ): bool {
		try {
			do_action('dm_log', 'debug', 'Executing step with flow_step_id', [
				'flow_step_id' => $flow_step_id
			]);
			
			// Load complete step configuration using flow_step_id
			$step_config = $this->load_flow_step_config($flow_step_id);
			if (!$step_config) {
				do_action('dm_log', 'error', 'Failed to load step configuration', [
					'flow_step_id' => $flow_step_id
				]);
				return false;
			}

			// Get step class via direct discovery
			$step_type = $step_config['step_type'] ?? '';
			$all_steps = apply_filters('dm_steps', []);
			$step_definition = $all_steps[$step_type] ?? null;
			
			if ( ! $step_definition ) {
				do_action('dm_log', 'error', 'Step type not found in registry', [
					'flow_step_id' => $flow_step_id,
					'step_type' => $step_type
				] );
				return false;
			}
			
			$step_class = $step_definition['class'] ?? '';
			if ( ! class_exists( $step_class ) ) {
				do_action('dm_log', 'error', 'Pipeline step class not found', [
					'flow_step_id' => $flow_step_id,
					'step_type' => $step_type,
					'class' => $step_class
				] );
				return false;
			}

			// Create step instance (parameter-less constructor)
			$step_instance = new $step_class();
			
			// Enforce standard execute method requirement
			if ( ! method_exists( $step_instance, 'execute' ) ) {
				do_action('dm_log', 'error', 'Pipeline step must have execute method', [
					'flow_step_id' => $flow_step_id,
					'class' => $step_class,
					'available_methods' => get_class_methods( $step_instance )
				] );
				return false;
			}

			// Add pipeline context for AI steps only (for directive discovery)
			if ($step_type === 'ai') {
				// AI steps need pipeline context to find next step for directive injection
				$parts = $this->split_flow_step_id($flow_step_id);
				$flow_id = $parts['flow_id'] ?? null;
				if ($flow_id) {
					$all_databases = apply_filters('dm_db', []);
					$db_flows = $all_databases['flows'] ?? null;
					if ($db_flows) {
						$flow = $db_flows->get_flow($flow_id);
						$full_flow_config = is_string($flow['flow_config']) ? json_decode($flow['flow_config'], true) : $flow['flow_config'];
						$step_config['full_flow_config'] = $full_flow_config;
					}
				}
			}
			
			// Extract job_id from flow_step_id for step context
			$step_config['job_id'] = $this->extract_job_id_from_flow_step_id($flow_step_id);
			
			// Execute step with complete configuration
			$data = $step_instance->execute( $flow_step_id, $data, $step_config );
			
			// Validate step return - must be data packet array
			if ( ! is_array( $data ) ) {
				do_action('dm_log', 'error', 'Step must return data packet array', [
					'flow_step_id' => $flow_step_id,
					'class' => $step_class,
					'returned_type' => gettype( $data )
				] );
				return false;
			}
			
			do_action('dm_log', 'debug', 'Step executed with data packet array', [
				'flow_step_id' => $flow_step_id,
				'class' => $step_class,
				'final_items' => count( $data )
			] );

			// Success = non-empty data packet array, failure = empty array
			$step_success = ! empty( $data );
			
			// Handle pipeline flow based on step success
			if ( $step_success ) {
				// Find next step using execution_order
				$next_flow_step_id = $this->find_next_flow_step_id($flow_step_id);
				
				if ( $next_flow_step_id ) {
					// Schedule next step with updated data packet
					if ( ! $this->schedule_next_step( $next_flow_step_id, $data ) ) {
						do_action('dm_log', 'error', 'Failed to schedule next step', [
							'current_flow_step_id' => $flow_step_id,
							'next_flow_step_id' => $next_flow_step_id
						] );
						return false;
					}
					
					do_action('dm_log', 'debug', 'Scheduled next step with data packet array', [
						'current_flow_step_id' => $flow_step_id,
						'next_flow_step_id' => $next_flow_step_id,
						'items_passed' => count( $data )
					] );
				} else {
					do_action('dm_log', 'debug', 'Pipeline execution completed successfully', [
						'final_flow_step_id' => $flow_step_id,
						'final_items' => count( $data )
					] );
				}
			} else {
				do_action('dm_log', 'error', 'Step execution failed - empty data packet', [
					'flow_step_id' => $flow_step_id,
					'class' => $step_class
				] );
			}

			return $step_success;

		} catch ( \Exception $e ) {
			do_action('dm_log', 'error', 'Exception in pipeline step execution', [
					'flow_step_id' => $flow_step_id,
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				] );
			return false;
		}
	}


	/**
	 * Schedule the next step in the async pipeline using flow_step_id.
	 *
	 * @param string $flow_step_id The next flow step ID to execute.
	 * @param array $data Previous step data packet array for next execution.
	 * @return bool True on success, false on failure.
	 */
	private function schedule_next_step( string $flow_step_id, array $data = [] ): bool {
		// Use centralized action hook for flow_step_id scheduling
		do_action('dm_schedule_next_step', $flow_step_id, $data);
		return true; // Action hook handles error logging internally
	}

	/**
	 * Extract job_id from flow_step_id context for step execution.
	 * 
	 * @param string $flow_step_id The flow step ID to extract job_id from
	 * @return int Job ID or 0 if not found
	 */
	private function extract_job_id_from_flow_step_id(string $flow_step_id): int {
		$parts = $this->split_flow_step_id($flow_step_id);
		$flow_id = $parts['flow_id'] ?? null;
		
		if ($flow_id) {
			$all_databases = apply_filters('dm_db', []);
			$db_jobs = $all_databases['jobs'] ?? null;
			if ($db_jobs) {
				$active_jobs = $db_jobs->get_active_jobs_for_flow($flow_id);
				if (!empty($active_jobs)) {
					return (int)$active_jobs[0]['job_id'];
				}
			}
		}
		
		return 0; // Fallback - steps can handle this
	}

} // End class