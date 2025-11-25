<?php
/**
 * REST API Pipeline Steps Endpoint
 *
 * Provides REST API access to pipeline step management operations.
 * Requires WordPress manage_options capability.
 *
 * @package DataMachine\Api\Pipelines
 */

namespace DataMachine\Api\Pipelines;

use DataMachine\Engine\Actions\Delete;
use WP_REST_Server;

if (!defined('WPINC')) {
	die;
}

class PipelineSteps {

	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action('rest_api_init', [self::class, 'register_routes']);
	}

	/**
	 * Register pipeline step management endpoints
	 */
	public static function register_routes() {
		register_rest_route('datamachine/v1', '/pipelines/(?P<pipeline_id>\d+)/steps', [
			'methods' => 'POST',
			'callback' => [self::class, 'handle_create_step'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'pipeline_id' => [
					'required' => true,
					'type' => 'integer',
					'sanitize_callback' => 'absint',
					'description' => __('Pipeline ID to add step to', 'datamachine'),
				],
				'step_type' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => function( $param ) {
						$types = apply_filters('datamachine_step_types', []);
						$valid_step_types = is_array($types) ? array_keys($types) : [];
						return in_array($param, $valid_step_types, true);
					},
					'description' => __('Step type (supports custom step types)', 'datamachine'),
				]
			]
		]);

		register_rest_route('datamachine/v1', '/pipelines/(?P<pipeline_id>\d+)/steps/(?P<step_id>[A-Za-z0-9\-_]+)', [
			[
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => [self::class, 'handle_delete_pipeline_step'],
				'permission_callback' => [self::class, 'check_permission'],
				'args' => [
					'pipeline_id' => [
						'required' => true,
						'type' => 'integer',
						'sanitize_callback' => 'absint',
						'description' => __('Pipeline ID containing the step', 'datamachine'),
					],
					'step_id' => [
						'required' => true,
						'type' => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description' => __('Pipeline step ID to delete', 'datamachine'),
					],
				]
			]
		]);

		register_rest_route('datamachine/v1', '/pipelines/(?P<pipeline_id>\d+)/steps/reorder', [
			'methods' => 'PUT',
			'callback' => [self::class, 'handle_reorder_steps'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'pipeline_id' => [
					'required' => true,
					'type' => 'integer',
					'sanitize_callback' => 'absint',
					'description' => __('Pipeline ID to reorder steps for', 'datamachine'),
				],
				'step_order' => [
					'required' => true,
					'type' => 'array',
					'description' => __('Array of step IDs in new execution order', 'datamachine'),
					'validate_callback' => [self::class, 'validate_step_order'],
				]
			]
		]);

		register_rest_route('datamachine/v1', '/pipelines/steps/(?P<pipeline_step_id>[A-Za-z0-9_\-]+)/system-prompt', [
			'methods' => 'PATCH',
			'callback' => [self::class, 'handle_update_system_prompt'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'pipeline_step_id' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('Pipeline step ID (UUID4)', 'datamachine'),
				],
				'system_prompt' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
					'description' => __('System prompt for AI step', 'datamachine'),
				],
			]
		]);

		register_rest_route('datamachine/v1', '/pipelines/steps/(?P<pipeline_step_id>[A-Za-z0-9_\-]+)/config', [
			[
				'methods' => 'PUT',
				'callback' => [self::class, 'handle_update_step_config'],
				'permission_callback' => [self::class, 'check_permission'],
				'args' => self::get_step_config_args(false)
			],
			[
				'methods' => 'PATCH',
				'callback' => [self::class, 'handle_update_step_config'],
				'permission_callback' => [self::class, 'check_permission'],
				'args' => self::get_step_config_args(true)
			]
		]);
	}

	/**
	 * Shared REST arg definition for AI step configuration.
	 */
	private static function get_step_config_args(bool $is_patch = false): array {
		return [
			'pipeline_step_id' => [
				'required' => true,
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description' => __('Pipeline step ID (UUID4)', 'datamachine'),
			],
			'step_type' => [
				'required' => !$is_patch,
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description' => __('Step type (must be "ai")', 'datamachine'),
			],
			'pipeline_id' => [
				'required' => !$is_patch,
				'type' => 'integer',
				'sanitize_callback' => 'absint',
				'description' => __('Pipeline ID for context', 'datamachine'),
			],
			'provider' => [
				'required' => false,
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description' => __('AI provider slug', 'datamachine'),
			],
			'model' => [
				'required' => false,
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description' => __('AI model identifier', 'datamachine'),
			],
			'ai_api_key' => [
				'required' => false,
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description' => __('AI API key', 'datamachine'),
			],
			'enabled_tools' => [
				'required' => false,
				'type' => 'array',
				'description' => __('Array of enabled tool IDs', 'datamachine'),
			],
			'system_prompt' => [
				'required' => false,
				'type' => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'description' => __('System prompt for AI processing', 'datamachine'),
			]
		];
	}

	/**
	 * Check if user has permission to manage pipeline steps
	 */
	public static function check_permission($request) {
		if (!current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have permission to manage pipeline steps.', 'datamachine'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Handle pipeline step creation request
	 */
	public static function handle_create_step($request) {
		$pipeline_id = (int) $request->get_param('pipeline_id');
		$step_type = $request->get_param('step_type');

		// Delegate to datamachine_create_step filter
		$step_id = apply_filters('datamachine_create_step', false, [
			'pipeline_id' => $pipeline_id,
			'step_type' => $step_type
		]);

		if (!$step_id) {
			return new \WP_Error(
				'step_creation_failed',
				__('Failed to create step.', 'datamachine'),
				['status' => 500]
			);
		}

		// Get step data and registered steps for response
		$all_steps = apply_filters('datamachine_step_types', []);
		$step_config = $all_steps[$step_type] ?? [];
		$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
		$pipeline_steps = $db_pipelines->get_pipeline_config($pipeline_id);

		// Find the newly created step
		$step_data = null;
		foreach ($pipeline_steps as $step) {
			if ($step['pipeline_step_id'] === $step_id) {
				$step_data = $step;
				break;
			}
		}

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'step_type' => $step_type,
				'step_config' => $step_config,
				'pipeline_id' => $pipeline_id,
				'pipeline_step_id' => $step_id,
				'step_data' => $step_data,
				'created_type' => 'step'
			],
			'message' => sprintf(__('Step "%s" added successfully', 'datamachine'), $step_config['label'] ?? $step_type)
		]);
	}

	/**
	 * Handle pipeline step deletion request
	 */
	public static function handle_delete_pipeline_step($request) {
		$pipeline_id = (int) $request->get_param('pipeline_id');
		$step_id = (string) $request->get_param('step_id');

		$result = Delete::delete_pipeline_step($step_id, $pipeline_id);

		if (is_wp_error($result)) {
			return $result;
		}

		return rest_ensure_response([
			'success' => true,
			'data' => $result
		]);
	}

	/**
	 * Validate step_order parameter structure
	 */
	public static function validate_step_order($param, $request, $key) {
		if (!is_array($param) || empty($param)) {
			return new \WP_Error(
				'invalid_step_order',
				__('Step order must be a non-empty array', 'datamachine'),
				['status' => 400]
			);
		}

		foreach ($param as $item) {
			if (!is_array($item)) {
				return new \WP_Error(
					'invalid_step_order_item',
					__('Each step order item must be an object', 'datamachine'),
					['status' => 400]
				);
			}

			if (!isset($item['pipeline_step_id']) || !isset($item['execution_order'])) {
				return new \WP_Error(
					'invalid_step_order_structure',
					__('Each step order item must have pipeline_step_id and execution_order', 'datamachine'),
					['status' => 400]
				);
			}

			if (!is_string($item['pipeline_step_id']) || !is_numeric($item['execution_order'])) {
				return new \WP_Error(
					'invalid_step_order_types',
					__('pipeline_step_id must be string and execution_order must be numeric', 'datamachine'),
					['status' => 400]
				);
			}
		}

		return true;
	}

	/**
	 * Handle pipeline step reordering request
	 */
	public static function handle_reorder_steps($request) {
		$pipeline_id = (int) $request->get_param('pipeline_id');
		$step_order = $request->get_param('step_order');

		// Get database service
		$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();

		// Retrieve current pipeline configuration
		$pipeline_steps = $db_pipelines->get_pipeline_config($pipeline_id);
		if (empty($pipeline_steps)) {
			return new \WP_Error(
				'pipeline_not_found',
				__('Pipeline not found', 'datamachine'),
				['status' => 404]
			);
		}

		// Update execution_order based on new sequence
		$updated_steps = [];
		foreach ($step_order as $item) {
			$pipeline_step_id = sanitize_text_field($item['pipeline_step_id']);
			$execution_order = (int) $item['execution_order'];

			// Find step in current configuration
			$step_found = false;
			foreach ($pipeline_steps as $step) {
				if ($step['pipeline_step_id'] === $pipeline_step_id) {
					$step['execution_order'] = $execution_order;
					$updated_steps[] = $step;
					$step_found = true;
					break;
				}
			}

			if (!$step_found) {
				return new \WP_Error(
					'step_not_found',
					sprintf(
						__('Step %s not found in pipeline', 'datamachine'),
						$pipeline_step_id
					),
					['status' => 400]
				);
			}
		}

		// Verify we updated all steps
		if (count($updated_steps) !== count($pipeline_steps)) {
			return new \WP_Error(
				'step_count_mismatch',
				__('Step count mismatch during reorder', 'datamachine'),
				['status' => 400]
			);
		}

		// Save updated pipeline configuration
		$success = $db_pipelines->update_pipeline($pipeline_id, [
			'pipeline_config' => $updated_steps
		]);

		if (!$success) {
			return new \WP_Error(
				'save_failed',
				__('Failed to save step order', 'datamachine'),
				['status' => 500]
			);
		}

		// Clear pipeline cache
		do_action('datamachine_clear_pipeline_cache', $pipeline_id);

		// Sync execution_order to flows
		$db_flows = new \DataMachine\Core\Database\Flows\Flows();
		$flows = $db_flows->get_flows_for_pipeline($pipeline_id);

		foreach ($flows as $flow) {
			$flow_id = $flow['flow_id'];
			$flow_config = $flow['flow_config'] ?? [];

			// Update only execution_order in flow steps
			foreach ($flow_config as $flow_step_id => &$flow_step) {
				if (!isset($flow_step['pipeline_step_id']) || empty($flow_step['pipeline_step_id'])) {
					continue;
				}
				$pipeline_step_id = $flow_step['pipeline_step_id'];

				// Find matching updated step and sync execution_order
				foreach ($updated_steps as $updated_step) {
					if ($updated_step['pipeline_step_id'] === $pipeline_step_id) {
						$flow_step['execution_order'] = $updated_step['execution_order'];
						break;
					}
				}
			}
			unset($flow_step);

			// Update flow with only execution_order changes
			$db_flows = new \DataMachine\Core\Database\Flows\Flows();
			$db_flows->update_flow($flow_id, [
				'flow_config' => $flow_config
			]);

			// Clear only flow config cache
			do_action('datamachine_clear_flow_config_cache', $flow_id);
		}

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'pipeline_id' => $pipeline_id,
				'step_count' => count($updated_steps)
			],
			'message' => __('Step order saved successfully', 'datamachine')
		]);
	}

	/**
	 * Handle system prompt update for AI pipeline steps
	 *
	 * PATCH /datamachine/v1/pipelines/steps/{pipeline_step_id}/system-prompt
	 */
	public static function handle_update_system_prompt($request) {
		$pipeline_step_id = sanitize_text_field($request->get_param('pipeline_step_id'));
		$system_prompt = sanitize_textarea_field($request->get_param('system_prompt'));

		// Use centralized system prompt update action with validation
		$success = apply_filters('datamachine_update_system_prompt_result', false, $pipeline_step_id, $system_prompt);

		if (!$success) {
			return new \WP_Error(
				'update_failed',
				__('Failed to save system prompt', 'datamachine'),
				['status' => 500]
			);
		}

		// Get pipeline_id from pipeline_step_id for cache clearing
		$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
		$pipeline_step_config = $db_pipelines->get_pipeline_step_config( $pipeline_step_id );

		if (!empty($pipeline_step_config['pipeline_id'])) {
			$pipeline_id = (int) $pipeline_step_config['pipeline_id'];
			do_action('datamachine_clear_pipeline_cache', $pipeline_id);
		}

		return rest_ensure_response([
			'success' => true,
			'data' => [],
			'message' => __('System prompt saved successfully', 'datamachine')
		]);
	}

	/**
	 * Handle AI step configuration update
	 *
	 * PUT /datamachine/v1/pipelines/steps/{pipeline_step_id}/config
	 */
	public static function handle_update_step_config($request) {
		// Validate permissions
		if (!current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('Insufficient permissions.', 'datamachine'),
				['status' => 403]
			);
		}

		// Extract pipeline_step_id from URL parameter
		$pipeline_step_id = sanitize_text_field($request->get_param('pipeline_step_id'));
		$is_patch = strtoupper($request->get_method()) === 'PATCH';

		$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();

		// Validate pipeline_step_id format
		$parsed_step_id = apply_filters('datamachine_split_pipeline_step_id', null, $pipeline_step_id);
		if ($parsed_step_id === null) {
			return new \WP_Error(
				'invalid_pipeline_step_id',
				__('Pipeline step ID format invalid - expected {pipeline_id}_{uuid4}', 'datamachine'),
				['status' => 400]
			);
		}

		$pipeline_id = (int) $request->get_param('pipeline_id');
		if (empty($pipeline_id) && !empty($parsed_step_id['pipeline_id'])) {
			$pipeline_id = (int) $parsed_step_id['pipeline_id'];
		}

		if (empty($pipeline_id)) {
			return new \WP_Error(
				'missing_pipeline_id',
				__('Pipeline ID is required for configuration updates.', 'datamachine'),
				['status' => 400]
			);
		}

		$step_type = sanitize_text_field($request->get_param('step_type'));
		if (empty($step_type)) {
			$step_type = 'ai';
		}

		if ($step_type !== 'ai') {
			return new \WP_Error(
				'invalid_step_type',
				__('Only AI steps support configuration updates.', 'datamachine'),
				['status' => 400]
			);
		}

		$pipeline = $db_pipelines->get_pipeline($pipeline_id);
		if (!$pipeline) {
			return new \WP_Error(
				'pipeline_not_found',
				__('Pipeline not found', 'datamachine'),
				['status' => 404]
			);
		}

		$pipeline_config = $pipeline['pipeline_config'] ?? [];
		$existing_config = $pipeline_config[$pipeline_step_id] ?? [];

		// Collect AI configuration parameters (only when provided)
		$has_provider = $request->has_param('provider');
		$has_model = $request->has_param('model');
		$has_system_prompt = $request->has_param('system_prompt');
		$has_enabled_tools = $request->has_param('enabled_tools');
		$has_api_key = $request->has_param('ai_api_key');
		$api_key_saved = false;

		$effective_provider = $has_provider
			? sanitize_text_field($request->get_param('provider'))
			: ($existing_config['provider'] ?? '');
		$effective_model = $has_model
			? sanitize_text_field($request->get_param('model'))
			: ($existing_config['model'] ?? '');
		$system_prompt = $has_system_prompt
			? sanitize_textarea_field($request->get_param('system_prompt'))
			: null;

		// Build step configuration data
		$step_config_data = [];

		if ($has_provider) {
			$step_config_data['provider'] = $effective_provider;
		}

		if ($has_model) {
			$step_config_data['model'] = $effective_model;

			$provider_for_model = $has_provider ? $effective_provider : ($existing_config['provider'] ?? '');

			if (!empty($provider_for_model) && !empty($effective_model)) {
				if (!isset($step_config_data['providers'])) {
					$step_config_data['providers'] = [];
				}
				if (!isset($step_config_data['providers'][$provider_for_model])) {
					$step_config_data['providers'][$provider_for_model] = [];
				}
				$step_config_data['providers'][$provider_for_model]['model'] = $effective_model;
			}
		}

		if ($has_system_prompt) {
			$step_config_data['system_prompt'] = $system_prompt;
		}

		if ($has_enabled_tools) {
			$enabled_tools_raw = $request->get_param('enabled_tools');
			$sanitized_tool_ids = [];
			if (is_array($enabled_tools_raw)) {
				$sanitized_tool_ids = array_map('sanitize_text_field', $enabled_tools_raw);
			}

			$tools_manager = new \DataMachine\Engine\AI\Tools\ToolManager();
			$step_config_data['enabled_tools'] = $tools_manager->save_step_tool_selections($pipeline_step_id, $sanitized_tool_ids);
		}

		if (empty($step_config_data) && !$has_api_key) {
			return new \WP_Error(
				'no_config_values',
				__('No configuration values were provided.', 'datamachine'),
				['status' => 400]
			);
		}

		// Store API key if provided
		if ($has_api_key && !empty($effective_provider)) {
			$ai_api_key = sanitize_text_field($request->get_param('ai_api_key'));
			try {
				// Use AI HTTP Client library's filters directly to save API key
				$all_keys = apply_filters('chubes_ai_provider_api_keys', null);
				$all_keys[$effective_provider] = $ai_api_key;
				apply_filters('chubes_ai_provider_api_keys', $all_keys);
				$api_key_saved = true;
			} catch (\Exception $e) {
			}
		}

		// Preserve provider-specific models by merging with existing config
		if (!empty($existing_config)) {
			if (isset($existing_config['providers']) && isset($step_config_data['providers'])) {
				$step_config_data['providers'] = array_merge(
					$existing_config['providers'],
					$step_config_data['providers']
				);
			} elseif (isset($existing_config['providers']) && !isset($step_config_data['providers'])) {
				$step_config_data['providers'] = $existing_config['providers'];
			}

			$pipeline_config[$pipeline_step_id] = array_merge($existing_config, $step_config_data);
		} else {
			$pipeline_config[$pipeline_step_id] = $step_config_data;
		}

		// Save updated pipeline configuration
		$success = $db_pipelines->update_pipeline($pipeline_id, [
			'pipeline_config' => $pipeline_config
		]);

		if (!$success) {
			return new \WP_Error(
				'save_failed',
				__('Error saving AI configuration', 'datamachine'),
				['status' => 500]
			);
		}

		$provider_for_log = $step_config_data['provider'] ?? ($existing_config['provider'] ?? null);

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'pipeline_step_id' => $pipeline_step_id,
				'debug_info' => [
					'api_key_saved' => $api_key_saved,
					'step_config_saved' => true,
					'provider' => $provider_for_log
				]
			],
			'message' => __('AI step configuration saved successfully', 'datamachine')
		]);
	}
}
