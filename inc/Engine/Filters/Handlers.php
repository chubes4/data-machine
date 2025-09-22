<?php
/**
 * Handler-Centric Cross-Cutting Filters
 *
 * Centralizes filter hooks used by multiple handlers to keep per-handler
 * files focused on registration and handler-specific behavior.
 *
 * Examples:
 * - dm_timeframe_limit: Shared timeframe parsing for fetch handlers
 *
 * @package DataMachine\Engine\Filters
 */

if (!defined('WPINC')) {
    die;
}

function dm_register_handler_filters() {
    // Base aggregator for handler discovery; handlers append their own entries
    add_filter('dm_handlers', function($handlers) {
        return $handlers;
    }, 5, 1);

    // Base aggregator for handler settings; handlers append their own entries
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

}

dm_register_handler_filters();
