<?php
/**
 * REST API Flows Endpoint
 *
 * Provides REST API access to flow creation operations.
 * Requires WordPress manage_options capability.
 *
 * @package DataMachine\Api
 */

namespace DataMachine\Api;

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
	 * Register /datamachine/v1/flows endpoint
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
				'callback' => [self::class, 'handle_update_flow_title'],
				'permission_callback' => [self::class, 'check_permission'],
				'args' => [
					'flow_id' => [
						'required' => true,
						'type' => 'integer',
						'sanitize_callback' => 'absint',
						'description' => __('Flow ID to update', 'data-machine'),
					],
					'flow_name' => [
						'required' => true,
						'type' => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description' => __('New flow title', 'data-machine'),
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

		register_rest_route('datamachine/v1', '/flows/(?P<flow_id>\d+)/config', [
			'methods' => 'GET',
			'callback' => [self::class, 'handle_get_flow_config'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'flow_id' => [
					'required' => true,
					'type' => 'integer',
					'sanitize_callback' => 'absint',
					'description' => __('Flow ID to retrieve configuration for', 'data-machine'),
				],
			]
		]);

		register_rest_route('datamachine/v1', '/flows/steps/(?P<flow_step_id>[A-Za-z0-9_\-]+)/config', [
			'methods' => 'GET',
			'callback' => [self::class, 'handle_get_flow_step_config'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'flow_step_id' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('Flow step ID (composite key: pipeline_step_id_flow_id)', 'data-machine'),
				],
			]
		]);

		register_rest_route('datamachine/v1', '/flows/steps/(?P<flow_step_id>[A-Za-z0-9_\-]+)/handler', [
			'methods' => 'PUT',
			'callback' => [self::class, 'handle_update_flow_step_handler'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'flow_step_id' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('Flow step ID (composite key: pipeline_step_id_flow_id)', 'data-machine'),
				],
				'handler_slug' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('Handler identifier', 'data-machine'),
				],
				'pipeline_id' => [
					'required' => true,
					'type' => 'integer',
					'sanitize_callback' => 'absint',
					'description' => __('Pipeline ID for context', 'data-machine'),
				],
				'step_type' => [
					'required' => true,
					'type' => 'string',
					'enum' => ['fetch', 'publish', 'update'],
					'description' => __('Step type', 'data-machine'),
				],
			]
		]);

		register_rest_route('datamachine/v1', '/flows/steps/(?P<flow_step_id>[A-Za-z0-9_\-]+)/user-message', [
			'methods' => 'PATCH',
			'callback' => [self::class, 'handle_update_user_message'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'flow_step_id' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('Flow step ID (composite key: pipeline_step_id_flow_id)', 'data-machine'),
				],
				'user_message' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
					'description' => __('User message for AI step', 'data-machine'),
				],
			]
		]);
	}

	/**
	 * Check if user has permission to create flows
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
				__('Failed to create flow.', 'data-machine'),
				['status' => 500]
			);
		}

		// Get flow data for response
		$flow = apply_filters('datamachine_get_flow', null, $flow_id);
		$pipeline_steps = apply_filters('datamachine_get_pipeline_steps', [], $params['pipeline_id']);

		do_action('datamachine_log', 'info', 'Flow created via REST API', [
			'flow_id' => $flow_id,
			'flow_name' => $params['flow_name'] ?? 'Flow',
			'pipeline_id' => $params['pipeline_id'],
			'synced_steps' => count($pipeline_steps),
			'user_id' => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login
		]);

		return [
			'success' => true,
			'flow_id' => $flow_id,
			'flow_name' => $params['flow_name'] ?? 'Flow',
			'pipeline_id' => $params['pipeline_id'],
			'synced_steps' => count($pipeline_steps),
			'flow_data' => $flow
		];
	}

	/**
	 * Handle flow deletion request.
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
				__('Failed to duplicate flow.', 'data-machine'),
				['status' => 500]
			);
		}

		// Get duplicated flow data for response
		$flow = apply_filters('datamachine_get_flow', null, $new_flow_id);
		$source_flow = apply_filters('datamachine_get_flow', null, $source_flow_id);
		$pipeline_steps = apply_filters('datamachine_get_pipeline_steps', [], $source_flow['pipeline_id'] ?? 0);

		do_action('datamachine_log', 'info', 'Flow duplicated via REST API', [
			'source_flow_id' => $source_flow_id,
			'new_flow_id' => $new_flow_id,
			'pipeline_id' => $source_flow['pipeline_id'] ?? 0,
			'flow_name' => $flow['flow_name'] ?? '',
			'user_id' => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login
		]);

		return [
			'success' => true,
			'source_flow_id' => $source_flow_id,
			'new_flow_id' => $new_flow_id,
			'flow_name' => $flow['flow_name'] ?? '',
			'pipeline_id' => $source_flow['pipeline_id'] ?? 0,
			'flow_data' => $flow,
			'pipeline_steps' => $pipeline_steps
		];
	}

	/**
	 * Handle flow configuration retrieval request
	 */
	public static function handle_get_flow_config($request) {
		$flow_id = (int) $request->get_param('flow_id');

		// Retrieve flow data via filter
		$flow = apply_filters('datamachine_get_flow', null, $flow_id);

		if (!$flow) {
			return new \WP_Error(
				'flow_not_found',
				__('Flow not found.', 'data-machine'),
				['status' => 404]
			);
		}

		$flow_config = $flow['flow_config'] ?? [];

		return [
			'success' => true,
			'flow_id' => $flow_id,
			'flow_config' => $flow_config
		];
	}

	/**
	 * Handle flow step configuration retrieval request
	 */
	public static function handle_get_flow_step_config($request) {
		$flow_step_id = sanitize_text_field($request->get_param('flow_step_id'));

		if (empty($flow_step_id)) {
			return new \WP_Error(
				'invalid_flow_step_id',
				__('Flow step ID is required.', 'data-machine'),
				['status' => 400]
			);
		}

		// Retrieve step configuration via filter
		$step_config = apply_filters('datamachine_get_flow_step_config', [], $flow_step_id);

		if (empty($step_config)) {
			return new \WP_Error(
				'flow_step_not_found',
				__('Flow step configuration not found.', 'data-machine'),
				['status' => 404]
			);
		}

		return [
			'success' => true,
			'flow_step_id' => $flow_step_id,
			'step_config' => $step_config
		];
	}

	/**
	 * Handle flows retrieval request
	 */
	public static function handle_get_flows($request) {
		$pipeline_id = $request->get_param('pipeline_id');

		if ($pipeline_id) {
			// Get flows for specific pipeline
			$flows = apply_filters('datamachine_get_pipeline_flows', [], $pipeline_id);

			return [
				'success' => true,
				'pipeline_id' => $pipeline_id,
				'flows' => $flows
			];
		}

		// Get all flows across all pipelines
		$all_pipelines = apply_filters('datamachine_get_pipelines_list', []);
		$all_flows = [];

		foreach ($all_pipelines as $pipeline) {
			$pipeline_flows = apply_filters('datamachine_get_pipeline_flows', [], $pipeline['pipeline_id']);
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
		$flow = apply_filters('datamachine_get_flow', null, $flow_id);

		if (!$flow) {
			return new \WP_Error(
				'flow_not_found',
				__('Flow not found.', 'data-machine'),
				['status' => 404]
			);
		}

		// Parse scheduling config
		$scheduling_config = is_array($flow['scheduling_config']) ?
			$flow['scheduling_config'] :
			json_decode($flow['scheduling_config'] ?? '{}', true);

		// Calculate last_run
		$last_run = null;
		if (!empty($scheduling_config['last_run_at'])) {
			$last_run = $scheduling_config['last_run_at'];
		} else {
			// Fallback: get most recent job
			$all_databases = apply_filters('datamachine_db', []);
			$db_jobs = $all_databases['jobs'] ?? null;
			if ($db_jobs) {
				$recent_jobs = $db_jobs->get_jobs(['flow_id' => $flow_id, 'limit' => 1]);
				if (!empty($recent_jobs)) {
					$last_run = $recent_jobs[0]['started_at'] ?? null;
				}
			}
		}

		// Calculate next_run using Action Scheduler
		$next_run = null;
		if (function_exists('as_next_scheduled_action')) {
			$next_timestamp = as_next_scheduled_action('datamachine_run_flow_now', [$flow_id]);
			if ($next_timestamp) {
				$next_run = gmdate('Y-m-d H:i:s', $next_timestamp);
			}
		}

		// Enrich flow config with handler settings display for React
		$flow_config = $flow['flow_config'] ?? [];
		foreach ($flow_config as $flow_step_id => &$step_data) {
			if (isset($step_data['handler_slug'])) {
				$step_type = $step_data['step_type'] ?? '';
				$step_data['settings_display'] = apply_filters(
					'datamachine_get_handler_settings_display',
					[],
					$flow_step_id,
					$step_type
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
	 * Handle flow step handler settings update
	 *
	 * PUT /datamachine/v1/flows/steps/{flow_step_id}/handler
	 */
	public static function handle_update_flow_step_handler($request) {
		$flow_step_id = sanitize_text_field($request->get_param('flow_step_id'));
		$handler_slug = sanitize_text_field($request->get_param('handler_slug'));
		$step_type = sanitize_text_field($request->get_param('step_type'));
		$pipeline_id = (int) $request->get_param('pipeline_id');

		do_action('datamachine_log', 'debug', 'Handler settings update via REST API', [
			'flow_step_id' => $flow_step_id,
			'handler_slug' => $handler_slug,
			'step_type' => $step_type,
			'pipeline_id' => $pipeline_id
		]);

		// Validate required fields
		if (empty($handler_slug) || empty($flow_step_id)) {
			return new \WP_Error(
				'missing_required_fields',
				__('Handler slug and flow step ID are required.', 'data-machine'),
				['status' => 400]
			);
		}

		// Validate handler exists
		$all_handlers = apply_filters('datamachine_handlers', [], $step_type);
		$handler_info = null;

		foreach ($all_handlers as $slug => $config) {
			if ($slug === $handler_slug) {
				$handler_info = $config;
				break;
			}
		}

		if (!$handler_info) {
			return new \WP_Error(
				'handler_not_found',
				__('Handler not found.', 'data-machine'),
				['status' => 404]
			);
		}

		// Process handler settings
		$handler_settings = self::process_handler_settings($handler_slug, $request->get_params());

		do_action('datamachine_log', 'debug', 'Handler settings processed', [
			'handler_slug' => $handler_slug,
			'flow_step_id' => $flow_step_id,
			'settings_count' => count($handler_settings)
		]);

		try {
			// Update flow handler via centralized action
			do_action('datamachine_update_flow_handler', $flow_step_id, $handler_slug, $handler_settings);

			// Split flow step ID
			$parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);
			$flow_id = $parts['flow_id'] ?? null;
			$pipeline_step_id = $parts['pipeline_step_id'] ?? null;

			// Build config from memory (performance optimization)
			$step_config = [
				'step_type' => $step_type,
				'handler_slug' => $handler_slug,
				'handler_config' => $handler_settings,
				'enabled' => true,
				'flow_id' => $flow_id,
				'pipeline_step_id' => $pipeline_step_id,
				'flow_step_id' => $flow_step_id
			];

			// Preserve execution_order if it exists
			$all_databases = apply_filters('datamachine_db', []);
			$db_flows = $all_databases['flows'] ?? null;
			if ($db_flows) {
				$flow = $db_flows->get_flow($flow_id);
				$flow_config = $flow['flow_config'] ?? [];
				$existing_step = $flow_config[$flow_step_id] ?? [];
				if (isset($existing_step['execution_order'])) {
					$step_config['execution_order'] = $existing_step['execution_order'];
				}
			}

			// Get handler settings display for UI
			$handler_settings_display = apply_filters('datamachine_get_handler_settings_display', [], $flow_step_id, $step_type);

			/* translators: %s: Handler name or label */
			$message = sprintf(__('Handler "%s" settings saved successfully.', 'data-machine'), $handler_info['label'] ?? $handler_slug);

			return [
				'success' => true,
				'message' => $message,
				'handler_slug' => $handler_slug,
				'step_type' => $step_type,
				'flow_step_id' => $flow_step_id,
				'flow_id' => $flow_id,
				'pipeline_step_id' => $pipeline_step_id,
				'step_config' => $step_config,
				'handler_settings_display' => $handler_settings_display
			];

		} catch (\Exception $e) {
			do_action('datamachine_log', 'error', 'Handler settings save failed: ' . $e->getMessage(), [
				'handler_slug' => $handler_slug,
				'flow_step_id' => $flow_step_id
			]);

			return new \WP_Error(
				'save_failed',
				__('Failed to save handler settings.', 'data-machine'),
				['status' => 500]
			);
		}
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
				__('Flow title cannot be empty', 'data-machine'),
				['status' => 400]
			);
		}

		// Update flow title using centralized filter
		$success = apply_filters('datamachine_update_flow', false, $flow_id, [
			'flow_name' => $flow_name
		]);

		if (!$success) {
			return new \WP_Error(
				'update_failed',
				__('Failed to save flow title', 'data-machine'),
				['status' => 500]
			);
		}

		// Clear caches
		do_action('datamachine_clear_flow_cache', $flow_id);
		do_action('datamachine_clear_pipelines_list_cache');

		return [
			'success' => true,
			'message' => __('Flow title saved successfully', 'data-machine')
		];
	}

	/**
	 * Handle user message update for AI flow steps
	 *
	 * PATCH /datamachine/v1/flows/steps/{flow_step_id}/user-message
	 */
	public static function handle_update_user_message($request) {
		$flow_step_id = sanitize_text_field($request->get_param('flow_step_id'));
		$user_message = sanitize_textarea_field($request->get_param('user_message'));

		// Use centralized flow user message update action with validation
		$success = apply_filters('datamachine_update_flow_user_message_result', false, $flow_step_id, $user_message);

		if (!$success) {
			return new \WP_Error(
				'update_failed',
				__('Failed to save user message', 'data-machine'),
				['status' => 500]
			);
		}

		// Extract flow_id from flow_step_id to get pipeline_id for cache clearing
		$parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);
		if ($parts && !empty($parts['flow_id'])) {
			$flow_id = $parts['flow_id'];

			// Get flow data to extract pipeline_id
			$flow = apply_filters('datamachine_get_flow', null, $flow_id);
			if ($flow && !empty($flow['pipeline_id'])) {
				$pipeline_id = (int) $flow['pipeline_id'];
				do_action('datamachine_clear_pipeline_cache', $pipeline_id);
			}
		}

		return [
			'success' => true,
			'message' => __('User message saved successfully', 'data-machine')
		];
	}

	/**
	 * Process handler settings from request parameters
	 *
	 * @param string $handler_slug Handler identifier
	 * @param array $params Request parameters
	 * @return array Sanitized handler settings
	 */
	private static function process_handler_settings($handler_slug, $params) {
		$all_settings = apply_filters('datamachine_handler_settings', [], $handler_slug);
		$handler_settings = $all_settings[$handler_slug] ?? null;

		if (!$handler_settings || !method_exists($handler_settings, 'sanitize')) {
			return [];
		}

		$raw_settings = [];
		foreach ($params as $key => $value) {
			// Sanitize key
			$safe_key = sanitize_key($key);

			// Skip REST API meta parameters
			if (in_array($safe_key, ['flow_step_id', 'handler_slug', 'pipeline_id', 'step_type'], true) || empty($safe_key)) {
				continue;
			}

			// Sanitize values
			if (is_array($value)) {
				$raw_settings[$safe_key] = array_map('sanitize_text_field', $value);
			} else {
				$raw_settings[$safe_key] = sanitize_text_field($value);
			}
		}

		return $handler_settings->sanitize($raw_settings);
	}
}
