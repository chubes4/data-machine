<?php
/**
 * WordPress utilities for taxonomy filtering.
 *
 * @package DataMachine
 * @subpackage Engine\Filters
 */

namespace DataMachine\Engine\Filters;

if (!defined('ABSPATH')) {
    exit;
}

function datamachine_register_wordpress_filters() {

    /**
     * System taxonomies excluded from Data Machine processing.
     */
    add_filter('datamachine_wordpress_system_taxonomies', function($default) {
        return ['post_format', 'nav_menu', 'link_category'];
    });

    add_filter('datamachine_wordpress_public_taxonomies', function($default, $args = []) {
        $defaults = ['public' => true];
        $args = array_merge($defaults, $args);

        $taxonomies = get_taxonomies($args, 'objects');
        $excluded = apply_filters('datamachine_wordpress_system_taxonomies', []);

        return array_filter($taxonomies, function($taxonomy) use ($excluded) {
            return !in_array($taxonomy->name, $excluded);
        });
    }, 10, 2);
}

datamachine_register_wordpress_filters();
