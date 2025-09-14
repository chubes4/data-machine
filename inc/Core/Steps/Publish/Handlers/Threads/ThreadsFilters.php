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
 * @subpackage Core\Steps\Publish\Handlers\Threads
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Threads;

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
    
    // Threads tool registration with AI HTTP Client library
    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        // Only generate Threads tool when it's the target handler
        if ($handler_slug === 'threads') {
            $tools['threads_publish'] = dm_get_threads_tool($handler_config);
        }
        return $tools;
    }, 10, 3);

    
    // Modal registrations removed - now handled by generic modal system via pure discovery
}

/**
 * Generate Threads tool definition with dynamic parameters based on handler configuration.
 *
 * Dynamically constructs tool parameters based on Threads-specific settings:
 * - Conditionally includes source_url parameter based on include_source setting
 * - Conditionally includes image_url parameter based on enable_images setting
 * - Configures 500-character limit and Meta platform integration specifics
 *
 * @param array $handler_config Handler configuration containing Threads-specific settings.
 * @return array Complete Threads tool configuration for AI HTTP Client.
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
    
    // Store handler configuration for execution time
    if (!empty($handler_config)) {
        $tool['handler_config'] = $handler_config;
    }
    
    // Get configuration values with defaults from extracted config
    $include_images = $threads_config['include_images'] ?? true;
    $link_handling = $threads_config['link_handling'] ?? 'append';
    
    // Update description based on enabled features
    $description_parts = ['Post content to Threads (500 character limit)'];
    if ($link_handling === 'append') {
        $description_parts[] = 'source URLs from data will be appended to posts';
    }
    if ($include_images) {
        $description_parts[] = 'images from data will be uploaded automatically';
    }
    $tool['description'] = implode(', ', $description_parts);
    
    do_action('dm_log', 'debug', 'Threads Tool: Generation complete', [
        'parameter_count' => count($tool['parameters']),
        'parameter_names' => array_keys($tool['parameters']),
        'include_images' => $include_images
    ]);
    
    return $tool;
}

/**
 * Register Threads-specific success message formatter.
 */
function dm_register_threads_success_message() {
    add_filter('dm_tool_success_message', function($default_message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name === 'threads_publish' && !empty($tool_result['data']['post_url'])) {
            return "THREADS POST PUBLISHED: Your content is now live on Threads at {$tool_result['data']['post_url']}. Your thread is ready for conversation!";
        }
        return $default_message;
    }, 10, 4);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_threads_filters();
dm_register_threads_success_message();