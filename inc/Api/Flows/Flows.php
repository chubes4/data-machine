<?php
/**
 * REST API Flows Endpoint
 *
 * Provides REST API access to flow CRUD operations.
 * Requires WordPress manage_options capability.
 *
 * @package DataMachine\Api\Flows
 */

namespace DataMachine\Api\Flows;

use DataMachine\Core\Admin\DateFormatter;
use DataMachine\Core\Database\Jobs\Jobs;
use DataMachine\Services\FlowManager;
use DataMachine\Services\HandlerService;
use WP_REST_Server;

if (!defined('WPINC')) {
	die;
}

class Flows {

	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action('rest_api_init', [self::class, 'register_routes']);
	}

	/**
	 * Register flow CRUD endpoints
	 */
	public static function register_routes() {
		register_rest_route('datamachine/v1', '/flows', [
			'methods' => 'POST',
			'callback' => [self::class, 'handle_create_flow'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'pipeline_id' => [
					'required' => true,
					'type' => 'integer',
					'description' => __('Parent pipeline ID', 'data-machine'),
					'validate_callback' => function($param) {
						return is_numeric($param) && $param > 0;
					},
					'sanitize_callback' => function($param) {
						return (int) $param;
					}
				],
				'flow_name' => [
					'required' => false,
					'type' => 'string',
					'default' => 'Flow',
					'description' => __('Flow name', 'data-machine'),
					'sanitize_callback' => function($param) {
						return sanitize_text_field($param);
					}
				],
				'flow_config' => [
					'required' => false,
					'type' => 'array',
					'description' => __('Flow configuration (handler settings per step)', 'data-machine'),
				],
				'scheduling_config' => [
					'required' => false,
					'type' => 'array',
					'description' => __('Scheduling configuration', 'data-machine'),
				]
			]
		]);

		register_rest_route('datamachine/v1', '/flows', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [self::class, 'handle_get_flows'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'pipeline_id' => [
					'required' => false,
					'type' => 'integer',
					'sanitize_callback' => 'absint',
					'description' => __('Optional pipeline ID to filter flows', 'data-machine'),
				],
				'per_page' => [
					'required' => false,
					'type' => 'integer',
					'default' => 20,
					'minimum' => 1,
					'maximum' => 100,
					'sanitize_callback' => 'absint',
					'description' => __('Number of flows per page', 'data-machine'),
				],
				'offset' => [
					'required' => false,
					'type' => 'integer',
					'default' => 0,
					'minimum' => 0,
					'sanitize_callback' => 'absint',
					'description' => __('Offset for pagination', 'data-machine'),
				],
			]
		]);

		register_rest_route('datamachine/v1', '/flows/(?P<flow_id>\d+)', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [self::class, 'handle_get_single_flow'],
				'permission_callback' => [self::class, 'check_permission'],
				'args' => [
					'flow_id' => [
						'required' => true,
						'type' => 'integer',
						'sanitize_callback' => 'absint',
						'description' => __('Flow ID to retrieve', 'data-machine'),
					],
				]
			],
			[
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => [self::class, 'handle_delete_flow'],
				'permission_callback' => [self::class, 'check_permission'],
				'args' => [
					'flow_id' => [
						'required' => true,
						'type' => 'integer',
						'sanitize_callback' => 'absint',
						'description' => __('Flow ID to delete', 'data-machine'),
					],
				]
			],
			[
				'methods' => 'PATCH',
				'callback' => [self::class, 'handle_update_flow'],
				'permission_callback' => [self::class, 'check_permission'],
				'args' => [
					'flow_id' => [
						'required' => true,
						'type' => 'integer',
						'sanitize_callback' => 'absint',
						'description' => __('Flow ID to update', 'data-machine'),
					],
					'flow_name' => [
						'required' => false,
						'type' => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description' => __('New flow title', 'data-machine'),
					],
					'scheduling_config' => [
						'required' => false,
						'type' => 'object',
						'description' => __('Scheduling configuration', 'data-machine'),
					],
				]
			]
		]);

		register_rest_route('datamachine/v1', '/flows/(?P<flow_id>\d+)/duplicate', [
			'methods' => 'POST',
			'callback' => [self::class, 'handle_duplicate_flow'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'flow_id' => [
					'required' => true,
					'type' => 'integer',
					'sanitize_callback' => 'absint',
					'description' => __('Source flow ID to duplicate', 'data-machine'),
				],
			]
		]);

		register_rest_route('datamachine/v1', '/flows/problems', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [self::class, 'handle_get_problem_flows'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'threshold' => [
					'required' => false,
					'type' => 'integer',
					'sanitize_callback' => 'absint',
					'description' => __('Minimum consecutive failures (defaults to problem_flow_threshold setting)', 'data-machine'),
				],
			]
		]);
	}

	/**
	 * Check if user has permission to manage flows
	 */
	public static function check_permission($request) {
		if (!current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have permission to create flows.', 'data-machine'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Handle flow creation request
	 */
	public static function handle_create_flow($request) {
		$manager = new FlowManager();

		$options = [];
		if ($request->get_param('flow_config')) {
			$options['flow_config'] = $request->get_param('flow_config');
		}
		if ($request->get_param('scheduling_config')) {
			$options['scheduling_config'] = $request->get_param('scheduling_config');
		}

		$result = $manager->create(
			$request->get_param('pipeline_id'),
			$request->get_param('flow_name') ?? 'Flow',
			$options
		);

		if (!$result) {
			return new \WP_Error(
				'flow_creation_failed',
				__('Failed to create flow.', 'data-machine'),
				['status' => 500]
			);
		}

		return rest_ensure_response([
			'success' => true,
			'data' => $result
		]);
	}

	/**
	 * Handle flow deletion request
	 */
	public static function handle_delete_flow($request) {
		$flow_id = (int) $request->get_param('flow_id');

		$manager = new FlowManager();
		$success = $manager->delete($flow_id);

		if (!$success) {
			return new \WP_Error(
				'flow_deletion_failed',
				__('Failed to delete flow.', 'data-machine'),
				['status' => 500]
			);
		}

		return rest_ensure_response([
			'success' => true,
			'data' => ['flow_id' => $flow_id]
		]);
	}

	/**
	 * Handle flow duplication request
	 */
	public static function handle_duplicate_flow($request) {
		$source_flow_id = (int) $request->get_param('flow_id');

		$manager = new FlowManager();
		$result = $manager->duplicate($source_flow_id);

		if (!$result) {
			return new \WP_Error(
				'flow_duplication_failed',
				__('Failed to duplicate flow.', 'data-machine'),
				['status' => 500]
			);
		}

		return rest_ensure_response([
			'success' => true,
			'data' => $result
		]);
	}

	/**
	 * Handle flows retrieval request with pagination support
	 */
	public static function handle_get_flows($request) {
		$pipeline_id = $request->get_param('pipeline_id');
		$per_page = $request->get_param('per_page') ?? 20;
		$offset = $request->get_param('offset') ?? 0;

		$db_jobs = new Jobs();

		if ($pipeline_id) {
			$db_flows = new \DataMachine\Core\Database\Flows\Flows();

			$flows = $db_flows->get_flows_for_pipeline_paginated($pipeline_id, $per_page, $offset);
			$total = $db_flows->count_flows_for_pipeline($pipeline_id);

			// Batch query latest jobs for all flows
			$flow_ids = array_column($flows, 'flow_id');
			$latest_jobs = $db_jobs->get_latest_jobs_by_flow_ids($flow_ids);

			$formatted_flows = array_map(function($flow) use ($latest_jobs) {
				$flow_id = (int) $flow['flow_id'];
				$latest_job = $latest_jobs[$flow_id] ?? null;
				return self::format_flow_for_response($flow, $latest_job);
			}, $flows);

			return rest_ensure_response([
				'success' => true,
				'data' => [
					'pipeline_id' => $pipeline_id,
					'flows' => $formatted_flows
				],
				'total' => $total,
				'per_page' => $per_page,
				'offset' => $offset
			]);
		}

		// Get all flows across all pipelines (no pagination for this case)
		$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
		$db_flows = new \DataMachine\Core\Database\Flows\Flows();
		$all_pipelines = $db_pipelines->get_pipelines_list();
		$all_flows = [];

		foreach ($all_pipelines as $pipeline) {
			$pipeline_flows = $db_flows->get_flows_for_pipeline($pipeline['pipeline_id']);

			// Batch query latest jobs for this pipeline's flows
			$flow_ids = array_column($pipeline_flows, 'flow_id');
			$latest_jobs = $db_jobs->get_latest_jobs_by_flow_ids($flow_ids);

			foreach ($pipeline_flows as $flow) {
				$flow_id = (int) $flow['flow_id'];
				$latest_job = $latest_jobs[$flow_id] ?? null;
				$all_flows[] = self::format_flow_for_response($flow, $latest_job);
			}
		}

		return rest_ensure_response([
			'success' => true,
			'data' => $all_flows
		]);
	}

	/**
	 * Handle single flow retrieval request with scheduling metadata
	 */
	public static function handle_get_single_flow($request) {
		$flow_id = (int) $request->get_param('flow_id');

		// Retrieve flow data via filter
		$db_flows = new \DataMachine\Core\Database\Flows\Flows();
		$flow = $db_flows->get_flow($flow_id);

		if (!$flow) {
			return new \WP_Error(
				'flow_not_found',
				__('Flow not found.', 'data-machine'),
				['status' => 404]
			);
		}

		// Get latest job for this flow
		$db_jobs = new Jobs();
		$jobs = $db_jobs->get_jobs_for_flow($flow_id);
		$latest_job = $jobs[0] ?? null;

		$flow_payload = self::format_flow_for_response($flow, $latest_job);

		return rest_ensure_response([
			'success' => true,
			'data' => $flow_payload
		]);
	}

	/**
	 * Format a flow record with handler config and scheduling metadata.
	 *
	 * @param array      $flow       Flow data from database
	 * @param array|null $latest_job Latest job for this flow (optional, for batch efficiency)
	 */
	private static function format_flow_for_response(array $flow, ?array $latest_job = null): array {
		$flow_config = $flow['flow_config'] ?? [];

		$handler_service = new HandlerService();

		foreach ($flow_config as $flow_step_id => &$step_data) {
			if (!isset($step_data['handler_slug'])) {
				continue;
			}

			$step_type = $step_data['step_type'] ?? '';
			$handler_slug = $step_data['handler_slug'];

			$step_data['settings_display'] = apply_filters(
				'datamachine_get_handler_settings_display',
				[],
				$flow_step_id,
				$step_type
			);

			$step_data['handler_config'] = $handler_service->applyDefaults(
				$handler_slug,
				$step_data['handler_config'] ?? []
			);

			// Map display settings to a clean string for UI summaries
			if (!empty($step_data['settings_display']) && is_array($step_data['settings_display'])) {
				$display_parts = array_map(function($setting) {
					return sprintf('%s: %s', $setting['label'], $setting['display_value']);
				}, $step_data['settings_display']);
				$step_data['settings_summary'] = implode(' | ', $display_parts);
			} else {
				$step_data['settings_summary'] = '';
			}
		}
		unset($step_data);

		$scheduling_config = $flow['scheduling_config'] ?? [];
		$flow_id = $flow['flow_id'] ?? null;

		// Derive execution status from jobs table (single source of truth)
		$last_run_at = $latest_job['created_at'] ?? null;
		$last_run_status = $latest_job['status'] ?? null;
		$is_running = $latest_job && $latest_job['completed_at'] === null;

		$next_run = self::get_next_run_time($flow_id);

		return [
			'flow_id' => $flow_id,
			'flow_name' => $flow['flow_name'] ?? '',
			'pipeline_id' => $flow['pipeline_id'] ?? null,
			'flow_config' => $flow_config,
			'scheduling_config' => $scheduling_config,
			'last_run' => $last_run_at,
			'last_run_status' => $last_run_status,
			'last_run_display' => DateFormatter::format_for_display($last_run_at, $last_run_status),
			'is_running' => $is_running,
			'next_run' => $next_run,
			'next_run_display' => DateFormatter::format_for_display($next_run),
		];
	}

	/**
	 * Determine next scheduled run time for a flow if Action Scheduler is available.
	 */
	private static function get_next_run_time(?int $flow_id): ?string {
		if (!$flow_id || !function_exists('as_next_scheduled_action')) {
			return null;
		}

		$next_timestamp = as_next_scheduled_action('datamachine_run_flow_now', [$flow_id], 'data-machine');

		return $next_timestamp ? wp_date('Y-m-d H:i:s', $next_timestamp) : null;
	}

	/**
	 * Handle flow update request (title and/or scheduling)
	 *
	 * PATCH /datamachine/v1/flows/{id}
	 */
	public static function handle_update_flow($request) {
		$flow_id = (int) $request->get_param('flow_id');
		$flow_name = $request->get_param('flow_name');
		$scheduling_config = $request->get_param('scheduling_config');

		$db_flows = new \DataMachine\Core\Database\Flows\Flows();

		// Validate that at least one update parameter is provided
		if ($flow_name === null && $scheduling_config === null) {
			return new \WP_Error(
				'no_updates',
				__('Must provide flow_name or scheduling_config to update', 'data-machine'),
				['status' => 400]
			);
		}

		// Handle title updates
		if ($flow_name !== null) {
			$flow_name = sanitize_text_field($flow_name);
			if (empty($flow_name)) {
				return new \WP_Error(
					'empty_title',
					__('Flow title cannot be empty', 'data-machine'),
					['status' => 400]
				);
			}

			$success = $db_flows->update_flow($flow_id, [
				'flow_name' => $flow_name
			]);

			if (!$success) {
				return new \WP_Error(
					'update_failed',
					__('Failed to save flow title', 'data-machine'),
					['status' => 500]
				);
			}
		}

		// Handle scheduling updates
		if ($scheduling_config !== null) {
			$result = FlowScheduling::handle_scheduling_update($flow_id, $scheduling_config);
			if (is_wp_error($result)) {
				return $result;
			}
		}

		// Get updated flow data for response
		$flow = $db_flows->get_flow($flow_id);
		if (!$flow) {
			return new \WP_Error(
				'flow_not_found',
				__('Flow not found after update', 'data-machine'),
				['status' => 404]
			);
		}

		// Get latest job for this flow
		$db_jobs = new Jobs();
		$jobs = $db_jobs->get_jobs_for_flow($flow_id);
		$latest_job = $jobs[0] ?? null;

		$flow_payload = self::format_flow_for_response($flow, $latest_job);

		return rest_ensure_response([
			'success' => true,
			'data' => $flow_payload,
			'message' => __('Flow updated successfully', 'data-machine')
		]);
	}

	/**
	 * Handle problem flows retrieval request.
	 *
	 * Returns flows with consecutive failures at or above the threshold.
	 *
	 * GET /datamachine/v1/flows/problems
	 */
	public static function handle_get_problem_flows($request) {
		$threshold = $request->get_param('threshold');

		// Use setting if threshold not provided
		if ($threshold === null || $threshold <= 0) {
			$threshold = \DataMachine\Core\PluginSettings::get('problem_flow_threshold', 3);
		}

		$db_flows = new \DataMachine\Core\Database\Flows\Flows();
		$problem_flows = $db_flows->get_problem_flows((int) $threshold);

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'problem_flows' => $problem_flows,
				'total' => count($problem_flows),
				'threshold' => (int) $threshold
			]
		]);
	}

}
