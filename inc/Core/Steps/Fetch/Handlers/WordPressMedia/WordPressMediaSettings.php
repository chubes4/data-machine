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
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        $fields = [
            'file_types' => [
                'type' => 'multiselect',
                'label' => __('File Types', 'data-machine'),
                'description' => __('Select which types of media files to fetch.', 'data-machine'),
                'options' => [
                    'image' => __('Images', 'data-machine'),
                    'video' => __('Videos', 'data-machine'),
                    'audio' => __('Audio', 'data-machine'),
                    'document' => __('Documents', 'data-machine'),
                ],
            ],
            'include_parent_content' => [
                'type' => 'checkbox',
                'label' => __('Include parent post content', 'data-machine'),
                'description' => __('Include the content of the post/page this media is attached to.', 'data-machine'),
            ],
            'timeframe_limit' => [
                'type' => 'select',
                'label' => __('Process Items Within', 'data-machine'),
                'description' => __('Only consider items uploaded within this timeframe.', 'data-machine'),
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
                'description' => __('Filter media by title or description.', 'data-machine'),
            ],
            'randomize_selection' => [
                'type' => 'checkbox',
                'label' => __('Randomize selection', 'data-machine'),
                'description' => __('Select a random media file instead of most recently uploaded.', 'data-machine'),
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
            'file_types' => self::sanitize_file_types($raw_settings['file_types'] ?? []),
            'include_parent_content' => !empty($raw_settings['include_parent_content']),
            'timeframe_limit' => sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time'),
            'search' => sanitize_text_field($raw_settings['search'] ?? ''),
            'randomize_selection' => !empty($raw_settings['randomize_selection']),
        ];

        return $sanitized;
    }

    /**
     * Sanitize file types selection.
     *
     * @param array $file_types Raw file types array.
     * @return array Sanitized file types.
     */
    private static function sanitize_file_types(array $file_types): array {
        $valid_types = ['image', 'video', 'audio', 'document'];
        $sanitized = [];
        
        foreach ($file_types as $type) {
            $clean_type = sanitize_text_field($type);
            if (in_array($clean_type, $valid_types)) {
                $sanitized[] = $clean_type;
            }
        }
        
        // Default to images if no valid types selected
        return empty($sanitized) ? ['image'] : array_unique($sanitized);
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