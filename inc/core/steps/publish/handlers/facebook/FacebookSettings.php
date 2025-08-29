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
            'include_images' => [
                'type' => 'checkbox',
                'label' => __('Include Images', 'data-machine'),
                'description' => __('Attach images from the original content when available.', 'data-machine'),
            ],
            'link_handling' => [
                'type' => 'select',
                'label' => __('Link Handling', 'data-machine'),
                'description' => __('How to handle links in posts.', 'data-machine'),
                'options' => [
                    'append' => __('Include in post content', 'data-machine'),
                    'comment' => __('Post as comment', 'data-machine'),
                    'none' => __('No links', 'data-machine')
                ],
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
        $sanitized['include_images'] = isset($raw_settings['include_images']) && $raw_settings['include_images'] == '1';
        $link_handling = $raw_settings['link_handling'] ?? 'append';
        if (!in_array($link_handling, ['append', 'comment', 'none'])) {
            do_action('dm_log', 'error', 'Facebook Settings: Invalid link_handling parameter provided', [
                'provided_value' => $link_handling,
                'valid_options' => ['append', 'comment', 'none']
            ]);
            $link_handling = 'append'; // Fall back to default
        }
        $sanitized['link_handling'] = $link_handling;
        return $sanitized;
    }

}
