<?php
/**
 * WordPress Publish Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as WordPress Publish Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Publish\WordPress
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Publish\WordPress;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all WordPress Publish Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers WordPress Publish Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_wordpress_publish_filters() {
    
    // Handler registration - WordPress declares itself as publish handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['wordpress_publish'] = [
            'type' => 'publish',
            'class' => WordPress::class,
            'label' => __('WordPress', 'data-machine'),
            'description' => __('Create and update WordPress posts and pages', 'data-machine')
        ];
        return $handlers;
    });
    
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['wordpress_publish'] = new WordPressSettings();
        return $all_settings;
    });
    
    // WordPress tool registration with AI HTTP Client library
    add_filter('ai_tools', function($tools) {
        $tools['wordpress_publish'] = dm_get_wordpress_base_tool();
        return $tools;
    });

    // Dynamic tool generation based on current taxonomy configuration
    add_filter('dm_generate_handler_tool', function($tool, $handler_slug, $handler_config) {
        if ($handler_slug === 'wordpress_publish') {
            return dm_get_dynamic_wordpress_tool($handler_config);
        }
        return $tool;
    }, 10, 3);
    
    // WordPress handler does not register any modals - site-local publishing only
}

/**
 * Get base WordPress tool definition.
 *
 * @return array Base WordPress tool configuration.
 */
function dm_get_wordpress_base_tool(): array {
    return [
        'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\WordPress\\WordPress',
        'method' => 'handle_tool_call',
        'handler' => 'wordpress_publish',
        'description' => 'Publish content to WordPress',
        'parameters' => [
            'title' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Post title'
            ],
            'content' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Post content'
            ]
        ]
    ];
}

/**
 * Generate dynamic WordPress tool based on enabled taxonomies.
 *
 * @param array $handler_config Handler configuration containing taxonomy selections.
 * @return array Dynamic tool configuration with taxonomy parameters.
 */
function dm_get_dynamic_wordpress_tool(array $handler_config): array {
    // Debug logging for tool generation
    do_action('dm_log', 'debug', 'WordPress Tool: Starting generation', [
        'handler_config_keys' => array_keys($handler_config),
        'handler_config_values' => $handler_config
    ]);
    
    // Start with base tool
    $tool = dm_get_wordpress_base_tool();
    
    // Input validation
    if (!is_array($handler_config)) {
        do_action('dm_log', 'error', 'WordPress Tool: Invalid handler config type', [
            'expected' => 'array',
            'received' => gettype($handler_config),
            'value' => $handler_config
        ]);
        return $tool;
    }
    
    // Sanitize handler config to prevent corruption
    $sanitized_config = [];
    foreach ($handler_config as $key => $value) {
        if (is_string($key) && (is_string($value) || is_array($value))) {
            $sanitized_config[sanitize_key($key)] = is_string($value) ? sanitize_text_field($value) : $value;
        }
    }
    
    if (empty($sanitized_config)) {
        do_action('dm_log', 'warning', 'WordPress Tool: Empty or invalid config, using base tool', [
            'original_config' => $handler_config
        ]);
        return $tool;
    }
    
    // Get all public taxonomies
    $taxonomies = get_taxonomies(['public' => true], 'objects');
    
    do_action('dm_log', 'debug', 'WordPress Tool: Taxonomies found', [
        'taxonomy_count' => count($taxonomies),
        'taxonomy_names' => array_keys($taxonomies)
    ]);
    
    foreach ($taxonomies as $taxonomy) {
        // Skip built-in formats and other non-content taxonomies
        if (in_array($taxonomy->name, ['post_format', 'nav_menu', 'link_category'])) {
            continue;
        }
        
        $field_key = "taxonomy_{$taxonomy->name}_selection";
        $selection = $sanitized_config[$field_key] ?? 'skip';
        
        do_action('dm_log', 'debug', 'WordPress Tool: Processing taxonomy', [
            'taxonomy_name' => $taxonomy->name,
            'field_key' => $field_key,
            'selection' => $selection,
            'hierarchical' => $taxonomy->hierarchical
        ]);
        
        // Only include taxonomies for "instruct_model" (AI Decides) - others handled via publish_config
        if ($selection === 'instruct_model') {
            $parameter_name = $taxonomy->name === 'category' ? 'category' : $taxonomy->name;
            
            // Instruct model - include parameter with required flag
            if ($taxonomy->hierarchical) {
                $tool['parameters'][$parameter_name] = [
                    'type' => 'string',
                    'required' => true,
                    'description' => "Select most appropriate {$taxonomy->name} based on content"
                ];
            } else {
                $tool['parameters'][$parameter_name] = [
                    'type' => 'array',
                    'required' => true,
                    'description' => "Choose 3-5 relevant {$taxonomy->name} for the content"
                ];
            }
            
            do_action('dm_log', 'debug', 'WordPress Tool: Added instruct_model taxonomy parameter', [
                'taxonomy_name' => $taxonomy->name,
                'parameter_name' => $parameter_name,
                'required' => true
            ]);
        } else {
            // Skip and Specific Selection: NOT included in tool parameters
            // These are handled automatically during publishing via publish_config
            do_action('dm_log', 'debug', 'WordPress Tool: Taxonomy excluded from AI tool', [
                'taxonomy_name' => $taxonomy->name,
                'selection_type' => $selection,
                'reason' => $selection === 'skip' ? 'skipped by config' : 'pre-selected via config'
            ]);
        }
    }
    
    do_action('dm_log', 'debug', 'WordPress Tool: Generation complete', [
        'parameter_count' => count($tool['parameters']),
        'parameter_names' => array_keys($tool['parameters'])
    ]);
    
    return $tool;
}


// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_publish_filters();