<?php
/**
 * WordPress Input Handler Settings Module
 *
 * Defines settings fields and sanitization for WordPress input handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/handlers/input/wordpress
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Input\WordPress;

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
     * Get settings fields for WordPress input handler.
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

        // Get categories
        $categories = get_categories(['hide_empty' => false]);
        $category_options = [0 => __('All Categories', 'data-machine')];
        foreach ($categories as $category) {
            $category_options[$category->term_id] = $category->name;
        }

        // Get tags
        $tags = get_tags(['hide_empty' => false]);
        $tag_options = [0 => __('All Tags', 'data-machine')];
        foreach ($tags as $tag) {
            $tag_options[$tag->term_id] = $tag->name;
        }

        return [
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
            'category_id' => [
                'type' => 'select',
                'label' => __('Category', 'data-machine'),
                'description' => __('Filter by a specific category.', 'data-machine'),
                'options' => $category_options,
            ],
            'tag_id' => [
                'type' => 'select',
                'label' => __('Tag', 'data-machine'),
                'description' => __('Filter by a specific tag.', 'data-machine'),
                'options' => $tag_options,
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
        $all_databases = apply_filters('dm_get_database_services', []);
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
            'item_count' => [
                'type' => 'number',
                'label' => __('Items to Process', 'data-machine'),
                'description' => __('Maximum number of *new* items to process per run.', 'data-machine'),
                'min' => 1,
                'max' => 100,
            ],
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
     * Sanitize WordPress input handler settings.
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
        $sanitized['item_count'] = max(1, absint($raw_settings['item_count'] ?? 1));
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
        return [
            'post_type' => sanitize_text_field($raw_settings['post_type'] ?? 'post'),
            'post_status' => sanitize_text_field($raw_settings['post_status'] ?? 'publish'),
            'category_id' => absint($raw_settings['category_id'] ?? 0),
            'tag_id' => absint($raw_settings['tag_id'] ?? 0),
            'orderby' => sanitize_text_field($raw_settings['orderby'] ?? 'date'),
            'order' => sanitize_text_field($raw_settings['order'] ?? 'DESC'),
        ];
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
        return [
            'source_type' => 'local',
            'post_type' => 'post',
            'post_status' => 'publish',
            'category_id' => 0,
            'tag_id' => 0,
            'orderby' => 'date',
            'order' => 'DESC',
            'item_count' => 1,
            'timeframe_limit' => 'all_time',
            'search' => '',
        ];
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
