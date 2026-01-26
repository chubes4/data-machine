<?php
/**
 * Job Abilities
 *
 * Abilities API primitives for job operations.
 * Centralizes job query, execution, and monitoring logic for REST API, CLI, and Chat tools.
 *
 * @package DataMachine\Abilities
 */

namespace DataMachine\Abilities;

use DataMachine\Core\Admin\DateFormatter;
use DataMachine\Core\Database\Flows\Flows;
use DataMachine\Core\Database\Jobs\Jobs;
use DataMachine\Core\Database\ProcessedItems\ProcessedItems;
use DataMachine\Core\PluginSettings;
use DataMachine\Abilities\StepTypeAbilities;

defined( 'ABSPATH' ) || exit;

class JobAbilities {

	private const DEFAULT_PER_PAGE = 50;

	private static bool $registered = false;

	private Jobs $db_jobs;
	private Flows $db_flows;
	private ProcessedItems $db_processed_items;

	public function __construct() {
		$this->db_jobs            = new Jobs();
		$this->db_flows           = new Flows();
		$this->db_processed_items = new ProcessedItems();

		if ( ! class_exists( 'WP_Ability' ) || self::$registered ) {
			return;
		}

		$this->registerAbilities();
		self::$registered = true;
	}

	private function registerAbilities(): void {
		$register_callback = function () {
			$this->registerGetJobs();
			$this->registerDeleteJobs();
			$this->registerExecuteWorkflow();
			$this->registerGetFlowHealth();
			$this->registerGetProblemFlows();
		};

		if ( did_action( 'wp_abilities_api_init' ) ) {
			$register_callback();
		} else {
			add_action( 'wp_abilities_api_init', $register_callback );
		}
	}

	/**
	 * Register datamachine/get-jobs ability.
	 */
	private function registerGetJobs(): void {
		wp_register_ability(
			'datamachine/get-jobs',
			array(
				'label'               => __( 'Get Jobs', 'data-machine' ),
				'description'         => __( 'List jobs with optional filtering by flow_id, pipeline_id, or status. Supports pagination, sorting, and single job lookup via job_id.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'job_id'      => array(
							'type'        => array( 'integer', 'null' ),
							'description' => __( 'Get a specific job by ID (ignores pagination/filters when provided)', 'data-machine' ),
						),
						'flow_id'     => array(
							'type'        => array( 'integer', 'string', 'null' ),
							'description' => __( 'Filter jobs by flow ID (integer or "direct")', 'data-machine' ),
						),
						'pipeline_id' => array(
							'type'        => array( 'integer', 'string', 'null' ),
							'description' => __( 'Filter jobs by pipeline ID (integer or "direct")', 'data-machine' ),
						),
						'status'      => array(
							'type'        => array( 'string', 'null' ),
							'description' => __( 'Filter jobs by status (pending, processing, completed, failed, completed_no_items, agent_skipped)', 'data-machine' ),
						),
						'per_page'    => array(
							'type'        => 'integer',
							'default'     => self::DEFAULT_PER_PAGE,
							'minimum'     => 1,
							'maximum'     => 100,
							'description' => __( 'Number of jobs per page', 'data-machine' ),
						),
						'offset'      => array(
							'type'        => 'integer',
							'default'     => 0,
							'minimum'     => 0,
							'description' => __( 'Offset for pagination', 'data-machine' ),
						),
						'orderby'     => array(
							'type'        => 'string',
							'default'     => 'j.job_id',
							'description' => __( 'Column to order by', 'data-machine' ),
						),
						'order'       => array(
							'type'        => 'string',
							'enum'        => array( 'ASC', 'DESC' ),
							'default'     => 'DESC',
							'description' => __( 'Sort order', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'         => array( 'type' => 'boolean' ),
						'jobs'            => array( 'type' => 'array' ),
						'total'           => array( 'type' => 'integer' ),
						'per_page'        => array( 'type' => 'integer' ),
						'offset'          => array( 'type' => 'integer' ),
						'filters_applied' => array( 'type' => 'object' ),
						'error'           => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeGetJobs' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * Register datamachine/delete-jobs ability.
	 */
	private function registerDeleteJobs(): void {
		wp_register_ability(
			'datamachine/delete-jobs',
			array(
				'label'               => __( 'Delete Jobs', 'data-machine' ),
				'description'         => __( 'Delete jobs by type (all or failed). Optionally cleanup processed items tracking.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'type' ),
					'properties' => array(
						'type'              => array(
							'type'        => 'string',
							'enum'        => array( 'all', 'failed' ),
							'description' => __( 'Which jobs to delete: all or failed', 'data-machine' ),
						),
						'cleanup_processed' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Also clear processed items tracking for deleted jobs', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'                 => array( 'type' => 'boolean' ),
						'deleted_count'           => array( 'type' => 'integer' ),
						'processed_items_cleaned' => array( 'type' => 'integer' ),
						'message'                 => array( 'type' => 'string' ),
						'error'                   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeDeleteJobs' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * Register datamachine/execute-workflow ability.
	 *
	 * Unified primitive for workflow execution. Supports both database flows (via flow_id)
	 * and ephemeral workflows (via workflow steps). Mutually exclusive inputs.
	 */
	private function registerExecuteWorkflow(): void {
		wp_register_ability(
			'datamachine/execute-workflow',
			array(
				'label'               => __( 'Execute Workflow', 'data-machine' ),
				'description'         => __( 'Execute a workflow immediately or with delayed scheduling. Accepts either flow_id (database flow) OR workflow (ephemeral steps) - mutually exclusive.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'flow_id'      => array(
							'type'        => 'integer',
							'description' => __( 'Database flow ID to execute (mutually exclusive with workflow)', 'data-machine' ),
						),
						'workflow'     => array(
							'type'        => 'object',
							'description' => __( 'Ephemeral workflow with steps array (mutually exclusive with flow_id)', 'data-machine' ),
							'properties'  => array(
								'steps' => array(
									'type'        => 'array',
									'description' => __( 'Array of step objects with type, handler_slug, handler_config', 'data-machine' ),
								),
							),
						),
						'count'        => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 10,
							'default'     => 1,
							'description' => __( 'Number of times to run (1-10, database flow only). Each run spawns an independent job.', 'data-machine' ),
						),
						'timestamp'    => array(
							'type'        => array( 'integer', 'null' ),
							'description' => __( 'Future Unix timestamp for delayed execution. Omit for immediate execution.', 'data-machine' ),
						),
						'initial_data' => array(
							'type'        => 'object',
							'description' => __( 'Optional initial engine data to merge before workflow execution (ephemeral only)', 'data-machine' ),
						),
						'dry_run'      => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Preview execution without creating posts. Returns preview data instead of publishing (ephemeral only).', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'        => array( 'type' => 'boolean' ),
						'execution_mode' => array( 'type' => 'string' ),
						'execution_type' => array( 'type' => 'string' ),
						'flow_id'        => array( 'type' => 'integer' ),
						'flow_name'      => array( 'type' => 'string' ),
						'job_id'         => array( 'type' => 'integer' ),
						'job_ids'        => array( 'type' => 'array' ),
						'step_count'     => array( 'type' => 'integer' ),
						'count'          => array( 'type' => 'integer' ),
						'dry_run'        => array( 'type' => 'boolean' ),
						'message'        => array( 'type' => 'string' ),
						'error'          => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeWorkflow' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * Register datamachine/get-flow-health ability.
	 */
	private function registerGetFlowHealth(): void {
		wp_register_ability(
			'datamachine/get-flow-health',
			array(
				'label'               => __( 'Get Flow Health', 'data-machine' ),
				'description'         => __( 'Get health metrics for a flow including consecutive failures and no-items counts.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'flow_id' ),
					'properties' => array(
						'flow_id' => array(
							'type'        => 'integer',
							'description' => __( 'Flow ID to check health for', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'              => array( 'type' => 'boolean' ),
						'flow_id'              => array( 'type' => 'integer' ),
						'consecutive_failures' => array( 'type' => 'integer' ),
						'consecutive_no_items' => array( 'type' => 'integer' ),
						'latest_job'           => array( 'type' => array( 'object', 'null' ) ),
						'error'                => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeGetFlowHealth' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * Register datamachine/get-problem-flows ability.
	 */
	private function registerGetProblemFlows(): void {
		$default_threshold = PluginSettings::get( 'problem_flow_threshold', 3 );

		wp_register_ability(
			'datamachine/get-problem-flows',
			array(
				'label'               => __( 'Get Problem Flows', 'data-machine' ),
				'description'         => sprintf(
					/* translators: %d: default threshold */
					__( 'Identify flows with issues: consecutive failures (broken) or consecutive no-items runs (source exhausted). Default threshold: %d.', 'data-machine' ),
					$default_threshold
				),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'threshold' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'default'     => $default_threshold,
							'description' => sprintf(
								/* translators: %d: default threshold */
								__( 'Minimum consecutive count to report (default: %d from settings)', 'data-machine' ),
								$default_threshold
							),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'failing'   => array( 'type' => 'array' ),
						'idle'      => array( 'type' => 'array' ),
						'count'     => array( 'type' => 'integer' ),
						'threshold' => array( 'type' => 'integer' ),
						'message'   => array( 'type' => 'string' ),
						'error'     => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeGetProblemFlows' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * Permission callback for abilities.
	 *
	 * @return bool True if user has permission.
	 */
	public function checkPermission(): bool {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * Execute get-jobs ability.
	 *
	 * @param array $input Input parameters.
	 * @return array Result with jobs list.
	 */
	public function executeGetJobs( array $input ): array {
		$job_id      = $input['job_id'] ?? null;
		$flow_id     = $input['flow_id'] ?? null;
		$pipeline_id = $input['pipeline_id'] ?? null;
		$status      = $input['status'] ?? null;
		$per_page    = (int) ( $input['per_page'] ?? self::DEFAULT_PER_PAGE );
		$offset      = (int) ( $input['offset'] ?? 0 );
		$orderby     = $input['orderby'] ?? 'j.job_id';
		$order       = $input['order'] ?? 'DESC';

		// Direct job lookup by ID - bypasses pagination and filters.
		if ( $job_id ) {
			if ( ! is_numeric( $job_id ) || (int) $job_id <= 0 ) {
				return array(
					'success' => false,
					'error'   => 'job_id must be a positive integer',
				);
			}

			$job = $this->db_jobs->get_job( (int) $job_id );

			if ( ! $job ) {
				return array(
					'success'         => true,
					'jobs'            => array(),
					'total'           => 0,
					'per_page'        => $per_page,
					'offset'          => $offset,
					'filters_applied' => array( 'job_id' => (int) $job_id ),
				);
			}

			$job = $this->addDisplayFields( $job );

			return array(
				'success'         => true,
				'jobs'            => array( $job ),
				'total'           => 1,
				'per_page'        => $per_page,
				'offset'          => $offset,
				'filters_applied' => array( 'job_id' => (int) $job_id ),
			);
		}

		$args = array(
			'orderby'  => $orderby,
			'order'    => $order,
			'per_page' => $per_page,
			'offset'   => $offset,
		);

		$filters_applied = array();

		if ( null !== $flow_id ) {
			$args['flow_id']            = $flow_id;
			$filters_applied['flow_id'] = $flow_id;
		}

		if ( null !== $pipeline_id ) {
			$args['pipeline_id']            = $pipeline_id;
			$filters_applied['pipeline_id'] = $pipeline_id;
		}

		if ( null !== $status && '' !== $status ) {
			$args['status']            = sanitize_text_field( $status );
			$filters_applied['status'] = $args['status'];
		}

		$jobs  = $this->db_jobs->get_jobs_for_list_table( $args );
		$total = $this->db_jobs->get_jobs_count( $args );

		$jobs = array_map( array( $this, 'addDisplayFields' ), $jobs );

		return array(
			'success'         => true,
			'jobs'            => $jobs,
			'total'           => $total,
			'per_page'        => $per_page,
			'offset'          => $offset,
			'filters_applied' => $filters_applied,
		);
	}

	/**
	 * Execute delete-jobs ability.
	 *
	 * @param array $input Input parameters with type and cleanup_processed.
	 * @return array Result with deleted count.
	 */
	public function executeDeleteJobs( array $input ): array {
		$type              = $input['type'] ?? null;
		$cleanup_processed = (bool) ( $input['cleanup_processed'] ?? false );

		if ( ! in_array( $type, array( 'all', 'failed' ), true ) ) {
			return array(
				'success' => false,
				'error'   => 'type is required and must be "all" or "failed"',
			);
		}

		$criteria = array();
		if ( 'failed' === $type ) {
			$criteria['failed'] = true;
		} else {
			$criteria['all'] = true;
		}

		$result = $this->deleteJobs( $criteria, $cleanup_processed );

		if ( ! $result['success'] ) {
			return array(
				'success' => false,
				'error'   => 'Failed to delete jobs',
			);
		}

		$message_parts = array();
		/* translators: %d: number of jobs deleted */
		$message_parts[] = sprintf( __( 'Deleted %d jobs', 'data-machine' ), $result['jobs_deleted'] );

		if ( $cleanup_processed && $result['processed_items_cleaned'] > 0 ) {
			$message_parts[] = __( 'and their associated processed items', 'data-machine' );
		}

		$message = implode( ' ', $message_parts ) . '.';

		do_action(
			'datamachine_log',
			'info',
			'Jobs deleted via ability',
			array(
				'type'                    => $type,
				'jobs_deleted'            => $result['jobs_deleted'],
				'processed_items_cleaned' => $result['processed_items_cleaned'],
			)
		);

		return array(
			'success'                 => true,
			'deleted_count'           => $result['jobs_deleted'],
			'processed_items_cleaned' => $result['processed_items_cleaned'],
			'message'                 => $message,
		);
	}

	/**
	 * Execute workflow ability.
	 *
	 * Unified primitive for workflow execution. Handles both database flows (via flow_id)
	 * and ephemeral workflows (via workflow steps).
	 *
	 * @param array $input Input parameters with flow_id OR workflow, plus optional timestamp/count/initial_data.
	 * @return array Result with job_id(s) and execution info.
	 */
	public function executeWorkflow( array $input ): array {
		$flow_id      = $input['flow_id'] ?? null;
		$workflow     = $input['workflow'] ?? null;
		$timestamp    = $input['timestamp'] ?? null;
		$initial_data = $input['initial_data'] ?? null;

		// Validate: must have flow_id OR workflow (mutually exclusive)
		if ( ! $flow_id && ! $workflow ) {
			return array(
				'success' => false,
				'error'   => 'Must provide either flow_id or workflow',
			);
		}

		if ( $flow_id && $workflow ) {
			return array(
				'success' => false,
				'error'   => 'Cannot provide both flow_id and workflow',
			);
		}

		// Route to appropriate execution path
		if ( $flow_id ) {
			return $this->executeDatabaseFlow( $input );
		}

		return $this->executeEphemeralWorkflow( $input );
	}

	/**
	 * Execute a database flow.
	 *
	 * @param array $input Input parameters with flow_id, optional count and timestamp.
	 * @return array Result with job_id(s) and execution info.
	 */
	private function executeDatabaseFlow( array $input ): array {
		$flow_id = $input['flow_id'] ?? null;

		if ( ! is_numeric( $flow_id ) || (int) $flow_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'flow_id must be a positive integer',
			);
		}

		$flow_id        = (int) $flow_id;
		$count          = max( 1, min( 10, (int) ( $input['count'] ?? 1 ) ) );
		$timestamp      = $input['timestamp'] ?? null;
		$execution_type = 'immediate';

		if ( ! empty( $timestamp ) && is_numeric( $timestamp ) && (int) $timestamp > time() ) {
			$timestamp      = (int) $timestamp;
			$execution_type = 'delayed';

			if ( $count > 1 ) {
				return array(
					'success' => false,
					'error'   => 'Cannot schedule multiple runs with a timestamp. Use count only for immediate execution.',
				);
			}
		} else {
			$timestamp = null;
		}

		$flow = $this->db_flows->get_flow( $flow_id );
		if ( ! $flow ) {
			return array(
				'success' => false,
				'error'   => sprintf( 'Flow %d not found', $flow_id ),
			);
		}

		$flow_name   = $flow['flow_name'] ?? "Flow {$flow_id}";
		$pipeline_id = (int) $flow['pipeline_id'];
		$jobs        = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$job_id = $this->createJob( $flow_id, $pipeline_id );

			if ( ! $job_id ) {
				if ( empty( $jobs ) ) {
					return array(
						'success' => false,
						'error'   => 'Failed to create job record',
					);
				}
				break;
			}

			$schedule_time = $timestamp ?? time();
			as_schedule_single_action(
				$schedule_time,
				'datamachine_run_flow_now',
				array( $flow_id, $job_id ),
				'data-machine'
			);

			$jobs[] = $job_id;
		}

		do_action(
			'datamachine_log',
			'info',
			'Workflow executed via ability (database mode)',
			array(
				'flow_id'        => $flow_id,
				'execution_mode' => 'database',
				'execution_type' => $execution_type,
				'job_count'      => count( $jobs ),
				'job_ids'        => $jobs,
			)
		);

		if ( 1 === $count ) {
			$message = 'immediate' === $execution_type
				? 'Flow queued for immediate background execution. It will start within seconds. Use job_id to check status.'
				: 'Flow scheduled for delayed background execution at the specified time.';

			return array(
				'success'        => true,
				'execution_mode' => 'database',
				'execution_type' => $execution_type,
				'flow_id'        => $flow_id,
				'flow_name'      => $flow_name,
				'job_id'         => $jobs[0] ?? null,
				'message'        => $message,
			);
		}

		return array(
			'success'        => true,
			'execution_mode' => 'database',
			'execution_type' => $execution_type,
			'flow_id'        => $flow_id,
			'flow_name'      => $flow_name,
			'count'          => count( $jobs ),
			'job_ids'        => $jobs,
			'message'        => sprintf(
				'Queued %d jobs for flow "%s". Each job will process one item independently.',
				count( $jobs ),
				$flow_name
			),
		);
	}

	/**
	 * Execute an ephemeral workflow.
	 *
	 * @param array $input Input parameters with workflow, optional timestamp and initial_data.
	 * @return array Result with job_id and execution info.
	 */
	private function executeEphemeralWorkflow( array $input ): array {
		$workflow     = $input['workflow'] ?? null;
		$timestamp    = $input['timestamp'] ?? null;
		$initial_data = $input['initial_data'] ?? null;

		// Validate workflow structure
		$validation = $this->validateWorkflow( $workflow );
		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'error'   => $validation['error'],
			);
		}

		// Build configs from workflow
		$configs = $this->buildConfigsFromWorkflow( $workflow );

		// Create job record for direct execution
		$job_id = $this->db_jobs->create_job(
			array(
				'pipeline_id' => 'direct',
				'flow_id'     => 'direct',
			)
		);

		if ( ! $job_id ) {
			return array(
				'success' => false,
				'error'   => 'Failed to create job record',
			);
		}

		// Build engine data with configs and optional initial data
		$engine_data = array(
			'flow_config'     => $configs['flow_config'],
			'pipeline_config' => $configs['pipeline_config'],
		);

		if ( ! empty( $initial_data ) && is_array( $initial_data ) ) {
			$engine_data = array_merge( $engine_data, $initial_data );
		}

		// Set dry_run_mode flag for preview execution
		if ( ! empty( $input['dry_run'] ) ) {
			$engine_data['dry_run_mode'] = true;
		}

		$this->db_jobs->store_engine_data( $job_id, $engine_data );

		// Find first step
		$first_step_id = $this->getFirstStepId( $configs['flow_config'] );

		if ( ! $first_step_id ) {
			return array(
				'success' => false,
				'error'   => 'Could not determine first step in workflow',
			);
		}

		$step_count     = count( $workflow['steps'] ?? array() );
		$execution_type = 'immediate';

		$is_dry_run = ! empty( $input['dry_run'] );

		// Immediate execution
		if ( ! $timestamp || ! is_numeric( $timestamp ) || (int) $timestamp <= time() ) {
			do_action( 'datamachine_schedule_next_step', $job_id, $first_step_id, array() );

			do_action(
				'datamachine_log',
				'info',
				'Workflow executed via ability (direct mode)',
				array(
					'execution_mode' => 'direct',
					'execution_type' => 'immediate',
					'job_id'         => $job_id,
					'step_count'     => $step_count,
					'dry_run'        => $is_dry_run,
				)
			);

			$message = $is_dry_run
				? 'Ephemeral workflow dry-run started. No posts will be created - preview data will be returned.'
				: 'Ephemeral workflow execution started';

			$response = array(
				'success'        => true,
				'execution_mode' => 'direct',
				'execution_type' => 'immediate',
				'job_id'         => $job_id,
				'step_count'     => $step_count,
				'message'        => $message,
			);

			if ( $is_dry_run ) {
				$response['dry_run'] = true;
			}

			return $response;
		}

		// Delayed execution
		$execution_type = 'delayed';
		$timestamp      = (int) $timestamp;

		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return array(
				'success' => false,
				'error'   => 'Action Scheduler not available for delayed execution',
			);
		}

		$action_id = as_schedule_single_action(
			$timestamp,
			'datamachine_schedule_next_step',
			array( $job_id, $first_step_id, array() ),
			'data-machine'
		);

		if ( false === $action_id ) {
			return array(
				'success' => false,
				'error'   => 'Failed to schedule workflow execution',
			);
		}

		do_action(
			'datamachine_log',
			'info',
			'Workflow scheduled via ability (direct mode)',
			array(
				'execution_mode' => 'direct',
				'execution_type' => 'delayed',
				'job_id'         => $job_id,
				'step_count'     => $step_count,
				'timestamp'      => $timestamp,
			)
		);

		return array(
			'success'        => true,
			'execution_mode' => 'direct',
			'execution_type' => 'delayed',
			'job_id'         => $job_id,
			'step_count'     => $step_count,
			'timestamp'      => $timestamp,
			'scheduled_time' => wp_date( 'c', $timestamp ),
			'message'        => 'Ephemeral workflow scheduled for one-time execution at ' . wp_date( 'M j, Y g:i A', $timestamp ),
		);
	}

	/**
	 * Validate workflow structure.
	 *
	 * @param array|null $workflow Workflow to validate.
	 * @return array Validation result with 'valid' boolean and optional 'error' string.
	 */
	private function validateWorkflow( $workflow ): array {
		if ( ! isset( $workflow['steps'] ) || ! is_array( $workflow['steps'] ) ) {
			return array(
				'valid' => false,
				'error' => 'Workflow must contain steps array',
			);
		}

		if ( empty( $workflow['steps'] ) ) {
			return array(
				'valid' => false,
				'error' => 'Workflow must have at least one step',
			);
		}

		$step_type_abilities = new StepTypeAbilities();
		$valid_types         = array_keys( $step_type_abilities->getAllStepTypes() );

		foreach ( $workflow['steps'] as $index => $step ) {
			if ( ! isset( $step['type'] ) ) {
				return array(
					'valid' => false,
					'error' => "Step {$index} missing type",
				);
			}

			if ( ! in_array( $step['type'], $valid_types, true ) ) {
				return array(
					'valid' => false,
					'error' => "Step {$index} has invalid type: {$step['type']}. Valid types: " . implode( ', ', $valid_types ),
				);
			}

			if ( 'ai' !== $step['type'] && ! isset( $step['handler_slug'] ) ) {
				return array(
					'valid' => false,
					'error' => "Step {$index} missing handler_slug (required for non-AI steps)",
				);
			}
		}

		return array( 'valid' => true );
	}

	/**
	 * Build flow_config and pipeline_config from workflow structure.
	 *
	 * @param array $workflow Workflow with steps.
	 * @return array Array with 'flow_config' and 'pipeline_config' keys.
	 */
	private function buildConfigsFromWorkflow( array $workflow ): array {
		$flow_config     = array();
		$pipeline_config = array();

		foreach ( $workflow['steps'] as $index => $step ) {
			$step_id          = "ephemeral_step_{$index}";
			$pipeline_step_id = "ephemeral_pipeline_{$index}";

			// Flow config (instance-specific)
			$flow_config[ $step_id ] = array(
				'flow_step_id'     => $step_id,
				'pipeline_step_id' => $pipeline_step_id,
				'step_type'        => $step['type'],
				'execution_order'  => $index,
				'handler_slug'     => $step['handler_slug'] ?? '',
				'handler_config'   => $step['handler_config'] ?? array(),
				'user_message'     => $step['user_message'] ?? '',
				'enabled_tools'    => $step['enabled_tools'] ?? array(),
				'pipeline_id'      => 'direct',
				'flow_id'          => 'direct',
			);

			// Pipeline config (AI settings only)
			if ( 'ai' === $step['type'] ) {
				$pipeline_config[ $pipeline_step_id ] = array(
					'provider'      => $step['provider'] ?? '',
					'model'         => $step['model'] ?? '',
					'system_prompt' => $step['system_prompt'] ?? '',
					'enabled_tools' => $step['enabled_tools'] ?? array(),
				);
			}
		}

		return array(
			'flow_config'     => $flow_config,
			'pipeline_config' => $pipeline_config,
		);
	}

	/**
	 * Get first step ID from flow_config.
	 *
	 * @param array $flow_config Flow configuration.
	 * @return string|null First step ID or null if not found.
	 */
	private function getFirstStepId( array $flow_config ): ?string {
		foreach ( $flow_config as $step_id => $config ) {
			if ( ( $config['execution_order'] ?? -1 ) === 0 ) {
				return $step_id;
			}
		}
		return null;
	}

	/**
	 * Execute get-flow-health ability.
	 *
	 * @param array $input Input parameters with flow_id.
	 * @return array Result with health metrics.
	 */
	public function executeGetFlowHealth( array $input ): array {
		$flow_id = $input['flow_id'] ?? null;

		if ( ! is_numeric( $flow_id ) || (int) $flow_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'flow_id is required and must be a positive integer',
			);
		}

		$flow_id = (int) $flow_id;

		$flow = $this->db_flows->get_flow( $flow_id );
		if ( ! $flow ) {
			return array(
				'success' => false,
				'error'   => sprintf( 'Flow %d not found', $flow_id ),
			);
		}

		$health = $this->db_jobs->get_flow_health( $flow_id );

		return array(
			'success'              => true,
			'flow_id'              => $flow_id,
			'consecutive_failures' => $health['consecutive_failures'] ?? 0,
			'consecutive_no_items' => $health['consecutive_no_items'] ?? 0,
			'latest_job'           => $health['latest_job'] ?? null,
		);
	}

	/**
	 * Execute get-problem-flows ability.
	 *
	 * @param array $input Input parameters with optional threshold.
	 * @return array Result with problem flows.
	 */
	public function executeGetProblemFlows( array $input ): array {
		$threshold = $input['threshold'] ?? null;

		if ( null === $threshold || ! is_numeric( $threshold ) || (int) $threshold <= 0 ) {
			$threshold = PluginSettings::get( 'problem_flow_threshold', 3 );
		}

		$threshold = (int) $threshold;

		$problem_flows = $this->db_flows->get_problem_flows( $threshold );

		if ( empty( $problem_flows ) ) {
			return array(
				'success'   => true,
				'failing'   => array(),
				'idle'      => array(),
				'count'     => 0,
				'threshold' => $threshold,
				'message'   => sprintf(
					/* translators: %d: threshold */
					__( 'No problem flows detected. All flows are below the threshold of %d.', 'data-machine' ),
					$threshold
				),
			);
		}

		$failing_flows = array();
		$idle_flows    = array();

		foreach ( $problem_flows as $flow ) {
			$failures = $flow['consecutive_failures'] ?? 0;
			$no_items = $flow['consecutive_no_items'] ?? 0;

			if ( $failures >= $threshold ) {
				$failing_flows[] = array(
					'flow_id'              => (int) $flow['flow_id'],
					'flow_name'            => $flow['flow_name'] ?? '',
					'consecutive_failures' => $failures,
					'description'          => sprintf(
						'%s (Flow #%d) - %d consecutive failures - investigate errors',
						$flow['flow_name'] ?? '',
						$flow['flow_id'],
						$failures
					),
				);
			}

			if ( $no_items >= $threshold ) {
				$idle_flows[] = array(
					'flow_id'              => (int) $flow['flow_id'],
					'flow_name'            => $flow['flow_name'] ?? '',
					'consecutive_no_items' => $no_items,
					'description'          => sprintf(
						'%s (Flow #%d) - %d runs with no new items - consider lowering interval',
						$flow['flow_name'] ?? '',
						$flow['flow_id'],
						$no_items
					),
				);
			}
		}

		$message_parts = array();

		if ( ! empty( $failing_flows ) ) {
			$descriptions    = array_map( fn( $f ) => $f['description'], $failing_flows );
			$message_parts[] = sprintf(
				"FAILING FLOWS (%d+ consecutive failures):\n- %s",
				$threshold,
				implode( "\n- ", $descriptions )
			);
		}

		if ( ! empty( $idle_flows ) ) {
			$descriptions    = array_map( fn( $f ) => $f['description'], $idle_flows );
			$message_parts[] = sprintf(
				"IDLE FLOWS (%d+ runs with no new items):\n- %s",
				$threshold,
				implode( "\n- ", $descriptions )
			);
		}

		$message = implode( "\n\n", $message_parts );

		return array(
			'success'   => true,
			'failing'   => $failing_flows,
			'idle'      => $idle_flows,
			'count'     => count( $problem_flows ),
			'threshold' => $threshold,
			'message'   => $message,
		);
	}

	/**
	 * Add formatted display fields for timestamps.
	 *
	 * @param array $job Job data.
	 * @return array Job data with *_display fields added.
	 */
	private function addDisplayFields( array $job ): array {
		if ( isset( $job['created_at'] ) ) {
			$job['created_at_display'] = DateFormatter::format_for_display( $job['created_at'] );
		}

		if ( isset( $job['completed_at'] ) ) {
			$job['completed_at_display'] = DateFormatter::format_for_display( $job['completed_at'] );
		}

		return $job;
	}

	/**
	 * Create a new job for a flow execution.
	 *
	 * @param int $flow_id Flow ID to execute.
	 * @param int $pipeline_id Pipeline ID (optional, will be looked up if not provided).
	 * @return int|null Job ID on success, null on failure.
	 */
	private function createJob( int $flow_id, int $pipeline_id = 0 ): ?int {
		if ( $pipeline_id <= 0 ) {
			$flow = $this->db_flows->get_flow( $flow_id );
			if ( ! $flow ) {
				do_action( 'datamachine_log', 'error', 'Job creation failed - flow not found', array( 'flow_id' => $flow_id ) );
				return null;
			}
			$pipeline_id = (int) $flow['pipeline_id'];
		}

		$job_id = $this->db_jobs->create_job(
			array(
				'pipeline_id' => $pipeline_id,
				'flow_id'     => $flow_id,
			)
		);

		if ( ! $job_id ) {
			do_action(
				'datamachine_log',
				'error',
				'Job creation failed - database insert failed',
				array(
					'flow_id'     => $flow_id,
					'pipeline_id' => $pipeline_id,
				)
			);
			return null;
		}

		do_action(
			'datamachine_log',
			'debug',
			'Job created',
			array(
				'job_id'      => $job_id,
				'flow_id'     => $flow_id,
				'pipeline_id' => $pipeline_id,
			)
		);

		return $job_id;
	}

	/**
	 * Delete jobs based on criteria.
	 *
	 * @param array $criteria Deletion criteria ('all' => true or 'failed' => true).
	 * @param bool  $cleanup_processed Whether to cleanup associated processed items.
	 * @return array Result with deleted count and cleanup info.
	 */
	private function deleteJobs( array $criteria, bool $cleanup_processed = false ): array {
		$job_ids_to_delete = array();

		if ( $cleanup_processed ) {
			global $wpdb;
			$jobs_table = $wpdb->prefix . 'datamachine_jobs';

			if ( ! empty( $criteria['failed'] ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$job_ids_to_delete = $wpdb->get_col( $wpdb->prepare( 'SELECT job_id FROM %i WHERE status = %s', $jobs_table, 'failed' ) );
			} elseif ( ! empty( $criteria['all'] ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$job_ids_to_delete = $wpdb->get_col( $wpdb->prepare( 'SELECT job_id FROM %i', $jobs_table ) );
			}
		}

		$deleted_count = $this->db_jobs->delete_jobs( $criteria );

		if ( false === $deleted_count ) {
			return array(
				'success'                 => false,
				'jobs_deleted'            => 0,
				'processed_items_cleaned' => 0,
			);
		}

		if ( $cleanup_processed && ! empty( $job_ids_to_delete ) ) {
			foreach ( $job_ids_to_delete as $job_id ) {
				$this->db_processed_items->delete_processed_items( array( 'job_id' => (int) $job_id ) );
			}
		}

		return array(
			'success'                 => true,
			'jobs_deleted'            => $deleted_count,
			'processed_items_cleaned' => $cleanup_processed ? count( $job_ids_to_delete ) : 0,
		);
	}
}
