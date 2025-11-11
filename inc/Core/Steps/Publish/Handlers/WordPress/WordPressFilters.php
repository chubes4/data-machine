<?php
/**
 * WordPress publish handler filter registration.
 *
 * @package DataMachine
 * @subpackage Core\Steps\Publish\Handlers\WordPress
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

function datamachine_register_wordpress_publish_filters() {
    add_filter('datamachine_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'publish') {
            $handlers['wordpress_publish'] = [
                'type' => 'publish',
                'class' => WordPress::class,
                'label' => __('WordPress', 'data-machine'),
                'description' => __('Create WordPress posts and pages', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);

    add_filter('datamachine_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'wordpress_publish') {
            $all_settings['wordpress_publish'] = new WordPressSettings();
        }
        return $all_settings;
    }, 10, 2);

    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        if ($handler_slug === 'wordpress_publish') {
            $tools['wordpress_publish'] = datamachine_get_dynamic_wordpress_tool($handler_config);
        }
        return $tools;
    }, 10, 3);

    add_filter('datamachine_tool_success_message', function($default_message, $tool_name, $tool_result) {
        if ($tool_name === 'wordpress_publish' && !empty($tool_result['data']['post_title'])) {
            $title = $tool_result['data']['post_title'];
            $url = $tool_result['data']['post_url'] ?? '';
            $post_id = $tool_result['data']['post_id'] ?? '';

            if (!empty($url)) {
                return "WordPress post published successfully. Title: '{$title}' at {$url} (ID: {$post_id}).";
            } else {
                return "WordPress post created successfully. Title: '{$title}' (ID: {$post_id}).";
            }
        }
        return $default_message;
    }, 10, 4);
}

/**
 * Get base WordPress tool definition.
 *
 * @return array Base WordPress tool configuration.
 */
function datamachine_get_wordpress_base_tool(): array {
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
                'description' => 'Post content MUST be valid Gutenberg block HTML.

STRUCTURE:
1) Every block uses comment wrappers. Pattern: <!-- wp:block {"attrs":...} -->[inner HTML]<!-- /wp:block -->
   - JSON lives on the opening line, uses straight quotes, no trailing commas.
   - Always include the closing comment, even for single-line blocks.
2) Only use core blocks from this palette: heading, paragraph, list, quote, separator, image.
3) No Markdown or raw HTML wrappers outside blocks. All links use <a href="URL">Text</a>.
4) Do not repeat the post title. Do not embed source links or featured images; the system injects them.
5) Only output attributes explicitly documented below for each block.

CORE BLOCK DETAILS:
- heading: default H2. Add {"level":3} for H3, {"level":4} for H4. Inner HTML must keep class="wp-block-heading".
- paragraph: wrap prose in <p>…</p>. Keep inline elements valid HTML.
- list: <!-- wp:list --> for unordered, add {"ordered":true} for ordered. Use class="wp-block-list" on <ul>/<ol>; no extra list attributes.
- quote: include citation text in <cite> when needed.
- separator: <!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->.
- image: only when supplied. Opening JSON must include url, alt when known, optionally caption. Inner HTML should be <figure class="wp-block-image"><img src="..." alt="..."/><figcaption>…</figcaption></figure>.

INLINE FORMATTING:
- Use standard HTML tags inside headings/paragraphs: <strong>, <em>, <code>, <sup>, <sub>, <a>.
- Tags must be balanced inside the same block and never wrap entire blocks.

VALIDATION CHECKLIST:
- Confirm every opening comment has a matching closing comment.
- JSON braces/quotes balanced and closed on the same line.
- Inline tags open and close properly; no dangling markup.
'
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
function datamachine_get_dynamic_wordpress_tool(array $handler_config): array {
    // Extract WordPress-specific config from nested structure
    $wordpress_config = $handler_config['wordpress_publish'] ?? $handler_config;
    
    // Apply global WordPress defaults from settings page before processing
    $wordpress_config = apply_filters('datamachine_apply_global_defaults', $wordpress_config, 'wordpress_publish', 'publish');
    
    // Start with base tool
    $tool = datamachine_get_wordpress_base_tool();
    
    // Store resolved configuration for execution (flat structure) with defaults applied
    $tool['handler_config'] = $wordpress_config;
    
    
    // Input validation
    if (!is_array($handler_config)) {
        do_action('datamachine_log', 'error', 'WordPress Tool: Invalid handler config type', [
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
        do_action('datamachine_log', 'warning', 'WordPress Tool: Empty or invalid config, using base tool', [
            'original_config' => $handler_config
        ]);
        return $tool;
    }
    
    // Get all public taxonomies
    $taxonomies = get_taxonomies(['public' => true], 'objects');
    
    do_action('datamachine_log', 'debug', 'WordPress Tool: Taxonomies found', [
        'taxonomy_count' => count($taxonomies),
        'taxonomy_names' => array_keys($taxonomies)
    ]);
    
    foreach ($taxonomies as $taxonomy) {
        // Skip built-in formats and other non-content taxonomies using centralized filter
        $excluded = apply_filters('datamachine_wordpress_system_taxonomies', []);
        if (in_array($taxonomy->name, $excluded)) {
            continue;
        }

        $field_key = "taxonomy_{$taxonomy->name}_selection";
        $selection = $sanitized_config[$field_key] ?? 'skip';
        
        do_action('datamachine_log', 'debug', 'WordPress Tool: Processing taxonomy', [
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
            
            do_action('datamachine_log', 'debug', 'WordPress Tool: Added ai_decides taxonomy parameter', [
                'taxonomy_name' => $taxonomy->name,
                'parameter_name' => $parameter_name,
                'required' => true
            ]);
        } else {
            // Skip and Specific Selection: NOT included in tool parameters
            // These are handled automatically during publishing via publish_config
            do_action('datamachine_log', 'debug', 'WordPress Tool: Taxonomy excluded from AI tool', [
                'taxonomy_name' => $taxonomy->name,
                'selection_type' => $selection,
                'reason' => $selection === 'skip' ? 'skipped by config' : 'pre-selected via config'
            ]);
        }
    }
    
    do_action('datamachine_log', 'debug', 'WordPress Tool: Generation complete', [
        'parameter_count' => count($tool['parameters']),
        'parameter_names' => array_keys($tool['parameters'])
    ]);
    
    return $tool;
}


// Auto-register when file loads - achieving complete self-containment
datamachine_register_wordpress_publish_filters();