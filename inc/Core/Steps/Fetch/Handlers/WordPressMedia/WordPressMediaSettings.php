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

use DataMachine\Core\Steps\Fetch\Handlers\FetchHandlerSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WordPressMediaSettings extends FetchHandlerSettings {

    /**
     * Get settings fields for WordPress media fetch handler.
     *
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(): array {
        // Handler-specific fields
        $fields = [
            'include_parent_content' => [
                'type' => 'checkbox',
                'label' => __('Include parent post content', 'data-machine'),
                'description' => __('Include the content of the post/page this media is attached to.', 'data-machine'),
            ],
            'randomize_selection' => [
                'type' => 'checkbox',
                'label' => __('Randomize selection', 'data-machine'),
                'description' => __('Select a random media file instead of most recently uploaded.', 'data-machine'),
            ],
        ];

        // Merge with common fetch handler fields
        return array_merge($fields, parent::get_common_fields());
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