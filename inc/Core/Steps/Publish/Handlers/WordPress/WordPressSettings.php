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
use DataMachine\Core\WordPress\WordPressSettingsHandler;

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
        // Get post type options
        $post_type_options = WordPressSettingsHandler::get_post_type_options(false);

        // Get dynamic taxonomy fields
        $taxonomy_fields = WordPressSettingsHandler::get_taxonomy_fields([
            'field_suffix' => '_selection',
            'first_options' => [
                'skip' => __('Skip', 'datamachine'),
                'ai_decides' => __('AI Decides', 'datamachine')
            ],
            'description_template' => __('Configure %1$s assignment: Skip to exclude from AI instructions, let AI choose, or select specific %2$s.', 'datamachine')
        ]);

        // Get user options
        $user_options = WordPressSettingsHandler::get_user_options();

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
        $sanitized = array_merge($sanitized, WordPressSettingsHandler::sanitize_taxonomy_fields($raw_settings, [
            'field_suffix' => '_selection',
            'allowed_values' => ['skip', 'ai_decides'],
            'default_value' => 'skip'
        ]));

        return $sanitized;
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
