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
 * @subpackage Core\Handlers\Publish\Facebook
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Publish\Facebook;

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
    
    // Handler directive registration - pure discovery mode
    add_filter('dm_handler_directives', function($directives) {
        $directives['facebook'] = 'When posting to Facebook, create engaging content that encourages interaction. Use compelling headlines, appropriate emojis, and ask questions to drive engagement. Format with clear paragraphs and include relevant hashtags.';
        return $directives;
    });
    
    // Facebook tool registration with AI HTTP Client library
    add_filter('ai_tools', function($tools) {
        $tools['facebook_publish'] = dm_get_facebook_tool();
        return $tools;
    });

    // Dynamic tool generation based on current configuration
    add_filter('dm_generate_handler_tool', function($tool, $handler_slug, $handler_config) {
        if ($handler_slug === 'facebook') {
            return dm_get_facebook_tool($handler_config);
        }
        return $tool;
    }, 10, 3);
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
}

/**
 * Get Facebook tool definition with dynamic parameters based on configuration.
 *
 * @param array $handler_config Optional handler configuration for dynamic parameters.
 * @return array Facebook tool configuration.
 */
function dm_get_facebook_tool(array $handler_config = []): array {
    // Debug logging for tool generation
    if (!empty($handler_config)) {
        do_action('dm_log', 'debug', 'Facebook Tool: Generating with configuration', [
            'handler_config_keys' => array_keys($handler_config),
            'handler_config_values' => $handler_config
        ]);
    }
    
    // Base tool definition
    $tool = [
        'class' => 'DataMachine\\Core\\Handlers\\Publish\\Facebook\\Facebook',
        'method' => 'handle_tool_call',
        'handler' => 'facebook',
        'description' => 'Post content to Facebook pages and profiles',
        'parameters' => [
            'content' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Post content'
            ],
            'title' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Optional title to prepend to content'
            ]
        ]
    ];
    
    // Store handler configuration for execution time
    if (!empty($handler_config)) {
        $tool['handler_config'] = $handler_config;
    }
    
    // Get configuration values with defaults
    $include_images = $handler_config['include_images'] ?? true;
    $include_videos = $handler_config['include_videos'] ?? true;
    $link_handling = $handler_config['link_handling'] ?? 'append';
    
    // Add conditional parameters based on configuration
    if ($link_handling !== 'none') {
        $description = $link_handling === 'comment' ? 'Optional source URL to post as Facebook comment' : 'Optional source URL (will be ' . $link_handling . 'ed to post)';
        $tool['parameters']['source_url'] = [
            'type' => 'string',
            'required' => false,
            'description' => $description
        ];
    }
    
    if ($include_images) {
        $tool['parameters']['image_url'] = [
            'type' => 'string',
            'required' => false,
            'description' => 'Optional image URL to attach to post'
        ];
    }
    
    // Update description based on enabled features
    $description_parts = ['Post content to Facebook'];
    if ($link_handling !== 'none') {
        if ($link_handling === 'comment') {
            $description_parts[] = 'source URLs will be posted as comments';
        } else {
            $description_parts[] = "links will be {$link_handling}ed";
        }
    }
    if ($include_images) {
        $description_parts[] = 'images will be uploaded if provided';
    }
    if ($include_videos) {
        $description_parts[] = 'video links will be included';
    }
    $tool['description'] = implode(', ', $description_parts);
    
    do_action('dm_log', 'debug', 'Facebook Tool: Generation complete', [
        'parameter_count' => count($tool['parameters']),
        'parameter_names' => array_keys($tool['parameters']),
        'include_images' => $include_images,
        'include_videos' => $include_videos,
        'link_handling' => $link_handling
    ]);
    
    return $tool;
}

// Auto-register when file loads - achieving complete self-containment
dm_register_facebook_filters();