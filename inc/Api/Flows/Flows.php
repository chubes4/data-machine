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
use DataMachine\Services\FlowManager;
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
					'description' => __('Parent pipeline ID', 'datamachine'),
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
					'description' => __('Flow name', 'datamachine'),
					'sanitize_callback' => function($param) {
						return sanitize_text_field($param);
					}
				],
				'flow_config' => [
					'required' => false,
					'type' => 'array',
					'description' => __('Flow configuration (handler settings per step)', 'datamachine'),
				],
				'scheduling_config' => [
					'required' => false,
					'type' => 'array',
					'description' => __('Scheduling configuration', 'datamachine'),
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
					'description' => __('Optional pipeline ID to filter flows', 'datamachine'),
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
						'description' => __('Flow ID to retrieve', 'datamachine'),
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
						'description' => __('Flow ID to delete', 'datamachine'),
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
						'description' => __('Flow ID to update', 'datamachine'),
					],
					'flow_name' => [
						'required' => false,
						'type' => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description' => __('New flow title', 'datamachine'),
					],
					'scheduling_config' => [
						'required' => false,
						'type' => 'object',
						'description' => __('Scheduling configuration', 'datamachine'),
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
					'description' => __('Source flow ID to duplicate', 'datamachine'),
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
				__('You do not have permission to create flows.', 'datamachine'),
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
				__('Failed to create flow.', 'datamachine'),
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
				__('Failed to delete flow.', 'datamachine'),
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
				__('Failed to duplicate flow.', 'datamachine'),
				['status' => 500]
			);
		}

		return rest_ensure_response([
			'success' => true,
			'data' => $result
		]);
	}

	/**
	 * Handle flows retrieval request
	 */
	public static function handle_get_flows($request) {
		$pipeline_id = $request->get_param('pipeline_id');

		if ($pipeline_id) {
			// Get flows for specific pipeline
			$db_flows = new \DataMachine\Core\Database\Flows\Flows();
			$flows = $db_flows->get_flows_for_pipeline($pipeline_id);
			$formatted_flows = array_map([self::class, 'format_flow_for_response'], $flows);

			return rest_ensure_response([
				'success' => true,
				'data' => [
					'pipeline_id' => $pipeline_id,
					'flows' => $formatted_flows
				]
			]);
		}

		// Get all flows across all pipelines
		$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
		$db_flows = new \DataMachine\Core\Database\Flows\Flows();
		$all_pipelines = $db_pipelines->get_pipelines_list();
		$all_flows = [];

		foreach ($all_pipelines as $pipeline) {
			$pipeline_flows = $db_flows->get_flows_for_pipeline($pipeline['pipeline_id']);
			foreach ($pipeline_flows as $flow) {
				$all_flows[] = self::format_flow_for_response($flow);
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
				__('Flow not found.', 'datamachine'),
				['status' => 404]
			);
		}

		$flow_payload = self::format_flow_for_response($flow);

		return rest_ensure_response([
			'success' => true,
			'data' => $flow_payload
		]);
	}

	/**
	 * Format a flow record with handler config and scheduling metadata
	 */
	private static function format_flow_for_response(array $flow): array {
		$flow_config = $flow['flow_config'] ?? [];

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

			$step_data['handler_config'] = self::merge_handler_defaults(
				$handler_slug,
				$step_data['handler_config'] ?? []
			);
		}
		unset($step_data);

		$raw_scheduling = $flow['scheduling_config'] ?? [];
		if (is_array($raw_scheduling)) {
			$scheduling_config = $raw_scheduling;
		} else {
			$scheduling_config = json_decode($raw_scheduling ?: '{}', true);
			if (!is_array($scheduling_config)) {
				$scheduling_config = [];
			}
		}

		$flow_id = $flow['flow_id'] ?? null;
		$last_run_at = $scheduling_config['last_run_at'] ?? null;
		$next_run = self::get_next_run_time($flow_id);

		return [
			'flow_id' => $flow_id,
			'flow_name' => $flow['flow_name'] ?? '',
			'pipeline_id' => $flow['pipeline_id'] ?? null,
			'flow_config' => $flow_config,
			'scheduling_config' => $scheduling_config,
			'last_run' => $last_run_at,
			'last_run_display' => DateFormatter::format_for_display($last_run_at),
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

		$next_timestamp = as_next_scheduled_action('datamachine_run_flow_now', [$flow_id], 'datamachine');

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
				__('Must provide flow_name or scheduling_config to update', 'datamachine'),
				['status' => 400]
			);
		}

		// Handle title updates
		if ($flow_name !== null) {
			$flow_name = sanitize_text_field($flow_name);
			if (empty($flow_name)) {
				return new \WP_Error(
					'empty_title',
					__('Flow title cannot be empty', 'datamachine'),
					['status' => 400]
				);
			}

			$success = $db_flows->update_flow($flow_id, [
				'flow_name' => $flow_name
			]);

			if (!$success) {
				return new \WP_Error(
					'update_failed',
					__('Failed to save flow title', 'datamachine'),
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
				__('Flow not found after update', 'datamachine'),
				['status' => 404]
			);
		}

		$flow_payload = self::format_flow_for_response($flow);

		// Clear caches
		do_action('datamachine_clear_flow_cache', $flow_id);
		do_action('datamachine_clear_pipelines_list_cache');

		return rest_ensure_response([
			'success' => true,
			'data' => $flow_payload,
			'message' => __('Flow updated successfully', 'datamachine')
		]);
	}



	/**
	 * Merge handler defaults with stored configuration.
	 *
	 * Retrieves handler settings schema and merges default values with
	 * stored configuration. Stored values always take precedence.
	 *
	 * @param string $handler_slug Handler identifier
	 * @param array $stored_config Stored handler configuration
	 * @return array Complete configuration with defaults merged
	 */
	private static function merge_handler_defaults(string $handler_slug, array $stored_config): array {
		// Get handler settings class via filter
		$all_settings = apply_filters('datamachine_handler_settings', [], $handler_slug);

		if (!isset($all_settings[$handler_slug])) {
			// No settings class registered - return stored config as-is
			return $stored_config;
		}

		$settings_class = $all_settings[$handler_slug];

		// Get field schema
		if (!method_exists($settings_class, 'get_fields')) {
			return $stored_config;
		}

		$fields = $settings_class::get_fields();

		// Build complete config: defaults + stored values
		$complete_config = [];

		foreach ($fields as $key => $field_config) {
			if (array_key_exists($key, $stored_config)) {
				// Stored value exists - use it (user's choice)
				$complete_config[$key] = $stored_config[$key];
			} elseif (isset($field_config['default'])) {
				// No stored value - use default from schema
				$complete_config[$key] = $field_config['default'];
			}
			// If neither exists, omit the field entirely
		}

		// Preserve any stored keys not in schema (forward compatibility)
		foreach ($stored_config as $key => $value) {
			if (!isset($fields[$key])) {
				$complete_config[$key] = $value;
			}
		}

		return $complete_config;
	}
}
