<?php
/**
 * Twitter Handler Registration
 *
 * Auto-registers Twitter publish handler, authentication, and AI tools via filter system.
 *
 * @package DataMachine\Core\Steps\Publish\Handlers\Twitter
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Twitter;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function dm_register_twitter_filters() {
    
    // Register as publish handler
    add_filter('dm_handlers', function($handlers) {
        $handlers['twitter'] = [
            'type' => 'publish',
            'class' => Twitter::class,
            'label' => __('Twitter', 'data-machine'),
            'description' => __('Post content to Twitter with media support', 'data-machine')
        ];
        return $handlers;
    });
    
    // Register authentication provider
    add_filter('dm_auth_providers', function($providers) {
        $providers['twitter'] = new TwitterAuth();
        return $providers;
    });
    
    // Register settings handler
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['twitter'] = new TwitterSettings();
        return $all_settings;
    });
    
    // Register AI tool for handler-specific publishing
    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        // Only generate Twitter tool when it's the target handler
        if ($handler_slug === 'twitter') {
            $tools['twitter_publish'] = dm_get_twitter_tool($handler_config);
        }
        return $tools;
    }, 10, 3);

    
    // Modal handling via generic discovery system
}

function dm_get_twitter_tool(array $handler_config = []): array {
    // Extract Twitter-specific config from nested structure
    $twitter_config = $handler_config['twitter'] ?? $handler_config;
    
    // Debug logging for tool generation
    if (!empty($handler_config)) {
        do_action('dm_log', 'debug', 'Twitter Tool: Generating with configuration', [
            'handler_config_keys' => array_keys($handler_config),
            'twitter_config_keys' => array_keys($twitter_config),
            'twitter_config_values' => $twitter_config
        ]);
    }
    
    // Base tool definition
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
    
    // Store handler configuration for execution time
    if (!empty($handler_config)) {
        $tool['handler_config'] = $handler_config;
    }
    
    // Get configuration values with defaults from extracted config
    $include_images = $twitter_config['include_images'] ?? true;
    $link_handling = $twitter_config['link_handling'] ?? 'append';
    
    // URL parameters handled by system - AI only provides content
    
    // Update description based on enabled features
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
    
    do_action('dm_log', 'debug', 'Twitter Tool: Generation complete', [
        'parameter_count' => count($tool['parameters']),
        'parameter_names' => array_keys($tool['parameters']),
        'include_images' => $include_images,
        'link_handling' => $link_handling
    ]);
    
    return $tool;
}

/**
 * Register Twitter-specific success message formatter.
 */
function dm_register_twitter_success_message() {
    add_filter('dm_tool_success_message', function($default_message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name === 'twitter_publish' && !empty($tool_result['data']['tweet_url'])) {
            return "TWEET POSTED: Your content is now live on Twitter at {$tool_result['data']['tweet_url']}. Your tweet is published and ready for engagement!";
        }
        return $default_message;
    }, 10, 4);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_twitter_filters();
dm_register_twitter_success_message();