<?php
/**
 * WordPress Settings Handler Utilities
 *
 * Provides reusable WordPress-specific settings utilities for taxonomy fields,
 * post type options, and user options across all WordPress handler Settings classes.
 * Eliminates duplication between Publish, Fetch, and Update Settings.
 *
 * @package DataMachine\Core\WordPress
 * @since 0.2.1
 */

namespace DataMachine\Core\WordPress;

defined('ABSPATH') || exit;

class WordPressSettingsHandler {

    /**
     * Get dynamic taxonomy fields for all available public taxonomies.
     *
     * @param array $config Configuration array with:
     *  - field_suffix: '_selection' or '_filter'
     *  - first_options: Array of initial options (skip/ai_decides or all)
     *  - description_template: sprintf template for field description
     * @return array Taxonomy field definitions
     */
    public static function get_taxonomy_fields(array $config = []): array {
        $defaults = [
            'field_suffix' => 'selection',
            'first_options' => [
                'skip' => __('Skip', 'datamachine'),
                'ai_decides' => __('AI Decides', 'datamachine')
            ],
            'description_template' => __('Configure %1$s assignment: Skip to exclude from AI instructions, let AI choose, or select specific %2$s.', 'datamachine'),
            'default' => 'skip'
        ];
        $config = array_merge($defaults, $config);

        $taxonomy_fields = [];
        $taxonomies = TaxonomyHandler::getPublicTaxonomies();

        foreach ($taxonomies as $taxonomy) {
            if (TaxonomyHandler::shouldSkipTaxonomy($taxonomy->name)) {
                continue;
            }

            $taxonomy_slug = $taxonomy->name;
            $taxonomy_label = (is_object($taxonomy->labels) && isset($taxonomy->labels->name))
                ? $taxonomy->labels->name
                : (isset($taxonomy->label) ? $taxonomy->label : $taxonomy->name);

            // Build options with configured first options
            $options = $config['first_options'];

            // Add visual separator after system options
            $options['separator'] = '──────────';

            // Get terms for this taxonomy
            $terms = get_terms(['taxonomy' => $taxonomy_slug, 'hide_empty' => false]);
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    $options[$term->term_id] = $term->name;
                }
            }

            // Generate field definition
            $field_key = "taxonomy_{$taxonomy_slug}{$config['field_suffix']}";
            $taxonomy_fields[$field_key] = [
                'type' => 'select',
                'label' => $taxonomy_label,
                'description' => sprintf(
                    $config['description_template'],
                    strtolower($taxonomy_label),
                    $taxonomy->hierarchical ? __('category', 'datamachine') : __('term', 'datamachine')
                ),
                'options' => $options,
                'default' => $config['default'] ?? 'skip',
            ];
        }

        return $taxonomy_fields;
    }

    /**
     * Sanitize dynamic taxonomy field settings.
     *
     * @param array $raw_settings Raw settings input
     * @param array $config Configuration array with:
     *  - field_suffix: '_selection' or '_filter'
     *  - allowed_values: Array of allowed string values (e.g., ['skip', 'ai_decides'] or [0])
     *  - default_value: Default value if validation fails
     * @return array Sanitized taxonomy settings
     */
    public static function sanitize_taxonomy_fields(array $raw_settings, array $config = []): array {
        $defaults = [
            'field_suffix' => 'selection',
            'allowed_values' => ['skip', 'ai_decides'],
            'default_value' => 'skip'
        ];
        $config = array_merge($defaults, $config);

        $sanitized = [];
        $taxonomies = TaxonomyHandler::getPublicTaxonomies();

        foreach ($taxonomies as $taxonomy) {
            if (TaxonomyHandler::shouldSkipTaxonomy($taxonomy->name)) {
                continue;
            }

            $field_key = "taxonomy_{$taxonomy->name}{$config['field_suffix']}";
            $raw_value = $raw_settings[$field_key] ?? $config['default_value'];

            // Check if value is one of the allowed string values
            if (in_array($raw_value, $config['allowed_values'], true)) {
                $sanitized[$field_key] = $raw_value;
            } else {
                // Must be a term ID - validate it exists in this taxonomy
                $term_id = absint($raw_value);
                if ($term_id > 0) {
                    $term_name = TaxonomyHandler::getTermName($term_id, $taxonomy->name);
                    if ($term_name !== null) {
                        $sanitized[$field_key] = $term_id;
                    } else {
                        // Invalid term ID - use default
                        $sanitized[$field_key] = $config['default_value'];
                    }
                } else {
                    // Invalid value - use default
                    $sanitized[$field_key] = $config['default_value'];
                }
            }
        }

        return $sanitized;
    }

    /**
     * Get available WordPress post type options.
     *
     * @param bool $include_any Include "Any" option at the start
     * @return array Post type options
     */
    public static function get_post_type_options(bool $include_any = false): array {
        $post_types = get_post_types(['public' => true], 'objects');
        $post_type_options = [];

        // Add "Any" option if requested (for fetch handlers)
        if ($include_any) {
            $post_type_options['any'] = __('Any', 'datamachine');
        }

        // Remove attachment post type as it's not suitable for content publishing
        unset($post_types['attachment']);

        // Prioritize common post types first
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

        return $post_type_options;
    }

    /**
     * Get available WordPress users for post authorship.
     *
     * @return array User options (user_id => display_name)
     */
    public static function get_user_options(): array {
        $user_options = [];
        $users = get_users(['fields' => ['ID', 'display_name', 'user_login']]);

        foreach ($users as $user) {
            $display_name = !empty($user->display_name) ? $user->display_name : $user->user_login;
            $user_options[$user->ID] = $display_name;
        }

        return $user_options;
    }

}
