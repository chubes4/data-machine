<?php
/**
 * @package DataMachine\Core\Steps\Publish\Handlers\Bluesky
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Bluesky;

if (!defined('ABSPATH')) {
    exit;
}

function datamachine_register_bluesky_filters() {
    add_filter('datamachine_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'publish') {
            $handlers['bluesky'] = [
                'type' => 'publish',
                'class' => Bluesky::class,
                'label' => __('Bluesky', 'data-machine'),
                'description' => __('Post content to Bluesky with media support and AT Protocol integration', 'data-machine'),
                'requires_auth' => true
            ];
        }
        return $handlers;
    }, 10, 2);

    add_filter('datamachine_auth_providers', function($providers, $step_type = null) {
        if ($step_type === null || $step_type === 'publish') {
            $providers['bluesky'] = new BlueskyAuth();
        }
        return $providers;
    }, 10, 2);

    add_filter('datamachine_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'bluesky') {
            $all_settings['bluesky'] = new BlueskySettings();
        }
        return $all_settings;
    }, 10, 2);

    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        if ($handler_slug === 'bluesky') {
            $tools['bluesky_publish'] = datamachine_get_bluesky_tool($handler_config);
        }
        return $tools;
    }, 10, 3);
}

function datamachine_get_bluesky_tool(array $handler_config = []): array {
    $bluesky_config = $handler_config['bluesky'] ?? $handler_config;

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

    $include_images = $bluesky_config['include_images'] ?? true;
    $link_handling = $bluesky_config['link_handling'] ?? 'append';

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