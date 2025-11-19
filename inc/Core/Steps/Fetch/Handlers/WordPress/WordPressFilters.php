<?php
/**
 * @package DataMachine\Core\Steps\Fetch\Handlers\WordPress
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPress;

use DataMachine\Core\Steps\HandlerRegistrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress fetch handler registration and configuration.
 *
 * Uses HandlerRegistrationTrait to provide standardized handler registration
 * for fetching posts and pages from this WordPress installation.
 *
 * @since 0.2.2
 */
class WordPressFilters {
    use HandlerRegistrationTrait;

    /**
     * Register WordPress fetch handler with all required filters.
     */
    public static function register(): void {
        self::registerHandler(
            'wordpress_posts',
            'fetch',
            WordPress::class,
            __('Local WordPress Posts', 'datamachine'),
            __('Fetch posts and pages from this WordPress installation', 'datamachine'),
            false,
            null,
            WordPressSettings::class,
            null
        );
    }
}

/**
 * Register WordPress fetch handler filters.
 *
 * @since 0.1.0
 */
function datamachine_register_wordpress_fetch_filters() {
    WordPressFilters::register();
}

datamachine_register_wordpress_fetch_filters();