<?php
/**
 * Centralized cross-cutting filters for handler capabilities.
 *
 * @package DataMachine\Engine\Filters
 */

if (!defined('WPINC')) {
    die;
}

function dm_register_handler_filters() {
    add_filter('dm_handlers', function($handlers) {
        return $handlers;
    }, 5, 1);

    add_filter('dm_handler_settings', function($all_settings) {
        return $all_settings;
    }, 5, 1);

    // Timeframe parsing for fetch handlers: returns cutoff timestamp, options array, or null
    add_filter('dm_timeframe_limit', function($default, $timeframe_limit) {
        // Discovery mode: return available timeframe options for dropdowns
        if ($timeframe_limit === null) {
            return [
                'all_time' => __('All Time', 'data-machine'),
                '24_hours' => __('Last 24 Hours', 'data-machine'),
                '72_hours' => __('Last 72 Hours', 'data-machine'),
                '7_days'   => __('Last 7 Days', 'data-machine'),
                '30_days'  => __('Last 30 Days', 'data-machine'),
            ];
        }

        // Conversion mode: return timestamp or null
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

    // Keyword search matching for all fetch handlers: parses comma-separated keywords with OR logic
    add_filter('dm_keyword_search_match', function($default, $content, $search_term) {
        if (empty($search_term)) {
            return true; // No filter = match all
        }

        // Parse comma-separated keywords (or single term)
        $keywords = array_map('trim', explode(',', $search_term));
        $keywords = array_filter($keywords);

        if (empty($keywords)) {
            return true;
        }

        // OR logic - any keyword match passes
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
