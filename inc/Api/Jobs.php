<?php
/**
 * Jobs REST API Endpoint
 *
 * Provides REST API access to job execution history.
 * Requires WordPress manage_options capability for all operations.
 *
 * Endpoints:
 * - GET /datamachine/v1/jobs - Retrieve jobs list with pagination and filtering
 * - DELETE /datamachine/v1/jobs - Clear jobs (all or failed)
 *
 * @package DataMachine\Api
 */

namespace DataMachine\Api;

if (!defined('WPINC')) {
	die;
}

class Jobs {

	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action('rest_api_init', [self::class, 'register_routes']);
	}

	/**
	 * Register all jobs related REST endpoints
	 */
	public static function register_routes() {

		// GET /datamachine/v1/jobs - Retrieve jobs
		register_rest_route('datamachine/v1', '/jobs', [
			'methods' => 'GET',
			'callback' => [self::class, 'handle_get_jobs'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'orderby' => [
					'required' => false,
					'type' => 'string',
					'default' => 'job_id',
					'description' => __('Order jobs by field', 'datamachine')
				],
				'order' => [
					'required' => false,
					'type' => 'string',
					'default' => 'DESC',
					'enum' => ['ASC', 'DESC'],
					'description' => __('Sort order', 'datamachine')
				],
				'per_page' => [
					'required' => false,
					'type' => 'integer',
					'default' => 50,
					'minimum' => 1,
					'maximum' => 100,
					'description' => __('Number of jobs per page', 'datamachine')
				],
				'offset' => [
					'required' => false,
					'type' => 'integer',
					'default' => 0,
					'minimum' => 0,
					'description' => __('Offset for pagination', 'datamachine')
				],
				'pipeline_id' => [
					'required' => false,
					'type' => 'integer',
					'description' => __('Filter by pipeline ID', 'datamachine')
				],
				'flow_id' => [
					'required' => false,
					'type' => 'integer',
					'description' => __('Filter by flow ID', 'datamachine')
				],
				'status' => [
					'required' => false,
					'type' => 'string',
					'description' => __('Filter by job status', 'datamachine')
				]
			]
		]);

		// GET /datamachine/v1/jobs/{id} - Get specific job details
		register_rest_route('datamachine/v1', '/jobs/(?P<id>\d+)', [
			'methods' => 'GET',
			'callback' => [self::class, 'handle_get_job_by_id'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'id' => [
					'required' => true,
					'type' => 'integer',
					'description' => __('Job ID', 'datamachine')
				]
			]
		]);

		// DELETE /datamachine/v1/jobs - Clear jobs
		register_rest_route('datamachine/v1', '/jobs', [
			'methods' => 'DELETE',
			'callback' => [self::class, 'handle_clear'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'type' => [
					'required' => true,
					'type' => 'string',
					'enum' => ['all', 'failed'],
					'description' => __('Which jobs to clear: all or failed', 'datamachine')
				],
				'cleanup_processed' => [
					'required' => false,
					'type' => 'boolean',
					'default' => false,
					'description' => __('Also clear processed items tracking', 'datamachine')
				]
			]
		]);
	}

	/**
	 * Check if user has permission to manage jobs
	 */
	public static function check_permission($request) {
		if (!current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have permission to manage jobs.', 'datamachine'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Handle get jobs request
	 *
	 * GET /datamachine/v1/jobs
	 */
	public static function handle_get_jobs($request) {
		// Get database service
		$db_jobs = new \DataMachine\Core\Database\Jobs\Jobs();

		// Build query args
		$args = [
			'orderby' => $request->get_param('orderby'),
			'order' => $request->get_param('order'),
			'per_page' => $request->get_param('per_page'),
			'offset' => $request->get_param('offset')
		];

		// Add optional filters
		if ($request->get_param('pipeline_id')) {
			$args['pipeline_id'] = (int) $request->get_param('pipeline_id');
		}
		if ($request->get_param('flow_id')) {
			$args['flow_id'] = (int) $request->get_param('flow_id');
		}
		if ($request->get_param('status')) {
			$args['status'] = sanitize_text_field($request->get_param('status'));
		}

		// Retrieve jobs
		$jobs = $db_jobs->get_jobs_for_list_table($args);
		$total_jobs = $db_jobs->get_jobs_count();

		return rest_ensure_response([
			'success' => true,
			'data' => $jobs,
			'total' => $total_jobs,
			'per_page' => $args['per_page'],
			'offset' => $args['offset']
		]);
	}

	/**
	 * Handle get specific job by ID request
	 *
	 * GET /datamachine/v1/jobs/{id}
	 */
	public static function handle_get_job_by_id($request) {
		$job_id = $request->get_param('id');

		// Get job from database directly
		$db_jobs = new \DataMachine\Core\Database\Jobs\Jobs();
		$job = $db_jobs->get_job($job_id);

		if (!$job) {
			return new \WP_Error(
				'job_not_found',
				sprintf(__('Job %d not found.', 'datamachine'), $job_id),
				['status' => 404]
			);
		}

		return rest_ensure_response([
			'success' => true,
			'data' => $job
		]);
	}

	/**
	 * Handle clear jobs request
	 *
	 * DELETE /datamachine/v1/jobs
	 */
	public static function handle_clear($request) {
		$type = $request->get_param('type');
		$cleanup_processed = $request->get_param('cleanup_processed');

		// Delegate to centralized delete action
		do_action('datamachine_delete_jobs', $type, $cleanup_processed);

		return rest_ensure_response([
			'success' => true,
			'message' => __('Jobs cleared successfully.', 'datamachine')
		]);
	}
}
