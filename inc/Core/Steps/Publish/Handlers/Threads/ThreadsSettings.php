<?php
/**
 * Threads Publish Handler Settings
 *
 * Defines settings fields and sanitization for Threads publish handler.
 * Part of the modular handler architecture.
 *
 * @package DataMachine
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Threads;

defined('ABSPATH') || exit;

class ThreadsSettings {


    /**
     * Get settings fields for Threads publish handler.
     *
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(): array {
        return [
            'link_handling' => [
                'type' => 'select',
                'label' => __('Source URL Handling', 'datamachine'),
                'description' => __('Choose how to handle source URLs when posting to Threads.', 'datamachine'),
                'options' => [
                    'none' => __('No URL - exclude source link entirely', 'datamachine'),
                    'append' => __('Append to post - add URL to post content', 'datamachine')
                ],
                'default' => 'append'
            ],
            'include_images' => [
                'type' => 'checkbox',
                'label' => __('Enable Image Posting', 'datamachine'),
                'description' => __('Attempt to find and include an image from the source data (if available).', 'datamachine'),
            ]
        ];
    }

    /**
     * Sanitize Threads handler settings.
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
