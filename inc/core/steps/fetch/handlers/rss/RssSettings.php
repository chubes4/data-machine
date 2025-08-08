<?php
/**
 * RSS Fetch Handler Settings
 *
 * Defines settings fields and sanitization for RSS fetch handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/handlers/fetch/rss
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Fetch\Rss;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class RssSettings {

    /**
     * Constructor.
     * Pure filter-based architecture - no dependencies.
     */
    public function __construct() {
        // No constructor dependencies - all services accessed via filters
    }

    /**
     * Get settings fields for RSS fetch handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        return [
            'feed_url' => [
                'type' => 'url',
                'label' => __('RSS Feed URL', 'data-machine'),
                'description' => __('Enter the full URL of the RSS or Atom feed (e.g., https://example.com/feed).', 'data-machine'),
                'required' => true,
            ],
            'item_count' => [
                'type' => 'number',
                'label' => __('Items to Process', 'data-machine'),
                'description' => __('Maximum number of *new* RSS items to process per run.', 'data-machine'),
                'min' => 1,
                'max' => 50,
            ],
            'timeframe_limit' => [
                'type' => 'select',
                'label' => __('Process Items Within', 'data-machine'),
                'description' => __('Only consider RSS items published within this timeframe.', 'data-machine'),
                'options' => [
                    'all_time' => __('All Time', 'data-machine'),
                    '24_hours' => __('Last 24 Hours', 'data-machine'),
                    '72_hours' => __('Last 72 Hours', 'data-machine'),
                    '7_days'   => __('Last 7 Days', 'data-machine'),
                    '30_days'  => __('Last 30 Days', 'data-machine'),
                ],
            ],
            'search' => [
                'type' => 'text',
                'label' => __('Search Term Filter', 'data-machine'),
                'description' => __('Filter RSS items by keywords (comma-separated). Only items containing at least one keyword in their title or content will be processed.', 'data-machine'),
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
        
        // Item count
        $sanitized['item_count'] = max(1, min(50, absint($raw_settings['item_count'] ?? 1)));
        
        // Timeframe limit
        $valid_timeframes = ['all_time', '24_hours', '72_hours', '7_days', '30_days'];
        $timeframe = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
        if (!in_array($timeframe, $valid_timeframes)) {
            throw new Exception(esc_html__('Invalid timeframe parameter provided in settings.', 'data-machine'));
        }
        $sanitized['timeframe_limit'] = $timeframe;
        
        // Search terms
        $sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? '');
        
        
        return $sanitized;
    }

    /**
     * Get default values for all settings.
     *
     * @return array Default values for all settings.
     */
    public static function get_defaults(): array {
        return [
            'feed_url' => '',
            'item_count' => 1,
            'timeframe_limit' => 'all_time',
            'search' => '',
        ];
    }
}
