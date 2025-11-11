<?php
/**
 * Processed Items REST API Endpoint
 *
 * Provides REST API access to clear processed items tracking for deduplication.
 * Requires WordPress manage_options capability for all operations.
 *
 * Endpoints:
 * - DELETE /datamachine/v1/processed-items - Clear processed items by pipeline or flow
 *
 * @package DataMachine\Api
 */

namespace DataMachine\Api;

if (!defined('WPINC')) {
	die;
}

class ProcessedItems {

	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action('rest_api_init', [self::class, 'register_routes']);
	}

	/**
	 * Register all processed items related REST endpoints
	 */
	public static function register_routes() {

		// DELETE /datamachine/v1/processed-items - Clear processed items
		register_rest_route('datamachine/v1', '/processed-items', [
			'methods' => 'DELETE',
			'callback' => [self::class, 'handle_clear'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'clear_type' => [
					'required' => true,
					'type' => 'string',
					'enum' => ['pipeline', 'flow'],
					'description' => __('Clear by pipeline or flow', 'data-machine')
				],
				'target_id' => [
					'required' => true,
					'type' => 'integer',
					'description' => __('Pipeline ID or Flow ID', 'data-machine')
				]
			]
		]);
	}

	/**
	 * Check if user has permission to manage processed items
	 */
	public static function check_permission($request) {
		if (!current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have permission to manage processed items.', 'data-machine'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Handle clear processed items request
	 *
	 * DELETE /datamachine/v1/processed-items
	 */
	public static function handle_clear($request) {
		$clear_type = $request->get_param('clear_type');
		$target_id = $request->get_param('target_id');

		// Build criteria based on clear type
		$criteria = $clear_type === 'pipeline'
			? ['pipeline_id' => (int)$target_id]
			: ['flow_id' => (int)$target_id];

		// Delegate to centralized delete action
		do_action('datamachine_delete_processed_items', $criteria);

		// Log operation
		do_action('datamachine_log', 'info', 'Processed items cleared via REST API', [
			'clear_type' => $clear_type,
			'target_id' => $target_id,
			'user_id' => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login
		]);

		return [
			'success' => true,
			'message' => __('Processed items cleared successfully.', 'data-machine')
		];
	}
}
