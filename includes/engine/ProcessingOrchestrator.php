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
use DataMachine\Database\Jobs;
use DataMachine\Helpers\{ActionScheduler, Logger};
use DataMachine\Contracts\{LoggerInterface, ActionSchedulerInterface};

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ProcessingOrchestrator {

	/** @var LoggerInterface */
	private LoggerInterface $logger;

	/** @var ActionSchedulerInterface */
	private ActionSchedulerInterface $action_scheduler;

	/** @var Jobs */
	private Jobs $db_jobs;

	/**
	 * Constructor with dependency injection.
	 *
	 * @param LoggerInterface $logger Logger service for error and info logging.
	 * @param ActionSchedulerInterface $action_scheduler Service for scheduling background jobs.
	 * @param Jobs $db_jobs Database service for job data management.
	 */
	public function __construct(
		LoggerInterface $logger,
		ActionSchedulerInterface $action_scheduler,
		Jobs $db_jobs
	) {
		$this->logger = $logger;
		$this->action_scheduler = $action_scheduler;
		$this->db_jobs = $db_jobs;
	}
	/**
	 * Execute a pipeline step dynamically based on registered steps.
	 *
	 * @param string $step_name The step name (e.g., 'input', 'process').
	 * @param int $job_id The job ID.
	 * @return bool True on success, false on failure.
	 */
	public function execute_step( string $step_name, int $job_id ): bool {
		try {
			$pipeline_steps = $this->get_pipeline_steps();
			
			if ( ! isset( $pipeline_steps[ $step_name ] ) ) {
				$this->logger->error( 'Pipeline step not found', [
					'step_name' => $step_name,
					'job_id' => $job_id,
					'available_steps' => array_keys( $pipeline_steps )
				] );
				return false;
			}

			$step_config = $pipeline_steps[ $step_name ];
			$step_class = $step_config['class'];

			if ( ! class_exists( $step_class ) ) {
				$this->logger->error( 'Pipeline step class not found', [
					'step_name' => $step_name,
					'class' => $step_class,
					'job_id' => $job_id
				] );
				return false;
			}

			// Create step instance with dependency injection via service locator
			$step_instance = $this->create_step_instance( $step_class );
			if ( ! $step_instance instanceof PipelineStepInterface ) {
				$this->logger->error( 'Pipeline step must implement PipelineStepInterface', [
					'step_name' => $step_name,
					'class' => $step_class,
					'job_id' => $job_id
				] );
				return false;
			}

			$result = $step_instance->execute( $job_id );

			if ( $result && isset( $step_config['next'] ) && $step_config['next'] ) {
				$next_step = $step_config['next'];
				$next_hook = 'dm_' . $next_step . '_job_event';
				
				if ( ! $this->schedule_next_step( $job_id, $next_hook ) ) {
					$this->logger->error( 'Failed to schedule next step', [
						'current_step' => $step_name,
						'next_step' => $next_step,
						'job_id' => $job_id
					] );
					return false;
				}
			}

			return $result;

		} catch ( Exception $e ) {
			$this->logger->error( 'Exception in pipeline step execution', [
				'step_name' => $step_name,
				'job_id' => $job_id,
				'error' => $e->getMessage()
			] );
			return false;
		}
	}

	/**
	 * Get registered pipeline steps from WordPress filters.
	 *
	 * @return array Pipeline steps configuration.
	 */
	private function get_pipeline_steps(): array {
		return apply_filters( 'dm_register_pipeline_steps', [] );
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
		$action_id = $this->action_scheduler->schedule_single_job(
			$hook,
			['job_id' => $job_id],
			time() + 1 // Schedule immediately
		);

		$success = $action_id !== false && $action_id !== 0;
		
		if ( ! $success ) {
			$this->logger->error( 'Failed to schedule next step', [
				'job_id' => $job_id,
				'hook' => $hook,
				'action_id' => $action_id
			] );
		}
		
		return $success;
	}





} // End class