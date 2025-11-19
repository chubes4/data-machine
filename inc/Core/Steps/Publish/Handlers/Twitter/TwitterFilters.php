<?php
/**
 * @package DataMachine\Core\Steps\Publish\Handlers\Twitter
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Twitter;

use DataMachine\Core\Steps\HandlerRegistrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Twitter handler registration and configuration.
 *
 * Uses HandlerRegistration to provide standardized handler registration
 * with OAuth 1.0a authentication support and AI tool integration.
 *
 * @since 0.2.2
 */
class TwitterFilters {
    use HandlerRegistrationTrait;

    /**
     * Register Twitter publishing handler with all required filters.
     */
    public static function register(): void {
        self::registerHandler(
            'twitter',
            'publish',
            Twitter::class,
            __('Twitter', 'datamachine'),
            __('Post content to Twitter with media support', 'datamachine'),
            true,
            TwitterAuth::class,
            TwitterSettings::class,
            function($tools, $handler_slug, $handler_config) {
                if ($handler_slug === 'twitter') {
                    $tools['twitter_publish'] = datamachine_get_twitter_tool($handler_config);
                }
                return $tools;
            }
        );
    }
}

/**
 * Register Twitter publishing handler and authentication filters.
 *
 * @since 0.1.0
 */
function datamachine_register_twitter_filters() {
    TwitterFilters::register();
}

function datamachine_get_twitter_tool(array $handler_config = []): array {
    $tool = [
        'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\Twitter\\Twitter',
        'method' => 'handle_tool_call',
        'handler' => 'twitter',
        'description' => 'Post content to Twitter',
        'parameters' => [
            'content' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Tweet content (will be formatted and truncated if needed). Do not include URLs - they are handled automatically.'
            ]
        ]
    ];

    if (!empty($handler_config)) {
        $tool['handler_config'] = $handler_config;
    }

    return $tool;
}

function datamachine_register_twitter_success_message() {
    add_filter('datamachine_tool_success_message', function($default_message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name === 'twitter_publish' && !empty($tool_result['data']['tweet_url'])) {
            return "Tweet published successfully to Twitter at {$tool_result['data']['tweet_url']}.";
        }
        return $default_message;
    }, 10, 4);
}

datamachine_register_twitter_filters();
datamachine_register_twitter_success_message();