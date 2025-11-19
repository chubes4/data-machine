<?php
/**
 * @package DataMachine\Core\Steps\Publish\Handlers\Threads
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Threads;

use DataMachine\Core\Steps\HandlerRegistrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Threads handler registration and configuration.
 *
 * Uses HandlerRegistrationTrait to provide standardized handler registration
 * with OAuth 2.0 authentication support and AI tool integration.
 *
 * @since 0.2.2
 */
class ThreadsFilters {
    use HandlerRegistrationTrait;

    /**
     * Register Threads publishing handler with all required filters.
     */
    public static function register(): void {
        self::registerHandler(
            'threads',
            'publish',
            Threads::class,
            __('Threads', 'datamachine'),
            __('Publish content to Threads (Meta\'s Twitter alternative)', 'datamachine'),
            true,
            ThreadsAuth::class,
            'DataMachine\\Core\\Steps\\Publish\\Handlers\\PublishHandlerSettings',
            function($tools, $handler_slug, $handler_config) {
                if ($handler_slug === 'threads') {
                    $tools['threads_publish'] = datamachine_get_threads_tool($handler_config);
                }
                return $tools;
            }
        );
    }
}

/**
 * Register Threads publishing handler and authentication filters.
 *
 * @since 0.1.0
 */
function datamachine_register_threads_filters() {
    ThreadsFilters::register();
}

/**
 * Generate Threads tool definition with dynamic description based on handler settings.
 */
function datamachine_get_threads_tool(array $handler_config = []): array {
    // handler_config is ALWAYS flat structure - no nesting

    $tool = [
        'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\Threads\\Threads',
        'method' => 'handle_tool_call',
        'handler' => 'threads',
        'description' => 'Post content to Threads (500 character limit)',
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

    $description_parts = ['Post content to Threads (500 character limit)'];
    if ($link_handling === 'append') {
        $description_parts[] = 'source URLs from data will be appended to posts';
    }
    if ($include_images) {
        $description_parts[] = 'images from data will be uploaded automatically';
    }
    $tool['description'] = implode(', ', $description_parts);

    return $tool;
}

function datamachine_register_threads_success_message() {
    add_filter('datamachine_tool_success_message', function($default_message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name === 'threads_publish' && !empty($tool_result['data']['post_url'])) {
            return "Post published successfully to Threads at {$tool_result['data']['post_url']}.";
        }
        return $default_message;
    }, 10, 4);
}

datamachine_register_threads_filters();
datamachine_register_threads_success_message();