<?php
/**
 * @package DataMachine\Core\Steps\Fetch\Handlers\Reddit
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Reddit;

if (!defined('ABSPATH')) {
    exit;
}

function datamachine_register_reddit_fetch_filters() {
    add_filter('datamachine_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $handlers['reddit'] = [
                'type' => 'fetch',
                'class' => Reddit::class,
                'label' => __('Reddit', 'data-machine'),
                'description' => __('Fetch posts from subreddits via Reddit API', 'data-machine'),
                'requires_auth' => true
            ];
        }
        return $handlers;
    }, 10, 2);

    add_filter('datamachine_auth_providers', function($providers, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $providers['reddit'] = new RedditAuth();
        }
        return $providers;
    }, 10, 2);

    add_filter('datamachine_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'reddit') {
            $all_settings['reddit'] = new RedditSettings();
        }
        return $all_settings;
    }, 10, 2);

    add_filter('datamachine_get_handler_settings_display', function($settings_display, $flow_step_id, $step_type) {
        // Get flow step config to identify handler
        $flow_step_config = apply_filters('datamachine_get_flow_step_config', [], $flow_step_id);
        $handler_slug = $flow_step_config['handler_slug'] ?? '';

        if ($handler_slug !== 'reddit') {
            return $settings_display;
        }

        $customized_display = [];

        foreach ($settings_display as $setting) {
            $setting_key = $setting['key'] ?? '';
            $current_value = $setting['value'] ?? '';

            // Reddit subreddit display formatting
            if ($setting_key === 'subreddit') {
                $customized_display[] = [
                    'key' => $setting_key,
                    'label' => '', // Remove label for clean display
                    'value' => $current_value,
                    'display_value' => 'r/' . $current_value // Add r/ prefix
                ];
                continue;
            }

            // Keep all other settings unchanged
            $customized_display[] = $setting;
        }

        return $customized_display;
    }, 15, 3);
}

datamachine_register_reddit_fetch_filters();