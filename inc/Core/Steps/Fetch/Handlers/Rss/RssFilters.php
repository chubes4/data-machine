<?php
/**
 * @package DataMachine\Core\Steps\Fetch\Handlers\Rss
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Rss;

use DataMachine\Core\Steps\HandlerRegistrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * RSS handler registration and configuration.
 *
 * Uses HandlerRegistrationTrait to provide standardized handler registration
 * for retrieving content from RSS/Atom feeds.
 *
 * @since 0.2.2
 */
class RssFilters {
    use HandlerRegistrationTrait;

    /**
     * Register RSS fetch handler with all required filters.
     */
    public static function register(): void {
        self::registerHandler(
            'rss',
            'fetch',
            Rss::class,
            __('RSS', 'datamachine'),
            __('Monitor and process RSS feeds', 'datamachine'),
            false,
            null,
            RssSettings::class,
            null
        );
    }
}

/**
 * Register RSS feed fetch handler filters.
 *
 * @since 0.1.0
 */
function datamachine_register_rss_fetch_filters() {
    RssFilters::register();
}

datamachine_register_rss_fetch_filters();