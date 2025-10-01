<?php
/**
 * Cross-cutting filters for handler capabilities.
 *
 * Provides shared functionality used across multiple handlers:
 * - Timeframe parsing (discovery and conversion modes)
 * - Keyword matching with OR logic
 * - Handler registration discovery
 *
 * @package DataMachine\Engine\Filters
 */

if (!defined('WPINC')) {
    die;
}

function dm_register_handler_filters() {
    add_filter('dm_handlers', function($handlers, $step_type = null) {
        return $handlers;
    }, 5, 2);

    add_filter('dm_handler_settings', function($all_settings, $handler_slug = null) {
        return $all_settings;
    }, 5, 2);

    add_filter('dm_auth_providers', function($providers, $step_type = null) {
        return $providers;
    }, 5, 2);

    /**
     * Dual-mode timeframe parsing: options discovery or timestamp conversion.
     *
     * @param mixed $default Default value
     * @param string|null $timeframe_limit Timeframe identifier (null for discovery mode)
     * @return array|int|null Options array, timestamp, or null
     */
    add_filter('dm_timeframe_limit', function($default, $timeframe_limit) {
        if ($timeframe_limit === null) {
            return [
                'all_time' => __('All Time', 'data-machine'),
                '24_hours' => __('Last 24 Hours', 'data-machine'),
                '72_hours' => __('Last 72 Hours', 'data-machine'),
                '7_days'   => __('Last 7 Days', 'data-machine'),
                '30_days'  => __('Last 30 Days', 'data-machine'),
            ];
        }

        if ($timeframe_limit === 'all_time') {
            return null;
        }

        $interval_map = [
            '24_hours' => '-24 hours',
            '72_hours' => '-72 hours',
            '7_days'   => '-7 days',
            '30_days'  => '-30 days'
        ];

        if (!isset($interval_map[$timeframe_limit])) {
            return null;
        }

        return strtotime($interval_map[$timeframe_limit], current_time('timestamp', true));
    }, 10, 2);

    /**
     * Keyword matching with OR logic across comma-separated terms.
     *
     * @param bool $default Default match result
     * @param string $content Content to search
     * @param string $search_term Comma-separated keywords (empty matches all)
     * @return bool Match result
     */
    add_filter('dm_keyword_search_match', function($default, $content, $search_term) {
        if (empty($search_term)) {
            return true;
        }

        $keywords = array_map('trim', explode(',', $search_term));
        $keywords = array_filter($keywords);

        if (empty($keywords)) {
            return true;
        }

        $content_lower = strtolower($content);
        foreach ($keywords as $keyword) {
            if (mb_stripos($content_lower, strtolower($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }, 10, 3);

}

dm_register_handler_filters();
