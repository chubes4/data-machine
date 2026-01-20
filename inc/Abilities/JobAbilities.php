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
use DataMachine\Core\PluginSettings;
use DataMachine\Services\JobManager;

defined( 'ABSPATH' ) || exit;

class JobAbilities {

	private const DEFAULT_PER_PAGE = 50;

	private Jobs $db_jobs;
	private Flows $db_flows;
	private JobManager $job_manager;

	public function __construct() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			return;
		}

		$this->db_jobs     = new Jobs();
		$this->db_flows    = new Flows();
		$this->job_manager = new JobManager();
		$this->registerAbilities();
	}

	private function registerAbilities(): void {
		add_action(
			'wp_abilities_api_init',
			function () {
				$this->registerGetJobs();
				$this->registerGetJob();
				$this->registerDeleteJobs();
				$this->registerRunFlow();
				$this->registerGetFlowHealth();
				$this->registerGetProblemFlows();
			}
		);
	}

	/**
	 * Register datamachine/get-jobs ability.
	 */
	private function registerGetJobs(): void {
		wp_register_ability(
			'datamachine/get-jobs',
			array(
				'label'               => __( 'Get Jobs', 'data-machine' ),
				'description'         => __( 'List jobs with optional filtering by flow_id, pipeline_id, or status. Supports pagination and sorting.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
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
	 * Register datamachine/get-job ability.
	 */
	private function registerGetJob(): void {
		wp_register_ability(
			'datamachine/get-job',
			array(
				'label'               => __( 'Get Job', 'data-machine' ),
				'description'         => __( 'Get a single job by ID with full details.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'job_id' ),
					'properties' => array(
						'job_id' => array(
							'type'        => 'integer',
							'description' => __( 'Job ID to retrieve', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'job'     => array( 'type' => 'object' ),
						'error'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeGetJob' ),
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
	 * Register datamachine/run-flow ability.
	 */
	private function registerRunFlow(): void {
		wp_register_ability(
			'datamachine/run-flow',
			array(
				'label'               => __( 'Run Flow', 'data-machine' ),
				'description'         => __( 'Execute a flow immediately or schedule for delayed execution. For immediate: provide only flow_id. For scheduled: provide flow_id AND future timestamp.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'flow_id' ),
					'properties' => array(
						'flow_id'   => array(
							'type'        => 'integer',
							'description' => __( 'Flow ID to execute', 'data-machine' ),
						),
						'count'     => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 10,
							'default'     => 1,
							'description' => __( 'Number of times to run the flow (1-10). Each run spawns an independent job.', 'data-machine' ),
						),
						'timestamp' => array(
							'type'        => array( 'integer', 'null' ),
							'description' => __( 'Future Unix timestamp for scheduled execution. Omit for immediate execution. Cannot combine with count > 1.', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'        => array( 'type' => 'boolean' ),
						'flow_id'        => array( 'type' => 'integer' ),
						'flow_name'      => array( 'type' => 'string' ),
						'execution_type' => array( 'type' => 'string' ),
						'job_id'         => array( 'type' => 'integer' ),
						'job_ids'        => array( 'type' => 'array' ),
						'count'          => array( 'type' => 'integer' ),
						'message'        => array( 'type' => 'string' ),
						'error'          => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeRunFlow' ),
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
		$flow_id     = $input['flow_id'] ?? null;
		$pipeline_id = $input['pipeline_id'] ?? null;
		$status      = $input['status'] ?? null;
		$per_page    = (int) ( $input['per_page'] ?? self::DEFAULT_PER_PAGE );
		$offset      = (int) ( $input['offset'] ?? 0 );
		$orderby     = $input['orderby'] ?? 'j.job_id';
		$order       = $input['order'] ?? 'DESC';

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
	 * Execute get-job ability.
	 *
	 * @param array $input Input parameters with job_id.
	 * @return array Result with job data.
	 */
	public function executeGetJob( array $input ): array {
		$job_id = $input['job_id'] ?? null;

		if ( ! is_numeric( $job_id ) || (int) $job_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'job_id is required and must be a positive integer',
			);
		}

		$job_id = (int) $job_id;
		$job    = $this->db_jobs->get_job( $job_id );

		if ( ! $job ) {
			return array(
				'success' => false,
				'error'   => sprintf( 'Job %d not found', $job_id ),
			);
		}

		$job = $this->addDisplayFields( $job );

		return array(
			'success' => true,
			'job'     => $job,
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

		$result = $this->job_manager->delete( $criteria, $cleanup_processed );

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
	 * Execute run-flow ability.
	 *
	 * @param array $input Input parameters with flow_id, optional count and timestamp.
	 * @return array Result with job_id(s) and execution info.
	 */
	public function executeRunFlow( array $input ): array {
		$flow_id = $input['flow_id'] ?? null;

		if ( ! is_numeric( $flow_id ) || (int) $flow_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'flow_id is required and must be a positive integer',
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
			$job_id = $this->job_manager->create( $flow_id, $pipeline_id );

			if ( ! $job_id ) {
				if ( empty( $jobs ) ) {
					return array(
						'success' => false,
						'error'   => 'Failed to create job record',
					);
				}
				break;
			}

			if ( null === $timestamp ) {
				as_schedule_single_action(
					time(),
					'datamachine_run_flow_now',
					array( $flow_id, $job_id ),
					'data-machine'
				);
			} else {
				as_schedule_single_action(
					$timestamp,
					'datamachine_run_flow_now',
					array( $flow_id, $job_id ),
					'data-machine'
				);
			}

			$jobs[] = $job_id;
		}

		do_action(
			'datamachine_log',
			'info',
			'Flow executed via ability',
			array(
				'flow_id'        => $flow_id,
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
				'flow_id'        => $flow_id,
				'flow_name'      => $flow_name,
				'execution_type' => $execution_type,
				'job_id'         => $jobs[0] ?? null,
				'message'        => $message,
			);
		}

		return array(
			'success'        => true,
			'flow_id'        => $flow_id,
			'flow_name'      => $flow_name,
			'execution_type' => $execution_type,
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
}
