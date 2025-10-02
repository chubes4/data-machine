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
    add_filter('dm_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $handlers['reddit'] = [
                'type' => 'fetch',
                'class' => Reddit::class,
                'label' => __('Reddit', 'data-machine'),
                'description' => __('Fetch posts from subreddits via Reddit API', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);

    add_filter('dm_auth_providers', function($providers, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $providers['reddit'] = new RedditAuth();
        }
        return $providers;
    }, 10, 2);

    add_filter('dm_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'reddit') {
            $all_settings['reddit'] = new RedditSettings();
        }
        return $all_settings;
    }, 10, 2);
}

dm_register_reddit_fetch_filters();