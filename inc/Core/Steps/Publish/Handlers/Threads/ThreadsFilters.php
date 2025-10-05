<?php
/**
 * @package DataMachine\Core\Steps\Publish\Handlers\Threads
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Threads;

if (!defined('ABSPATH')) {
    exit;
}

function dm_register_threads_filters() {
    add_filter('dm_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'publish') {
            $handlers['threads'] = [
                'type' => 'publish',
                'class' => Threads::class,
                'label' => __('Threads', 'data-machine'),
                'description' => __('Publish content to Threads (Meta\'s Twitter alternative)', 'data-machine'),
                'requires_auth' => true
            ];
        }
        return $handlers;
    }, 10, 2);

    add_filter('dm_auth_providers', function($providers, $step_type = null) {
        if ($step_type === null || $step_type === 'publish') {
            $providers['threads'] = new ThreadsAuth();
        }
        return $providers;
    }, 10, 2);

    add_filter('dm_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'threads') {
            $all_settings['threads'] = new ThreadsSettings();
        }
        return $all_settings;
    }, 10, 2);

    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        if ($handler_slug === 'threads') {
            $tools['threads_publish'] = dm_get_threads_tool($handler_config);
        }
        return $tools;
    }, 10, 3);
}

/**
 * Generate Threads tool definition with dynamic description based on handler settings.
 */
function dm_get_threads_tool(array $handler_config = []): array {
    $threads_config = $handler_config['threads'] ?? $handler_config;

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

    $include_images = $threads_config['include_images'] ?? true;
    $link_handling = $threads_config['link_handling'] ?? 'append';

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

function dm_register_threads_success_message() {
    add_filter('dm_tool_success_message', function($default_message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name === 'threads_publish' && !empty($tool_result['data']['post_url'])) {
            return "Post published successfully to Threads at {$tool_result['data']['post_url']}.";
        }
        return $default_message;
    }, 10, 4);
}

dm_register_threads_filters();
dm_register_threads_success_message();