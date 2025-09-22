<?php
/**
 * RSS Fetch Handler Settings
 *
 * Defines settings fields and sanitization for RSS fetch handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\Rss
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Rss;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class RssSettings {

    public function __construct() {
    }

    /**
     * Get settings fields for RSS fetch handler.
     *
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(): array {
        return [
            'feed_url' => [
                'type' => 'url',
                'label' => __('RSS Feed URL', 'data-machine'),
                'description' => __('Enter the full URL of the RSS or Atom feed (e.g., https://example.com/feed).', 'data-machine'),
                'required' => true,
            ],
            'timeframe_limit' => [
                'type' => 'select',
                'label' => __('Process Items Within', 'data-machine'),
                'description' => __('Only consider RSS items published within this timeframe.', 'data-machine'),
                'options' => apply_filters('dm_timeframe_limit', [], null),
            ],
            'search' => [
                'type' => 'text',
                'label' => __('Search Term Filter', 'data-machine'),
                'description' => __('Filter items by searching title and content for this term.', 'data-machine'),
                'placeholder' => __('Optional search term', 'data-machine'),
            ],
        ];
    }

    /**
     * Sanitize RSS fetch handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [];
        
        // Feed URL is required
        $feed_url = esc_url_raw($raw_settings['feed_url'] ?? '');
        if (empty($feed_url)) {
            throw new \InvalidArgumentException(esc_html__('RSS Feed URL is required.', 'data-machine'));
        }
        $sanitized['feed_url'] = $feed_url;
        
        
        // Timeframe limit
        $sanitized['timeframe_limit'] = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
        
        // Search terms
        $sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? '');
        
        
        return $sanitized;
    }

}
