<?php
/**
 * REST API Pipelines Endpoint
 *
 * Provides REST API access to pipeline CRUD operations.
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

class Pipelines {

	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action('rest_api_init', [self::class, 'register_routes']);
	}

	/**
	 * Register pipeline CRUD endpoints
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
	}

	/**
	 * Check if user has permission to access pipelines
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
				$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
				$all_pipelines = $db_pipelines->get_all_pipelines();
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
			$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
			$pipeline = $db_pipelines->get_pipeline($pipeline_id);

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

			// Get flows for this pipeline
			$db_flows = new \DataMachine\Core\Database\Flows\Flows();
			$flows = $db_flows->get_flows_for_pipeline($pipeline_id);

			return rest_ensure_response([
				'success' => true,
				'data' => [
					'pipeline' => $pipeline,
					'flows' => $flows
				]
			]);
		} else {
			// All pipelines retrieval
			$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
			$pipelines = $db_pipelines->get_all_pipelines();

			// Apply field filtering if requested
			if (!empty($requested_fields)) {
				$pipelines = array_map(function($pipeline) use ($requested_fields) {
					return array_intersect_key($pipeline, array_flip($requested_fields));
				}, $pipelines);
			}

			return rest_ensure_response([
				'success' => true,
				'data' => [
					'pipelines' => $pipelines,
					'total' => count($pipelines)
				]
			]);
		}
	}

	/**
	 * Handle pipeline creation request
	 */
	public static function handle_create_pipeline($request) {
		// Validate permissions
		if (!current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('Insufficient permissions.', 'datamachine'),
				['status' => 403]
			);
		}

		// Get parameters from request
		$params = $request->get_json_params();
		if (empty($params) || !isset($params['pipeline_name'])) {
			return new \WP_Error(
				'rest_invalid_param',
				__('Pipeline name is required.', 'datamachine'),
				['status' => 400]
			);
		}

		// Create the pipeline using the centralized filter
		$pipeline_id = apply_filters('datamachine_create_pipeline', null, $params);
		if (!$pipeline_id) {
			return new \WP_Error(
				'rest_internal_server_error',
				__('Failed to create pipeline.', 'datamachine'),
				['status' => 500]
			);
		}

		// Get pipeline and flow data for response
		$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
		$db_flows = new \DataMachine\Core\Database\Flows\Flows();

		$pipeline = $db_pipelines->get_pipeline($pipeline_id);
		$existing_flows = $db_flows ? $db_flows->get_flows_for_pipeline($pipeline_id) : [];

		$creation_mode = isset($params['steps']) && is_array($params['steps']) ? 'complete' : 'simple';

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'pipeline_id' => $pipeline_id,
				'pipeline_name' => $params['pipeline_name'] ?? 'Pipeline',
				'pipeline_data' => $pipeline,
				'existing_flows' => $existing_flows,
				'creation_mode' => $creation_mode
			]
		]);
	}

	/**
	 * Handle pipeline deletion request
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
	 * Handle pipeline title update
	 *
	 * PATCH /datamachine/v1/pipelines/{id}
	 */
	public static function handle_update_pipeline_title($request) {
		// Validate permissions
		if (!current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('Insufficient permissions.', 'datamachine'),
				['status' => 403]
			);
		}

		// Get parameters from request
		$pipeline_id = (int) $request->get_param('id');
		$params = $request->get_json_params();

		if (!$pipeline_id || empty($params['pipeline_name'])) {
			return new \WP_Error(
				'rest_invalid_param',
				__('Pipeline ID and name are required.', 'datamachine'),
				['status' => 400]
			);
		}

		// Get database service
		$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();

		// Update pipeline title
		$success = $db_pipelines->update_pipeline($pipeline_id, [
			'pipeline_name' => sanitize_text_field(wp_unslash($params['pipeline_name']))
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

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'pipeline_id' => $pipeline_id,
				'pipeline_name' => sanitize_text_field(wp_unslash($params['pipeline_name']))
			],
			'message' => __('Pipeline title saved successfully', 'datamachine')
		]);
	}
}
