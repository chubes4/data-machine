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
					'description' => __('Clear by pipeline or flow', 'datamachine')
				],
				'target_id' => [
					'required' => true,
					'type' => 'integer',
					'description' => __('Pipeline ID or Flow ID', 'datamachine')
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
				__('You do not have permission to manage processed items.', 'datamachine'),
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
		$target_id = (int) $request->get_param('target_id');

		$processed_items_manager = new \DataMachine\Services\ProcessedItemsManager();

		$result = $clear_type === 'pipeline'
			? $processed_items_manager->deleteForPipeline($target_id)
			: $processed_items_manager->deleteForFlow($target_id);

		if ($result === false) {
			return new \WP_Error(
				'delete_failed',
				__('Failed to delete processed items.', 'datamachine'),
				['status' => 500]
			);
		}

		return rest_ensure_response([
			'success' => true,
			'data' => null,
			'message' => sprintf(
				/* translators: %d: Number of processed items deleted */
				__('Deleted %d processed items.', 'datamachine'),
				$result
			),
			'items_deleted' => $result
		]);
	}
}
