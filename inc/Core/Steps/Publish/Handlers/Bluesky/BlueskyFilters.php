<?php
/**
 * @package DataMachine\Core\Steps\Publish\Handlers\Bluesky
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Bluesky;

use DataMachine\Core\Steps\HandlerRegistrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bluesky handler registration and configuration.
 *
 * Uses HandlerRegistrationTrait to provide standardized handler registration
 * with app password authentication support and AI tool integration.
 *
 * @since 0.2.2
 */
class BlueskyFilters {
    use HandlerRegistrationTrait;

    /**
     * Register Bluesky publishing handler with all required filters.
     */
    public static function register(): void {
        self::registerHandler(
            'bluesky',
            'publish',
            Bluesky::class,
            __('Bluesky', 'datamachine'),
            __('Post content to Bluesky with media support and AT Protocol integration', 'datamachine'),
            true,
            BlueskyAuth::class,
            'DataMachine\\Core\\Steps\\Publish\\Handlers\\PublishHandlerSettings',
            function($tools, $handler_slug, $handler_config) {
                if ($handler_slug === 'bluesky') {
                    $tools['bluesky_publish'] = datamachine_get_bluesky_tool($handler_config);
                }
                return $tools;
            }
        );
    }
}

/**
 * Register Bluesky publishing handler and authentication filters.
 *
 * @since 0.1.0
 */
function datamachine_register_bluesky_filters() {
    BlueskyFilters::register();
}

function datamachine_get_bluesky_tool(array $handler_config = []): array {
    // handler_config is ALWAYS flat structure - no nesting

    $tool = [
        'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\Bluesky\\Bluesky',
        'method' => 'handle_tool_call',
        'handler' => 'bluesky',
        'description' => 'Post content to Bluesky (300 character limit)',
        'parameters' => [
            'content' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Post content (will be formatted and truncated if needed)'
            ]
        ]
    ];

    if (!empty($handler_config)) {
        $tool['handler_config'] = $handler_config;
    }

    $include_images = $handler_config['include_images'] ?? true;
    $link_handling = $handler_config['link_handling'] ?? 'append';

    $description_parts = ['Post content to Bluesky (300 character limit)'];
    if ($link_handling === 'append') {
        $description_parts[] = 'source URLs from data will be appended to posts';
    }
    if ($include_images) {
        $description_parts[] = 'images from data will be uploaded automatically';
    }
    $tool['description'] = implode(', ', $description_parts);

    return $tool;
}

function datamachine_register_bluesky_success_message() {
    add_filter('datamachine_tool_success_message', function($default_message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name === 'bluesky_publish' && !empty($tool_result['data']['post_url'])) {
            return "Post published successfully to Bluesky at {$tool_result['data']['post_url']}.";
        }
        return $default_message;
    }, 10, 4);
}

datamachine_register_bluesky_filters();
datamachine_register_bluesky_success_message();