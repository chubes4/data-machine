<?php
/**
 * Twitter Publish Handler Settings
 *
 * Defines settings fields and sanitization for Twitter publish handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Publish\Handlers\Twitter
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Twitter;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class TwitterSettings {

    public function __construct() {
    }

    /**
     * Get settings fields for Twitter publish handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        return [
            'link_handling' => [
                'type' => 'select',
                'label' => __('Source URL Handling', 'data-machine'),
                'description' => __('Choose how to handle source URLs when posting to Twitter.', 'data-machine'),
                'options' => [
                    'none' => __('No URL - exclude source link entirely', 'data-machine'),
                    'append' => __('Append to tweet - add URL to tweet content (if it fits in 280 chars)', 'data-machine'),
                    'reply' => __('Post as reply - create separate reply tweet with URL', 'data-machine')
                ],
                'default' => 'append'
            ],
            'include_images' => [
                'type' => 'checkbox',
                'label' => __('Enable Image Posting', 'data-machine'),
                'description' => __('Upload and attach images to tweets when available in the data.', 'data-machine'),
            ]
        ];
    }

    /**
     * Sanitize Twitter handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [];
        $sanitized['link_handling'] = in_array($raw_settings['link_handling'] ?? 'append', ['none', 'append', 'reply']) 
            ? $raw_settings['link_handling'] 
            : 'append';
        $sanitized['include_images'] = isset($raw_settings['include_images']) && $raw_settings['include_images'] == '1';
        return $sanitized;
    }

}