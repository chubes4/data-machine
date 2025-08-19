<?php
/**
 * Threads Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Threads' complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Output\Threads
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Publish\Threads;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Threads component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Threads capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_threads_filters() {
    
    // Handler registration - Threads declares itself as publish handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['threads'] = [
            'type' => 'publish',
            'class' => Threads::class,
            'label' => __('Threads', 'data-machine'),
            'description' => __('Publish content to Threads (Meta\'s Twitter alternative)', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_auth_providers', function($providers) {
        $providers['threads'] = new ThreadsAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['threads'] = new ThreadsSettings();
        return $all_settings;
    });
    
    // Handler directive registration - pure discovery mode
    add_filter('dm_handler_directives', function($directives) {
        $directives['threads'] = 'When posting to Threads, create content that encourages conversation and connection. Use a more casual, authentic tone similar to Instagram. Keep posts under 500 characters when possible, use relevant hashtags, and consider adding questions to spark discussion.';
        return $directives;
    });
    
    // Threads tool registration with AI HTTP Client library
    add_filter('ai_tools', function($tools) {
        $tools['threads_publish'] = dm_get_threads_tool();
        return $tools;
    });

    // Dynamic tool generation based on current configuration
    add_filter('dm_generate_handler_tool', function($tool, $handler_slug, $handler_config) {
        if ($handler_slug === 'threads') {
            return dm_get_threads_tool($handler_config);
        }
        return $tool;
    }, 10, 3);
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
}

/**
 * Get Threads tool definition with dynamic parameters based on configuration.
 *
 * @param array $handler_config Optional handler configuration for dynamic parameters.
 * @return array Threads tool configuration.
 */
function dm_get_threads_tool(array $handler_config = []): array {
    // Extract Threads-specific config from nested structure
    $threads_config = $handler_config['threads'] ?? $handler_config;
    
    // Debug logging for tool generation
    if (!empty($handler_config)) {
        do_action('dm_log', 'debug', 'Threads Tool: Generating with configuration', [
            'handler_config_keys' => array_keys($handler_config),
            'threads_config_keys' => array_keys($threads_config),
            'threads_config_values' => $threads_config
        ]);
    }
    
    // Base tool definition
    $tool = [
        'class' => 'DataMachine\\Core\\Handlers\\Publish\\Threads\\Threads',
        'method' => 'handle_tool_call',
        'handler' => 'threads',
        'description' => 'Post content to Threads (500 character limit)',
        'parameters' => [
            'content' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Post content (will be formatted and truncated if needed)'
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
    
    // Get configuration values with defaults from extracted config
    $include_images = $threads_config['include_images'] ?? true;
    
    // Add conditional parameters based on configuration
    if ($include_images) {
        $tool['parameters']['image_url'] = [
            'type' => 'string',
            'required' => false,
            'description' => 'Optional image URL to attach to post'
        ];
    }
    
    // Update description based on enabled features
    $description_parts = ['Post content to Threads (500 character limit)'];
    if ($include_images) {
        $description_parts[] = 'images will be uploaded if provided';
    }
    $tool['description'] = implode(', ', $description_parts);
    
    do_action('dm_log', 'debug', 'Threads Tool: Generation complete', [
        'parameter_count' => count($tool['parameters']),
        'parameter_names' => array_keys($tool['parameters']),
        'include_images' => $include_images
    ]);
    
    return $tool;
}

// Auto-register when file loads - achieving complete self-containment
dm_register_threads_filters();