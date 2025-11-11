<?php
/**
 * @package DataMachine\Core\Steps\Fetch\Handlers\Rss
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Rss;

if (!defined('ABSPATH')) {
    exit;
}

function datamachine_register_rss_fetch_filters() {
    add_filter('datamachine_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $handlers['rss'] = [
                'type' => 'fetch',
                'class' => Rss::class,
                'label' => __('RSS', 'data-machine'),
                'description' => __('Monitor and process RSS feeds', 'data-machine')
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