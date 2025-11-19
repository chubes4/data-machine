<?php
/**
 * WordPress Media Fetch Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as WordPress Media Fetch Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\WordPressMedia
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPressMedia;

use DataMachine\Core\Steps\HandlerRegistrationTrait;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress Media handler registration and configuration.
 *
 * Uses HandlerRegistrationTrait to provide standardized handler registration
 * for sourcing attached images and media from WordPress media library.
 *
 * @since 0.2.2
 */
class WordPressMediaFilters {
    use HandlerRegistrationTrait;

    /**
     * Register WordPress Media fetch handler with all required filters.
     */
    public static function register(): void {
        self::registerHandler(
            'wordpress_media',
            'fetch',
            WordPressMedia::class,
            __('WordPress Media', 'datamachine'),
            __('Source attached images and media from WordPress media library', 'datamachine'),
            false,
            null,
            WordPressMediaSettings::class,
            null
        );
    }
}

/**
 * Register all WordPress Media Fetch Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers WordPress Media Fetch Handler capabilities purely through filter-based discovery.
 * 
 * @since 1.0.0
 */
function datamachine_register_wordpress_media_fetch_filters() {
    WordPressMediaFilters::register();
}

// Auto-register when file loads - achieving complete self-containment
datamachine_register_wordpress_media_fetch_filters();