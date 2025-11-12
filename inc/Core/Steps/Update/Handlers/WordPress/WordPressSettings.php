<?php
/**
 * WordPress Update Handler Settings
 *
 * Defines settings fields and sanitization for WordPress update handler.
 * Part of the modular handler architecture.
 *
 * @package DataMachine
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Update\Handlers\WordPress;

defined('ABSPATH') || exit;

class WordPressSettings {


    /**
     * Get settings fields for WordPress update handler.
     *
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(): array {
        // WordPress update settings for local WordPress installation only
        $fields = self::get_local_fields();

        // Add common fields for all destination types
        $fields = array_merge($fields, self::get_common_fields());

        return $fields;
    }

    /**
     * Get settings fields specific to local WordPress updating.
     *
     * @return array Settings fields.
     */
    private static function get_local_fields(): array {
        return [
            'allow_title_updates' => [
                'type' => 'checkbox',
                'label' => __('Allow Title Updates', 'datamachine'),
                'description' => __('Enable AI to modify post titles. When disabled, titles will remain unchanged.', 'datamachine'),
            ],
            'allow_content_updates' => [
                'type' => 'checkbox',
                'label' => __('Allow Content Updates', 'datamachine'),
                'description' => __('Enable AI to modify post content. When disabled, content will remain unchanged.', 'datamachine'),
            ],
        ];
    }

    /**
     * Get dynamic taxonomy fields for all available public taxonomies.
     *
     * @return array Taxonomy field definitions.
     */
    private static function get_taxonomy_fields(): array {
        $taxonomy_fields = [];
        
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        
        foreach ($taxonomies as $taxonomy) {
            // Skip built-in formats and other non-content taxonomies using centralized filter
            $excluded = apply_filters('datamachine_wordpress_system_taxonomies', []);
            if (in_array($taxonomy->name, $excluded)) {
                continue;
            }
            
            $taxonomy_slug = $taxonomy->name;
            $taxonomy_label = $taxonomy->labels->name ?? $taxonomy->label;
            
            // Build options with skip as default
            $options = [
                'skip' => __('Skip', 'datamachine'),
                'ai_decides' => __('AI Decides', 'datamachine')
            ];
            
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
                    __('Configure %1$s assignment for updates: Skip to exclude from AI instructions, let AI choose, or select specific %2$s.', 'datamachine'),
                    strtolower($taxonomy_label),
                    $taxonomy->hierarchical ? __('category', 'datamachine') : __('term', 'datamachine')
                ),
                'options' => $options,
            ];
        }
        
        return $taxonomy_fields;
    }


    /**
     * Get common settings fields for all destination types.
     *
     * @return array Settings fields.
     */
    private static function get_common_fields(): array {
        return [];
    }

    /**
     * Sanitize WordPress update handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        // Sanitize local WordPress settings
        return self::sanitize_local_settings($raw_settings);
    }

    /**
     * Sanitize local WordPress settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     */
    private static function sanitize_local_settings(array $raw_settings): array {
        return [
            'allow_title_updates' => !empty($raw_settings['allow_title_updates']),
            'allow_content_updates' => !empty($raw_settings['allow_content_updates']),
        ];
    }

    /**
     * Sanitize dynamic taxonomy selection settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized taxonomy selections.
     */
    private static function sanitize_taxonomy_selections(array $raw_settings): array {
        $sanitized = [];
        
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