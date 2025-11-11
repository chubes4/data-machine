<?php
/**
 * @package DataMachine\Core\Steps\Publish\Handlers\Facebook
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Facebook;

if (!defined('ABSPATH')) {
    exit;
}

function datamachine_register_facebook_filters() {
    add_filter('datamachine_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'publish') {
            $handlers['facebook'] = [
                'type' => 'publish',
                'class' => Facebook::class,
                'label' => __('Facebook', 'data-machine'),
                'description' => __('Post content to Facebook pages and profiles', 'data-machine'),
                'requires_auth' => true
            ];
        }
        return $handlers;
    }, 10, 2);

    add_filter('datamachine_auth_providers', function($providers, $step_type = null) {
        if ($step_type === null || $step_type === 'publish') {
            $providers['facebook'] = new FacebookAuth();
        }
        return $providers;
    }, 10, 2);

    add_filter('datamachine_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'facebook') {
            $all_settings['facebook'] = new FacebookSettings();
        }
        return $all_settings;
    }, 10, 2);

    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        if ($handler_slug === 'facebook') {
            $tools['facebook_publish'] = datamachine_get_facebook_tool($handler_config);
        }
        return $tools;
    }, 10, 3);

    add_filter('datamachine_get_handler_settings_display', function($settings_display, $flow_step_id, $step_type) {
        // Get flow step config to identify handler
        $flow_step_config = apply_filters('datamachine_get_flow_step_config', [], $flow_step_id);
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

/**
 * Generate Facebook tool definition with dynamic description based on handler settings.
 */
function datamachine_get_facebook_tool(array $handler_config = []): array {
    $facebook_config = $handler_config['facebook'] ?? $handler_config;

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

    $include_images = $facebook_config['include_images'] ?? true;
    $link_handling = $facebook_config['link_handling'] ?? 'append';

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