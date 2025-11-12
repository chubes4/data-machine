<?php
/**
 * REST API Pipelines Endpoint
 *
 * Provides REST API access to pipeline creation operations.
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

class Pipelines {

	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action('rest_api_init', [self::class, 'register_routes']);
	}

	/**
	 * Register /datamachine/v1/pipelines endpoint
	 */
	public static function register_routes() {
		register_rest_route('datamachine/v1', '/pipelines', [
			[
				'methods' => 'GET',
				'callback' => [self::class, 'handle_get_pipelines'],
				'permission_callback' => [self::class, 'check_permission'],
				'args' => [
					'pipeline_id' => [
						'required' => false,
						'type' => 'integer',
						'description' => __('Pipeline ID to retrieve (omit for all pipelines)', 'datamachine'),
						'sanitize_callback' => 'absint',
					],
					'fields' => [
						'required' => false,
						'type' => 'string',
						'description' => __('Comma-separated list of fields to return', 'datamachine'),
						'sanitize_callback' => function($param) {
							return sanitize_text_field($param);
						}
					],
					'format' => [
						'required' => false,
						'type' => 'string',
						'default' => 'json',
						'enum' => ['json', 'csv'],
						'description' => __('Response format (json or csv)', 'datamachine'),
						'sanitize_callback' => 'sanitize_text_field',
					],
					'ids' => [
						'required' => false,
						'type' => 'string',
						'description' => __('Comma-separated pipeline IDs for export', 'datamachine'),
						'sanitize_callback' => 'sanitize_text_field',
					]
				]
			],
			[
				'methods' => 'POST',
				'callback' => [self::class, 'handle_create_pipeline'],
				'permission_callback' => [self::class, 'check_permission'],
				'args' => [
					'pipeline_name' => [
						'required' => false,
						'type' => 'string',
						'default' => 'Pipeline',
						'description' => __('Pipeline name', 'datamachine'),
						'sanitize_callback' => function($param) {
							return sanitize_text_field($param);
						}
					],
					'steps' => [
						'required' => false,
						'type' => 'array',
						'description' => __('Pipeline steps configuration (for complete mode)', 'datamachine'),
					],
					'flow_config' => [
						'required' => false,
						'type' => 'array',
						'description' => __('Flow configuration', 'datamachine'),
					],
					'batch_import' => [
						'required' => false,
						'type' => 'boolean',
						'default' => false,
						'description' => __('Enable batch import mode', 'datamachine'),
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
					'format' => [
						'required' => false,
						'type' => 'string',
						'default' => 'json',
						'enum' => ['json', 'csv'],
						'description' => __('Import format (json or csv)', 'datamachine'),
						'sanitize_callback' => 'sanitize_text_field',
					],
					'data' => [
						'required' => false,
						'type' => 'string',
						'description' => __('CSV data for batch import', 'datamachine'),
						'sanitize_callback' => function($param) {
							return wp_unslash($param);
						}
					]
				]
			]
		]);

		register_rest_route('datamachine/v1', '/pipelines/(?P<pipeline_id>\d+)', [
			[
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => [self::class, 'handle_delete_pipeline'],
				'permission_callback' => [self::class, 'check_permission'],
				'args' => [
					'pipeline_id' => [
						'required' => true,
						'type' => 'integer',
						'sanitize_callback' => 'absint',
						'description' => __('Pipeline ID to delete', 'datamachine'),
					],
				]
			],
			[
				'methods' => 'PATCH',
				'callback' => [self::class, 'handle_update_pipeline_title'],
				'permission_callback' => [self::class, 'check_permission'],
				'args' => [
					'pipeline_id' => [
						'required' => true,
						'type' => 'integer',
						'sanitize_callback' => 'absint',
						'description' => __('Pipeline ID to update', 'datamachine'),
					],
					'pipeline_name' => [
						'required' => true,
						'type' => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description' => __('New pipeline title', 'datamachine'),
					],
				]
			]
		]);

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
				'ai_provider' => [
					'required' => false,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('AI provider slug', 'datamachine'),
				],
				'ai_model' => [
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
				]
			]
		]);

		register_rest_route('datamachine/v1', '/pipelines/(?P<pipeline_id>\d+)/flows', [
			'methods' => 'GET',
			'callback' => [self::class, 'handle_get_pipeline_flows'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'pipeline_id' => [
					'required' => true,
					'type' => 'integer',
					'sanitize_callback' => 'absint',
					'description' => __('Pipeline ID to retrieve flows for', 'datamachine'),
				],
			]
		]);
	}

	/**
	 * Check if user has permission to create pipelines
	 */
	public static function check_permission($request) {
		if (!current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have permission to access pipelines.', 'datamachine'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Handle pipeline retrieval request
	 */
	public static function handle_get_pipelines($request) {
		$pipeline_id = $request->get_param('pipeline_id');
		$fields = $request->get_param('fields');
		$format = $request->get_param('format') ?: 'json';
		$ids = $request->get_param('ids');

		// Handle CSV export
		if ($format === 'csv') {
			// Parse IDs parameter
			$export_ids = [];
			if ($ids) {
				$export_ids = array_map('absint', explode(',', $ids));
			} elseif ($pipeline_id) {
				$export_ids = [$pipeline_id];
			} else {
				// Export all pipelines
				$all_pipelines = apply_filters('datamachine_get_pipelines', []);
				$export_ids = array_column($all_pipelines, 'pipeline_id');
			}

			// Get CSV data using existing export logic
			$import_export = new \DataMachine\Engine\Actions\ImportExport();
			$csv_content = $import_export->handle_export('pipelines', $export_ids);

			if (!$csv_content) {
				return new \WP_Error(
					'export_failed',
					__('Failed to generate CSV export.', 'datamachine'),
					['status' => 500]
				);
			}

			// Return CSV with proper headers
			$response = new \WP_REST_Response($csv_content);
			$response->set_headers([
				'Content-Type' => 'text/csv; charset=utf-8',
				'Content-Disposition' => 'attachment; filename="pipelines-export-' . gmdate('Y-m-d-H-i-s') . '.csv"',
			]);

			do_action('datamachine_log', 'info', 'Pipelines exported via REST API (CSV)', [
				'pipeline_count' => count($export_ids),
				'user_id' => get_current_user_id(),
				'user_login' => wp_get_current_user()->user_login
			]);

			return $response;
		}

		// Parse fields parameter if provided (JSON format)
		$requested_fields = [];
		if ($fields) {
			$requested_fields = array_map('trim', explode(',', $fields));
		}

		// Get pipeline data via filter
		if ($pipeline_id) {
			// Single pipeline retrieval
			$pipeline = apply_filters('datamachine_get_pipelines', [], $pipeline_id);

			if (!$pipeline) {
				return new \WP_Error(
					'pipeline_not_found',
					__('Pipeline not found.', 'datamachine'),
					['status' => 404]
				);
			}

			// Apply field filtering if requested
			if (!empty($requested_fields)) {
				$pipeline = array_intersect_key($pipeline, array_flip($requested_fields));
			}

			// Get flows and steps for this pipeline
			$all_databases = apply_filters('datamachine_db', []);
			$db_flows = $all_databases['flows'] ?? null;
			$flows = $db_flows ? $db_flows->get_flows_for_pipeline($pipeline_id) : [];
			$pipeline_steps = apply_filters('datamachine_get_pipeline_steps', [], $pipeline_id);

			return [
				'success' => true,
				'pipeline' => $pipeline,
				'flows' => $flows,
				'pipeline_steps' => $pipeline_steps
			];
		} else {
			// All pipelines retrieval
			$pipelines = apply_filters('datamachine_get_pipelines', []);

			// Apply field filtering if requested
			if (!empty($requested_fields)) {
				$pipelines = array_map(function($pipeline) use ($requested_fields) {
					return array_intersect_key($pipeline, array_flip($requested_fields));
				}, $pipelines);
			}

			return [
				'success' => true,
				'pipelines' => $pipelines,
				'total' => count($pipelines)
			];
		}
	}

	/**
	 * Handle pipeline creation request
	 */
	public static function handle_create_pipeline($request) {
		$params = $request->get_params();
		$batch_import = $request->get_param('batch_import') ?: false;
		$format = $request->get_param('format') ?: 'json';
		$data = $request->get_param('data');

		// Handle batch import
		if ($batch_import && $format === 'csv' && $data) {
			// Use existing import logic
			$import_export = new \DataMachine\Engine\Actions\ImportExport();
			$result = $import_export->handle_import('pipelines', $data);

			if (!$result) {
				return new \WP_Error(
					'import_failed',
					__('Failed to import pipelines from CSV.', 'datamachine'),
					['status' => 500]
				);
			}

			do_action('datamachine_log', 'info', 'Pipelines imported via REST API (CSV)', [
				'pipeline_count' => count($result['imported'] ?? []),
				'user_id' => get_current_user_id(),
				'user_login' => wp_get_current_user()->user_login
			]);

			return [
				'success' => true,
				'imported_pipeline_ids' => $result['imported'] ?? [],
				'count' => count($result['imported'] ?? []),
				'message' => sprintf(
					__('Successfully imported %d pipeline(s)', 'datamachine'),
					count($result['imported'] ?? [])
				)
			];
		}

		// Delegate to existing datamachine_create_pipeline filter
		$pipeline_id = apply_filters('datamachine_create_pipeline', false, $params);

		if (!$pipeline_id) {
			do_action('datamachine_log', 'error', 'Failed to create pipeline via REST API', [
				'params' => $params,
				'user_id' => get_current_user_id()
			]);

			return new \WP_Error(
				'pipeline_creation_failed',
				__('Failed to create pipeline.', 'datamachine'),
				['status' => 500]
			);
		}

		// Get pipeline and flow data for response
		$all_databases = apply_filters('datamachine_db', []);
		$db_pipelines = $all_databases['pipelines'] ?? null;
		$db_flows = $all_databases['flows'] ?? null;

		$pipeline = $db_pipelines ? $db_pipelines->get_pipeline($pipeline_id) : null;
		$existing_flows = $db_flows ? $db_flows->get_flows_for_pipeline($pipeline_id) : [];

		$creation_mode = isset($params['steps']) && is_array($params['steps']) ? 'complete' : 'simple';

		do_action('datamachine_log', 'info', 'Pipeline created via REST API', [
			'pipeline_id' => $pipeline_id,
			'pipeline_name' => $params['pipeline_name'] ?? 'Pipeline',
			'creation_mode' => $creation_mode,
			'user_id' => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login
		]);

		return [
			'success' => true,
			'pipeline_id' => $pipeline_id,
			'pipeline_name' => $params['pipeline_name'] ?? 'Pipeline',
			'pipeline_data' => $pipeline,
			'existing_flows' => $existing_flows,
			'creation_mode' => $creation_mode
		];
	}

	/**
	 * Handle pipeline deletion request.
	 */
	public static function handle_delete_pipeline($request) {
		$pipeline_id = (int) $request->get_param('pipeline_id');

		$result = Delete::delete_pipeline($pipeline_id);

		if (is_wp_error($result)) {
			return $result;
		}

		return array_merge(['success' => true], $result);
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
		$pipeline_steps = apply_filters('datamachine_get_pipeline_steps', [], $pipeline_id);

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
	 * Handle pipeline step deletion request.
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
		$all_databases = apply_filters('datamachine_db', []);
		$db_pipelines = $all_databases['pipelines'] ?? null;

		if (!$db_pipelines) {
			return new \WP_Error(
				'database_unavailable',
				__('Database service unavailable', 'datamachine'),
				['status' => 500]
			);
		}

		// Retrieve current pipeline configuration
		$pipeline_steps = apply_filters('datamachine_get_pipeline_steps', [], $pipeline_id);
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
		$flows = apply_filters('datamachine_get_pipeline_flows', [], $pipeline_id);

		foreach ($flows as $flow) {
			$flow_id = $flow['flow_id'];
			$flow_config = $flow['flow_config'] ?? [];

			// Update only execution_order in flow steps
			foreach ($flow_config as $flow_step_id => &$flow_step) {
				$pipeline_step_id = $flow_step['pipeline_step_id'] ?? null;

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
			apply_filters('datamachine_update_flow', false, $flow_id, [
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
	 * Handle pipeline flows retrieval request
	 */
	public static function handle_get_pipeline_flows($request) {
		$pipeline_id = (int) $request->get_param('pipeline_id');

		// Retrieve flows for pipeline via filter
		$pipeline_flows = apply_filters('datamachine_get_pipeline_flows', [], $pipeline_id);

		// Verify pipeline exists by checking if it has any data
		$pipeline = apply_filters('datamachine_get_pipelines', [], $pipeline_id);
		if (!$pipeline) {
			return new \WP_Error(
				'pipeline_not_found',
				__('Pipeline not found.', 'datamachine'),
				['status' => 404]
			);
		}

		$first_flow_id = null;
		if (!empty($pipeline_flows)) {
			$first_flow_id = $pipeline_flows[0]['flow_id'] ?? null;
		}

		return [
			'success' => true,
			'pipeline_id' => $pipeline_id,
			'flows' => $pipeline_flows,
			'flow_count' => count($pipeline_flows),
			'first_flow_id' => $first_flow_id
		];
	}

	/**
	 * Handle pipeline title update
	 *
	 * PATCH /datamachine/v1/pipelines/{id}
	 */
	public static function handle_update_pipeline_title($request) {
		$pipeline_id = (int) $request->get_param('pipeline_id');
		$pipeline_name = sanitize_text_field($request->get_param('pipeline_name'));

		if (empty($pipeline_name)) {
			return new \WP_Error(
				'empty_title',
				__('Pipeline title cannot be empty', 'datamachine'),
				['status' => 400]
			);
		}

		// Get database service
		$all_databases = apply_filters('datamachine_db', []);
		$db_pipelines = $all_databases['pipelines'] ?? null;

		if (!$db_pipelines) {
			return new \WP_Error(
				'database_unavailable',
				__('Database service unavailable', 'datamachine'),
				['status' => 500]
			);
		}

		// Update pipeline title
		$success = $db_pipelines->update_pipeline($pipeline_id, [
			'pipeline_name' => $pipeline_name
		]);

		if (!$success) {
			return new \WP_Error(
				'update_failed',
				__('Failed to save pipeline title', 'datamachine'),
				['status' => 500]
			);
		}

		// Clear caches
		do_action('datamachine_clear_pipeline_cache', $pipeline_id);
		do_action('datamachine_clear_pipelines_list_cache');

		return [
			'success' => true,
			'message' => __('Pipeline title saved successfully', 'datamachine'),
			'pipeline_id' => $pipeline_id,
			'pipeline_name' => $pipeline_name
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
		$pipeline_step_config = apply_filters('datamachine_get_pipeline_step_config', [], $pipeline_step_id);

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
		$pipeline_step_id = sanitize_text_field($request->get_param('pipeline_step_id'));
		$step_type = sanitize_text_field($request->get_param('step_type'));
		$pipeline_id = (int) $request->get_param('pipeline_id');

		// Validate step type
		if ($step_type !== 'ai') {
			return new \WP_Error(
				'invalid_step_type',
				/* translators: %s: Step type name */
				sprintf(__('Configuration for %s steps is not yet implemented', 'datamachine'), $step_type),
				['status' => 400]
			);
		}

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
		$ai_provider = sanitize_text_field($request->get_param('ai_provider'));
		$ai_model = sanitize_text_field($request->get_param('ai_model'));
		$ai_api_key = sanitize_text_field($request->get_param('ai_api_key'));
		$enabled_tools_raw = $request->get_param('enabled_tools');

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

		// Save tool selections
		$tools_manager = new \DataMachine\Core\Steps\AI\AIStepTools();

		// Build request params array for tool manager (expects $_POST-like structure)
		$tool_params = [];
		if (is_array($enabled_tools_raw)) {
			$tool_params['enabled_tools'] = array_map('sanitize_text_field', $enabled_tools_raw);
		}

		do_action('datamachine_log', 'debug', 'PipelineStepConfig: Before saving tool selections', [
			'pipeline_step_id' => $pipeline_step_id,
			'enabled_tools_count' => count($tool_params['enabled_tools'] ?? [])
		]);

		$step_config_data['enabled_tools'] = $tools_manager->save_tool_selections($pipeline_step_id, $tool_params);

		do_action('datamachine_log', 'debug', 'PipelineStepConfig: After saving tool selections', [
			'pipeline_step_id' => $pipeline_step_id,
			'saved_enabled_tools' => $step_config_data['enabled_tools']
		]);

		// Store API key if provided
		if (!empty($ai_api_key) && !empty($ai_provider)) {
			$all_keys = apply_filters('ai_provider_api_keys', null);
			if (!is_array($all_keys)) {
				$all_keys = [];
			}
			$all_keys[$ai_provider] = $ai_api_key;
			apply_filters('ai_provider_api_keys', $all_keys);

			do_action('datamachine_log', 'debug', 'API key saved via ai_provider_api_keys filter', [
				'provider' => $ai_provider,
				'keys_count' => count($all_keys),
				'api_key_length' => strlen($ai_api_key)
			]);
		}

		// Get pipeline and merge configuration
		$all_databases = apply_filters('datamachine_db', []);
		$db_pipelines = $all_databases['pipelines'] ?? null;

		if (!$db_pipelines) {
			return new \WP_Error(
				'database_unavailable',
				__('Pipeline database service not available', 'datamachine'),
				['status' => 500]
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
