<?php
/**
 * Reddit handler filter registration.
 *
 * @package DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\Reddit
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Reddit;

if (!defined('ABSPATH')) {
    exit;
}

function dm_register_reddit_fetch_filters() {
    add_filter('dm_handlers', function($handlers) {
        $handlers['reddit'] = [
            'type' => 'fetch',
            'class' => Reddit::class,
            'label' => __('Reddit', 'data-machine'),
            'description' => __('Fetch posts from subreddits via Reddit API', 'data-machine')
        ];
        return $handlers;
    });

    add_filter('dm_auth_providers', function($providers) {
        $providers['reddit'] = new RedditAuth();
        return $providers;
    });

    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['reddit'] = new RedditSettings();
        return $all_settings;
    });
}

dm_register_reddit_fetch_filters();