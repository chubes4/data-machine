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
 * @subpackage Core\Steps\Publish\Handlers\WordPress
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\WordPress;

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
            'description' => __('Create WordPress posts and pages', 'data-machine')
        ];
        return $handlers;
    });
    
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['wordpress_publish'] = new WordPressSettings();
        return $all_settings;
    });
    
    // WordPress tool registration with AI HTTP Client library
    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        // Only generate WordPress tool when it's the target handler
        if ($handler_slug === 'wordpress_publish') {
            $tools['wordpress_publish'] = dm_get_dynamic_wordpress_tool($handler_config);
        }
        return $tools;
    }, 10, 3);

    // WordPress-specific success message formatting
    add_filter('dm_tool_success_message', function($default_message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name === 'wordpress_publish' && !empty($tool_result['data']['post_title'])) {
            $title = $tool_result['data']['post_title'];
            $url = $tool_result['data']['post_url'] ?? '';
            $post_id = $tool_result['data']['post_id'] ?? '';
            
            if (!empty($url)) {
                return "PUBLISHING COMPLETE: WordPress post published successfully. Title: '{$title}' is now live at {$url} (ID: {$post_id}). Your publishing task is finished and the content is ready for readers!";
            } else {
                return "PUBLISHING COMPLETE: WordPress post created successfully. Title: '{$title}' (ID: {$post_id}). Your publishing task is finished and the content is ready for readers!";
            }
        }
        return $default_message;
    }, 10, 4);
    
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
        'description' => 'Publish content to WordPress using Gutenberg block format',
        'parameters' => [
            'title' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Post title (page h1 element)'
            ],
            'content' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Post content formatted as WordPress Gutenberg blocks. Wrap ALL URLs in anchor tags like <a href="URL">Link Text</a>. Use <strong>bold</strong> and <em>italic</em> for formatting.

HEADINGS - Use proper heading hierarchy:
• H2: <!-- wp:heading --><h2 class="wp-block-heading">Section Title</h2><!-- /wp:heading -->
• H3: <!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Subsection Title</h3><!-- /wp:heading -->
• H4: <!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Sub-subsection Title</h4><!-- /wp:heading -->

LISTS - Use correct block syntax:
• Unordered lists: <!-- wp:list --><ul class="wp-block-list"><li>Item 1</li><li>Item 2</li></ul><!-- /wp:list -->
• Ordered lists (recipes/steps): <!-- wp:list {"ordered":true} --><ol class="wp-block-list"><li>Step 1</li><li>Step 2</li></ol><!-- /wp:list -->

PARAGRAPHS: <!-- wp:paragraph --><p>Content with <a href="https://example.com">proper links</a></p><!-- /wp:paragraph -->'
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
    // Extract WordPress-specific config from nested structure
    $wordpress_config = $handler_config['wordpress_publish'] ?? $handler_config;
    
    // Apply global WordPress defaults from settings page before processing
    $wordpress_config = apply_filters('dm_apply_global_defaults', $wordpress_config, 'wordpress_publish', 'publish');
    
    // Start with base tool
    $tool = dm_get_wordpress_base_tool();
    
    // Store resolved configuration for execution (flat structure) with defaults applied
    $tool['handler_config'] = $wordpress_config;
    
    
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
    foreach ($wordpress_config as $key => $value) {
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
            'hierarchical' => $taxonomy->hierarchical,
            'selection_equals_ai_decides' => ($selection === 'ai_decides'),
            'raw_config_value' => $sanitized_config[$field_key] ?? 'NOT_FOUND'
        ]);
        
        // Only include taxonomies for "ai_decides" (AI Decides) - others handled via publish_config
        if ($selection === 'ai_decides') {
            $parameter_name = $taxonomy->name === 'category' ? 'category' : 
                             ($taxonomy->name === 'post_tag' ? 'tags' : $taxonomy->name);
            
            // AI Decides - include parameter with required flag
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
                    'description' => "Choose one or more relevant {$taxonomy->name} for the content"
                ];
            }
            
            do_action('dm_log', 'debug', 'WordPress Tool: Added ai_decides taxonomy parameter', [
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