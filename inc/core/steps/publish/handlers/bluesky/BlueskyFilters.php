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
    add_filter('dm_handlers', function($handlers) {
        $handlers['bluesky'] = [
            'type' => 'publish',
            'class' => Bluesky::class,
            'label' => __('Bluesky', 'data-machine'),
            'description' => __('Post content to Bluesky with media support and AT Protocol integration', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_auth_providers', function($providers) {
        $providers['bluesky'] = new BlueskyAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['bluesky'] = new BlueskySettings();
        return $all_settings;
    });
    
    // Handler directive registration - pure discovery mode
    add_filter('dm_handler_directives', function($directives) {
        $directives['bluesky'] = 'When posting to Bluesky, create thoughtful content that promotes meaningful discussion. Use a conversational tone, limit posts to 300 characters for optimal engagement, and include relevant hashtags. Focus on community building and authentic interaction.';
        return $directives;
    });
    
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
 * Get Bluesky tool definition with dynamic parameters based on configuration.
 *
 * @param array $handler_config Optional handler configuration for dynamic parameters.
 * @return array Bluesky tool configuration.
 */
function dm_get_bluesky_tool(array $handler_config = []): array {
    // Extract Bluesky-specific config from nested structure
    $bluesky_config = $handler_config['bluesky'] ?? $handler_config;
    
    // Debug logging for tool generation
    if (!empty($handler_config)) {
        do_action('dm_log', 'debug', 'Bluesky Tool: Generating with configuration', [
            'handler_config_keys' => array_keys($handler_config),
            'bluesky_config_keys' => array_keys($bluesky_config),
            'bluesky_config_values' => $bluesky_config
        ]);
    }
    
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
    $include_source = $bluesky_config['bluesky_include_source'] ?? true;
    $enable_images = $bluesky_config['bluesky_enable_images'] ?? true;
    
    // Add conditional parameters based on configuration
    if ($include_source) {
        $tool['parameters']['source_url'] = [
            'type' => 'string',
            'required' => false,
            'description' => 'Optional source URL to append to post'
        ];
    }
    
    if ($enable_images) {
        $tool['parameters']['image_url'] = [
            'type' => 'string',
            'required' => false,
            'description' => 'Optional image URL to attach to post'
        ];
    }
    
    // Update description based on enabled features
    $description_parts = ['Post content to Bluesky (300 character limit)'];
    if ($include_source) {
        $description_parts[] = 'source URLs will be appended';
    }
    if ($enable_images) {
        $description_parts[] = 'images will be uploaded if provided';
    }
    $tool['description'] = implode(', ', $description_parts);
    
    do_action('dm_log', 'debug', 'Bluesky Tool: Generation complete', [
        'parameter_count' => count($tool['parameters']),
        'parameter_names' => array_keys($tool['parameters']),
        'include_source' => $include_source,
        'enable_images' => $enable_images
    ]);
    
    return $tool;
}

// Auto-register when file loads - achieving complete self-containment
dm_register_bluesky_filters();