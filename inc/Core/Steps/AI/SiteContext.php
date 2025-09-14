<?php
/**
 * WordPress Site Context - Cached site metadata for AI prompt injection
 */

namespace DataMachine\Core\Steps\AI;

defined('ABSPATH') || exit;

class SiteContext {

    const CACHE_KEY = 'dm_site_context_data';
    const CACHE_DURATION = 3600;

    /**
     * Get site context data.
     *
     * @return array Site context information
     */
    public static function get_context(): array {
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false) {
            return $cached;
        }

        $context = [
            'site' => self::get_site_metadata(),
            'post_types' => self::get_post_types_data(),
            'taxonomies' => self::get_taxonomies_data()
        ];

        // Cache the result
        set_transient(self::CACHE_KEY, $context, self::CACHE_DURATION);

        do_action('dm_log', 'debug', 'Site Context: Generated fresh context data', [
            'post_types_count' => count($context['post_types']),
            'taxonomies_count' => count($context['taxonomies'])
        ]);

        return $context;
    }

    /**
     * Get site metadata
     * 
     * @return array Site name, tagline, URL, and admin info
     */
    private static function get_site_metadata(): array {
        return [
            'name' => get_bloginfo('name'),
            'tagline' => get_bloginfo('description'),
            'url' => home_url(),
            'admin_url' => admin_url(),
            'language' => get_locale(),
            'timezone' => wp_timezone_string()
        ];
    }

    /**
     * Get post types with counts
     * 
     * @return array Post types with labels and post counts
     */
    private static function get_post_types_data(): array {
        $post_types_data = [];
        $post_types = get_post_types(['public' => true], 'objects');

        foreach ($post_types as $post_type) {
            $count = wp_count_posts($post_type->name);
            $published_count = $count->publish ?? 0;

            $post_types_data[$post_type->name] = [
                'label' => $post_type->label,
                'singular_label' => $post_type->labels->singular_name ?? $post_type->label,
                'count' => (int) $published_count,
                'hierarchical' => $post_type->hierarchical
            ];
        }

        return $post_types_data;
    }

    /**
     * Get taxonomies with term and post counts
     * 
     * @return array Taxonomies with labels, term counts, and post associations
     */
    private static function get_taxonomies_data(): array {
        $taxonomies_data = [];
        $taxonomies = get_taxonomies(['public' => true], 'objects');

        // Filter out non-content taxonomies
        $excluded_taxonomies = ['post_format', 'nav_menu', 'link_category'];

        foreach ($taxonomies as $taxonomy) {
            if (in_array($taxonomy->name, $excluded_taxonomies)) {
                continue;
            }

            $terms = get_terms([
                'taxonomy' => $taxonomy->name,
                'hide_empty' => false,
                'count' => true,
                'orderby' => 'count',
                'order' => 'DESC'
            ]);

            $term_data = [];
            if (is_array($terms)) {
                foreach ($terms as $term) {
                    if ($term->count > 0) {
                        $term_data[$term->name] = (int) $term->count;
                    }
                }
            }

            // Only include taxonomies that have terms with posts
            if (!empty($term_data)) {
                $taxonomies_data[$taxonomy->name] = [
                    'label' => $taxonomy->label,
                    'singular_label' => $taxonomy->labels->singular_name ?? $taxonomy->label,
                    'terms' => $term_data,
                    'hierarchical' => $taxonomy->hierarchical,
                    'post_types' => $taxonomy->object_type ?? []
                ];
            }
        }

        return $taxonomies_data;
    }

    /**
     * Clear site context cache
     * 
     * Used when site data changes to ensure fresh context
     */
    public static function clear_cache(): void {
        delete_transient(self::CACHE_KEY);
        
        do_action('dm_log', 'debug', 'Site Context: Cache cleared');
    }

    /**
     * Register cache invalidation hooks
     * 
     * Automatically clears cache when relevant site data changes
     */
    public static function register_cache_invalidation(): void {
        // Clear cache when posts are added, updated, or deleted
        add_action('save_post', [__CLASS__, 'clear_cache']);
        add_action('delete_post', [__CLASS__, 'clear_cache']);
        add_action('wp_trash_post', [__CLASS__, 'clear_cache']);
        add_action('untrash_post', [__CLASS__, 'clear_cache']);

        // Clear cache when taxonomies or terms change
        add_action('create_term', [__CLASS__, 'clear_cache']);
        add_action('edit_term', [__CLASS__, 'clear_cache']);
        add_action('delete_term', [__CLASS__, 'clear_cache']);
        add_action('set_object_terms', [__CLASS__, 'clear_cache']);

        // Clear cache when users change
        add_action('user_register', [__CLASS__, 'clear_cache']);
        add_action('delete_user', [__CLASS__, 'clear_cache']);
        add_action('set_user_role', [__CLASS__, 'clear_cache']);

        // Clear cache when themes change
        add_action('switch_theme', [__CLASS__, 'clear_cache']);

        // Clear cache when site options change
        add_action('update_option_blogname', [__CLASS__, 'clear_cache']);
        add_action('update_option_blogdescription', [__CLASS__, 'clear_cache']);
        add_action('update_option_home', [__CLASS__, 'clear_cache']);
        add_action('update_option_siteurl', [__CLASS__, 'clear_cache']);
    }
}

add_action('init', [SiteContext::class, 'register_cache_invalidation']);