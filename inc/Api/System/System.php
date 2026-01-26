<?php
/**
 * System REST API Endpoint
 *
 * System infrastructure operations for Data Machine.
 *
 * @package DataMachine\Api\System
 * @since   0.13.7
 */

namespace DataMachine\Api\System;

use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * System API Handler
 */
class System {


	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action('rest_api_init', array( self::class, 'register_routes' ));
	}

	/**
	 * Register system endpoints
	 */
	public static function register_routes() {
		// System status endpoint - could be useful for monitoring
		register_rest_route(
			'datamachine/v1',
			'/system/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_status' ),
				'permission_callback' => function () {
					return current_user_can('manage_options');
				},
			)
		);
	}

	/**
	 * Get system status
	 *
	 * @param  WP_REST_Request $request Request object
	 * @return array|WP_Error Response data or error
	 */
	public static function get_status( WP_REST_Request $request ) {
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'status'    => 'operational',
					'version'   => defined('DATAMACHINE_VERSION') ? DATAMACHINE_VERSION : 'unknown',
					'timestamp' => current_time('mysql', true),
				),
			)
		);
	}
}
