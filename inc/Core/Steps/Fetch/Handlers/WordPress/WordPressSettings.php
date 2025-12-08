<?php
/**
 * WordPress Fetch Handler Settings
 *
 * Defines settings fields and sanitization for WordPress fetch handler.
 * Part of the modular handler architecture.
 *
 * @package    DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\WordPress
 * @since      0.1.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPress;

use DataMachine\Core\Steps\Fetch\Handlers\FetchHandlerSettings;
use DataMachine\Core\WordPress\WordPressSettingsHandler;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WordPressSettings extends FetchHandlerSettings {

    /**
     * Get settings fields for WordPress fetch handler.
     *
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(): array {
        // WordPress fetch settings for local WordPress installation only
        $fields = self::get_local_fields();

        // Add common fetch handler fields (timeframe_limit, search)
        $fields = array_merge($fields, parent::get_common_fields());

        // Add WordPress-specific common fields (randomize_selection)
        $fields = array_merge($fields, self::get_wordpress_common_fields());

        return $fields;
    }

    /**
     * Get settings fields specific to local WordPress.
     *
     * @return array Settings fields.
     */
    private static function get_local_fields(): array {
        // Get post type options with "Any" option
        $post_type_options = WordPressSettingsHandler::get_post_type_options(true);

        // Get dynamic taxonomy filter fields
        $taxonomy_fields = WordPressSettingsHandler::get_taxonomy_fields([
            'field_suffix' => '_filter',
            /* translators: %s: taxonomy label */
            'first_options' => [0 => esc_html__('All %s', 'datamachine')],
            /* translators: 1: taxonomy label, 2: taxonomy term label */
            'description_template' => __('Filter by specific %1$s or fetch from all %2$s.', 'datamachine')
        ]);

        $fields = [
            'source_url' => [
                'type' => 'text',
                'label' => __('Specific Post URL', 'datamachine'),
                'description' => __('Target a specific post by URL. When provided, other filters are ignored.', 'datamachine'),
                'placeholder' => __('Leave empty for general query', 'datamachine'),
            ],
            'post_type' => [
                'type' => 'select',
                'label' => __('Post Type', 'datamachine'),
                'description' => __('Select the post type to fetch from the local site.', 'datamachine'),
                'options' => $post_type_options,
            ],
            'post_status' => [
                'type' => 'select',
                'label' => __('Post Status', 'datamachine'),
                'description' => __('Select the post status to fetch.', 'datamachine'),
                'options' => [
                    'publish' => __('Published', 'datamachine'),
                    'draft' => __('Draft', 'datamachine'),
                    'pending' => __('Pending', 'datamachine'),
                    'private' => __('Private', 'datamachine'),
                    'any' => __('Any', 'datamachine'),
                ],
            ],
        ];

        // Merge in dynamic taxonomy filter fields
        return array_merge($fields, $taxonomy_fields);
    }


    /**
     * Get WordPress-specific common fields beyond base fetch fields.
     *
     * @return array Settings fields.
     */
    private static function get_wordpress_common_fields(): array {
        return [
            'randomize_selection' => [
                'type' => 'checkbox',
                'label' => __('Randomize selection', 'datamachine'),
                'description' => __('Select a random post instead of most recently modified.', 'datamachine'),
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
        $taxonomy_filters = WordPressSettingsHandler::sanitize_taxonomy_fields($raw_settings, [
            'field_suffix' => '_filter',
            'allowed_values' => [0],
            'default_value' => 0
        ]);
        return array_merge($sanitized, $taxonomy_filters);
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
