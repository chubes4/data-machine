<?php
/**
 * Flow Abilities
 *
 * Abilities API primitives for flow operations.
 * Centralizes flow query and filtering logic for REST API, CLI, and Chat tools.
 *
 * @package DataMachine\Abilities
 */

namespace DataMachine\Abilities;

use DataMachine\Api\Flows\FlowScheduling;
use DataMachine\Core\Admin\FlowFormatter;
use DataMachine\Core\Database\Flows\Flows;
use DataMachine\Core\Database\Jobs\Jobs;
use DataMachine\Core\Database\Pipelines\Pipelines;

defined( 'ABSPATH' ) || exit;

class FlowAbilities {

	private const DEFAULT_PER_PAGE = 20;

	private Flows $db_flows;
	private Pipelines $db_pipelines;
	private Jobs $db_jobs;

	public function __construct() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			return;
		}

		$this->db_flows     = new Flows();
		$this->db_pipelines = new Pipelines();
		$this->db_jobs      = new Jobs();
		$this->registerAbility();
	}

	private function registerAbility(): void {
		add_action(
			'wp_abilities_api_categories_init',
			function () {
				wp_register_ability_category(
					'datamachine',
					array(
						'label'       => __( 'Data Machine', 'data-machine' ),
						'description' => __( 'Data Machine flow and pipeline operations', 'data-machine' ),
					)
				);
			}
		);

		add_action(
			'wp_abilities_api_init',
			function () {
				$this->registerGetFlow();

				wp_register_ability(
					'datamachine/get-flows',
					array(
						'label'               => __( 'Get Flows', 'data-machine' ),
						'description'         => __( 'Get flows with optional filtering by pipeline ID or handler slug. Supports single flow retrieval and flexible output modes.', 'data-machine' ),
						'category'            => 'datamachine',
						'input_schema'        => array(
							'type'       => 'object',
							'properties' => array(
								'flow_id'      => array(
									'type'        => array( 'integer', 'null' ),
									'description' => __( 'Get a specific flow by ID (ignores pagination when provided)', 'data-machine' ),
								),
								'pipeline_id'  => array(
									'type'        => array( 'integer', 'null' ),
									'description' => __( 'Filter flows by pipeline ID', 'data-machine' ),
								),
								'handler_slug' => array(
									'type'        => array( 'string', 'null' ),
									'description' => __( 'Filter flows using this handler slug (any step that uses this handler)', 'data-machine' ),
								),
								'per_page'     => array(
									'type'        => 'integer',
									'default'     => self::DEFAULT_PER_PAGE,
									'minimum'     => 1,
									'maximum'     => 100,
									'description' => __( 'Number of flows per page', 'data-machine' ),
								),
								'offset'       => array(
									'type'        => 'integer',
									'default'     => 0,
									'minimum'     => 0,
									'description' => __( 'Offset for pagination', 'data-machine' ),
								),
								'output_mode'  => array(
									'type'        => 'string',
									'enum'        => array( 'full', 'summary', 'ids' ),
									'default'     => 'full',
									'description' => __( 'Output mode: full=all data with latest job status, summary=key fields only, ids=just flow_ids', 'data-machine' ),
								),
							),
						),
						'output_schema'       => array(
							'type'       => 'object',
							'properties' => array(
								'flows'           => array( 'type' => 'array' ),
								'total'           => array( 'type' => 'integer' ),
								'per_page'        => array( 'type' => 'integer' ),
								'offset'          => array( 'type' => 'integer' ),
								'filters_applied' => array( 'type' => 'object' ),
								'output_mode'     => array( 'type' => 'string' ),
							),
						),
						'execute_callback'    => array( $this, 'executeAbility' ),
						'permission_callback' => array( $this, 'checkPermission' ),
						'meta'                => array( 'show_in_rest' => true ),
					)
				);

				wp_register_ability(
					'datamachine/delete-flow',
					array(
						'label'               => __( 'Delete Flow', 'data-machine' ),
						'description'         => __( 'Delete a flow and unschedule its actions.', 'data-machine' ),
						'category'            => 'datamachine',
						'input_schema'        => array(
							'type'       => 'object',
							'required'   => array( 'flow_id' ),
							'properties' => array(
								'flow_id' => array(
									'type'        => 'integer',
									'description' => __( 'Flow ID to delete', 'data-machine' ),
								),
							),
						),
						'output_schema'       => array(
							'type'       => 'object',
							'properties' => array(
								'success'     => array( 'type' => 'boolean' ),
								'flow_id'     => array( 'type' => 'integer' ),
								'pipeline_id' => array( 'type' => 'integer' ),
								'message'     => array( 'type' => 'string' ),
								'error'       => array( 'type' => 'string' ),
							),
						),
						'execute_callback'    => array( $this, 'executeDeleteFlow' ),
						'permission_callback' => array( $this, 'checkPermission' ),
						'meta'                => array( 'show_in_rest' => true ),
					)
				);

				wp_register_ability(
					'datamachine/create-flow',
					array(
						'label'               => __( 'Create Flow', 'data-machine' ),
						'description'         => __( 'Create a new flow for a pipeline.', 'data-machine' ),
						'category'            => 'datamachine',
						'input_schema'        => array(
							'type'       => 'object',
							'required'   => array( 'pipeline_id' ),
							'properties' => array(
								'pipeline_id'       => array(
									'type'        => 'integer',
									'description' => __( 'Pipeline ID to create flow for', 'data-machine' ),
								),
								'flow_name'         => array(
									'type'        => 'string',
									'default'     => 'Flow',
									'description' => __( 'Name for the new flow', 'data-machine' ),
								),
								'scheduling_config' => array(
									'type'        => 'object',
									'description' => __( 'Scheduling configuration with interval property', 'data-machine' ),
									'properties'  => array(
										'interval' => array(
											'type'    => 'string',
											'default' => 'manual',
										),
									),
								),
								'flow_config'       => array(
									'type'        => 'object',
									'description' => __( 'Initial flow configuration', 'data-machine' ),
								),
							),
						),
						'output_schema'       => array(
							'type'       => 'object',
							'properties' => array(
								'success'      => array( 'type' => 'boolean' ),
								'flow_id'      => array( 'type' => 'integer' ),
								'flow_name'    => array( 'type' => 'string' ),
								'pipeline_id'  => array( 'type' => 'integer' ),
								'synced_steps' => array( 'type' => 'integer' ),
								'flow_data'    => array( 'type' => 'object' ),
								'error'        => array( 'type' => 'string' ),
							),
						),
						'execute_callback'    => array( $this, 'executeCreateFlow' ),
						'permission_callback' => array( $this, 'checkPermission' ),
						'meta'                => array( 'show_in_rest' => true ),
					)
				);

				wp_register_ability(
					'datamachine/update-flow',
					array(
						'label'               => __( 'Update Flow', 'data-machine' ),
						'description'         => __( 'Update flow name or scheduling.', 'data-machine' ),
						'category'            => 'datamachine',
						'input_schema'        => array(
							'type'       => 'object',
							'required'   => array( 'flow_id' ),
							'properties' => array(
								'flow_id'           => array(
									'type'        => 'integer',
									'description' => __( 'Flow ID to update', 'data-machine' ),
								),
								'flow_name'         => array(
									'type'        => 'string',
									'description' => __( 'New flow name', 'data-machine' ),
								),
								'scheduling_config' => array(
									'type'        => 'object',
									'description' => __( 'New scheduling configuration', 'data-machine' ),
									'properties'  => array(
										'interval' => array( 'type' => 'string' ),
									),
								),
							),
						),
						'output_schema'       => array(
							'type'       => 'object',
							'properties' => array(
								'success'   => array( 'type' => 'boolean' ),
								'flow_id'   => array( 'type' => 'integer' ),
								'flow_name' => array( 'type' => 'string' ),
								'flow_data' => array( 'type' => 'object' ),
								'message'   => array( 'type' => 'string' ),
								'error'     => array( 'type' => 'string' ),
							),
						),
						'execute_callback'    => array( $this, 'executeUpdateFlow' ),
						'permission_callback' => array( $this, 'checkPermission' ),
						'meta'                => array( 'show_in_rest' => true ),
					)
				);

				wp_register_ability(
					'datamachine/duplicate-flow',
					array(
						'label'               => __( 'Duplicate Flow', 'data-machine' ),
						'description'         => __( 'Duplicate a flow, optionally to a different pipeline.', 'data-machine' ),
						'category'            => 'datamachine',
						'input_schema'        => array(
							'type'       => 'object',
							'required'   => array( 'source_flow_id' ),
							'properties' => array(
								'source_flow_id'        => array(
									'type'        => 'integer',
									'description' => __( 'Source flow ID to duplicate', 'data-machine' ),
								),
								'target_pipeline_id'    => array(
									'type'        => 'integer',
									'description' => __( 'Target pipeline ID (defaults to source pipeline)', 'data-machine' ),
								),
								'flow_name'             => array(
									'type'        => 'string',
									'description' => __( 'Name for new flow (defaults to "Copy of {source}")', 'data-machine' ),
								),
								'scheduling_config'     => array(
									'type'        => 'object',
									'description' => __( 'Scheduling config (defaults to source interval)', 'data-machine' ),
								),
								'step_config_overrides' => array(
									'type'        => 'object',
									'description' => __( 'Step overrides keyed by step_type or execution_order', 'data-machine' ),
								),
							),
						),
						'output_schema'       => array(
							'type'       => 'object',
							'properties' => array(
								'success'            => array( 'type' => 'boolean' ),
								'flow_id'            => array( 'type' => 'integer' ),
								'flow_name'          => array( 'type' => 'string' ),
								'source_flow_id'     => array( 'type' => 'integer' ),
								'source_pipeline_id' => array( 'type' => 'integer' ),
								'target_pipeline_id' => array( 'type' => 'integer' ),
								'flow_step_ids'      => array( 'type' => 'array' ),
								'scheduling'         => array( 'type' => 'string' ),
								'error'              => array( 'type' => 'string' ),
							),
						),
						'execute_callback'    => array( $this, 'executeDuplicateFlow' ),
						'permission_callback' => array( $this, 'checkPermission' ),
						'meta'                => array( 'show_in_rest' => true ),
					)
				);
			}
		);
	}

	/**
	 * Register datamachine/get-flow ability for single flow retrieval with full metadata.
	 */
	private function registerGetFlow(): void {
		wp_register_ability(
			'datamachine/get-flow',
			array(
				'label'               => __( 'Get Flow', 'data-machine' ),
				'description'         => __( 'Get a single flow by ID with full metadata including handler config, scheduling info, and execution status.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'flow_id' ),
					'properties' => array(
						'flow_id' => array(
							'type'        => 'integer',
							'description' => __( 'Flow ID to retrieve', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'flow'    => array( 'type' => 'object' ),
						'error'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeGetFlow' ),
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
	 * Execute get-flow ability for single flow retrieval with full metadata.
	 *
	 * @param array $input Input parameters with flow_id.
	 * @return array Result with flow data including scheduling and handler metadata.
	 */
	public function executeGetFlow( array $input ): array {
		$flow_id = $input['flow_id'] ?? null;

		if ( ! is_numeric( $flow_id ) || (int) $flow_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'flow_id is required and must be a positive integer',
			);
		}

		$flow_id = (int) $flow_id;
		$flow    = $this->db_flows->get_flow( $flow_id );

		if ( ! $flow ) {
			return array(
				'success' => false,
				'error'   => sprintf( 'Flow %d not found', $flow_id ),
			);
		}

		$jobs       = $this->db_jobs->get_jobs_for_flow( $flow_id );
		$latest_job = $jobs[0] ?? null;

		$flow_payload = $this->formatFlowWithMetadata( $flow, $latest_job );

		return array(
			'success' => true,
			'flow'    => $flow_payload,
		);
	}

	/**
	 * Format a flow record with handler config and scheduling metadata.
	 *
	 * @param array      $flow       Flow data from database.
	 * @param array|null $latest_job Latest job for this flow (optional).
	 * @return array Formatted flow data with metadata.
	 */
	private function formatFlowWithMetadata( array $flow, ?array $latest_job = null ): array {
		$flow_config       = $flow['flow_config'] ?? array();
		$handler_abilities = new HandlerAbilities();

		foreach ( $flow_config as $flow_step_id => &$step_data ) {
			if ( ! isset( $step_data['handler_slug'] ) ) {
				continue;
			}

			$step_type    = $step_data['step_type'] ?? '';
			$handler_slug = $step_data['handler_slug'];

			$step_data['settings_display'] = apply_filters(
				'datamachine_get_handler_settings_display',
				array(),
				$flow_step_id,
				$step_type
			);

			$step_data['handler_config'] = $handler_abilities->applyDefaults(
				$handler_slug,
				$step_data['handler_config'] ?? array()
			);

			if ( ! empty( $step_data['settings_display'] ) && is_array( $step_data['settings_display'] ) ) {
				$display_parts                 = array_map(
					function ( $setting ) {
						return sprintf( '%s: %s', $setting['label'], $setting['display_value'] );
					},
					$step_data['settings_display']
				);
				$step_data['settings_summary'] = implode( ' | ', $display_parts );
			} else {
				$step_data['settings_summary'] = '';
			}
		}
		unset( $step_data );

		$scheduling_config = $flow['scheduling_config'] ?? array();
		$flow_id           = $flow['flow_id'] ?? null;

		$last_run_at     = $latest_job['created_at'] ?? null;
		$last_run_status = $latest_job['status'] ?? null;
		$is_running      = $latest_job && null === $latest_job['completed_at'];

		$next_run = $this->getNextRunTime( $flow_id );

		return array(
			'flow_id'           => $flow_id,
			'flow_name'         => $flow['flow_name'] ?? '',
			'pipeline_id'       => $flow['pipeline_id'] ?? null,
			'flow_config'       => $flow_config,
			'scheduling_config' => $scheduling_config,
			'last_run'          => $last_run_at,
			'last_run_status'   => $last_run_status,
			'last_run_display'  => \DataMachine\Core\Admin\DateFormatter::format_for_display( $last_run_at, $last_run_status ),
			'is_running'        => $is_running,
			'next_run'          => $next_run,
			'next_run_display'  => \DataMachine\Core\Admin\DateFormatter::format_for_display( $next_run ),
		);
	}

	/**
	 * Determine next scheduled run time for a flow if Action Scheduler is available.
	 *
	 * @param int|null $flow_id Flow ID.
	 * @return string|null Next run timestamp or null.
	 */
	private function getNextRunTime( ?int $flow_id ): ?string {
		if ( ! $flow_id || ! function_exists( 'as_next_scheduled_action' ) ) {
			return null;
		}

		$next_timestamp = as_next_scheduled_action( 'datamachine_run_flow_now', array( $flow_id ), 'data-machine' );

		return $next_timestamp ? wp_date( 'Y-m-d H:i:s', $next_timestamp ) : null;
	}

	public function executeAbility( array $input ): array {
		try {
			$flow_id      = $input['flow_id'] ?? null;
			$pipeline_id  = $input['pipeline_id'] ?? null;
			$handler_slug = $input['handler_slug'] ?? null;
			$per_page     = (int) ( $input['per_page'] ?? self::DEFAULT_PER_PAGE );
			$offset       = (int) ( $input['offset'] ?? 0 );
			$output_mode  = $input['output_mode'] ?? 'full';

			if ( ! in_array( $output_mode, array( 'full', 'summary', 'ids' ), true ) ) {
				$output_mode = 'full';
			}

			// Direct flow lookup by ID - bypasses pagination and filters.
			if ( $flow_id ) {
				$flow = $this->db_flows->get_flow( (int) $flow_id );
				if ( ! $flow ) {
					return array(
						'success'         => true,
						'flows'           => array(),
						'total'           => 0,
						'per_page'        => $per_page,
						'offset'          => $offset,
						'output_mode'     => $output_mode,
						'filters_applied' => array( 'flow_id' => $flow_id ),
					);
				}

				$formatted_flow = $this->formatFlowByMode( $flow, $output_mode );

				return array(
					'success'         => true,
					'flows'           => array( $formatted_flow ),
					'total'           => 1,
					'per_page'        => $per_page,
					'offset'          => $offset,
					'output_mode'     => $output_mode,
					'filters_applied' => array( 'flow_id' => $flow_id ),
				);
			}

			$filters_applied = array(
				'pipeline_id'  => $pipeline_id,
				'handler_slug' => $handler_slug,
			);

			$flows = array();
			$total = 0;

			if ( $pipeline_id ) {
				$flows = $this->db_flows->get_flows_for_pipeline_paginated( $pipeline_id, $per_page, $offset );
				$total = $this->db_flows->count_flows_for_pipeline( $pipeline_id );
			} else {
				$flows = $this->getAllFlowsPaginated( $per_page, $offset );
				$total = $this->countAllFlows();
			}

			if ( $handler_slug ) {
				$flows = $this->filterByHandlerSlug( $flows, $handler_slug );
			}

			$formatted_flows = $this->formatFlowsByMode( $flows, $output_mode );

			return array(
				'success'         => true,
				'flows'           => $formatted_flows,
				'total'           => $total,
				'per_page'        => $per_page,
				'offset'          => $offset,
				'output_mode'     => $output_mode,
				'filters_applied' => $filters_applied,
			);
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	private function getAllFlowsPaginated( int $per_page, int $offset ): array {
		$db_pipelines  = new \DataMachine\Core\Database\Pipelines\Pipelines();
		$all_pipelines = $db_pipelines->get_pipelines_list();
		$all_flows     = array();

		foreach ( $all_pipelines as $pipeline ) {
			$pipeline_flows = $this->db_flows->get_flows_for_pipeline( $pipeline['pipeline_id'] );
			$all_flows      = array_merge( $all_flows, $pipeline_flows );
		}

		return array_slice( $all_flows, $offset, $per_page );
	}

	private function countAllFlows(): int {
		$db_pipelines  = new \DataMachine\Core\Database\Pipelines\Pipelines();
		$all_pipelines = $db_pipelines->get_pipelines_list();
		$total         = 0;

		foreach ( $all_pipelines as $pipeline ) {
			$total += $this->db_flows->count_flows_for_pipeline( $pipeline['pipeline_id'] );
		}

		return $total;
	}

	private function filterByHandlerSlug( array $flows, string $handler_slug ): array {
		return array_filter(
			$flows,
			function ( $flow ) use ( $handler_slug ) {
				$flow_config = $flow['flow_config'] ?? array();

				foreach ( $flow_config as $flow_step_id => $step_data ) {
					if ( ! empty( $step_data['handler_slug'] ) && $step_data['handler_slug'] === $handler_slug ) {
						return true;
					}
				}

				return false;
			}
		);
	}

	private function formatFlowsByMode( array $flows, string $output_mode ): array {
		if ( 'ids' === $output_mode ) {
			return $this->formatIds( $flows );
		}

		return array_map(
			function ( $flow ) use ( $output_mode ) {
				return $this->formatFlowByMode( $flow, $output_mode );
			},
			$flows
		);
	}

	private function formatFlowByMode( array $flow, string $output_mode ): array {
		if ( 'ids' === $output_mode ) {
			return (int) $flow['flow_id'];
		}

		if ( 'summary' === $output_mode ) {
			return $this->formatSummary( $flow );
		}

		return $this->formatFull( $flow );
	}

	private function formatFull( array $flow ): array {
		$flow_id     = (int) $flow['flow_id'];
		$latest_jobs = $this->db_jobs->get_latest_jobs_by_flow_ids( array( $flow_id ) );
		$latest_job  = $latest_jobs[ $flow_id ] ?? null;

		return FlowFormatter::format_flow_for_response( $flow, $latest_job );
	}

	private function formatSummary( array $flow ): array {
		$flow_id     = (int) $flow['flow_id'];
		$latest_jobs = $this->db_jobs->get_latest_jobs_by_flow_ids( array( $flow_id ) );
		$latest_job  = $latest_jobs[ $flow_id ] ?? null;

		return array(
			'flow_id'         => $flow_id,
			'flow_name'       => $flow['flow_name'] ?? '',
			'pipeline_id'     => $flow['pipeline_id'] ?? null,
			'last_run_status' => $latest_job['status'] ?? null,
		);
	}

	private function formatIds( array $flows ): array {
		return array_map(
			function ( $flow ) {
				return (int) $flow['flow_id'];
			},
			$flows
		);
	}

	/**
	 * Execute delete flow ability.
	 *
	 * @param array $input Input parameters with flow_id.
	 * @return array Result with success status.
	 */
	public function executeDeleteFlow( array $input ): array {
		$flow_id = $input['flow_id'] ?? null;

		if ( ! is_numeric( $flow_id ) || (int) $flow_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'flow_id is required and must be a positive integer',
			);
		}

		$flow_id = (int) $flow_id;
		$flow    = $this->db_flows->get_flow( $flow_id );

		if ( ! $flow ) {
			do_action( 'datamachine_log', 'error', 'Flow not found for deletion', array( 'flow_id' => $flow_id ) );
			return array(
				'success' => false,
				'error'   => 'Flow not found',
			);
		}

		$pipeline_id = (int) ( $flow['pipeline_id'] ?? 0 );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'datamachine_run_flow_now', array( $flow_id ), 'data-machine' );
		}

		$success = $this->db_flows->delete_flow( $flow_id );

		if ( $success ) {
			do_action(
				'datamachine_log',
				'info',
				'Flow deleted successfully',
				array(
					'flow_id'     => $flow_id,
					'pipeline_id' => $pipeline_id,
				)
			);

			return array(
				'success'     => true,
				'flow_id'     => $flow_id,
				'pipeline_id' => $pipeline_id,
				'message'     => 'Flow deleted successfully',
			);
		}

		do_action( 'datamachine_log', 'error', 'Failed to delete flow', array( 'flow_id' => $flow_id ) );
		return array(
			'success' => false,
			'error'   => 'Failed to delete flow',
		);
	}

	/**
	 * Execute create flow ability.
	 *
	 * @param array $input Input parameters with pipeline_id, optional flow_name and scheduling_config.
	 * @return array Result with flow data on success.
	 */
	public function executeCreateFlow( array $input ): array {
		$pipeline_id = $input['pipeline_id'] ?? null;

		if ( ! is_numeric( $pipeline_id ) || (int) $pipeline_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'pipeline_id is required and must be a positive integer',
			);
		}

		$pipeline_id = (int) $pipeline_id;
		$pipeline    = $this->db_pipelines->get_pipeline( $pipeline_id );

		if ( ! $pipeline ) {
			do_action( 'datamachine_log', 'error', 'Pipeline not found for flow creation', array( 'pipeline_id' => $pipeline_id ) );
			return array(
				'success' => false,
				'error'   => 'Pipeline not found',
			);
		}

		$flow_name = sanitize_text_field( wp_unslash( $input['flow_name'] ?? 'Flow' ) );
		if ( empty( trim( $flow_name ) ) ) {
			$flow_name = 'Flow';
		}

		$scheduling_config = $input['scheduling_config'] ?? array( 'interval' => 'manual' );
		$flow_config       = $input['flow_config'] ?? array();

		$flow_data = array(
			'pipeline_id'       => $pipeline_id,
			'flow_name'         => $flow_name,
			'flow_config'       => $flow_config,
			'scheduling_config' => $scheduling_config,
		);

		$flow_id = $this->db_flows->create_flow( $flow_data );
		if ( ! $flow_id ) {
			do_action(
				'datamachine_log',
				'error',
				'Failed to create flow',
				array(
					'pipeline_id' => $pipeline_id,
					'flow_name'   => $flow_name,
				)
			);
			return array(
				'success' => false,
				'error'   => 'Failed to create flow',
			);
		}

		$pipeline_config = $pipeline['pipeline_config'] ?? array();
		$synced_steps    = 0;

		if ( ! empty( $pipeline_config ) ) {
			$pipeline_steps = is_array( $pipeline_config ) ? array_values( $pipeline_config ) : array();
			$this->syncStepsToFlow( $flow_id, $pipeline_id, $pipeline_steps, $pipeline_config );
			$synced_steps = count( $pipeline_config );
		}

		if ( isset( $scheduling_config['interval'] ) && 'manual' !== $scheduling_config['interval'] ) {
			$scheduling_result = FlowScheduling::handle_scheduling_update( $flow_id, $scheduling_config );
			if ( is_wp_error( $scheduling_result ) ) {
				do_action(
					'datamachine_log',
					'error',
					'Failed to schedule flow with Action Scheduler',
					array(
						'flow_id' => $flow_id,
						'error'   => $scheduling_result->get_error_message(),
					)
				);
			}
		}

		$flow = $this->db_flows->get_flow( $flow_id );

		do_action(
			'datamachine_log',
			'info',
			'Flow created successfully',
			array(
				'flow_id'      => $flow_id,
				'flow_name'    => $flow_name,
				'pipeline_id'  => $pipeline_id,
				'synced_steps' => $synced_steps,
			)
		);

		return array(
			'success'      => true,
			'flow_id'      => $flow_id,
			'flow_name'    => $flow_name,
			'pipeline_id'  => $pipeline_id,
			'flow_data'    => $flow,
			'synced_steps' => $synced_steps,
		);
	}

	/**
	 * Execute update flow ability.
	 *
	 * @param array $input Input parameters with flow_id, optional flow_name and scheduling_config.
	 * @return array Result with updated flow data.
	 */
	public function executeUpdateFlow( array $input ): array {
		$flow_id           = $input['flow_id'] ?? null;
		$flow_name         = $input['flow_name'] ?? null;
		$scheduling_config = $input['scheduling_config'] ?? null;

		if ( ! is_numeric( $flow_id ) || (int) $flow_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'flow_id is required and must be a positive integer',
			);
		}

		$flow_id = (int) $flow_id;

		if ( null === $flow_name && null === $scheduling_config ) {
			return array(
				'success' => false,
				'error'   => 'Must provide flow_name or scheduling_config to update',
			);
		}

		$flow = $this->db_flows->get_flow( $flow_id );
		if ( ! $flow ) {
			return array(
				'success' => false,
				'error'   => 'Flow not found',
			);
		}

		if ( null !== $flow_name ) {
			$flow_name = sanitize_text_field( wp_unslash( $flow_name ) );
			if ( empty( trim( $flow_name ) ) ) {
				return array(
					'success' => false,
					'error'   => 'Flow name cannot be empty',
				);
			}

			$success = $this->db_flows->update_flow(
				$flow_id,
				array( 'flow_name' => $flow_name )
			);

			if ( ! $success ) {
				return array(
					'success' => false,
					'error'   => 'Failed to update flow name',
				);
			}
		}

		if ( null !== $scheduling_config ) {
			$result = FlowScheduling::handle_scheduling_update( $flow_id, $scheduling_config );
			if ( is_wp_error( $result ) ) {
				return array(
					'success' => false,
					'error'   => $result->get_error_message(),
				);
			}
		}

		$updated_flow = $this->db_flows->get_flow( $flow_id );

		do_action(
			'datamachine_log',
			'info',
			'Flow updated successfully',
			array(
				'flow_id'   => $flow_id,
				'flow_name' => $updated_flow['flow_name'] ?? '',
			)
		);

		return array(
			'success'   => true,
			'flow_id'   => $flow_id,
			'flow_name' => $updated_flow['flow_name'] ?? '',
			'flow_data' => $updated_flow,
			'message'   => 'Flow updated successfully',
		);
	}

	/**
	 * Execute duplicate flow ability.
	 *
	 * @param array $input Input parameters with source_flow_id, optional target_pipeline_id, flow_name, etc.
	 * @return array Result with duplicated flow data.
	 */
	public function executeDuplicateFlow( array $input ): array {
		$source_flow_id = $input['source_flow_id'] ?? null;

		if ( ! is_numeric( $source_flow_id ) || (int) $source_flow_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'source_flow_id is required and must be a positive integer',
			);
		}

		$source_flow_id = (int) $source_flow_id;
		$source_flow    = $this->db_flows->get_flow( $source_flow_id );

		if ( ! $source_flow ) {
			do_action( 'datamachine_log', 'error', 'Source flow not found for copy', array( 'source_flow_id' => $source_flow_id ) );
			return array(
				'success' => false,
				'error'   => 'Source flow not found',
			);
		}

		$source_pipeline_id = (int) $source_flow['pipeline_id'];
		$target_pipeline_id = isset( $input['target_pipeline_id'] ) ? (int) $input['target_pipeline_id'] : $source_pipeline_id;
		$is_cross_pipeline  = ( $target_pipeline_id !== $source_pipeline_id );

		$source_pipeline = $this->db_pipelines->get_pipeline( $source_pipeline_id );
		if ( ! $source_pipeline ) {
			return array(
				'success' => false,
				'error'   => 'Source pipeline not found',
			);
		}

		$target_pipeline = $this->db_pipelines->get_pipeline( $target_pipeline_id );
		if ( ! $target_pipeline ) {
			return array(
				'success' => false,
				'error'   => 'Target pipeline not found',
			);
		}

		if ( $is_cross_pipeline ) {
			$source_pipeline_config = $source_pipeline['pipeline_config'] ?? array();
			$target_pipeline_config = $target_pipeline['pipeline_config'] ?? array();

			$compatibility = $this->validatePipelineCompatibility( $source_pipeline_config, $target_pipeline_config );
			if ( ! $compatibility['compatible'] ) {
				do_action(
					'datamachine_log',
					'error',
					'Pipeline compatibility validation failed',
					array(
						'source_pipeline_id' => $source_pipeline_id,
						'target_pipeline_id' => $target_pipeline_id,
						'error'              => $compatibility['error'],
					)
				);
				return array(
					'success' => false,
					'error'   => $compatibility['error'],
				);
			}
		}

		$new_flow_name = isset( $input['flow_name'] ) && ! empty( $input['flow_name'] )
			? sanitize_text_field( $input['flow_name'] )
			: sprintf( 'Copy of %s', $source_flow['flow_name'] );

		$requested_scheduling_config = $input['scheduling_config'] ?? ( $source_flow['scheduling_config'] ?? array() );
		$scheduling_config           = $this->getIntervalOnlySchedulingConfig(
			is_array( $requested_scheduling_config ) ? $requested_scheduling_config : array()
		);

		$flow_data = array(
			'pipeline_id'       => $target_pipeline_id,
			'flow_name'         => $new_flow_name,
			'flow_config'       => array(),
			'scheduling_config' => $scheduling_config,
		);

		$new_flow_id = $this->db_flows->create_flow( $flow_data );
		if ( ! $new_flow_id ) {
			do_action(
				'datamachine_log',
				'error',
				'Failed to create flow during copy',
				array(
					'source_flow_id'     => $source_flow_id,
					'target_pipeline_id' => $target_pipeline_id,
				)
			);
			return array(
				'success' => false,
				'error'   => 'Failed to create new flow',
			);
		}

		$new_flow_config = $this->buildCopiedFlowConfig(
			$source_flow['flow_config'] ?? array(),
			$source_pipeline['pipeline_config'] ?? array(),
			$target_pipeline['pipeline_config'] ?? array(),
			$new_flow_id,
			$target_pipeline_id,
			$input['step_config_overrides'] ?? array()
		);

		$this->db_flows->update_flow(
			$new_flow_id,
			array( 'flow_config' => $new_flow_config )
		);

		if ( isset( $scheduling_config['interval'] ) && 'manual' !== $scheduling_config['interval'] ) {
			$scheduling_result = FlowScheduling::handle_scheduling_update( $new_flow_id, $scheduling_config );
			if ( is_wp_error( $scheduling_result ) ) {
				do_action(
					'datamachine_log',
					'error',
					'Failed to schedule copied flow',
					array(
						'flow_id' => $new_flow_id,
						'error'   => $scheduling_result->get_error_message(),
					)
				);
			}
		}

		$new_flow = $this->db_flows->get_flow( $new_flow_id );

		do_action(
			'datamachine_log',
			'info',
			'Flow copied successfully',
			array(
				'source_flow_id'     => $source_flow_id,
				'new_flow_id'        => $new_flow_id,
				'source_pipeline_id' => $source_pipeline_id,
				'target_pipeline_id' => $target_pipeline_id,
				'cross_pipeline'     => $is_cross_pipeline,
			)
		);

		return array(
			'success'            => true,
			'flow_id'            => $new_flow_id,
			'flow_name'          => $new_flow_name,
			'source_flow_id'     => $source_flow_id,
			'source_pipeline_id' => $source_pipeline_id,
			'target_pipeline_id' => $target_pipeline_id,
			'flow_data'          => $new_flow,
			'flow_step_ids'      => array_keys( $new_flow_config ),
			'scheduling'         => $scheduling_config['interval'] ?? 'manual',
		);
	}

	/**
	 * Sync pipeline steps to a flow's configuration.
	 *
	 * @param int   $flow_id Flow ID.
	 * @param int   $pipeline_id Pipeline ID.
	 * @param array $steps Array of pipeline step data.
	 * @param array $pipeline_config Full pipeline config.
	 * @return bool Success status.
	 */
	private function syncStepsToFlow( int $flow_id, int $pipeline_id, array $steps, array $pipeline_config = array() ): bool {
		$flow = $this->db_flows->get_flow( $flow_id );
		if ( ! $flow ) {
			do_action( 'datamachine_log', 'error', 'Flow not found for step sync', array( 'flow_id' => $flow_id ) );
			return false;
		}

		$flow_config = $flow['flow_config'] ?? array();

		foreach ( $steps as $step ) {
			$pipeline_step_id = $step['pipeline_step_id'] ?? null;
			if ( ! $pipeline_step_id ) {
				continue;
			}

			$flow_step_id = apply_filters( 'datamachine_generate_flow_step_id', '', $pipeline_step_id, $flow_id );

			$enabled_tools = $pipeline_config[ $pipeline_step_id ]['enabled_tools'] ?? array();

			$flow_config[ $flow_step_id ] = array(
				'flow_step_id'     => $flow_step_id,
				'step_type'        => $step['step_type'] ?? '',
				'pipeline_step_id' => $pipeline_step_id,
				'pipeline_id'      => $pipeline_id,
				'flow_id'          => $flow_id,
				'execution_order'  => $step['execution_order'] ?? 0,
				'enabled_tools'    => $enabled_tools,
				'handler'          => null,
			);
		}

		$success = $this->db_flows->update_flow(
			$flow_id,
			array( 'flow_config' => $flow_config )
		);

		if ( ! $success ) {
			do_action(
				'datamachine_log',
				'error',
				'Flow step sync failed - database update failed',
				array(
					'flow_id'     => $flow_id,
					'steps_count' => count( $steps ),
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Get an interval-only scheduling config for copied flows.
	 *
	 * @param array $scheduling_config Source scheduling config.
	 * @return array Interval-only config.
	 */
	private function getIntervalOnlySchedulingConfig( array $scheduling_config ): array {
		$interval = $scheduling_config['interval'] ?? 'manual';

		if ( ! is_string( $interval ) || '' === $interval ) {
			$interval = 'manual';
		}

		return array( 'interval' => $interval );
	}

	/**
	 * Validate that two pipelines have compatible step structures.
	 *
	 * @param array $source_config Source pipeline config.
	 * @param array $target_config Target pipeline config.
	 * @return array{compatible: bool, error?: string}
	 */
	private function validatePipelineCompatibility( array $source_config, array $target_config ): array {
		$source_steps = $this->getOrderedStepTypes( $source_config );
		$target_steps = $this->getOrderedStepTypes( $target_config );

		if ( $source_steps === $target_steps ) {
			return array( 'compatible' => true );
		}

		return array(
			'compatible' => false,
			'error'      => sprintf(
				'Incompatible pipeline structures. Source: [%s], Target: [%s]',
				implode( ', ', $source_steps ),
				implode( ', ', $target_steps )
			),
		);
	}

	/**
	 * Get ordered step types from pipeline config.
	 *
	 * @param array $pipeline_config Pipeline configuration.
	 * @return array Step types ordered by execution_order.
	 */
	private function getOrderedStepTypes( array $pipeline_config ): array {
		$steps = array_values( $pipeline_config );
		usort( $steps, fn( $a, $b ) => ( $a['execution_order'] ?? 0 ) <=> ( $b['execution_order'] ?? 0 ) );
		return array_map( fn( $s ) => $s['step_type'] ?? '', $steps );
	}

	/**
	 * Build flow config for copied flow, mapping source to target pipeline steps.
	 *
	 * @param array $source_flow_config Source flow configuration.
	 * @param array $source_pipeline_config Source pipeline configuration.
	 * @param array $target_pipeline_config Target pipeline configuration.
	 * @param int   $new_flow_id New flow ID.
	 * @param int   $target_pipeline_id Target pipeline ID.
	 * @param array $overrides Step configuration overrides.
	 * @return array New flow configuration.
	 */
	private function buildCopiedFlowConfig(
		array $source_flow_config,
		array $source_pipeline_config,
		array $target_pipeline_config,
		int $new_flow_id,
		int $target_pipeline_id,
		array $overrides = array()
	): array {
		$new_flow_config = array();

		$target_steps_by_order = array();
		foreach ( $target_pipeline_config as $pipeline_step_id => $step ) {
			$order                           = $step['execution_order'] ?? 0;
			$target_steps_by_order[ $order ] = array(
				'pipeline_step_id' => $pipeline_step_id,
				'step_type'        => $step['step_type'] ?? '',
			);
		}

		$source_steps_by_order = array();
		foreach ( $source_flow_config as $flow_step_id => $step_config ) {
			$order                           = $step_config['execution_order'] ?? 0;
			$source_steps_by_order[ $order ] = $step_config;
		}

		foreach ( $target_steps_by_order as $order => $target_step ) {
			$target_pipeline_step_id = $target_step['pipeline_step_id'];
			$step_type               = $target_step['step_type'];
			$new_flow_step_id        = $target_pipeline_step_id . '_' . $new_flow_id;

			$new_step_config = array(
				'flow_step_id'     => $new_flow_step_id,
				'step_type'        => $step_type,
				'pipeline_step_id' => $target_pipeline_step_id,
				'pipeline_id'      => $target_pipeline_id,
				'flow_id'          => $new_flow_id,
				'execution_order'  => $order,
			);

			if ( isset( $source_steps_by_order[ $order ] ) ) {
				$source_step = $source_steps_by_order[ $order ];

				if ( ! empty( $source_step['handler_slug'] ) ) {
					$new_step_config['handler_slug'] = $source_step['handler_slug'];
				}
				if ( ! empty( $source_step['handler_config'] ) ) {
					$new_step_config['handler_config'] = $source_step['handler_config'];
				}
				if ( ! empty( $source_step['user_message'] ) ) {
					$new_step_config['user_message'] = $source_step['user_message'];
				}
				if ( isset( $source_step['enabled_tools'] ) ) {
					$new_step_config['enabled_tools'] = $source_step['enabled_tools'];
				}
			}

			$override = $this->resolveOverride( $overrides, $step_type, $order );
			if ( $override ) {
				if ( ! empty( $override['handler_slug'] ) ) {
					$new_step_config['handler_slug'] = $override['handler_slug'];
				}
				if ( ! empty( $override['handler_config'] ) ) {
					$existing_config                   = $new_step_config['handler_config'] ?? array();
					$new_step_config['handler_config'] = array_merge( $existing_config, $override['handler_config'] );
				}
				if ( ! empty( $override['user_message'] ) ) {
					$new_step_config['user_message'] = $override['user_message'];
				}
			}

			$new_flow_config[ $new_flow_step_id ] = $new_step_config;
		}

		return $new_flow_config;
	}

	/**
	 * Resolve override config by step_type or execution_order.
	 *
	 * @param array  $overrides Override configurations.
	 * @param string $step_type Step type.
	 * @param int    $execution_order Execution order.
	 * @return array|null Override config or null.
	 */
	private function resolveOverride( array $overrides, string $step_type, int $execution_order ): ?array {
		if ( isset( $overrides[ $step_type ] ) ) {
			return $overrides[ $step_type ];
		}

		if ( isset( $overrides[ (string) $execution_order ] ) ) {
			return $overrides[ (string) $execution_order ];
		}

		if ( isset( $overrides[ $execution_order ] ) ) {
			return $overrides[ $execution_order ];
		}

		return null;
	}
}
