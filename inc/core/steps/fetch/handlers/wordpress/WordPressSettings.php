<?php
/**
 * WordPress Fetch Handler Settings
 *
 * Defines settings fields and sanitization for WordPress fetch handler.
 * Part of the modular handler architecture.
 *
 * @package    DataMachine
 * @subpackage Core\Handlers\Fetch\WordPress
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Fetch\WordPress;

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
     * Get settings fields for WordPress fetch handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        $source_type = $current_config['source_type'] ?? 'local';

        $fields = [
            'source_type' => [
                'type' => 'select',
                'label' => __('WordPress Source Type', 'data-machine'),
                'description' => __('Select the type of WordPress source to fetch content from.', 'data-machine'),
                'options' => [
                    'local' => __('Local WordPress', 'data-machine'),
                    'remote_rest' => __('Remote WordPress (REST API)', 'data-machine'),
                    'remote_airdrop' => __('Remote WordPress (Airdrop)', 'data-machine'),
                ],
            ],
        ];

        // Add conditional fields based on source type
        switch ($source_type) {
            case 'local':
                $fields = array_merge($fields, self::get_local_fields());
                break;
            
            case 'remote_rest':
                $fields = array_merge($fields, self::get_remote_rest_fields());
                break;
            
            case 'remote_airdrop':
                $fields = array_merge($fields, self::get_remote_airdrop_fields($current_config));
                break;
        }

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
        $post_type_options = [];
        foreach ($post_types as $post_type) {
            $post_type_options[$post_type->name] = $post_type->label;
        }

        // Get dynamic taxonomy filter fields for all available taxonomies
        $taxonomy_fields = self::get_taxonomy_filter_fields();

        $fields = [
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
            'orderby' => [
                'type' => 'select',
                'label' => __('Order By', 'data-machine'),
                'description' => __('Select the field to order results by.', 'data-machine'),
                'options' => [
                    'date' => __('Date', 'data-machine'),
                    'modified' => __('Modified Date', 'data-machine'),
                    'title' => __('Title', 'data-machine'),
                    'ID' => __('ID', 'data-machine'),
                ],
            ],
            'order' => [
                'type' => 'select',
                'label' => __('Order', 'data-machine'),
                'description' => __('Select the order direction.', 'data-machine'),
                'options' => [
                    'DESC' => __('Descending', 'data-machine'),
                    'ASC' => __('Ascending', 'data-machine'),
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
            // Skip built-in formats and other non-content taxonomies
            if (in_array($taxonomy->name, ['post_format', 'nav_menu', 'link_category'])) {
                continue;
            }
            
            $taxonomy_slug = $taxonomy->name;
            $taxonomy_label = $taxonomy->labels->name ?? $taxonomy->label;
            
            // Build filter options with "All" as default
            $options = [
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
                    __('Filter by specific %s or fetch from all %s.', 'data-machine'),
                    strtolower($taxonomy_label),
                    strtolower($taxonomy_label)
                ),
                'options' => $options,
            ];
        }
        
        return $taxonomy_fields;
    }

    /**
     * Get settings fields specific to remote REST API.
     *
     * @return array Settings fields.
     */
    private static function get_remote_rest_fields(): array {
        return [
            'api_endpoint_url' => [
                'type' => 'url',
                'label' => __('API Endpoint URL', 'data-machine'),
                'description' => __('Enter the full URL of the WordPress REST API endpoint (e.g., https://example.com/wp-json/wp/v2/posts).', 'data-machine'),
                'required' => true,
            ],
            'data_path' => [
                'type' => 'text',
                'label' => __('Data Path (Optional)', 'data-machine'),
                'description' => __('If the items are nested within the JSON response, specify the path using dot notation (e.g., `data.items`). Leave empty to auto-detect the first array of objects.', 'data-machine'),
            ],
        ];
    }

    /**
     * Get settings fields specific to remote Airdrop.
     *
     * @param array $current_config Current configuration.
     * @return array Settings fields.
     */
    private static function get_remote_airdrop_fields(array $current_config = []): array {
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
                'description' => __('Select the pre-configured remote WordPress site to fetch data from.', 'data-machine'),
                'options' => $options,
            ],
            'rest_post_type' => [
                'type' => 'select',
                'label' => __('Post Type', 'data-machine'),
                'description' => __('Select the post type to fetch from the remote site.', 'data-machine'),
                'options' => ['post' => 'Posts', 'page' => 'Pages'],
            ],
            'rest_post_status' => [
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
            'rest_orderby' => [
                'type' => 'select',
                'label' => __('Order By', 'data-machine'),
                'description' => __('Select the field to order results by.', 'data-machine'),
                'options' => [
                    'date' => __('Date', 'data-machine'),
                    'modified' => __('Modified Date', 'data-machine'),
                    'title' => __('Title', 'data-machine'),
                    'ID' => __('ID', 'data-machine'),
                ],
            ],
            'rest_order' => [
                'type' => 'select',
                'label' => __('Order', 'data-machine'),
                'description' => __('Select the order direction.', 'data-machine'),
                'options' => [
                    'DESC' => __('Descending', 'data-machine'),
                    'ASC' => __('Ascending', 'data-machine'),
                ],
            ],
        ];
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
                'options' => [
                    'all_time' => __('All Time', 'data-machine'),
                    '24_hours' => __('Last 24 Hours', 'data-machine'),
                    '72_hours' => __('Last 72 Hours', 'data-machine'),
                    '7_days'   => __('Last 7 Days', 'data-machine'),
                    '30_days'  => __('Last 30 Days', 'data-machine'),
                ],
            ],
            'search' => [
                'type' => 'text',
                'label' => __('Search Term Filter', 'data-machine'),
                'description' => __('Filter items using a search term.', 'data-machine'),
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
        $sanitized = [];
        
        // Source type is required
        $sanitized['source_type'] = sanitize_text_field($raw_settings['source_type'] ?? 'local');
        if (!in_array($sanitized['source_type'], ['local', 'remote_rest', 'remote_airdrop'])) {
            $sanitized['source_type'] = 'local';
        }

        // Sanitize based on source type
        switch ($sanitized['source_type']) {
            case 'local':
                $sanitized = array_merge($sanitized, self::sanitize_local_settings($raw_settings));
                break;
                
            case 'remote_rest':
                $sanitized = array_merge($sanitized, self::sanitize_remote_rest_settings($raw_settings));
                break;
                
            case 'remote_airdrop':
                $sanitized = array_merge($sanitized, self::sanitize_remote_airdrop_settings($raw_settings));
                break;
        }

        // Sanitize common fields
        $sanitized['timeframe_limit'] = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
        $sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? '');

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
            'post_status' => sanitize_text_field($raw_settings['post_status'] ?? 'publish'),
            'orderby' => sanitize_text_field($raw_settings['orderby'] ?? 'date'),
            'order' => sanitize_text_field($raw_settings['order'] ?? 'DESC'),
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
                $term_id = absint($raw_value);
                $term = get_term($term_id, $taxonomy->name);
                if (!is_wp_error($term) && $term) {
                    $sanitized[$field_key] = $term_id;
                } else {
                    // Invalid term ID - default to all
                    $sanitized[$field_key] = 0;
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize remote REST API settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     */
    private static function sanitize_remote_rest_settings(array $raw_settings): array {
        return [
            'api_endpoint_url' => esc_url_raw($raw_settings['api_endpoint_url'] ?? ''),
            'data_path' => sanitize_text_field($raw_settings['data_path'] ?? ''),
        ];
    }

    /**
     * Sanitize remote Airdrop settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     */
    private static function sanitize_remote_airdrop_settings(array $raw_settings): array {
        return [
            'location_id' => absint($raw_settings['location_id'] ?? 0),
            'rest_post_type' => sanitize_text_field($raw_settings['rest_post_type'] ?? 'post'),
            'rest_post_status' => sanitize_text_field($raw_settings['rest_post_status'] ?? 'publish'),
            'rest_orderby' => sanitize_text_field($raw_settings['rest_orderby'] ?? 'date'),
            'rest_order' => sanitize_text_field($raw_settings['rest_order'] ?? 'DESC'),
        ];
    }

    /**
     * Get default values for all settings.
     *
     * @return array Default values for all settings.
     */
    public static function get_defaults(): array {
        $defaults = [
            'source_type' => 'local',
            'post_type' => 'post',
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'timeframe_limit' => 'all_time',
            'search' => '',
        ];

        // Add dynamic taxonomy filter defaults (all set to 0 = "All")
        $taxonomy_defaults = self::get_taxonomy_filter_defaults();
        return array_merge($defaults, $taxonomy_defaults);
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
        $source_type = $current_config['source_type'] ?? 'local';
        
        // Only remote airdrop requires authentication (Remote Locations)
        return $source_type === 'remote_airdrop';
    }
}
