<?php
/**
 * Facebook Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Facebook's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Publish\Handlers\Facebook
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Facebook;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Facebook component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Facebook capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_facebook_filters() {
    
    // Handler registration - Facebook declares itself as publish handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['facebook'] = [
            'type' => 'publish',
            'class' => Facebook::class,
            'label' => __('Facebook', 'data-machine'),
            'description' => __('Post content to Facebook pages and profiles', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_auth_providers', function($providers) {
        $providers['facebook'] = new FacebookAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['facebook'] = new FacebookSettings();
        return $all_settings;
    });
    
    // Facebook tool registration with AI HTTP Client library
    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        // Only generate Facebook tool when it's the target handler
        if ($handler_slug === 'facebook') {
            $tools['facebook_publish'] = dm_get_facebook_tool($handler_config);
        }
        return $tools;
    }, 10, 3);

    
    // Modal registrations removed - now handled by generic modal system via pure discovery
}

/**
 * Generate Facebook tool definition with dynamic parameters based on handler configuration.
 *
 * Dynamically constructs tool parameters based on Facebook-specific settings:
 * - Conditionally includes source_url parameter based on link_handling setting
 * - Conditionally includes image_url parameter based on include_images setting
 * - Modifies parameter descriptions based on link_handling mode (append/comment/none)
 *
 * @param array $handler_config Handler configuration containing Facebook-specific settings.
 * @return array Complete Facebook tool configuration for AI HTTP Client.
 */
function dm_get_facebook_tool(array $handler_config = []): array {
    // Extract Facebook-specific config from nested structure
    $facebook_config = $handler_config['facebook'] ?? $handler_config;
    
    // Debug logging for tool generation
    if (!empty($handler_config)) {
        do_action('dm_log', 'debug', 'Facebook Tool: Generating with configuration', [
            'handler_config_keys' => array_keys($handler_config),
            'facebook_config_keys' => array_keys($facebook_config),
            'facebook_config_values' => $facebook_config
        ]);
    }
    
    // Base tool definition
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
    
    // Store handler configuration for execution time
    if (!empty($handler_config)) {
        $tool['handler_config'] = $handler_config;
    }
    
    // Get configuration values with defaults from extracted config
    $include_images = $facebook_config['include_images'] ?? true;
    $link_handling = $facebook_config['link_handling'] ?? 'append';
    
    // URL parameters handled by system - AI only provides content
    
    // Update description based on enabled features
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
    
    do_action('dm_log', 'debug', 'Facebook Tool: Generation complete', [
        'parameter_count' => count($tool['parameters']),
        'parameter_names' => array_keys($tool['parameters']),
        'include_images' => $include_images,
        'link_handling' => $link_handling
    ]);
    
    return $tool;
}

/**
 * Register Facebook-specific success message formatter.
 */
function dm_register_facebook_success_message() {
    add_filter('dm_tool_success_message', function($default_message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name === 'facebook_publish' && !empty($tool_result['data']['post_url'])) {
            return "Post published successfully to Facebook at {$tool_result['data']['post_url']}.";
        }
        return $default_message;
    }, 10, 4);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_facebook_filters();
dm_register_facebook_success_message();