<?php
/**
 * WordPress Publish Handler Settings
 *
 * Defines settings fields and sanitization for WordPress publish handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/handlers/publish/wordpress
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Publish\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WordPressSettings {

    /**
     * Constructor.
     * Pure filter-based architecture - no dependencies.
     */
    public function __construct() {
        // No constructor dependencies - all services accessed via filters
    }

    /**
     * Get settings fields for WordPress publish handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        // Provide default destination_type if not set (for modal configuration)
        $destination_type = $current_config['destination_type'] ?? 'local';

        $fields = [
            'destination_type' => [
                'type' => 'select',
                'label' => __('WordPress Destination Type', 'data-machine'),
                'description' => __('Select where to publish the processed content.', 'data-machine'),
                'options' => [
                    'local' => __('Local WordPress', 'data-machine'),
                    'remote' => __('Remote WordPress (Airdrop)', 'data-machine'),
                ],
            ],
        ];

        // Add conditional fields based on destination type
        switch ($destination_type) {
            case 'local':
                $fields = array_merge($fields, self::get_local_fields());
                break;

            case 'remote':
                $fields = array_merge($fields, self::get_remote_fields($current_config));
                break;
        }

        // Add common fields for all destination types
        $fields = array_merge($fields, self::get_common_fields());

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
        $common_types = ['post' => 'Post', 'page' => 'Page'];
        foreach ($common_types as $slug => $label) {
            if (isset($post_types[$slug])) {
                $post_type_options[$slug] = $label;
                unset($post_types[$slug]);
            }
        }
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
                'label' => __('Post Type', 'data-machine'),
                'description' => __('Select the post type for published content.', 'data-machine'),
                'options' => $post_type_options,
            ],
            'post_status' => [
                'type' => 'select',
                'label' => __('Post Status', 'data-machine'),
                'description' => __('Select the status for the newly created post.', 'data-machine'),
                'options' => [
                    'draft' => __('Draft', 'data-machine'),
                    'publish' => __('Publish', 'data-machine'),
                    'pending' => __('Pending Review', 'data-machine'),
                    'private' => __('Private', 'data-machine'),
                ],
            ],
            'post_author' => [
                'type' => 'select',
                'label' => __('Post Author', 'data-machine'),
                'description' => __('Select which WordPress user to publish posts under.', 'data-machine'),
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
            // Skip built-in formats and other non-content taxonomies
            if (in_array($taxonomy->name, ['post_format', 'nav_menu', 'link_category'])) {
                continue;
            }
            
            $taxonomy_slug = $taxonomy->name;
            $taxonomy_label = $taxonomy->labels->name ?? $taxonomy->label;
            
            // Build options with skip as default
            $options = [
                'skip' => sprintf(__('Skip %s', 'data-machine'), $taxonomy_label),
                'instruct_model' => sprintf(__('Let AI Choose %s', 'data-machine'), $taxonomy_label)
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
                    __('Configure %s assignment: Skip to exclude from AI instructions, let AI choose, or select specific %s.', 'data-machine'),
                    strtolower($taxonomy_label),
                    $taxonomy->hierarchical ? __('category', 'data-machine') : __('term', 'data-machine')
                ),
                'options' => $options,
            ];
        }
        
        return $taxonomy_fields;
    }

    /**
     * Get settings fields specific to remote WordPress publishing.
     *
     * @param array $current_config Current configuration.
     * @return array Settings fields.
     */
    private static function get_remote_fields(array $current_config = []): array {
        // Get remote locations service via filter system
        $all_databases = apply_filters('dm_db', []);
        $db_remote_locations = $all_databases['remote_locations'] ?? null;
        $locations = $db_remote_locations ? $db_remote_locations->get_locations_for_current_user() : [];

        $options = [0 => __('Select a Remote Location', 'data-machine')];
        foreach ($locations as $loc) {
            $options[$loc->location_id] = $loc->location_name . ' (' . $loc->target_site_url . ')';
        }

        return [
            'location_id' => [
                'type' => 'select',
                'label' => __('Remote Location', 'data-machine'),
                'description' => __('Select the pre-configured remote WordPress site to publish to.', 'data-machine'),
                'options' => $options,
            ],
            'selected_remote_post_type' => [
                'type' => 'select',
                'label' => __('Post Type', 'data-machine'),
                'description' => __('Select the post type for the remote site.', 'data-machine'),
                'options' => ['post' => 'Posts', 'page' => 'Pages'],
            ],
            'remote_post_status' => [
                'type' => 'select',
                'label' => __('Post Status', 'data-machine'),
                'description' => __('Select the status for the newly created post on the remote site.', 'data-machine'),
                'options' => [
                    'draft' => __('Draft', 'data-machine'),
                    'publish' => __('Publish', 'data-machine'),
                    'pending' => __('Pending Review', 'data-machine'),
                    'private' => __('Private', 'data-machine'),
                ],
            ],
            'selected_remote_category_id' => [
                'type' => 'select',
                'label' => __('Category', 'data-machine'),
                'description' => __('Select a category or let the AI choose based on your prompt.', 'data-machine'),
                'options' => ['instruct_model' => '-- Instruct Model --'],
            ],
            'selected_remote_tag_id' => [
                'type' => 'select',
                'label' => __('Tag', 'data-machine'),
                'description' => __('Select a tag or let the AI choose based on your prompt.', 'data-machine'),
                'options' => ['instruct_model' => '-- Instruct Model --'],
            ],
        ];
    }

    /**
     * Get common settings fields for all destination types.
     *
     * @return array Settings fields.
     */
    private static function get_common_fields(): array {
        return [
            'post_date_source' => [
                'type' => 'select',
                'label' => __('Post Date Setting', 'data-machine'),
                'description' => __('Choose whether to use the original date from the source (if available) or the current date when publishing.', 'data-machine'),
                'options' => [
                    'current_date' => __('Use Current Date', 'data-machine'),
                    'source_date' => __('Use Source Date (if available)', 'data-machine'),
                ],
            ],
        ];
    }

    /**
     * Sanitize WordPress publish handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [];

        // Destination type is required - no defaults allowed
        if (!isset($raw_settings['destination_type'])) {
            throw new \Exception(esc_html__('WordPress destination_type setting is required.', 'data-machine'));
        }
        
        $sanitized['destination_type'] = sanitize_text_field($raw_settings['destination_type']);
        if (!in_array($sanitized['destination_type'], ['local', 'remote'])) {
            throw new \Exception(esc_html__('Invalid destination_type value. Must be "local" or "remote".', 'data-machine'));
        }

        // Sanitize based on destination type
        switch ($sanitized['destination_type']) {
            case 'local':
                $sanitized = array_merge($sanitized, self::sanitize_local_settings($raw_settings));
                break;

            case 'remote':
                $sanitized = array_merge($sanitized, self::sanitize_remote_settings($raw_settings));
                break;
        }

        // Sanitize common fields - require explicit values
        if (!isset($raw_settings['post_date_source'])) {
            throw new \Exception(esc_html__('WordPress post_date_source setting is required.', 'data-machine'));
        }
        
        $valid_date_sources = ['current_date', 'source_date'];
        $date_source = sanitize_text_field($raw_settings['post_date_source']);
        if (!in_array($date_source, $valid_date_sources)) {
            throw new \Exception(esc_html__('Invalid post date source parameter provided in settings.', 'data-machine'));
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
            'post_author' => absint($raw_settings['post_author'] ?? get_current_user_id()),
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
            // Skip built-in formats and other non-content taxonomies
            if (in_array($taxonomy->name, ['post_format', 'nav_menu', 'link_category'])) {
                continue;
            }
            
            $field_key = "taxonomy_{$taxonomy->name}_selection";
            $raw_value = $raw_settings[$field_key] ?? 'skip';
            
            // Sanitize taxonomy selection value
            if ($raw_value === 'skip' || $raw_value === 'instruct_model') {
                $sanitized[$field_key] = $raw_value;
            } else {
                // Must be a term ID - validate it exists in this taxonomy
                $term_id = absint($raw_value);
                $term = get_term($term_id, $taxonomy->name);
                if (!is_wp_error($term) && $term) {
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
     * Sanitize remote WordPress settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     */
    private static function sanitize_remote_settings(array $raw_settings): array {
        $sanitized = [
            'location_id' => absint($raw_settings['location_id'] ?? 0),
            'selected_remote_post_type' => sanitize_text_field($raw_settings['selected_remote_post_type'] ?? 'post'),
            'remote_post_status' => sanitize_text_field($raw_settings['remote_post_status'] ?? 'draft'),
        ];

        // Sanitize Remote Category ID/Mode
        $cat_val = $raw_settings['selected_remote_category_id'] ?? 'instruct_model';
        if ($cat_val === 'instruct_model') {
            $sanitized['selected_remote_category_id'] = $cat_val;
        } else {
            $sanitized['selected_remote_category_id'] = intval($cat_val);
        }

        // Sanitize Remote Tag ID/Mode
        $tag_val = $raw_settings['selected_remote_tag_id'] ?? 'instruct_model';
        if ($tag_val === 'instruct_model') {
            $sanitized['selected_remote_tag_id'] = $tag_val;
        } else {
            $sanitized['selected_remote_tag_id'] = intval($tag_val);
        }

        return $sanitized;
    }

    /**
     * Get default values for all settings.
     *
     * @return array Default values for all settings.
     */
    public static function get_defaults(): array {
        $defaults = [
            'destination_type' => 'local',
            'post_type' => 'post',
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
            'post_date_source' => 'current_date',
        ];

        // Add dynamic taxonomy defaults (all skip by default)
        $taxonomy_defaults = self::get_taxonomy_defaults();
        return array_merge($defaults, $taxonomy_defaults);
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
            // Skip built-in formats and other non-content taxonomies
            if (in_array($taxonomy->name, ['post_format', 'nav_menu', 'link_category'])) {
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
        // Provide default destination_type if not set (for modal configuration)
        $destination_type = $current_config['destination_type'] ?? 'local';
        
        // Only remote destination requires authentication (Remote Locations)
        return $destination_type === 'remote';
    }
}
