<?php
/**
 * Facebook Publish Handler Settings
 *
 * Defines settings fields and sanitization for Facebook publish handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Publish\Handlers\Facebook
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Facebook;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class FacebookSettings {

    public function __construct() {
    }

    /**
     * Get settings fields for Facebook publish handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        return [
            'link_handling' => [
                'type' => 'select',
                'label' => __('Source URL Handling', 'data-machine'),
                'description' => __('Choose how to handle source URLs when posting to Facebook.', 'data-machine'),
                'options' => [
                    'none' => __('No URL - exclude source link entirely', 'data-machine'),
                    'append' => __('Append to post - add URL to post content', 'data-machine'),
                    'comment' => __('Post as comment - add URL as separate comment', 'data-machine')
                ],
                'default' => 'append'
            ],
            'include_images' => [
                'type' => 'checkbox',
                'label' => __('Enable Image Posting', 'data-machine'),
                'description' => __('Upload and attach images to posts when available in the data.', 'data-machine'),
            ]
        ];
    }

    /**
     * Sanitize Facebook handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [];
        $sanitized['link_handling'] = in_array($raw_settings['link_handling'] ?? 'append', ['none', 'append', 'comment']) 
            ? $raw_settings['link_handling'] 
            : 'append';
        $sanitized['include_images'] = isset($raw_settings['include_images']) && $raw_settings['include_images'] == '1';
        return $sanitized;
    }

}
