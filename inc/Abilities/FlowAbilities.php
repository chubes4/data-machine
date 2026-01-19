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

use DataMachine\Core\Admin\FlowFormatter;
use DataMachine\Services\HandlerService;
use DataMachine\Core\Database\Flows\Flows;
use DataMachine\Core\Database\Jobs\Jobs;

defined( 'ABSPATH' ) || exit;

class FlowAbilities {

	private const DEFAULT_PER_PAGE = 20;

	private Flows $db_flows;
	private Jobs $db_jobs;
	private HandlerService $handler_service;

	public function __construct() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			return;
		}

		$this->db_flows        = new Flows();
		$this->db_jobs         = new Jobs();
		$this->handler_service = new HandlerService();
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
				wp_register_ability(
					'datamachine/list-flows',
					array(
						'label'               => __( 'List Flows', 'data-machine' ),
						'description'         => __( 'List flows with optional filtering by pipeline ID or handler slug', 'data-machine' ),
						'category'            => 'datamachine',
						'input_schema'        => array(
							'type'       => 'object',
							'properties' => array(
								'pipeline_id'  => array(
									'type'        => array( 'integer', 'null' ),
									'description' => __( 'Filter flows by pipeline ID', 'data-machine' ),
								),
								'flow_id'      => array(
									'type'        => array( 'integer', 'null' ),
									'description' => __( 'Get a specific flow by ID', 'data-machine' ),
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
							),
						),
						'execute_callback'    => array( $this, 'executeAbility' ),
						'permission_callback' => function () {
							if ( defined( 'WP_CLI' ) && WP_CLI ) {
								return true;
							}
							return current_user_can( 'manage_options' );
						},
						'meta'                => array( 'show_in_rest' => true ),
					)
				);
			}
		);
	}

	public function executeAbility( array $input ): array {
		try {
			$flow_id      = $input['flow_id'] ?? null;
			$pipeline_id  = $input['pipeline_id'] ?? null;
			$handler_slug = $input['handler_slug'] ?? null;
			$per_page     = (int) ( $input['per_page'] ?? self::DEFAULT_PER_PAGE );
			$offset       = (int) ( $input['offset'] ?? 0 );

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
						'filters_applied' => array( 'flow_id' => $flow_id ),
					);
				}
				$latest_jobs = $this->db_jobs->get_latest_jobs_by_flow_ids( array( $flow['flow_id'] ) );
				$latest_job  = $latest_jobs[ (int) $flow['flow_id'] ] ?? null;
				return array(
					'success'         => true,
					'flows'           => array( FlowFormatter::format_flow_for_response( $flow, $latest_job ) ),
					'total'           => 1,
					'per_page'        => $per_page,
					'offset'          => $offset,
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

			$flow_ids    = array_column( $flows, 'flow_id' );
			$latest_jobs = $this->db_jobs->get_latest_jobs_by_flow_ids( $flow_ids );

			$formatted_flows = array_map(
				function ( $flow ) use ( $latest_jobs ) {
					$flow_id    = (int) $flow['flow_id'];
					$latest_job = $latest_jobs[ $flow_id ] ?? null;
					return FlowFormatter::format_flow_for_response( $flow, $latest_job );
				},
				$flows
			);

			return array(
				'success'         => true,
				'flows'           => $formatted_flows,
				'total'           => $total,
				'per_page'        => $per_page,
				'offset'          => $offset,
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
}
