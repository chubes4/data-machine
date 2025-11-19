<?php
/**
 * @package DataMachine\Core\Steps\Fetch\Handlers\Reddit
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Reddit;

use DataMachine\Core\Steps\HandlerRegistrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reddit handler registration and configuration.
 *
 * Uses HandlerRegistrationTrait to provide standardized handler registration
 * with OAuth 2.0 authentication support and AI tool integration.
 * Preserves custom settings display formatting for subreddit display.
 *
 * @since 0.2.2
 */
class RedditFilters {
    use HandlerRegistrationTrait;

    /**
     * Register Reddit fetch handler with all required filters.
     */
    public static function register(): void {
        self::registerHandler(
            'reddit',
            'fetch',
            Reddit::class,
            __('Reddit', 'datamachine'),
            __('Fetch posts from subreddits via Reddit API', 'datamachine'),
            true,
            RedditAuth::class,
            RedditSettings::class,
            null
        );

        // Custom settings display formatting (preserved)
        add_filter('datamachine_get_handler_settings_display', function($settings_display, $flow_step_id, $step_type) {
            // Get flow step config to identify handler
            $db_flows = new \DataMachine\Core\Database\Flows\Flows();
            $flow_step_config = $db_flows->get_flow_step_config( $flow_step_id );
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
}

/**
 * Register Reddit fetch handler filters.
 *
 * @since 0.1.0
 */
function datamachine_register_reddit_fetch_filters() {
    RedditFilters::register();
}

datamachine_register_reddit_fetch_filters();