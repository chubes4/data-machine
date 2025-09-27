<?php
/**
 * WordPress Update Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as WordPress Update Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Update\Handlers\WordPress
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Update\Handlers\WordPress;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all WordPress Update Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers WordPress Update Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_wordpress_update_filters() {
    
    // Handler registration - WordPress declares itself as update handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['wordpress_update'] = [
            'type' => 'update',
            'class' => WordPress::class,
            'label' => __('WordPress Update', 'data-machine'),
            'description' => __('Update existing WordPress posts and pages', 'data-machine')
        ];
        return $handlers;
    });
    
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['wordpress_update'] = new WordPressSettings();
        return $all_settings;
    });
    
    // WordPress update tool registration with AI HTTP Client library
    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        // Only generate WordPress update tool when it's the target handler
        if ($handler_slug === 'wordpress_update') {
            $tools['wordpress_update'] = dm_get_dynamic_wordpress_update_tool($handler_config);
        }
        return $tools;
    }, 10, 3);

    
    // WordPress update handler does not register any modals - site-local updates only
}

/**
 * Get base WordPress update tool definition.
 *
 * @return array Base WordPress update tool configuration.
 */
function dm_get_wordpress_update_base_tool(array $handler_config = []): array {
    $tool = [
        'class' => 'DataMachine\\Core\\Steps\\Update\\Handlers\\WordPress\\WordPress',
        'method' => 'handle_tool_call',
        'handler' => 'wordpress_update',
        'description' => 'Make surgical updates to WordPress posts using find-and-replace operations. Preserves all images, blocks, and formatting.',
        'parameters' => [
            'updates' => [
                'type' => 'array',
                'required' => false,
                'description' => 'Array of surgical find-and-replace operations: [{"find": "old text", "replace": "new text"}]. Use for precise content changes without affecting rest of post.'
            ],
            'block_updates' => [
                'type' => 'array',
                'required' => false,
                'description' => 'Array of block-specific updates: [{"block_index": 0, "find": "old text", "replace": "new text"}]. Target specific Gutenberg blocks by index.'
            ]
        ]
    ];

    // Conditionally add title parameter
    if ($handler_config['allow_title_updates'] ?? true) {
        $tool['parameters']['title'] = [
            'type' => 'string',
            'required' => false,
            'description' => 'New post title (leave empty to keep existing)'
        ];
    }

    // Conditionally add legacy content parameter for backward compatibility
    if ($handler_config['allow_content_updates'] ?? true) {
        $tool['parameters']['content'] = [
            'type' => 'string',
            'required' => false,
            'description' => 'LEGACY: Complete replacement content (use "updates" array for surgical changes instead)'
        ];
    }

    // Description remains surgical-focused regardless of legacy settings
    // The new surgical update capabilities are always available

    return $tool;
}

/**
 * Generate dynamic WordPress update tool based on enabled taxonomies.
 *
 * @param array $handler_config Handler configuration containing taxonomy selections.
 * @return array Dynamic tool configuration with taxonomy parameters.
 */
function dm_get_dynamic_wordpress_update_tool(array $handler_config): array {
    // Extract WordPress-specific config from nested structure
    $wordpress_config = $handler_config['wordpress_update'] ?? $handler_config;
    
    
    // Start with base tool, passing config for conditional parameters
    $tool = dm_get_wordpress_update_base_tool($wordpress_config);
    
    // Store resolved configuration for execution (flat structure)
    $tool['handler_config'] = $wordpress_config;
    
    
    // Input validation
    if (!is_array($handler_config)) {
        do_action('dm_log', 'error', 'WordPress Update Tool: Invalid handler config type', [
            'expected' => 'array',
            'received' => gettype($handler_config),
            'value' => $handler_config
        ]);
        return $tool;
    }
    
    // Sanitize handler config to prevent corruption
    $sanitized_config = [];
    foreach ($wordpress_config as $key => $value) {
        if (is_string($key) && (is_string($value) || is_array($value))) {
            $sanitized_config[sanitize_key($key)] = is_string($value) ? sanitize_text_field($value) : $value;
        }
    }
    
    if (empty($sanitized_config)) {
        do_action('dm_log', 'warning', 'WordPress Update Tool: Empty or invalid config, using base tool', [
            'original_config' => $handler_config
        ]);
        return $tool;
    }
    
    // Get all public taxonomies
    $taxonomies = get_taxonomies(['public' => true], 'objects');
    
    do_action('dm_log', 'debug', 'WordPress Update Tool: Taxonomies found', [
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
        
        do_action('dm_log', 'debug', 'WordPress Update Tool: Processing taxonomy', [
            'taxonomy_name' => $taxonomy->name,
            'field_key' => $field_key,
            'selection' => $selection,
            'hierarchical' => $taxonomy->hierarchical,
            'selection_equals_ai_decides' => ($selection === 'ai_decides'),
            'raw_config_value' => $sanitized_config[$field_key] ?? 'NOT_FOUND'
        ]);
        
        // Only include taxonomies for "ai_decides" (AI Decides) - others handled via update_config
        if ($selection === 'ai_decides') {
            $parameter_name = $taxonomy->name === 'category' ? 'category' : 
                             ($taxonomy->name === 'post_tag' ? 'tags' : $taxonomy->name);
            
            // AI Decides - include parameter as optional for updates
            if ($taxonomy->hierarchical) {
                $tool['parameters'][$parameter_name] = [
                    'type' => 'string',
                    'required' => false,
                    'description' => "Update {$taxonomy->name} based on content (leave empty to keep existing)"
                ];
            } else {
                $tool['parameters'][$parameter_name] = [
                    'type' => 'array',
                    'required' => false,
                    'description' => "Update {$taxonomy->name} for the content (leave empty to keep existing)"
                ];
            }
            
            do_action('dm_log', 'debug', 'WordPress Update Tool: Added ai_decides taxonomy parameter', [
                'taxonomy_name' => $taxonomy->name,
                'parameter_name' => $parameter_name,
                'required' => false
            ]);
        } else {
            // Skip and Specific Selection: NOT included in tool parameters
            // These are handled automatically during updating via update_config
            do_action('dm_log', 'debug', 'WordPress Update Tool: Taxonomy excluded from AI tool', [
                'taxonomy_name' => $taxonomy->name,
                'selection_type' => $selection,
                'reason' => $selection === 'skip' ? 'skipped by config' : 'pre-selected via config'
            ]);
        }
    }
    
    do_action('dm_log', 'debug', 'WordPress Update Tool: Generation complete', [
        'parameter_count' => count($tool['parameters']),
        'parameter_names' => array_keys($tool['parameters'])
    ]);
    
    return $tool;
}


// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_update_filters();