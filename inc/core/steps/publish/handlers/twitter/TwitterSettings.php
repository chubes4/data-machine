<?php
/**
 * Twitter Publish Handler Settings
 *
 * Defines settings fields and sanitization for Twitter publish handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/handlers/publish/twitter
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Publish\Twitter;

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
     * Get settings fields for Twitter publish handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        return [
            'twitter_include_source' => [
                'type' => 'checkbox',
                'label' => __('Include Source Link', 'data-machine'),
                'description' => __('Append the original source URL to the tweet. AI will have access to source_url parameter.', 'data-machine'),
            ],
            'twitter_enable_images' => [
                'type' => 'checkbox',
                'label' => __('Enable Image Posting', 'data-machine'),
                'description' => __('Enable image upload capability. AI will have access to image_url parameter.', 'data-machine'),
            ],
            'twitter_url_as_reply' => [
                'type' => 'checkbox',
                'label' => __('Post URLs as Reply Tweets', 'data-machine'),
                'description' => __('When enabled, source URLs will be posted as separate reply tweets instead of being included in the main tweet. WARNING: This uses additional API calls and counts toward your rate limit.', 'data-machine'),
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
        $sanitized['twitter_include_source'] = isset($raw_settings['twitter_include_source']) && $raw_settings['twitter_include_source'] == '1';
        $sanitized['twitter_enable_images'] = isset($raw_settings['twitter_enable_images']) && $raw_settings['twitter_enable_images'] == '1';
        $sanitized['twitter_url_as_reply'] = isset($raw_settings['twitter_url_as_reply']) && $raw_settings['twitter_url_as_reply'] == '1';
        return $sanitized;
    }

    /**
     * Get default values for all settings.
     *
     * @return array Default values for all settings.
     */
    public static function get_defaults(): array {
        return [
            'twitter_include_source' => true,
            'twitter_enable_images' => true,
            'twitter_url_as_reply' => false,
        ];
    }
}