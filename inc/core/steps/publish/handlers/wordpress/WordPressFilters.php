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
    
    // Authentication registration - pure discovery mode
    add_filter('dm_auth_providers', function($providers) {
        $providers['wordpress_publish'] = new WordPressAuth();
        return $providers;
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
    $directive_parts = [
        'When publishing to WordPress, format your response as:',
        'TITLE: [compelling post title]'
    ];
    
    // Get all public taxonomies
    $taxonomies = get_taxonomies(['public' => true], 'objects');
    
    foreach ($taxonomies as $taxonomy) {
        // Skip built-in formats and other non-content taxonomies
        if (in_array($taxonomy->name, ['post_format', 'nav_menu', 'link_category'])) {
            continue;
        }
        
        $field_key = "taxonomy_{$taxonomy->name}_selection";
        $selection = $handler_config[$field_key] ?? 'skip';
        
        // Only include taxonomies that are not skipped
        if ($selection !== 'skip') {
            $taxonomy_label = strtoupper($taxonomy->name);
            if ($taxonomy->hierarchical) {
                // Hierarchical taxonomies (like categories) - single selection
                $directive_parts[] = "{$taxonomy_label}: [single {$taxonomy->name} name]";
            } else {
                // Flat taxonomies (like tags) - comma-separated
                $directive_parts[] = "{$taxonomy_label}: [comma,separated,{$taxonomy->name}]";
            }
        }
    }
    
    $directive_parts[] = 'CONTENT:';
    $directive_parts[] = '[your content here]';
    
    return implode("\n", $directive_parts);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_publish_filters();