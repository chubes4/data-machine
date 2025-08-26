<?php
/**
 * Site Context Data Collection
 * 
 * Provides comprehensive WordPress site information for AI model context.
 * Uses caching to optimize performance and includes automatic cache invalidation.
 *
 * @package DataMachine\Core\Steps\AI
 * @author Chris Huber <https://chubes.net>
 */

namespace DataMachine\Core\Steps\AI;

defined('ABSPATH') || exit;

/**
 * WordPress Site Context Collector
 * 
 * Gathers detailed site information including post types, taxonomies,
 * and metadata to provide AI models with comprehensive site context.
 */
class SiteContext {

    /**
     * Cache key for site context data
     */
    const CACHE_KEY = 'dm_site_context_data';

    /**
     * Cache duration in seconds (1 hour)
     */
    const CACHE_DURATION = 3600;

    /**
     * Get complete site context data
     * 
     * @return array Complete site context information
     */
    public static function get_context(): array {
        // Check cache first
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false) {
            return $cached;
        }

        // Generate fresh context data
        $context = [
            'site' => self::get_site_metadata(),
            'post_types' => self::get_post_types_data(),
            'taxonomies' => self::get_taxonomies_data(),
            'users' => self::get_user_statistics(),
            'theme' => self::get_theme_info()
        ];

        // Cache the result
        set_transient(self::CACHE_KEY, $context, self::CACHE_DURATION);

        do_action('dm_log', 'debug', 'Site Context: Generated fresh context data', [
            'post_types_count' => count($context['post_types']),
            'taxonomies_count' => count($context['taxonomies']),
            'users_count' => $context['users']['total'] ?? 0
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
                'supports' => get_all_post_type_supports($post_type->name),
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
                'count' => true
            ]);

            $term_count = is_array($terms) ? count($terms) : 0;
            $posts_with_terms = 0;

            if (is_array($terms)) {
                foreach ($terms as $term) {
                    $posts_with_terms += $term->count;
                }
            }

            $taxonomies_data[$taxonomy->name] = [
                'label' => $taxonomy->label,
                'singular_label' => $taxonomy->labels->singular_name ?? $taxonomy->label,
                'terms' => $term_count,
                'posts' => $posts_with_terms,
                'hierarchical' => $taxonomy->hierarchical,
                'post_types' => $taxonomy->object_type ?? []
            ];
        }

        return $taxonomies_data;
    }

    /**
     * Get user statistics
     * 
     * @return array User counts by role and total
     */
    private static function get_user_statistics(): array {
        $user_count = count_users();
        
        return [
            'total' => $user_count['total_users'],
            'roles' => $user_count['avail_roles'] ?? []
        ];
    }

    /**
     * Get current theme information
     * 
     * @return array Theme name, version, and parent theme if applicable
     */
    private static function get_theme_info(): array {
        $theme = wp_get_theme();
        
        $theme_info = [
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'template' => $theme->get_template()
        ];

        // Add parent theme info if child theme
        if ($theme->parent()) {
            $theme_info['parent'] = [
                'name' => $theme->parent()->get('Name'),
                'version' => $theme->parent()->get('Version')
            ];
        }

        return $theme_info;
    }

    /**
     * Format context data as readable text for AI models
     * 
     * @param array $context Site context data
     * @return string Formatted context text
     */
    public static function format_for_ai(array $context): string {
        $formatted = "WordPress Site Context:\n\n";

        // Site information
        if (!empty($context['site'])) {
            $site = $context['site'];
            $formatted .= "Site: {$site['name']}";
            if (!empty($site['tagline'])) {
                $formatted .= " - {$site['tagline']}";
            }
            $formatted .= "\nURL: {$site['url']}\n";
            $formatted .= "Language: {$site['language']}\n\n";
        }

        // Post types
        if (!empty($context['post_types'])) {
            $formatted .= "Content Types:\n";
            foreach ($context['post_types'] as $slug => $data) {
                $formatted .= "- {$data['label']} ({$slug}): {$data['count']} published";
                if ($data['hierarchical']) {
                    $formatted .= " (hierarchical)";
                }
                $formatted .= "\n";
            }
            $formatted .= "\n";
        }

        // Taxonomies
        if (!empty($context['taxonomies'])) {
            $formatted .= "Taxonomies:\n";
            foreach ($context['taxonomies'] as $slug => $data) {
                $formatted .= "- {$data['label']} ({$slug}): {$data['terms']} terms, {$data['posts']} total associations";
                if ($data['hierarchical']) {
                    $formatted .= " (hierarchical)";
                }
                $formatted .= "\n";
            }
            $formatted .= "\n";
        }

        // Users
        if (!empty($context['users']['total'])) {
            $formatted .= "Users: {$context['users']['total']} total\n";
            if (!empty($context['users']['roles'])) {
                $roles = array_map(function($role, $count) {
                    return "{$role} ({$count})";
                }, array_keys($context['users']['roles']), $context['users']['roles']);
                $formatted .= "Roles: " . implode(', ', $roles) . "\n";
            }
            $formatted .= "\n";
        }

        // Theme
        if (!empty($context['theme']['name'])) {
            $formatted .= "Active Theme: {$context['theme']['name']}";
            if (!empty($context['theme']['parent']['name'])) {
                $formatted .= " (child of {$context['theme']['parent']['name']})";
            }
            $formatted .= "\n";
        }

        return trim($formatted);
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

// Register cache invalidation hooks when class loads
add_action('init', [SiteContext::class, 'register_cache_invalidation']);