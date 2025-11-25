<?php
/**
 * Cached WordPress site metadata for AI context injection.
 */

namespace DataMachine\Engine\AI\Directives;

defined('ABSPATH') || exit;

class SiteContext {

    const CACHE_KEY = 'datamachine_site_context_data';

    /**
     * Get site context data with automatic caching.
     *
     * Plugins can extend the context data via the 'datamachine_site_context' filter.
     * Note: Filtering bypasses cache to ensure dynamic data is always fresh.
     *
     * @return array Site metadata, post types, and taxonomies
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

        /**
         * Filter site context data before caching.
         *
         * Plugins can use this hook to inject custom context data (e.g., events,
         * analytics, custom post type summaries). Note: When this filter is used,
         * caching is bypassed to ensure dynamic data remains fresh.
         *
         * @param array $context Site context data with 'site', 'post_types', 'taxonomies' keys
         * @return array Modified context data
         */
        $context = apply_filters('datamachine_site_context', $context);

        set_transient(self::CACHE_KEY, $context, 0); // 0 = permanent until invalidated

        return $context;
    }

    /**
     * Get site metadata.
     *
     * @return array Site name, URL, language, timezone
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
     * Get public post types with published counts.
     *
     * @return array Post type labels, counts, and hierarchy status
     */
    private static function get_post_types_data(): array {
        $post_types_data = [];
        $post_types = get_post_types(['public' => true], 'objects');

        foreach ($post_types as $post_type) {
            $count = wp_count_posts($post_type->name);
            $published_count = $count->publish ?? 0;

            $post_types_data[$post_type->name] = [
                'label' => $post_type->label,
                'singular_label' => (is_object($post_type->labels) && isset($post_type->labels->singular_name))
                    ? $post_type->labels->singular_name
                    : $post_type->label,
                'count' => (int) $published_count,
                'hierarchical' => $post_type->hierarchical
            ];
        }

        return $post_types_data;
    }

    /**
     * Get public taxonomies with term and post counts.
     *
     * Only includes taxonomies with at least one term associated with posts.
     * Excludes post_format, nav_menu, and link_category.
     *
     * @return array Taxonomy labels, terms with counts, hierarchy, post type associations
     */
    private static function get_taxonomies_data(): array {
        $taxonomies_data = [];
        $taxonomies = get_taxonomies(['public' => true], 'objects');

        foreach ($taxonomies as $taxonomy) {
            if (\DataMachine\Core\WordPress\TaxonomyHandler::shouldSkipTaxonomy($taxonomy->name)) {
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

            if (!empty($term_data)) {
                $taxonomies_data[$taxonomy->name] = [
                    'label' => $taxonomy->label,
                    'singular_label' => (is_object($taxonomy->labels) && isset($taxonomy->labels->singular_name))
                        ? $taxonomy->labels->singular_name
                        : $taxonomy->label,
                    'terms' => $term_data,
                    'hierarchical' => $taxonomy->hierarchical,
                    'post_types' => $taxonomy->object_type ?? []
                ];
            }
        }

        return $taxonomies_data;
    }

    /**
     * Clear site context cache.
     */
    public static function clear_cache(): void {
        delete_transient(self::CACHE_KEY);
    }

    /**
     * Register automatic cache invalidation hooks.
     *
     * Clears cache when posts, terms, or site options change.
     * Comprehensive invalidation hooks eliminate need for time-based expiration.
     */
    public static function register_cache_invalidation(): void {
        add_action('save_post', [__CLASS__, 'clear_cache']);
        add_action('delete_post', [__CLASS__, 'clear_cache']);
        add_action('wp_trash_post', [__CLASS__, 'clear_cache']);
        add_action('untrash_post', [__CLASS__, 'clear_cache']);

        add_action('create_term', [__CLASS__, 'clear_cache']);
        add_action('edit_term', [__CLASS__, 'clear_cache']);
        add_action('delete_term', [__CLASS__, 'clear_cache']);
        add_action('set_object_terms', [__CLASS__, 'clear_cache']);

        add_action('user_register', [__CLASS__, 'clear_cache']);
        add_action('delete_user', [__CLASS__, 'clear_cache']);
        add_action('set_user_role', [__CLASS__, 'clear_cache']);

        add_action('switch_theme', [__CLASS__, 'clear_cache']);

        add_action('update_option_blogname', [__CLASS__, 'clear_cache']);
        add_action('update_option_blogdescription', [__CLASS__, 'clear_cache']);
        add_action('update_option_home', [__CLASS__, 'clear_cache']);
        add_action('update_option_siteurl', [__CLASS__, 'clear_cache']);
    }
}

add_action('init', [SiteContext::class, 'register_cache_invalidation']);