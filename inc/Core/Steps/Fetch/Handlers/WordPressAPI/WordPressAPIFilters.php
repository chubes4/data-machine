<?php
/**
 * WordPress REST API Fetch Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as WordPress REST API Fetch Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\WordPressAPI
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPressAPI;

use DataMachine\Core\Steps\HandlerRegistrationTrait;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress API handler registration and configuration.
 *
 * Uses HandlerRegistrationTrait to provide standardized handler registration
 * for fetching content from public WordPress sites via REST API.
 *
 * @since 0.2.2
 */
class WordPressAPIFilters {
    use HandlerRegistrationTrait;

    /**
     * Register WordPress API fetch handler with all required filters.
     */
    public static function register(): void {
        self::registerHandler(
            'wordpress_api',
            'fetch',
            WordPressAPI::class,
            __('WordPress REST API', 'datamachine'),
            __('Fetch content from public WordPress sites via REST API', 'datamachine'),
            false,
            null,
            WordPressAPISettings::class,
            null
        );
    }
}

/**
 * Register all WordPress REST API Fetch Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers WordPress REST API Fetch Handler capabilities purely through filter-based discovery.
 * 
 * @since 1.0.0
 */
function datamachine_register_wordpress_api_fetch_filters() {
    WordPressAPIFilters::register();
}

// Auto-register when file loads - achieving complete self-containment
datamachine_register_wordpress_api_fetch_filters();