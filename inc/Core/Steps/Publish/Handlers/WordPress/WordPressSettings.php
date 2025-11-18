<?php
/**
 * WordPress Publish Handler Settings
 *
 * Defines settings fields and sanitization for WordPress publish handler.
 * Extends base publish handler settings with WordPress-specific configuration.
 *
 * @package DataMachine
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\WordPress;

use DataMachine\Core\Steps\Publish\Handlers\PublishHandlerSettings;

defined('ABSPATH') || exit;

class WordPressSettings extends PublishHandlerSettings {

    /**
     * Get settings fields for WordPress publish handler.
     *
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(): array {
        // WordPress publish settings for local WordPress installation only
        $fields = self::get_local_fields();

        // Add common publish handler fields with WordPress-specific alignment
        $fields = array_merge($fields, self::get_wordpress_common_fields());

        return $fields;
    }

    /**
     * Get settings fields specific to local WordPress publishing.
     *
     * @return array Settings fields.
     */
    private static function get_local_fields(): array {
        // Get available post types
        $post_type_options = [];
        $post_types = get_post_types(['public' => true], 'objects');

        // Remove attachment post type as it's not suitable for content publishing
        unset($post_types['attachment']);

        // Prioritize common post types first, but use WordPress's actual labels
        $common_type_order = ['post', 'page'];
        foreach ($common_type_order as $slug) {
            if (isset($post_types[$slug])) {
                $post_type_options[$slug] = $post_types[$slug]->label;
                unset($post_types[$slug]);
            }
        }
        // Add remaining post types
        foreach ($post_types as $pt) {
            $post_type_options[$pt->name] = $pt->label;
        }

        // Get dynamic taxonomy fields for all available taxonomies
        $taxonomy_fields = self::get_taxonomy_fields();

        // Get available WordPress users for post authorship
        $user_options = [];
        $users = get_users(['fields' => ['ID', 'display_name', 'user_login']]);
        foreach ($users as $user) {
            $display_name = !empty($user->display_name) ? $user->display_name : $user->user_login;
            $user_options[$user->ID] = $display_name;
        }

        $fields = [
            'post_type' => [
                'type' => 'select',
                'label' => __('Post Type', 'datamachine'),
                'description' => __('Select the post type for published content.', 'datamachine'),
                'options' => $post_type_options,
            ],
            'post_status' => [
                'type' => 'select',
                'label' => __('Post Status', 'datamachine'),
                'description' => __('Select the status for the newly created post.', 'datamachine'),
                'options' => get_post_statuses(),
            ],
            'post_author' => [
                'type' => 'select',
                'label' => __('Post Author', 'datamachine'),
                'description' => __('Select which WordPress user to publish posts under.', 'datamachine'),
                'options' => $user_options,
            ],
        ];

        // Merge in dynamic taxonomy fields
        return array_merge($fields, $taxonomy_fields);
    }

    /**
     * Get dynamic taxonomy fields for all available public taxonomies.
     *
     * @return array Taxonomy field definitions.
     */
    private static function get_taxonomy_fields(): array {
        $taxonomy_fields = [];
        
        // Get all public taxonomies
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        
        foreach ($taxonomies as $taxonomy) {
            // Skip built-in formats and other non-content taxonomies using centralized filter
            $excluded = apply_filters('datamachine_wordpress_system_taxonomies', []);
            if (in_array($taxonomy->name, $excluded)) {
                continue;
            }
            
            $taxonomy_slug = $taxonomy->name;
            $taxonomy_label = (is_object($taxonomy->labels) && isset($taxonomy->labels->name))
                ? $taxonomy->labels->name
                : (isset($taxonomy->label) ? $taxonomy->label : $taxonomy->name);
            
            // Build options with skip as default
            $options = [
                'skip' => __('Skip', 'datamachine'),
                'ai_decides' => __('AI Decides', 'datamachine')
            ];
            
            // Get terms for this taxonomy
            $terms = get_terms(['taxonomy' => $taxonomy_slug, 'hide_empty' => false]);
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    $options[$term->term_id] = $term->name;
                }
            }
            
            // Generate field definition
            $field_key = "taxonomy_{$taxonomy_slug}_selection";
            $taxonomy_fields[$field_key] = [
                'type' => 'select',
                'label' => $taxonomy_label,
                'description' => sprintf(
                    /* translators: %1$s: Taxonomy name (lowercase), %2$s: Category or term type */
                    __('Configure %1$s assignment: Skip to exclude from AI instructions, let AI choose, or select specific %2$s.', 'datamachine'),
                    strtolower($taxonomy_label),
                    $taxonomy->hierarchical ? __('category', 'datamachine') : __('term', 'datamachine')
                ),
                'options' => $options,
            ];
        }
        
        return $taxonomy_fields;
    }


    /**
     * Get WordPress-specific common fields aligned with base publish handler fields.
     *
     * @return array Settings fields.
     */
    private static function get_wordpress_common_fields(): array {
        $common_fields = parent::get_common_fields();

        // Align field names and add WordPress-specific fields
        return array_merge($common_fields, [
            'post_date_source' => [
                'type' => 'select',
                'label' => __('Post Date Setting', 'datamachine'),
                'description' => __('Choose whether to use the original date from the source (if available) or the current date when publishing.', 'datamachine'),
                'options' => [
                    'current_date' => __('Use Current Date', 'datamachine'),
                    'source_date' => __('Use Source Date (if available)', 'datamachine'),
                ],
            ],
        ]);
    }

    /**
     * Sanitize WordPress publish handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        // Sanitize local WordPress settings
        $sanitized = self::sanitize_local_settings($raw_settings);

        // Sanitize common publish handler fields
        $sanitized = array_merge($sanitized, parent::sanitize($raw_settings));

        // Sanitize WordPress-specific common fields
        $valid_date_sources = ['current_date', 'source_date'];
        $date_source = sanitize_text_field($raw_settings['post_date_source'] ?? 'current_date');
        if (!in_array($date_source, $valid_date_sources)) {
            do_action('datamachine_log', 'error', 'WordPress Settings: Invalid post_date_source parameter provided', [
                'provided_value' => $date_source,
                'valid_options' => $valid_date_sources
            ]);
            $date_source = 'current_date'; // Fall back to default
        }
        $sanitized['post_date_source'] = $date_source;

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
            'post_type' => sanitize_text_field($raw_settings['post_type'] ?? 'post'),
            'post_status' => sanitize_text_field($raw_settings['post_status'] ?? 'draft'),
            'post_author' => absint($raw_settings['post_author']),
        ];

        // Sanitize dynamic taxonomy selections
        $sanitized = array_merge($sanitized, self::sanitize_taxonomy_selections($raw_settings));

        return $sanitized;
    }

    /**
     * Sanitize dynamic taxonomy selection settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized taxonomy selections.
     */
    private static function sanitize_taxonomy_selections(array $raw_settings): array {
        $sanitized = [];
        
        // Get all public taxonomies to validate against
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        
        foreach ($taxonomies as $taxonomy) {
            // Skip built-in formats and other non-content taxonomies using centralized filter
            $excluded = apply_filters('datamachine_wordpress_system_taxonomies', []);
            if (in_array($taxonomy->name, $excluded)) {
                continue;
            }
            
            $field_key = "taxonomy_{$taxonomy->name}_selection";
            $raw_value = $raw_settings[$field_key] ?? 'skip';
            
            // Sanitize taxonomy selection value
            if ($raw_value === 'skip' || $raw_value === 'ai_decides') {
                $sanitized[$field_key] = $raw_value;
            } else {
                // Must be a term ID - validate it exists in this taxonomy using centralized filter
                $term_id = absint($raw_value);
                $term_name = apply_filters('datamachine_wordpress_term_name', null, $term_id, $taxonomy->name);
                if ($term_name !== null) {
                    $sanitized[$field_key] = $term_id;
                } else {
                    // Invalid term ID - default to skip
                    $sanitized[$field_key] = 'skip';
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Get default values for all available taxonomies.
     *
     * @return array Default taxonomy selections (all set to 'skip').
     */
    private static function get_taxonomy_defaults(): array {
        $defaults = [];
        
        // Get all public taxonomies
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        
        foreach ($taxonomies as $taxonomy) {
            // Skip built-in formats and other non-content taxonomies using centralized filter
            $excluded = apply_filters('datamachine_wordpress_system_taxonomies', []);
            if (in_array($taxonomy->name, $excluded)) {
                continue;
            }
            
            $field_key = "taxonomy_{$taxonomy->name}_selection";
            $defaults[$field_key] = 'skip'; // Default to skip for all taxonomies
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
