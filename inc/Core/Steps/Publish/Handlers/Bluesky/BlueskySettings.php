<?php
/**
 * Bluesky Publish Handler Settings
 *
 * Defines settings fields and sanitization for Bluesky publish handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Publish\Handlers\Bluesky
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Bluesky;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class BlueskySettings {

    /**
     * Get settings fields for Bluesky publish handler.
     *
    * @return array Associative array defining the settings fields.
    */
    public static function get_fields(): array {
        return [
            'link_handling' => [
                'type' => 'select',
                'label' => __('Source URL Handling', 'datamachine'),
                'description' => __('Choose how to handle source URLs when posting to Bluesky.', 'datamachine'),
                'options' => [
                    'none' => __('No URL - exclude source link entirely', 'datamachine'),
                    'append' => __('Append to post - add URL to post content', 'datamachine')
                ],
                'default' => 'append'
            ],
            'include_images' => [
                'type' => 'checkbox',
                'label' => __('Enable Image Posting', 'datamachine'),
                'description' => __('Attempt to find and upload an image from the source data (if available). Images must be under 1MB.', 'datamachine'),
                'default' => false
            ]
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
        $sanitized['link_handling'] = in_array($raw_settings['link_handling'] ?? 'append', ['none', 'append']) 
            ? $raw_settings['link_handling'] 
            : 'append';
        $sanitized['include_images'] = isset($raw_settings['include_images']) && $raw_settings['include_images'] == '1';
        return $sanitized;
    }

}