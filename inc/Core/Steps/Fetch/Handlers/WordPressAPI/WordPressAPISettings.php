<?php
/**
 * WordPress REST API Fetch Handler Settings
 *
 * Defines settings fields and sanitization for WordPress REST API fetch handler.
 * Part of the modular handler architecture.
 *
 * @package    DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\WordPressAPI
 * @since      1.0.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPressAPI;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WordPressAPISettings {

    public function __construct() {
    }

    /**
     * Get settings fields for WordPress REST API fetch handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        $fields = [
            'site_url' => [
                'type' => 'text',
                'label' => __('WordPress Site URL', 'data-machine'),
                'description' => __('Enter the base URL of the WordPress site (e.g., https://example.com)', 'data-machine'),
                'placeholder' => __('https://example.com', 'data-machine'),
                'required' => true,
            ],
            'post_type' => [
                'type' => 'select',
                'label' => __('Post Type', 'data-machine'),
                'description' => __('Select the post type to fetch from the remote site.', 'data-machine'),
                'options' => [
                    'posts' => __('Posts', 'data-machine'),
                    'pages' => __('Pages', 'data-machine'),
                ],
            ],
            'post_status' => [
                'type' => 'select',
                'label' => __('Post Status', 'data-machine'),
                'description' => __('Select the post status to fetch.', 'data-machine'),
                'options' => array_merge(get_post_statuses(), ['any' => __('Any', 'data-machine')]),
            ],
            'orderby' => [
                'type' => 'select',
                'label' => __('Order By', 'data-machine'),
                'description' => __('How to order the results.', 'data-machine'),
                'options' => [
                    'date' => __('Date Published', 'data-machine'),
                    'modified' => __('Date Modified', 'data-machine'),
                    'title' => __('Title', 'data-machine'),
                    'slug' => __('Slug', 'data-machine'),
                ],
            ],
            'order' => [
                'type' => 'select',
                'label' => __('Order', 'data-machine'),
                'description' => __('Sort order for results.', 'data-machine'),
                'options' => [
                    'desc' => __('Descending (Newest First)', 'data-machine'),
                    'asc' => __('Ascending (Oldest First)', 'data-machine'),
                ],
            ],
            'timeframe_limit' => [
                'type' => 'select',
                'label' => __('Process Items Within', 'data-machine'),
                'description' => __('Only consider items published within this timeframe.', 'data-machine'),
                'options' => [
                    'all_time' => __('All Time', 'data-machine'),
                    '24_hours' => __('Last 24 Hours', 'data-machine'),
                    '72_hours' => __('Last 72 Hours', 'data-machine'),
                    '7_days'   => __('Last 7 Days', 'data-machine'),
                    '30_days'  => __('Last 30 Days', 'data-machine'),
                ],
            ],
            'search' => [
                'type' => 'text',
                'label' => __('Search Term Filter', 'data-machine'),
                'description' => __('Filter items using a search term.', 'data-machine'),
                'placeholder' => __('Optional search term', 'data-machine'),
            ],
        ];

        return $fields;
    }

    /**
     * Sanitize WordPress REST API fetch handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [
            'site_url' => esc_url_raw(trim($raw_settings['site_url'] ?? '')),
            'post_type' => sanitize_text_field($raw_settings['post_type'] ?? 'posts'),
            'post_status' => sanitize_text_field($raw_settings['post_status'] ?? 'publish'),
            'orderby' => sanitize_text_field($raw_settings['orderby'] ?? 'date'),
            'order' => sanitize_text_field($raw_settings['order'] ?? 'desc'),
            'timeframe_limit' => sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time'),
            'search' => sanitize_text_field($raw_settings['search'] ?? ''),
        ];

        // Additional validation for site_url
        if (!empty($sanitized['site_url']) && !filter_var($sanitized['site_url'], FILTER_VALIDATE_URL)) {
            $sanitized['site_url'] = '';
        }

        // Validate post_type
        $valid_post_types = ['posts', 'pages'];
        if (!in_array($sanitized['post_type'], $valid_post_types)) {
            $sanitized['post_type'] = 'posts';
        }

        // Validate post_status
        $valid_statuses = ['publish', 'draft', 'pending', 'private', 'any'];
        if (!in_array($sanitized['post_status'], $valid_statuses)) {
            $sanitized['post_status'] = 'publish';
        }

        // Validate orderby
        $valid_orderby = ['date', 'modified', 'title', 'slug'];
        if (!in_array($sanitized['orderby'], $valid_orderby)) {
            $sanitized['orderby'] = 'date';
        }

        // Validate order
        $valid_order = ['desc', 'asc'];
        if (!in_array($sanitized['order'], $valid_order)) {
            $sanitized['order'] = 'desc';
        }

        // Validate timeframe_limit
        $valid_timeframes = ['all_time', '24_hours', '72_hours', '7_days', '30_days'];
        if (!in_array($sanitized['timeframe_limit'], $valid_timeframes)) {
            $sanitized['timeframe_limit'] = 'all_time';
        }

        return $sanitized;
    }

    /**
     * Determine if authentication is required based on current configuration.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return bool True if authentication is required, false otherwise.
     */
    public static function requires_authentication(array $current_config = []): bool {
        // Public REST API does not require authentication
        return false;
    }
}