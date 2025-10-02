<?php
/**
 * Bluesky Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Bluesky's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Publish\Handlers\Bluesky
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Bluesky;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Bluesky component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Bluesky capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_bluesky_filters() {

    // Handler registration - Bluesky declares itself as publish handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'publish') {
            $handlers['bluesky'] = [
                'type' => 'publish',
                'class' => Bluesky::class,
                'label' => __('Bluesky', 'data-machine'),
                'description' => __('Post content to Bluesky with media support and AT Protocol integration', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);

    // Authentication registration - pure discovery mode
    add_filter('dm_auth_providers', function($providers, $step_type = null) {
        if ($step_type === null || $step_type === 'publish') {
            $providers['bluesky'] = new BlueskyAuth();
        }
        return $providers;
    }, 10, 2);

    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'bluesky') {
            $all_settings['bluesky'] = new BlueskySettings();
        }
        return $all_settings;
    }, 10, 2);
    
    // Bluesky tool registration with AI HTTP Client library
    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        // Only generate Bluesky tool when it's the target handler
        if ($handler_slug === 'bluesky') {
            $tools['bluesky_publish'] = dm_get_bluesky_tool($handler_config);
        }
        return $tools;
    }, 10, 3);

}

/**
 * Generate Bluesky tool definition with dynamic parameters based on handler configuration.
 *
 * Dynamically constructs tool parameters based on Bluesky-specific settings:
 * - Conditionally includes source_url parameter based on include_source setting
 * - Conditionally includes image_url parameter based on enable_images setting
 * - Modifies descriptions based on link and media handling configuration
 *
 * @param array $handler_config Handler configuration containing Bluesky-specific settings.
 * @return array Complete Bluesky tool configuration for AI HTTP Client.
 */
function dm_get_bluesky_tool(array $handler_config = []): array {
    // Extract Bluesky-specific config from nested structure
    $bluesky_config = $handler_config['bluesky'] ?? $handler_config;
    
    
    // Base tool definition
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
    
    // Store handler configuration for execution time
    if (!empty($handler_config)) {
        $tool['handler_config'] = $handler_config;
    }
    
    // Get configuration values with defaults from extracted config
    $include_images = $bluesky_config['include_images'] ?? true;
    $link_handling = $bluesky_config['link_handling'] ?? 'append';
    
    // URL parameters handled by system - AI only provides content
    
    // Update description based on enabled features
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

/**
 * Register Bluesky-specific success message formatter.
 */
function dm_register_bluesky_success_message() {
    add_filter('dm_tool_success_message', function($default_message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name === 'bluesky_publish' && !empty($tool_result['data']['post_url'])) {
            return "Post published successfully to Bluesky at {$tool_result['data']['post_url']}.";
        }
        return $default_message;
    }, 10, 4);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_bluesky_filters();
dm_register_bluesky_success_message();