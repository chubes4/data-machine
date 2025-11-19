<?php
/**
 * @package DataMachine\Core\Steps\Publish\Handlers\Facebook
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Facebook;

use DataMachine\Core\Steps\HandlerRegistrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Facebook handler registration and configuration.
 *
 * Uses HandlerRegistrationTrait to provide standardized handler registration
 * with OAuth 2.0 authentication support and AI tool integration.
 * Preserves custom settings display filtering for OAuth fields.
 *
 * @since 0.2.2
 */
class FacebookFilters {
    use HandlerRegistrationTrait;

    /**
     * Register Facebook publishing handler with all required filters.
     */
    public static function register(): void {
        self::registerHandler(
            'facebook',
            'publish',
            Facebook::class,
            __('Facebook', 'datamachine'),
            __('Post content to Facebook pages and profiles', 'datamachine'),
            true,
            FacebookAuth::class,
            FacebookSettings::class,
            function($tools, $handler_slug, $handler_config) {
                if ($handler_slug === 'facebook') {
                    $tools['facebook_publish'] = datamachine_get_facebook_tool($handler_config);
                }
                return $tools;
            }
        );

        // Custom settings display filtering (preserved)
        add_filter('datamachine_get_handler_settings_display', function($settings_display, $flow_step_id, $step_type) {
            // Get flow step config to identify handler
            $db_flows = new \DataMachine\Core\Database\Flows\Flows();
            $flow_step_config = $db_flows->get_flow_step_config( $flow_step_id );
            $handler_slug = $flow_step_config['handler_slug'] ?? '';

            if ($handler_slug !== 'facebook') {
                return $settings_display;
            }

            $customized_display = [];

            // Facebook internal OAuth fields to hide from display
            $facebook_internal_fields = [
                'page_id', 'page_name', 'user_id', 'user_name',
                'access_token', 'page_access_token', 'user_access_token',
                'token_type', 'authenticated_at', 'token_expires_at', 'target_id'
            ];

            foreach ($settings_display as $setting) {
                $setting_key = $setting['key'] ?? '';

                // Hide internal OAuth authentication fields
                if (in_array($setting_key, $facebook_internal_fields)) {
                    continue;
                }

                // Keep all other settings
                $customized_display[] = $setting;
            }

            return $customized_display;
        }, 20, 3);
    }
}

/**
 * Register Facebook publishing handler and authentication filters.
 *
 * @since 0.1.0
 */
function datamachine_register_facebook_filters() {
    FacebookFilters::register();
}

/**
 * Generate Facebook tool definition with dynamic description based on handler settings.
 */
function datamachine_get_facebook_tool(array $handler_config = []): array {
    // handler_config is ALWAYS flat structure - no nesting

    $tool = [
        'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\Facebook\\Facebook',
        'method' => 'handle_tool_call',
        'handler' => 'facebook',
        'description' => 'Post content to Facebook pages and profiles',
        'parameters' => [
            'content' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Post content'
            ]
        ]
    ];

    if (!empty($handler_config)) {
        $tool['handler_config'] = $handler_config;
    }

    $include_images = $handler_config['include_images'] ?? true;
    $link_handling = $handler_config['link_handling'] ?? 'append';

    $description_parts = ['Post content to Facebook'];
    if ($link_handling !== 'none') {
        if ($link_handling === 'comment') {
            $description_parts[] = 'source URLs from data will be posted as comments';
        } else {
            $description_parts[] = "links from data will be {$link_handling}ed";
        }
    }
    if ($include_images) {
        $description_parts[] = 'images from data will be uploaded automatically';
    }
    $tool['description'] = implode(', ', $description_parts);

    return $tool;
}

function datamachine_register_facebook_success_message() {
    add_filter('datamachine_tool_success_message', function($default_message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name === 'facebook_publish' && !empty($tool_result['data']['post_url'])) {
            return "Post published successfully to Facebook at {$tool_result['data']['post_url']}.";
        }
        return $default_message;
    }, 10, 4);
}

datamachine_register_facebook_filters();
datamachine_register_facebook_success_message();