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

use DataMachine\Engine\Actions\Delete;
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
				'callback' => [self::class, 'handle_get_flow'],
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
				'callback' => [self::class, 'handle_update_flow_title'],
				'permission_callback' => [self::class, 'check_permission'],
				'args' => [
					'flow_id' => [
						'required' => true,
						'type' => 'integer',
						'sanitize_callback' => 'absint',
						'description' => __('Flow ID to update', 'datamachine'),
					],
					'flow_name' => [
						'required' => true,
						'type' => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description' => __('New flow title', 'datamachine'),
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
		$params = $request->get_params();

		// Delegate to existing datamachine_create_flow filter
		$flow_id = apply_filters('datamachine_create_flow', false, $params);

		if (!$flow_id) {
			do_action('datamachine_log', 'error', 'Failed to create flow via REST API', [
				'params' => $params,
				'user_id' => get_current_user_id()
			]);

			return new \WP_Error(
				'flow_creation_failed',
				__('Failed to create flow.', 'datamachine'),
				['status' => 500]
			);
		}

		// Get flow data for response
		$db_flows = new \DataMachine\Core\Database\Flows\Flows();
		$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
		$flow = $db_flows->get_flow($flow_id);
		$pipeline_steps = $db_pipelines->get_pipeline_config($params['pipeline_id']);

		if (!isset($flow['flow_name']) || empty(trim($flow['flow_name']))) {
			do_action('datamachine_log', 'error', 'Flow created but missing name in database', [
				'flow_id' => $flow_id,
				'params' => $params
			]);
			return new \WP_Error(
				'data_integrity_error',
				__('Flow data is corrupted - missing name.', 'datamachine'),
				['status' => 500]
			);
		}

		do_action('datamachine_log', 'info', 'Flow created via REST API', [
			'flow_id' => $flow_id,
			'flow_name' => $flow['flow_name'],
			'pipeline_id' => $params['pipeline_id'],
			'synced_steps' => count($pipeline_steps),
			'user_id' => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login
		]);

		return [
			'success' => true,
			'flow_id' => $flow_id,
			'flow_name' => $flow['flow_name'],
			'pipeline_id' => $params['pipeline_id'],
			'synced_steps' => count($pipeline_steps),
			'flow_data' => $flow
		];
	}

	/**
	 * Handle flow deletion request
	 */
	public static function handle_delete_flow($request) {
		$flow_id = (int) $request->get_param('flow_id');

		$result = Delete::delete_flow($flow_id);

		if (is_wp_error($result)) {
			return $result;
		}

		return array_merge(['success' => true], $result);
	}

	/**
	 * Handle flow duplication request
	 */
	public static function handle_duplicate_flow($request) {
		$source_flow_id = (int) $request->get_param('flow_id');

		// Delegate to existing datamachine_duplicate_flow filter
		$new_flow_id = apply_filters('datamachine_duplicate_flow', false, $source_flow_id);

		if (!$new_flow_id) {
			do_action('datamachine_log', 'error', 'Failed to duplicate flow via REST API', [
				'source_flow_id' => $source_flow_id,
				'user_id' => get_current_user_id()
			]);

			return new \WP_Error(
				'flow_duplication_failed',
				__('Failed to duplicate flow.', 'datamachine'),
				['status' => 500]
			);
		}

		// Get duplicated flow data for response
		$db_flows = new \DataMachine\Core\Database\Flows\Flows();
		$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
		$flow = $db_flows->get_flow($new_flow_id);
		$source_flow = $db_flows->get_flow($source_flow_id);
		if (!isset($source_flow['pipeline_id']) || empty($source_flow['pipeline_id'])) {
			return new \WP_Error(
				'rest_invalid_flow_data',
				__('Source flow is missing required pipeline_id.', 'datamachine'),
				['status' => 400]
			);
		}
		$pipeline_steps = $db_pipelines->get_pipeline_config($source_flow['pipeline_id']);

		do_action('datamachine_log', 'info', 'Flow duplicated via REST API', [
			'source_flow_id' => $source_flow_id,
			'new_flow_id' => $new_flow_id,
			'pipeline_id' => $source_flow['pipeline_id'],
			'flow_name' => $flow['flow_name'] ?? '',
			'user_id' => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login
		]);

		return [
			'success' => true,
			'source_flow_id' => $source_flow_id,
			'new_flow_id' => $new_flow_id,
			'flow_name' => $flow['flow_name'] ?? '',
			'pipeline_id' => $source_flow['pipeline_id'],
			'flow_data' => $flow,
			'pipeline_steps' => $pipeline_steps
		];
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

			return [
				'success' => true,
				'pipeline_id' => $pipeline_id,
				'flows' => $flows
			];
		}

		// Get all flows across all pipelines
		$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
		$db_flows = new \DataMachine\Core\Database\Flows\Flows();
		$all_pipelines = $db_pipelines->get_pipelines_list();
		$all_flows = [];

		foreach ($all_pipelines as $pipeline) {
			$pipeline_flows = $db_flows->get_flows_for_pipeline($pipeline['pipeline_id']);
			$all_flows = array_merge($all_flows, $pipeline_flows);
		}

		return [
			'success' => true,
			'flows' => $all_flows
		];
	}

	/**
	 * Handle single flow retrieval request with scheduling metadata
	 */
	public static function handle_get_flow($request) {
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

		// Parse scheduling config
		$scheduling_config = is_array($flow['scheduling_config']) ?
			$flow['scheduling_config'] :
			json_decode($flow['scheduling_config'] ?? '{}', true);

		// Calculate last_run
		$last_run = $scheduling_config['last_run_at'] ?? null;

		// Calculate next_run using Action Scheduler
		$next_run = null;
		if (function_exists('as_next_scheduled_action')) {
			$next_timestamp = as_next_scheduled_action('datamachine_run_flow_now', [$flow_id]);
			if ($next_timestamp) {
				$next_run = gmdate('Y-m-d H:i:s', $next_timestamp);
			}
		}

		// Enrich flow config with handler settings display and merge defaults
		$flow_config = $flow['flow_config'] ?? [];
		foreach ($flow_config as $flow_step_id => &$step_data) {
			if (isset($step_data['handler_slug'])) {
				$step_type = $step_data['step_type'] ?? '';
				$handler_slug = $step_data['handler_slug'];

				// Get settings display for UI rendering
				$step_data['settings_display'] = apply_filters(
					'datamachine_get_handler_settings_display',
					[],
					$flow_step_id,
					$step_type
				);

				// Merge defaults with stored handler_config (API is single source of truth)
				$step_data['handler_config'] = self::merge_handler_defaults(
					$handler_slug,
					$step_data['handler_config'] ?? []
				);
			}
		}

		return [
			'success' => true,
			'flow_id' => $flow_id,
			'flow_name' => $flow['flow_name'] ?? '',
			'pipeline_id' => $flow['pipeline_id'] ?? null,
			'flow_config' => $flow_config,
			'scheduling_config' => $scheduling_config,
			'last_run' => $last_run,
			'next_run' => $next_run
		];
	}

	/**
	 * Handle flow title update request
	 *
	 * PATCH /datamachine/v1/flows/{id}
	 */
	public static function handle_update_flow_title($request) {
		$flow_id = (int) $request->get_param('flow_id');
		$flow_name = sanitize_text_field($request->get_param('flow_name'));

		if (empty($flow_name)) {
			return new \WP_Error(
				'empty_title',
				__('Flow title cannot be empty', 'datamachine'),
				['status' => 400]
			);
		}

		// Update flow title using centralized filter
		$db_flows = new \DataMachine\Core\Database\Flows\Flows();
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

		// Clear caches
		do_action('datamachine_clear_flow_cache', $flow_id);
		do_action('datamachine_clear_pipelines_list_cache');

		return [
			'success' => true,
			'message' => __('Flow title saved successfully', 'datamachine')
		];
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
