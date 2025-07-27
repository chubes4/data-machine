<?php
/**
 * RSS Input Handler Settings Module
 *
 * Defines settings fields and sanitization for RSS input handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/handlers/input/rss
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Input\Rss;

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
     * Get settings fields for RSS input handler.
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
                'default' => '',
            ],
            'item_count' => [
                'type' => 'number',
                'label' => __('Items to Process', 'data-machine'),
                'description' => __('Maximum number of *new* RSS items to process per run.', 'data-machine'),
                'default' => 1,
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
                'default' => 'all_time',
            ],
            'search' => [
                'type' => 'text',
                'label' => __('Search Term Filter', 'data-machine'),
                'description' => __('Optional: Filter RSS items by keywords (comma-separated). Only items containing at least one keyword in their title or content will be processed.', 'data-machine'),
                'default' => '',
            ],
            'rss_refresh_interval' => [
                'type' => 'select',
                'label' => __('Feed Refresh Interval', 'data-machine'),
                'description' => __('How often to check the RSS feed for new items.', 'data-machine'),
                'options' => [
                    '15_minutes' => __('Every 15 Minutes', 'data-machine'),
                    '30_minutes' => __('Every 30 Minutes', 'data-machine'),
                    '1_hour' => __('Every Hour', 'data-machine'),
                    '6_hours' => __('Every 6 Hours', 'data-machine'),
                    '12_hours' => __('Every 12 Hours', 'data-machine'),
                    '24_hours' => __('Daily', 'data-machine'),
                ],
                'default' => '1_hour',
            ],
        ];
    }

    /**
     * Sanitize RSS input handler settings.
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
        $sanitized['timeframe_limit'] = in_array($timeframe, $valid_timeframes) ? $timeframe : 'all_time';
        
        // Search terms
        $sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? '');
        
        // Refresh interval
        $valid_intervals = ['15_minutes', '30_minutes', '1_hour', '6_hours', '12_hours', '24_hours'];
        $interval = sanitize_text_field($raw_settings['rss_refresh_interval'] ?? '1_hour');
        $sanitized['rss_refresh_interval'] = in_array($interval, $valid_intervals) ? $interval : '1_hour';
        
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
            'rss_refresh_interval' => '1_hour',
        ];
    }
}
