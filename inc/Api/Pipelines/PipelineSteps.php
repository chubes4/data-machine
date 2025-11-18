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
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('Step type (fetch, ai, publish, update)', 'datamachine'),
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
			'methods' => 'PUT',
			'callback' => [self::class, 'handle_update_step_config'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'pipeline_step_id' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('Pipeline step ID (UUID4)', 'datamachine'),
				],
				'step_type' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('Step type (must be "ai")', 'datamachine'),
				],
				'pipeline_id' => [
					'required' => true,
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
			]
		]);
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
			do_action('datamachine_log', 'error', 'Failed to create step via REST API', [
				'pipeline_id' => $pipeline_id,
				'step_type' => $step_type,
				'user_id' => get_current_user_id()
			]);

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

		do_action('datamachine_log', 'info', 'Step created via REST API', [
			'pipeline_id' => $pipeline_id,
			'step_type' => $step_type,
			'pipeline_step_id' => $step_id,
			'user_id' => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login
		]);

		return [
			'success' => true,
			'message' => sprintf(__('Step "%s" added successfully', 'datamachine'), $step_config['label'] ?? $step_type),
			'step_type' => $step_type,
			'step_config' => $step_config,
			'pipeline_id' => $pipeline_id,
			'pipeline_step_id' => $step_id,
			'step_data' => $step_data,
			'created_type' => 'step'
		];
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

		return array_merge(['success' => true], $result);
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
			do_action('datamachine_log', 'error', 'Failed to save step order via REST API', [
				'pipeline_id' => $pipeline_id,
				'step_count' => count($updated_steps),
				'user_id' => get_current_user_id()
			]);

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
					do_action('datamachine_log', 'error', 'Flow step missing pipeline_step_id during step update sync', [
						'flow_id' => $flow_id,
						'flow_step_id' => $flow_step_id,
						'flow_step' => $flow_step
					]);
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

		do_action('datamachine_log', 'info', 'Pipeline steps reordered via REST API', [
			'pipeline_id' => $pipeline_id,
			'step_count' => count($updated_steps),
			'flow_count' => count($flows),
			'user_id' => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login
		]);

		return [
			'success' => true,
			'message' => __('Step order saved successfully', 'datamachine'),
			'pipeline_id' => $pipeline_id,
			'step_count' => count($updated_steps)
		];
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

		return [
			'success' => true,
			'message' => __('System prompt saved successfully', 'datamachine')
		];
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

		// Extract pipeline_id from request body
		$pipeline_id = (int) $request->get_param('pipeline_id');

		// Get pipeline and merge configuration
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

		// Collect AI configuration parameters
		$ai_provider = sanitize_text_field($request->get_param('provider'));
		$ai_model = sanitize_text_field($request->get_param('model'));
		$ai_api_key = sanitize_text_field($request->get_param('ai_api_key'));
		$enabled_tools_raw = $request->get_param('enabled_tools');
		$system_prompt = sanitize_textarea_field($request->get_param('system_prompt'));

		// Build step configuration data
		$step_config_data = [];

		if (!empty($ai_provider)) {
			$step_config_data['provider'] = $ai_provider;
		}

		if (!empty($ai_model)) {
			$step_config_data['model'] = $ai_model;

			// Store provider-specific model
			if (!empty($ai_provider) && !empty($ai_model)) {
				if (!isset($step_config_data['providers'])) {
					$step_config_data['providers'] = [];
				}
				if (!isset($step_config_data['providers'][$ai_provider])) {
					$step_config_data['providers'][$ai_provider] = [];
				}
				$step_config_data['providers'][$ai_provider]['model'] = $ai_model;
			}
		}

		// Save system prompt if provided
		if (!empty($system_prompt)) {
			$step_config_data['system_prompt'] = $system_prompt;
		}

		// Save tool selections
		$tools_manager = new \DataMachine\Engine\AI\Tools\ToolManager();

		// Sanitize tool selections
		$sanitized_tool_ids = [];
		if (is_array($enabled_tools_raw)) {
			$sanitized_tool_ids = array_map('sanitize_text_field', $enabled_tools_raw);
		}

		do_action('datamachine_log', 'debug', 'PipelineStepConfig: Before saving tool selections', [
			'pipeline_step_id' => $pipeline_step_id,
			'enabled_tools_count' => count($sanitized_tool_ids)
		]);

		$step_config_data['enabled_tools'] = $tools_manager->save_step_tool_selections($pipeline_step_id, $sanitized_tool_ids);

		do_action('datamachine_log', 'debug', 'PipelineStepConfig: After saving tool selections', [
			'pipeline_step_id' => $pipeline_step_id,
			'saved_enabled_tools' => $step_config_data['enabled_tools']
		]);

		// Store API key if provided
		if (!empty($ai_api_key) && !empty($ai_provider)) {
			try {
				// Use AI HTTP Client library's filters directly to save API key
				$all_keys = apply_filters('chubes_ai_provider_api_keys', null);
				$all_keys[$ai_provider] = $ai_api_key;
				apply_filters('chubes_ai_provider_api_keys', $all_keys);

				do_action('datamachine_log', 'debug', 'API key saved via AI HTTP Client filters', [
					'provider' => $ai_provider,
					'api_key_length' => strlen($ai_api_key)
				]);
			} catch (Exception $e) {
				do_action('datamachine_log', 'error', 'Exception when saving API key', [
					'provider' => $ai_provider,
					'exception' => $e->getMessage()
				]);
			}
		}

		// Get pipeline and merge configuration
		$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();

		$pipeline = $db_pipelines->get_pipeline($pipeline_id);
		if (!$pipeline) {
			return new \WP_Error(
				'pipeline_not_found',
				__('Pipeline not found', 'datamachine'),
				['status' => 404]
			);
		}

		$pipeline_config = $pipeline['pipeline_config'] ?? [];

		// Preserve provider-specific models by merging with existing config
		if (isset($pipeline_config[$pipeline_step_id])) {
			$existing_config = $pipeline_config[$pipeline_step_id];

			do_action('datamachine_log', 'debug', 'PipelineStepConfig: Merging with existing config', [
				'pipeline_step_id' => $pipeline_step_id,
				'existing_enabled_tools' => $existing_config['enabled_tools'] ?? null,
				'new_enabled_tools' => $step_config_data['enabled_tools'] ?? null
			]);

			// Merge provider configurations
			if (isset($existing_config['providers']) && isset($step_config_data['providers'])) {
				$step_config_data['providers'] = array_merge(
					$existing_config['providers'],
					$step_config_data['providers']
				);
			} elseif (isset($existing_config['providers']) && !isset($step_config_data['providers'])) {
				$step_config_data['providers'] = $existing_config['providers'];
			}

			// Merge with existing configuration
			$pipeline_config[$pipeline_step_id] = array_merge($existing_config, $step_config_data);

			do_action('datamachine_log', 'debug', 'PipelineStepConfig: Config merged', [
				'pipeline_step_id' => $pipeline_step_id,
				'final_enabled_tools' => $pipeline_config[$pipeline_step_id]['enabled_tools'] ?? null
			]);
		} else {
			$pipeline_config[$pipeline_step_id] = $step_config_data;
		}

		// Save updated pipeline configuration
		$success = $db_pipelines->update_pipeline($pipeline_id, [
			'pipeline_config' => json_encode($pipeline_config)
		]);

		if (!$success) {
			do_action('datamachine_log', 'error', 'Failed to save pipeline step configuration', [
				'pipeline_step_id' => $pipeline_step_id,
				'pipeline_id' => $pipeline_id
			]);

			return new \WP_Error(
				'save_failed',
				__('Error saving AI configuration', 'datamachine'),
				['status' => 500]
			);
		}

		// Trigger auto-save
		do_action('datamachine_auto_save', $pipeline_id);

		do_action('datamachine_log', 'debug', 'AI step configuration saved successfully', [
			'pipeline_step_id' => $pipeline_step_id,
			'pipeline_id' => $pipeline_id,
			'provider' => $ai_provider,
			'config_keys' => array_keys($step_config_data)
		]);

		return [
			'success' => true,
			'message' => __('AI step configuration saved successfully', 'datamachine'),
			'pipeline_step_id' => $pipeline_step_id,
			'debug_info' => [
				'api_key_saved' => !empty($ai_api_key),
				'step_config_saved' => true,
				'provider' => $ai_provider
			]
		];
	}
}
