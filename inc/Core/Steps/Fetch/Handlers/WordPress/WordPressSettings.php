<?php
/**
 * WordPress Fetch Handler Settings
 *
 * Defines settings fields and sanitization for WordPress fetch handler.
 * Part of the modular handler architecture.
 *
 * @package    DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\WordPress
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WordPressSettings {

    public function __construct() {
    }

    /**
     * Get settings fields for WordPress fetch handler.
     *
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(): array {
        // WordPress fetch settings for local WordPress installation only
        $fields = self::get_local_fields();

        // Add common fields for all source types
        $fields = array_merge($fields, self::get_common_fields());

        return $fields;
    }

    /**
     * Get settings fields specific to local WordPress.
     *
     * @return array Settings fields.
     */
    private static function get_local_fields(): array {
        // Get available post types
        $post_types = get_post_types(['public' => true], 'objects');
        $post_type_options = [
            'any' => __('Any', 'data-machine'),
        ];
        foreach ($post_types as $post_type) {
            if (is_object($post_type)) {
                $post_type_options[$post_type->name] = $post_type->label;
            } elseif (is_string($post_type)) {
                $post_type_options[$post_type] = $post_type;
            }
        }

        // Get dynamic taxonomy filter fields for all available taxonomies
        $taxonomy_fields = self::get_taxonomy_filter_fields();

        $fields = [
            'source_url' => [
                'type' => 'text',
                'label' => __('Specific Post URL', 'data-machine'),
                'description' => __('Target a specific post by URL. When provided, other filters are ignored.', 'data-machine'),
                'placeholder' => __('Leave empty for general query', 'data-machine'),
            ],
            'post_type' => [
                'type' => 'select',
                'label' => __('Post Type', 'data-machine'),
                'description' => __('Select the post type to fetch from the local site.', 'data-machine'),
                'options' => $post_type_options,
            ],
            'post_status' => [
                'type' => 'select',
                'label' => __('Post Status', 'data-machine'),
                'description' => __('Select the post status to fetch.', 'data-machine'),
                'options' => [
                    'publish' => __('Published', 'data-machine'),
                    'draft' => __('Draft', 'data-machine'),
                    'pending' => __('Pending', 'data-machine'),
                    'private' => __('Private', 'data-machine'),
                    'any' => __('Any', 'data-machine'),
                ],
            ],
        ];

        // Merge in dynamic taxonomy filter fields
        return array_merge($fields, $taxonomy_fields);
    }

    /**
     * Get dynamic taxonomy filter fields for all available public taxonomies.
     *
     * @return array Taxonomy filter field definitions.
     */
    private static function get_taxonomy_filter_fields(): array {
        $taxonomy_fields = [];
        
        // Get all public taxonomies
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        
        foreach ($taxonomies as $taxonomy) {
            // Ensure $taxonomy is an object before accessing its properties
            if (!is_object($taxonomy)) {
                continue;
            }
            // Skip built-in formats and other non-content taxonomies
            if (in_array($taxonomy->name, ['post_format', 'nav_menu', 'link_category'])) {
                continue;
            }
            
            $taxonomy_slug = $taxonomy->name;
            $taxonomy_label = (is_object($taxonomy->labels) && isset($taxonomy->labels->name))
                ? $taxonomy->labels->name
                : $taxonomy->label;
            
            // Build filter options with "All" as default
            $options = [
                /* translators: %s: Taxonomy name (e.g., Categories, Tags) */
                0 => sprintf(__('All %s', 'data-machine'), $taxonomy_label)
            ];
            
            // Get terms for this taxonomy
            $terms = get_terms(['taxonomy' => $taxonomy_slug, 'hide_empty' => false]);
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    $options[$term->term_id] = $term->name;
                }
            }
            
            // Generate field definition
            $field_key = "taxonomy_{$taxonomy_slug}_filter";
            $taxonomy_fields[$field_key] = [
                'type' => 'select',
                'label' => $taxonomy_label,
                'description' => sprintf(
                    /* translators: %1$s: Taxonomy name (lowercase), %2$s: Taxonomy name (lowercase) */
                    __('Filter by specific %1$s or fetch from all %2$s.', 'data-machine'),
                    strtolower($taxonomy_label),
                    strtolower($taxonomy_label)
                ),
                'options' => $options,
            ];
        }
        
        return $taxonomy_fields;
    }


    /**
     * Get common settings fields for all source types.
     *
     * @return array Settings fields.
     */
    private static function get_common_fields(): array {
        return [
            'timeframe_limit' => [
                'type' => 'select',
                'label' => __('Process Items Within', 'data-machine'),
                'description' => __('Only consider items published within this timeframe.', 'data-machine'),
                'options' => apply_filters('dm_timeframe_limit', [], null),
            ],
            'search' => [
                'type' => 'text',
                'label' => __('Search Term Filter', 'data-machine'),
                'description' => __('Filter items using a search term.', 'data-machine'),
            ],
            'randomize_selection' => [
                'type' => 'checkbox',
                'label' => __('Randomize selection', 'data-machine'),
                'description' => __('Select a random post instead of most recently modified.', 'data-machine'),
            ],
        ];
    }

    /**
     * Sanitize WordPress fetch handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        // Sanitize local WordPress settings
        $sanitized = self::sanitize_local_settings($raw_settings);

        // Sanitize common fields
        $sanitized['timeframe_limit'] = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
        $sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? '');
        $sanitized['randomize_selection'] = !empty($raw_settings['randomize_selection']);

        return $sanitized;
    }

    /**
     * Sanitize local WordPress settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     */
    private static function sanitize_local_settings(array $raw_settings): array {
        $sanitized = [
            'source_url' => sanitize_url($raw_settings['source_url'] ?? ''),
            'post_type' => sanitize_text_field($raw_settings['post_type'] ?? 'any'),
            'post_status' => sanitize_text_field($raw_settings['post_status'] ?? 'publish'),
        ];

        // Sanitize dynamic taxonomy filter selections
        $taxonomy_filters = self::sanitize_taxonomy_filters($raw_settings);
        return array_merge($sanitized, $taxonomy_filters);
    }

    /**
     * Sanitize dynamic taxonomy filter settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized taxonomy filter selections.
     */
    private static function sanitize_taxonomy_filters(array $raw_settings): array {
        $sanitized = [];
        
        // Get all public taxonomies to validate against
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        
        foreach ($taxonomies as $taxonomy) {
            // Ensure $taxonomy is an object before accessing its properties
            if (!is_object($taxonomy)) {
                continue;
            }
            // Skip built-in formats and other non-content taxonomies
            if (in_array($taxonomy->name, ['post_format', 'nav_menu', 'link_category'])) {
                continue;
            }
            
            $field_key = "taxonomy_{$taxonomy->name}_filter";
            $raw_value = $raw_settings[$field_key] ?? 0;
            
            // Sanitize taxonomy filter value (0 = all, or specific term ID)
            if ($raw_value == 0) {
                $sanitized[$field_key] = 0; // All terms
            } else {
                // Must be a term ID - validate it exists in this taxonomy
                $term_id = intval($raw_value);
                if ($term_id <= 0) {
                    // Invalid numeric value - default to all
                    $sanitized[$field_key] = 0;
                } else {
                    $term = get_term($term_id, $taxonomy->name);
                    if (!is_wp_error($term) && $term) {
                        $sanitized[$field_key] = $term_id;
                    } else {
                        // Invalid term ID - default to all
                        $sanitized[$field_key] = 0;
                    }
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Get default values for all available taxonomy filters.
     *
     * @return array Default taxonomy filter selections (all set to 0 = "All").
     */
    private static function get_taxonomy_filter_defaults(): array {
        $defaults = [];
        
        // Get all public taxonomies
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        
        foreach ($taxonomies as $taxonomy) {
            // Ensure $taxonomy is an object before accessing its properties
            if (!is_object($taxonomy)) {
                continue;
            }
            // Skip built-in formats and other non-content taxonomies
            if (in_array($taxonomy->name, ['post_format', 'nav_menu', 'link_category'])) {
                continue;
            }
            
            $field_key = "taxonomy_{$taxonomy->name}_filter";
            $defaults[$field_key] = 0; // Default to "All" for all taxonomy filters
        }
        
        return $defaults;
    }

    /**
     * Determine if authentication is required based on current configuration.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return bool True if authentication is required, false otherwise.
     */
    public static function requires_authentication(array $current_config = []): bool {
        // Local WordPress does not require authentication
        return false;
    }
}
