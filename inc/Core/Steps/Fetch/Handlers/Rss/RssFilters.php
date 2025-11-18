<?php
/**
 * @package DataMachine\Core\Steps\Fetch\Handlers\Rss
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Rss;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register RSS feed fetch handler filters.
 *
 * Registers RSS as a fetch handler for retrieving content from RSS/Atom feeds.
 * Includes handler metadata and configuration options.
 *
 * @since 0.1.0
 */
function datamachine_register_rss_fetch_filters() {
    add_filter('datamachine_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $handlers['rss'] = [
                'type' => 'fetch',
                'class' => Rss::class,
                'label' => __('RSS', 'datamachine'),
                'description' => __('Monitor and process RSS feeds', 'datamachine')
            ];
        }
        return $handlers;
    }, 10, 2);

    add_filter('datamachine_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'rss') {
            $all_settings['rss'] = new RssSettings();
        }
        return $all_settings;
    }, 10, 2);
}

datamachine_register_rss_fetch_filters();