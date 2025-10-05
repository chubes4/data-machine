<?php
/**
 * @package DataMachine\Core\Steps\Fetch\Handlers\WordPress
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

function dm_register_wordpress_fetch_filters() {
    add_filter('dm_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $handlers['wordpress_posts'] = [
                'type' => 'fetch',
                'class' => WordPress::class,
                'label' => __('Local WordPress Posts', 'data-machine'),
                'description' => __('Fetch posts and pages from this WordPress installation', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);

    add_filter('dm_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'wordpress_posts') {
            $all_settings['wordpress_posts'] = new WordPressSettings();
        }
        return $all_settings;
    }, 10, 2);
}

dm_register_wordpress_fetch_filters();