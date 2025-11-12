<?php
/**
 * WordPress Media Fetch Handler Settings
 *
 * Defines settings fields and sanitization for WordPress media fetch handler.
 * Part of the modular handler architecture.
 *
 * @package    DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\WordPressMedia
 * @since      1.0.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPressMedia;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WordPressMediaSettings {

    public function __construct() {
    }

    /**
     * Get settings fields for WordPress media fetch handler.
     *
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(): array {
        $fields = [
            'include_parent_content' => [
                'type' => 'checkbox',
                'label' => __('Include parent post content', 'datamachine'),
                'description' => __('Include the content of the post/page this media is attached to.', 'datamachine'),
            ],
            'timeframe_limit' => [
                'type' => 'select',
                'label' => __('Process Items Within', 'datamachine'),
                'description' => __('Only consider items uploaded within this timeframe.', 'datamachine'),
                'options' => apply_filters('datamachine_timeframe_limit', [], null),
            ],
            'search' => [
                'type' => 'text',
                'label' => __('Search Term Filter', 'datamachine'),
                'description' => __('Filter media by keywords (comma-separated). Media containing any keyword in their title or description will be included.', 'datamachine'),
            ],
            'randomize_selection' => [
                'type' => 'checkbox',
                'label' => __('Randomize selection', 'datamachine'),
                'description' => __('Select a random media file instead of most recently uploaded.', 'datamachine'),
            ],
        ];

        return $fields;
    }

    /**
     * Sanitize WordPress media fetch handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [
            'include_parent_content' => !empty($raw_settings['include_parent_content']),
            'timeframe_limit' => sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time'),
            'search' => sanitize_text_field($raw_settings['search'] ?? ''),
            'randomize_selection' => !empty($raw_settings['randomize_selection']),
        ];

        return $sanitized;
    }

    /**
     * Determine if authentication is required based on current configuration.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return bool True if authentication is required, false otherwise.
     */
    public static function requires_authentication(array $current_config = []): bool {
        // Local WordPress media does not require authentication
        return false;
    }
}