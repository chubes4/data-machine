<?php
/**
 * Facebook Output Handler Settings Module
 *
 * Defines settings fields and sanitization for Facebook output handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/handlers/output/facebook
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Output\Facebook;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class FacebookSettings {

    /**
     * Constructor.
     * Pure filter-based architecture - no dependencies.
     */
    public function __construct() {
        // No constructor dependencies - all services accessed via filters
    }

    /**
     * Get settings fields for Facebook output handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        return [
            'facebook_target_id' => [
                'type' => 'text',
                'label' => __('Target Page/Group/User ID', 'data-machine'),
                'description' => __('Enter the Facebook Page ID, Group ID, or leave empty/use "me" to post to the authenticated user\'s feed.', 'data-machine'),
                'default' => 'me',
            ],
            'include_images' => [
                'type' => 'checkbox',
                'label' => __('Include Images', 'data-machine'),
                'description' => __('Attach images from the original content when available.', 'data-machine'),
                'default' => false
            ],
            'include_videos' => [
                'type' => 'checkbox',
                'label' => __('Include Video Links', 'data-machine'),
                'description' => __('Include video links in the post content.', 'data-machine'),
                'default' => false
            ],
            'link_handling' => [
                'type' => 'select',
                'label' => __('Link Handling', 'data-machine'),
                'description' => __('How to handle links in posts.', 'data-machine'),
                'options' => [
                    'append' => __('Append to content', 'data-machine'),
                    'replace' => __('Replace content', 'data-machine'),
                    'none' => __('No links', 'data-machine')
                ],
                'default' => 'append'
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
        $sanitized['facebook_target_id'] = sanitize_text_field($raw_settings['facebook_target_id'] ?? 'me');
        $sanitized['include_images'] = isset($raw_settings['include_images']) && $raw_settings['include_images'] == '1';
        $sanitized['include_videos'] = isset($raw_settings['include_videos']) && $raw_settings['include_videos'] == '1';
        $sanitized['link_handling'] = in_array($raw_settings['link_handling'] ?? 'append', ['append', 'replace', 'none']) 
            ? $raw_settings['link_handling'] : 'append';
        return $sanitized;
    }

    /**
     * Get default values for all settings.
     *
     * @return array Default values for all settings.
     */
    public static function get_defaults(): array {
        return [
            'facebook_target_id' => 'me',
            'include_images' => false,
            'include_videos' => false,
            'link_handling' => 'append',
        ];
    }
}
