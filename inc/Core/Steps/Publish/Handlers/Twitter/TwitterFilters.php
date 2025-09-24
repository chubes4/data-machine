<?php
/**
 * @package DataMachine\Core\Steps\Publish\Handlers\Twitter
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Twitter;

if (!defined('ABSPATH')) {
    exit;
}

function dm_register_twitter_filters() {
    add_filter('dm_handlers', function($handlers) {
        $handlers['twitter'] = [
            'type' => 'publish',
            'class' => Twitter::class,
            'label' => __('Twitter', 'data-machine'),
            'description' => __('Post content to Twitter with media support', 'data-machine')
        ];
        return $handlers;
    });

    add_filter('dm_auth_providers', function($providers) {
        $providers['twitter'] = new TwitterAuth();
        return $providers;
    });

    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['twitter'] = new TwitterSettings();
        return $all_settings;
    });

    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        if ($handler_slug === 'twitter') {
            $tools['twitter_publish'] = dm_get_twitter_tool($handler_config);
        }
        return $tools;
    }, 10, 3);

}

function dm_get_twitter_tool(array $handler_config = []): array {
    $twitter_config = $handler_config['twitter'] ?? $handler_config;
    
    
    $tool = [
        'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\Twitter\\Twitter',
        'method' => 'handle_tool_call',
        'handler' => 'twitter',
        'description' => 'Prepare and publish content to Twitter (280 char limit). This tool completes your pipeline task by publishing the processed content.',
        'parameters' => [
            'content' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Tweet content (will be formatted and truncated if needed)'
            ]
        ]
    ];
    
    if (!empty($handler_config)) {
        $tool['handler_config'] = $handler_config;
    }
    
    $include_images = $twitter_config['include_images'] ?? true;
    $link_handling = $twitter_config['link_handling'] ?? 'append';
    

    $description_parts = ['Post content to Twitter (280 character limit)'];
    if ($link_handling === 'append') {
        $description_parts[] = 'source URLs from data will be appended to tweets';
    } elseif ($link_handling === 'reply') {
        $description_parts[] = 'source URLs from data will be posted as reply tweets';
    }
    if ($include_images) {
        $description_parts[] = 'images from data will be uploaded automatically';
    }
    $tool['description'] = implode(', ', $description_parts);
    
    return $tool;
}

function dm_register_twitter_success_message() {
    add_filter('dm_tool_success_message', function($default_message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name === 'twitter_publish' && !empty($tool_result['data']['tweet_url'])) {
            return "TWEET POSTED: Your content is now live on Twitter at {$tool_result['data']['tweet_url']}. Your tweet is published and ready for engagement!";
        }
        return $default_message;
    }, 10, 4);
}

dm_register_twitter_filters();
dm_register_twitter_success_message();