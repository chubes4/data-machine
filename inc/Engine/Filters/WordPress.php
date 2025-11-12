<?php
/**
 * WordPress utilities for user display names, term names, and taxonomy filtering.
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
     * WordPress-specific display transformations.
     * Priority 10 runs after base implementation (priority 5).
     * Base already handles field order from Settings class - no reordering needed.
     */
    add_filter('datamachine_get_handler_settings_display', function($settings_display, $flow_step_id, $step_type) {

        // Get flow step config to identify handler
        $flow_step_config = apply_filters('datamachine_get_flow_step_config', [], $flow_step_id);
        $handler_slug = $flow_step_config['handler_slug'] ?? '';

        // Only apply to WordPress core handlers
        $wordpress_handlers = ['wordpress_publish', 'wordpress_posts', 'wordpress_update'];
        if (!in_array($handler_slug, $wordpress_handlers)) {
            return $settings_display;
        }

        $customized_display = [];

        foreach ($settings_display as $setting) {
            $setting_key = $setting['key'] ?? '';
            $current_value = $setting['value'] ?? '';

            // Skip taxonomy fields with 'skip' value
            if (strpos($setting_key, 'taxonomy_') === 0 && $current_value === 'skip') {
                continue;
            }

            // Start with base display value
            $display_value = $setting['display_value'] ?? $current_value;

            // Apply WordPress-specific transformations
            if ($setting_key === 'post_author') {
                // Convert user ID to display name
                $display_name = apply_filters('datamachine_wordpress_user_display_name', null, $current_value);
                if ($display_name) {
                    $display_value = $display_name;
                }
            } elseif (strpos($setting_key, 'taxonomy_') === 0 && strpos($setting_key, '_filter') !== false && is_numeric($current_value) && $current_value > 0) {
                // Convert taxonomy filter term IDs to names
                $taxonomy_name = str_replace(['taxonomy_', '_filter'], '', $setting_key);
                $term_name = apply_filters('datamachine_wordpress_term_name', null, $current_value, $taxonomy_name);
                if ($term_name) {
                    $display_value = $term_name;
                }
            } elseif (in_array($setting_key, ['post_status', 'post_type']) && $current_value === 'any') {
                // Special 'any' value
                $display_value = __('Any', 'datamachine');
            } elseif (in_array($setting_key, ['source_url', 'search']) && empty($current_value)) {
                // Empty source_url or search
                $display_value = __('N/A', 'datamachine');
            }

            $customized_display[] = [
                'key' => $setting_key,
                'label' => $setting['label'] ?? '',
                'value' => $current_value,
                'display_value' => $display_value
            ];
        }

        return $customized_display;
    }, 10, 3);

    add_filter('datamachine_wordpress_user_display_name', function($default, $user_id) {
        $user = get_userdata($user_id);
        return $user ? $user->display_name : null;
    }, 10, 2);

    add_filter('datamachine_wordpress_term_name', function($default, $term_id, $taxonomy) {
        $term = get_term($term_id, $taxonomy);
        return (!is_wp_error($term) && $term) ? $term->name : null;
    }, 10, 3);

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
