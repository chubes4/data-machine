<?php
/**
 * REST API Users Endpoint
 *
 * Provides REST API access for user-scoped Data Machine preferences.
 *
 * @package DataMachine\Api
 */

namespace DataMachine\Api;

use WP_REST_Request;
use WP_REST_Server;
use WP_Error;

if (!defined('WPINC')) {
    die;
}

class Users
{
    /**
     * Register REST API routes for user-scoped data.
     */
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /**
     * Register /datamachine/v1/users routes.
     */
    public static function register_routes(): void
    {
        register_rest_route('datamachine/v1', '/users/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'handle_get_user'],
                'permission_callback' => [self::class, 'check_permission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                        'description' => __('The user ID to retrieve preferences for.', 'datamachine'),
                    ],
                ],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'handle_update_user'],
                'permission_callback' => [self::class, 'check_permission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                        'description' => __('The user ID to update preferences for.', 'datamachine'),
                    ],
                    'selected_pipeline_id' => [
                        'required' => false,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                        'description' => __('Pipeline ID to set as the preferred pipeline.', 'datamachine'),
                    ],
                ],
            ],
        ]);

        register_rest_route('datamachine/v1', '/users/me', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'handle_get_current_user'],
                'permission_callback' => [self::class, 'check_current_user_permission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'handle_update_current_user'],
                'permission_callback' => [self::class, 'check_current_user_permission'],
                'args' => [
                    'selected_pipeline_id' => [
                        'required' => false,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                        'description' => __('Pipeline ID to set as the preferred pipeline.', 'datamachine'),
                    ],
                ],
            ],
        ]);
    }

    /**
     * Handle GET /users/{id} requests.
     */
    public static function handle_get_user(WP_REST_Request $request)
    {
        $user_id = (int) $request->get_param('id');

        $preference = self::get_pipeline_preference($user_id);

        return [
            'success' => true,
            'user_id' => $user_id,
            'selected_pipeline_id' => $preference,
        ];
    }

    /**
     * Handle POST /users/{id} requests.
     */
    public static function handle_update_user(WP_REST_Request $request)
    {
        $user_id = (int) $request->get_param('id');
        $selected_pipeline_id = $request->get_param('selected_pipeline_id');

        if (null === $selected_pipeline_id) {
            delete_user_meta($user_id, 'datamachine_selected_pipeline_id');

            return [
                'success' => true,
                'user_id' => $user_id,
                'selected_pipeline_id' => null,
            ];
        }

        if ($selected_pipeline_id <= 0) {
            return new WP_Error(
                'invalid_pipeline_id',
                __('Pipeline ID must be a positive integer.', 'datamachine'),
                ['status' => 400]
            );
        }

        if (!self::pipeline_exists($selected_pipeline_id)) {
            return new WP_Error(
                'pipeline_not_found',
                __('Pipeline not found.', 'datamachine'),
                ['status' => 404]
            );
        }

        update_user_meta($user_id, 'datamachine_selected_pipeline_id', (string) $selected_pipeline_id);

        return [
            'success' => true,
            'user_id' => $user_id,
            'selected_pipeline_id' => (int) $selected_pipeline_id,
        ];
    }

    /**
     * Handle GET /users/me requests.
     */
    public static function handle_get_current_user(WP_REST_Request $request)
    {
        $request->set_param('id', get_current_user_id());

        return self::handle_get_user($request);
    }

    /**
     * Handle POST /users/me requests.
     */
    public static function handle_update_current_user(WP_REST_Request $request)
    {
        $request->set_param('id', get_current_user_id());

        return self::handle_update_user($request);
    }

    /**
     * Permission callback for /users/{id} routes.
     */
    public static function check_permission(WP_REST_Request $request)
    {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                __('You must be logged in to access user preferences.', 'datamachine'),
                ['status' => 401]
            );
        }

        $target_user_id = (int) $request->get_param('id');

        if ($target_user_id <= 0) {
            return new WP_Error(
                'invalid_user_id',
                __('A valid user ID is required.', 'datamachine'),
                ['status' => 400]
            );
        }

        if (current_user_can('manage_options') || get_current_user_id() === $target_user_id) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __('You do not have permission to access this user.', 'datamachine'),
            ['status' => 403]
        );
    }

    /**
     * Permission callback for /users/me routes.
     */
    public static function check_current_user_permission($request = null)
    {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                __('You must be logged in to access user preferences.', 'datamachine'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Determine if a pipeline exists within the current system.
     */
    protected static function pipeline_exists(int $pipeline_id): bool
    {
        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $pipelines = $db_pipelines->get_pipelines_list();

        foreach ($pipelines as $pipeline) {
            if ((int) ($pipeline['pipeline_id'] ?? 0) === $pipeline_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve the stored pipeline preference for a given user.
     */
    protected static function get_pipeline_preference(int $user_id): ?int
    {
        $stored = get_user_meta($user_id, 'datamachine_selected_pipeline_id', true);

        if ($stored === '' || $stored === false) {
            return null;
        }

        $pipeline_id = (int) $stored;

        return $pipeline_id > 0 ? $pipeline_id : null;
    }
}
