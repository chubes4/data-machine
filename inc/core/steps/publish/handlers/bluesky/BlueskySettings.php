<?php
/**
 * Bluesky Publish Handler Settings
 *
 * Defines settings fields and sanitization for Bluesky publish handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/handlers/publish/bluesky
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Bluesky;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class BlueskySettings {

    /**
     * Constructor.
     * Pure filter-based architecture - no dependencies.
     */
    public function __construct() {
        // No constructor dependencies - all services accessed via filters
    }

    /**
     * Get settings fields for Bluesky publish handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        return [
            'bluesky_include_source' => [
                'type' => 'checkbox',
                'label' => __('Include Source Link', 'data-machine'),
                'description' => __('Append the original source URL to the post (if available and fits within the 300 character limit).', 'data-machine'),
            ],
            'bluesky_enable_images' => [
                'type' => 'checkbox',
                'label' => __('Enable Image Posting', 'data-machine'),
                'description' => __('Attempt to find and upload an image from the source data (if available). Images must be under 1MB.', 'data-machine'),
            ],
        ];
    }

    /**
     * Sanitize Bluesky handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [];
        $sanitized['bluesky_include_source'] = isset($raw_settings['bluesky_include_source']) && $raw_settings['bluesky_include_source'] == '1';
        $sanitized['bluesky_enable_images'] = isset($raw_settings['bluesky_enable_images']) && $raw_settings['bluesky_enable_images'] == '1';
        return $sanitized;
    }

    /**
     * Get default values for all settings.
     *
     * @return array Default values for all settings.
     */
    public static function get_defaults(): array {
        return [
            'bluesky_include_source' => true,
            'bluesky_enable_images' => true,
        ];
    }
}