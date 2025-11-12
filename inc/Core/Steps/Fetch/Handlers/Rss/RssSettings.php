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
                'label' => __('RSS Feed URL', 'datamachine'),
                'description' => __('Enter the full URL of the RSS or Atom feed (e.g., https://example.com/feed).', 'datamachine'),
                'required' => true,
            ],
            'timeframe_limit' => [
                'type' => 'select',
                'label' => __('Process Items Within', 'datamachine'),
                'description' => __('Only consider RSS items published within this timeframe.', 'datamachine'),
                'options' => apply_filters('datamachine_timeframe_limit', [], null),
            ],
            'search' => [
                'type' => 'text',
                'label' => __('Search Term Filter', 'datamachine'),
                'description' => __('Filter items by keywords (comma-separated). Items containing any keyword in their title or content will be included.', 'datamachine'),
                'placeholder' => __('Optional search term', 'datamachine'),
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
            throw new \InvalidArgumentException(esc_html__('RSS Feed URL is required.', 'datamachine'));
        }
        $sanitized['feed_url'] = $feed_url;
        
        
        // Timeframe limit
        $sanitized['timeframe_limit'] = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
        
        // Search terms
        $sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? '');
        
        
        return $sanitized;
    }

}
