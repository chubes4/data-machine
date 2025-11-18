<?php
/**
 * WordPress utilities service discovery and registration.
 *
 * Self-registration following established WordPress filter-based architecture.
 * Provides filter-based access to all WordPress handler utilities.
 *
 * @package DataMachine\Core\WordPress
 */

namespace DataMachine\Core\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register WordPress utilities for filter-based service discovery
 */
function datamachine_register_wordpress_utilities() {
    // Register TaxonomyHandler
    add_filter('datamachine_get_taxonomy_handler', function() {
        return new TaxonomyHandler();
    });

    // Register FeaturedImageHandler
    add_filter('datamachine_get_featured_image_handler', function() {
        return new FeaturedImageHandler();
    });

    // Register SourceUrlHandler
    add_filter('datamachine_get_source_url_handler', function() {
        return new SourceUrlHandler();
    });
}

// Auto-execute registration
datamachine_register_wordpress_utilities();
