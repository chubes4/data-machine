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
    
    // Handler directive registration - dynamic taxonomy support
    add_filter('dm_handler_directives', function($directives) {
        $directives['wordpress_publish'] = dm_get_wordpress_base_directive();
        return $directives;
    });

    // Dynamic directive generation based on current taxonomy configuration
    add_filter('dm_generate_handler_directive', function($directive, $handler_slug, $handler_config) {
        if ($handler_slug === 'wordpress_publish') {
            return dm_get_dynamic_wordpress_directive($handler_config);
        }
        return $directive;
    }, 10, 3);
    
    // Register AI response parser for WordPress-specific structured content
    add_filter('dm_parse_ai_response', 'DataMachine\Core\Handlers\Publish\WordPress\dm_wordpress_parse_ai_response', 10, 3);
    
    // WordPress handler does not register any modals - site-local publishing only
}

/**
 * Get base WordPress directive (fallback when no configuration is available).
 *
 * @return string Base WordPress directive.
 */
function dm_get_wordpress_base_directive(): string {
    return 'When publishing to WordPress, format your response as:\nTITLE: [compelling post title]\nCONTENT:\n[your content here]';
}

/**
 * Generate dynamic WordPress directive based on enabled taxonomies.
 *
 * @param array $handler_config Handler configuration containing taxonomy selections.
 * @return string Dynamic directive including only enabled taxonomies.
 */
function dm_get_dynamic_wordpress_directive(array $handler_config): string {
    // Debug logging for directive generation
    do_action('dm_log', 'debug', 'WordPress Directive: Starting generation', [
        'handler_config_keys' => array_keys($handler_config),
        'handler_config_values' => $handler_config
    ]);
    
    // Input validation
    if (!is_array($handler_config)) {
        do_action('dm_log', 'error', 'WordPress Directive: Invalid handler config type', [
            'expected' => 'array',
            'received' => gettype($handler_config),
            'value' => $handler_config
        ]);
        return dm_get_wordpress_base_directive();
    }
    
    // Sanitize handler config to prevent corruption
    $sanitized_config = [];
    foreach ($handler_config as $key => $value) {
        if (is_string($key) && (is_string($value) || is_array($value))) {
            $sanitized_config[sanitize_key($key)] = is_string($value) ? sanitize_text_field($value) : $value;
        }
    }
    
    if (empty($sanitized_config)) {
        do_action('dm_log', 'warning', 'WordPress Directive: Empty or invalid config, using base directive', [
            'original_config' => $handler_config
        ]);
        return dm_get_wordpress_base_directive();
    }
    
    $directive_parts = [
        'When publishing to WordPress, format your response as:',
        'TITLE: [compelling post title]'
    ];
    
    // Get all public taxonomies
    $taxonomies = get_taxonomies(['public' => true], 'objects');
    
    do_action('dm_log', 'debug', 'WordPress Directive: Taxonomies found', [
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
        
        do_action('dm_log', 'debug', 'WordPress Directive: Processing taxonomy', [
            'taxonomy_name' => $taxonomy->name,
            'field_key' => $field_key,
            'selection' => $selection,
            'hierarchical' => $taxonomy->hierarchical
        ]);
        
        // Only include taxonomies that are not skipped
        if ($selection !== 'skip') {
            $taxonomy_label = strtoupper($taxonomy->name);
            if ($taxonomy->hierarchical) {
                // Hierarchical taxonomies (like categories) - single selection
                $directive_part = "{$taxonomy_label}: [single {$taxonomy->name} name]";
                $directive_parts[] = $directive_part;
                
                do_action('dm_log', 'debug', 'WordPress Directive: Added hierarchical taxonomy', [
                    'taxonomy_name' => $taxonomy->name,
                    'directive_part' => $directive_part
                ]);
            } else {
                // Flat taxonomies (like tags) - comma-separated
                $directive_part = "{$taxonomy_label}: [comma,separated,{$taxonomy->name}]";
                $directive_parts[] = $directive_part;
                
                do_action('dm_log', 'debug', 'WordPress Directive: Added flat taxonomy', [
                    'taxonomy_name' => $taxonomy->name,
                    'directive_part' => $directive_part
                ]);
            }
        }
    }
    
    $directive_parts[] = 'CONTENT:';
    $directive_parts[] = '[your content here]';
    
    $final_directive = implode("\n", $directive_parts);
    
    // Validate directive structure before returning
    if (!dm_validate_wordpress_directive($final_directive)) {
        do_action('dm_log', 'error', 'WordPress Directive: Generated directive failed validation', [
            'directive_parts' => $directive_parts,
            'final_directive' => $final_directive
        ]);
        
        // Fallback to base directive
        $fallback_directive = dm_get_wordpress_base_directive();
        do_action('dm_log', 'warning', 'WordPress Directive: Using fallback directive', [
            'fallback_directive' => $fallback_directive
        ]);
        return $fallback_directive;
    }
    
    do_action('dm_log', 'debug', 'WordPress Directive: Generation complete', [
        'directive_parts_count' => count($directive_parts),
        'directive_parts' => $directive_parts,
        'final_directive_length' => strlen($final_directive),
        'final_directive' => $final_directive
    ]);
    
    return $final_directive;
}

/**
 * Validate WordPress directive structure
 * Ensures directive has proper format and required elements
 * 
 * @param string $directive The directive to validate
 * @return bool True if valid, false otherwise
 */
function dm_validate_wordpress_directive(string $directive): bool {
    // Check for basic structure
    if (empty($directive) || strlen($directive) < 20) {
        return false;
    }
    
    // Check for required elements
    $required_elements = ['TITLE:', 'CONTENT:'];
    foreach ($required_elements as $element) {
        if (strpos($directive, $element) === false) {
            do_action('dm_log', 'debug', 'WordPress Directive: Validation failed - missing element', [
                'missing_element' => $element,
                'directive' => $directive
            ]);
            return false;
        }
    }
    
    // Check for proper order (TITLE should come before CONTENT)
    $title_pos = strpos($directive, 'TITLE:');
    $content_pos = strpos($directive, 'CONTENT:');
    
    if ($title_pos === false || $content_pos === false || $title_pos >= $content_pos) {
        do_action('dm_log', 'debug', 'WordPress Directive: Validation failed - wrong order', [
            'title_position' => $title_pos,
            'content_position' => $content_pos,
            'directive' => $directive
        ]);
        return false;
    }
    
    // Check for syntax corruption (stray characters)
    if (preg_match('/[{}"]/', $directive)) {
        do_action('dm_log', 'debug', 'WordPress Directive: Validation failed - syntax corruption', [
            'directive' => $directive
        ]);
        return false;
    }
    
    return true;
}

/**
 * Parse AI response for WordPress-specific structured data
 * Extracts TITLE:, CATEGORY:, TAGS:, and CONTENT: from AI responses
 * 
 * @param array $ai_entry The AI data packet entry
 * @param string $ai_content The raw AI response content
 * @param string $flow_step_id The current flow step ID
 * @return array Modified AI entry with parsed structured data
 */
function dm_wordpress_parse_ai_response($ai_entry, $ai_content, $flow_step_id) {
    // Only process if this is destined for WordPress publishing
    $flow_step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
    $handler = $flow_step_config['handler'] ?? '';
    
    if ($handler !== 'wordpress_publish') {
        return $ai_entry; // Not for WordPress, return unchanged
    }
    
    // Parse structured AI response
    $lines = explode("\n", trim($ai_content));
    $parsed_data = [
        'title' => '',
        'category' => '',
        'tags' => [],
        'content' => ''
    ];
    
    $content_started = false;
    $content_lines = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if ($content_started) {
            $content_lines[] = $line;
            continue;
        }
        
        // Parse structured elements
        if (preg_match('/^TITLE:\s*(.+)$/i', $line, $matches)) {
            $parsed_data['title'] = trim($matches[1]);
        } elseif (preg_match('/^CATEGORY:\s*(.+)$/i', $line, $matches)) {
            $parsed_data['category'] = trim($matches[1]);
        } elseif (preg_match('/^TAGS:\s*(.+)$/i', $line, $matches)) {
            $tags = array_map('trim', explode(',', $matches[1]));
            $parsed_data['tags'] = array_filter($tags); // Remove empty tags
        } elseif (preg_match('/^CONTENT:\s*$/i', $line)) {
            $content_started = true;
        } elseif (preg_match('/^CONTENT:\s*(.+)$/i', $line, $matches)) {
            $content_started = true;
            $content_lines[] = trim($matches[1]);
        }
    }
    
    // Assemble final content
    $parsed_data['content'] = implode("\n", $content_lines);
    
    // Update AI entry with parsed data if we found structured content
    if (!empty($parsed_data['title']) || !empty($parsed_data['category']) || 
        !empty($parsed_data['tags']) || !empty($parsed_data['content'])) {
        
        // Use parsed title if available, otherwise keep original
        if (!empty($parsed_data['title'])) {
            $ai_entry['content']['title'] = $parsed_data['title'];
        }
        
        // Use parsed content if available, otherwise keep original
        if (!empty($parsed_data['content'])) {
            $ai_entry['content']['body'] = $parsed_data['content'];
        }
        
        // Add structured data for WordPress handler
        if (!empty($parsed_data['category'])) {
            $ai_entry['content']['category'] = $parsed_data['category'];
        }
        
        if (!empty($parsed_data['tags'])) {
            $ai_entry['content']['tags'] = $parsed_data['tags'];
        }
        
        do_action('dm_log', 'debug', 'WordPress AI Parser: Structured content parsed', [
            'flow_step_id' => $flow_step_id,
            'title_found' => !empty($parsed_data['title']),
            'category_found' => !empty($parsed_data['category']),
            'tags_count' => count($parsed_data['tags']),
            'content_parsed' => !empty($parsed_data['content'])
        ]);
    }
    
    return $ai_entry;
}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_publish_filters();