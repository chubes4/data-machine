<?php
/**
 * Twitter Output Handler Settings Module
 *
 * Defines settings fields and sanitization for Twitter output handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/handlers/output/twitter
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Output\Twitter;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class TwitterSettings {

    /**
     * Constructor.
     * Pure filter-based architecture - no dependencies.
     */
    public function __construct() {
        // No constructor dependencies - all services accessed via filters
    }

    /**
     * Get settings fields for Twitter output handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        return [
            'twitter_char_limit' => [
                'type' => 'number',
                'label' => __('Character Limit Override', 'data-machine'),
                'description' => __('Set a custom character limit for tweets. Text will be truncated if necessary.', 'data-machine'),
                'min' => 50,
                'max' => 280, // Twitter's standard limit
            ],
            'twitter_include_source' => [
                'type' => 'checkbox',
                'label' => __('Include Source Link', 'data-machine'),
                'description' => __('Append the original source URL to the tweet (if available and fits within character limits).', 'data-machine'),
            ],
            'twitter_enable_images' => [
                'type' => 'checkbox',
                'label' => __('Enable Image Posting', 'data-machine'),
                'description' => __('Attempt to find and upload an image from the source data (if available).', 'data-machine'),
            ],
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
        $sanitized['twitter_char_limit'] = min(280, max(50, absint($raw_settings['twitter_char_limit'] ?? 280)));
        $sanitized['twitter_include_source'] = isset($raw_settings['twitter_include_source']) && $raw_settings['twitter_include_source'] == '1';
        $sanitized['twitter_enable_images'] = isset($raw_settings['twitter_enable_images']) && $raw_settings['twitter_enable_images'] == '1';
        return $sanitized;
    }

    /**
     * Get default values for all settings.
     *
     * @return array Default values for all settings.
     */
    public static function get_defaults(): array {
        return [
            'twitter_char_limit' => 280,
            'twitter_include_source' => true,
            'twitter_enable_images' => true,
        ];
    }
}

// Self-register via parameter-based settings system
add_filter('dm_get_handler_settings', function($settings, $handler_key) {
    if ($handler_key === 'twitter') {
        return new \DataMachine\Core\Handlers\Output\Twitter\TwitterSettings();
    }
    return $settings;
}, 10, 2);