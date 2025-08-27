<?php
/**
 * Twitter Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Twitter's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Publish\Handlers\Twitter
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Twitter;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Twitter component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Twitter capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_twitter_filters() {
    
    // Handler registration - Twitter declares itself as publish handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['twitter'] = [
            'type' => 'publish',
            'class' => Twitter::class,
            'label' => __('Twitter', 'data-machine'),
            'description' => __('Post content to Twitter with media support', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_auth_providers', function($providers) {
        $providers['twitter'] = new TwitterAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['twitter'] = new TwitterSettings();
        return $all_settings;
    });
    
    // Handler directive registration - pure discovery mode
    add_filter('dm_handler_directives', function($directives) {
        $directives['twitter'] = 'When posting to Twitter, format your response as concise, engaging content under 280 characters. Use relevant hashtags and mention handles when appropriate. Keep the tone conversational and engaging.';
        return $directives;
    });
    
    // Twitter tool registration with AI HTTP Client library
    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        // Only generate Twitter tool when it's the target handler
        if ($handler_slug === 'twitter') {
            $tools['twitter_publish'] = dm_get_twitter_tool($handler_config);
        }
        return $tools;
    }, 10, 3);

    
    // Modal registrations removed - now handled by generic modal system via pure discovery
}

/**
 * Generate Twitter tool definition with dynamic parameters based on handler configuration.
 *
 * Dynamically constructs tool parameters based on Twitter-specific settings:
 * - Conditionally includes source_url parameter based on include_source setting
 * - Conditionally includes image_url parameter based on enable_images setting
 * - Modifies parameter descriptions based on url_as_reply setting
 *
 * @param array $handler_config Handler configuration containing Twitter-specific settings.
 * @return array Complete Twitter tool configuration for AI HTTP Client.
 */
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
    $include_source = $twitter_config['twitter_include_source'] ?? true;
    $enable_images = $twitter_config['twitter_enable_images'] ?? true;
    $url_as_reply = $twitter_config['twitter_url_as_reply'] ?? false;
    
    // URL parameters handled by system - AI only provides content
    
    // Update description based on enabled features
    $description_parts = ['Post content to Twitter (280 character limit)'];
    if ($include_source) {
        if ($url_as_reply) {
            $description_parts[] = 'source URLs from data will be posted as reply tweets';
        } else {
            $description_parts[] = 'source URLs from data will be appended';
        }
    }
    if ($enable_images) {
        $description_parts[] = 'images from data will be uploaded automatically';
    }
    $tool['description'] = implode(', ', $description_parts);
    
    do_action('dm_log', 'debug', 'Twitter Tool: Generation complete', [
        'parameter_count' => count($tool['parameters']),
        'parameter_names' => array_keys($tool['parameters']),
        'include_source' => $include_source,
        'enable_images' => $enable_images,
        'url_as_reply' => $url_as_reply
    ]);
    
    return $tool;
}

// Auto-register when file loads - achieving complete self-containment
dm_register_twitter_filters();