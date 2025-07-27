<?php
/**
 * WordPress Output Handler Settings Module
 *
 * Defines settings fields and sanitization for WordPress output handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/handlers/output/wordpress
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Output\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WordPressSettings {

    /**
     * Constructor.
     * Pure filter-based architecture - no dependencies.
     */
    public function __construct() {
        // No constructor dependencies - all services accessed via filters
    }

    /**
     * Get settings fields for WordPress output handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        $destination_type = $current_config['destination_type'] ?? 'local';

        $fields = [
            'destination_type' => [
                'type' => 'select',
                'label' => __('WordPress Destination Type', 'data-machine'),
                'description' => __('Select where to publish the processed content.', 'data-machine'),
                'options' => [
                    'local' => __('Local WordPress', 'data-machine'),
                    'remote' => __('Remote WordPress (Airdrop)', 'data-machine'),
                ],
                'default' => 'local',
            ],
        ];

        // Add conditional fields based on destination type
        switch ($destination_type) {
            case 'local':
                $fields = array_merge($fields, self::get_local_fields());
                break;

            case 'remote':
                $fields = array_merge($fields, self::get_remote_fields($current_config));
                break;
        }

        // Add common fields for all destination types
        $fields = array_merge($fields, self::get_common_fields());

        return $fields;
    }

    /**
     * Get settings fields specific to local WordPress publishing.
     *
     * @return array Settings fields.
     */
    private static function get_local_fields(): array {
        // Get available post types
        $post_type_options = [];
        $post_types = get_post_types(['public' => true], 'objects');
        $common_types = ['post' => 'Post', 'page' => 'Page'];
        foreach ($common_types as $slug => $label) {
            if (isset($post_types[$slug])) {
                $post_type_options[$slug] = $label;
                unset($post_types[$slug]);
            }
        }
        foreach ($post_types as $pt) {
            $post_type_options[$pt->name] = $pt->label;
        }

        // Get available categories
        $category_options = [
            'instruct_model' => '-- Instruct Model --'
        ];
        $local_categories = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
        if (!is_wp_error($local_categories)) {
            foreach ($local_categories as $cat) {
                $category_options[$cat->term_id] = $cat->name;
            }
        }

        // Get available tags
        $tag_options = [
            'instruct_model' => '-- Instruct Model --'
        ];
        $local_tags = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => false]);
        if (!is_wp_error($local_tags)) {
            foreach ($local_tags as $tag) {
                $tag_options[$tag->term_id] = $tag->name;
            }
        }

        return [
            'post_type' => [
                'type' => 'select',
                'label' => __('Post Type', 'data-machine'),
                'description' => __('Select the post type for published content.', 'data-machine'),
                'options' => $post_type_options,
                'default' => 'post',
            ],
            'post_status' => [
                'type' => 'select',
                'label' => __('Post Status', 'data-machine'),
                'description' => __('Select the status for the newly created post.', 'data-machine'),
                'options' => [
                    'draft' => __('Draft', 'data-machine'),
                    'publish' => __('Publish', 'data-machine'),
                    'pending' => __('Pending Review', 'data-machine'),
                    'private' => __('Private', 'data-machine'),
                ],
                'default' => 'draft',
            ],
            'selected_local_category_id' => [
                'type' => 'select',
                'label' => __('Category', 'data-machine'),
                'description' => __('Select a category, let the AI choose, or instruct the AI using your prompt.', 'data-machine'),
                'options' => $category_options,
                'default' => 'instruct_model',
            ],
            'selected_local_tag_id' => [
                'type' => 'select',
                'label' => __('Tag', 'data-machine'),
                'description' => __('Select a single tag, let the AI choose, or instruct the AI using your prompt.', 'data-machine'),
                'options' => $tag_options,
                'default' => 'instruct_model',
            ],
        ];
    }

    /**
     * Get settings fields specific to remote WordPress publishing.
     *
     * @param array $current_config Current configuration.
     * @return array Settings fields.
     */
    private static function get_remote_fields(array $current_config = []): array {
        // Get remote locations service via filter system
        $db_remote_locations = apply_filters('dm_get_db_remote_locations', null);
        $locations = $db_remote_locations ? $db_remote_locations->get_locations_for_current_user() : [];

        $options = [0 => __('Select a Remote Location', 'data-machine')];
        foreach ($locations as $loc) {
            $options[$loc->location_id] = $loc->location_name . ' (' . $loc->target_site_url . ')';
        }

        return [
            'location_id' => [
                'type' => 'select',
                'label' => __('Remote Location', 'data-machine'),
                'description' => __('Select the pre-configured remote WordPress site to publish to.', 'data-machine'),
                'options' => $options,
                'default' => 0,
            ],
            'selected_remote_post_type' => [
                'type' => 'select',
                'label' => __('Post Type', 'data-machine'),
                'description' => __('Select the post type for the remote site.', 'data-machine'),
                'options' => ['post' => 'Posts', 'page' => 'Pages'],
                'default' => 'post',
            ],
            'remote_post_status' => [
                'type' => 'select',
                'label' => __('Post Status', 'data-machine'),
                'description' => __('Select the status for the newly created post on the remote site.', 'data-machine'),
                'options' => [
                    'draft' => __('Draft', 'data-machine'),
                    'publish' => __('Publish', 'data-machine'),
                    'pending' => __('Pending Review', 'data-machine'),
                    'private' => __('Private', 'data-machine'),
                ],
                'default' => 'draft',
            ],
            'selected_remote_category_id' => [
                'type' => 'select',
                'label' => __('Category', 'data-machine'),
                'description' => __('Select a category or let the AI choose based on your prompt.', 'data-machine'),
                'options' => ['instruct_model' => '-- Instruct Model --'],
                'default' => 'instruct_model',
            ],
            'selected_remote_tag_id' => [
                'type' => 'select',
                'label' => __('Tag', 'data-machine'),
                'description' => __('Select a tag or let the AI choose based on your prompt.', 'data-machine'),
                'options' => ['instruct_model' => '-- Instruct Model --'],
                'default' => 'instruct_model',
            ],
        ];
    }

    /**
     * Get common settings fields for all destination types.
     *
     * @return array Settings fields.
     */
    private static function get_common_fields(): array {
        return [
            'use_gutenberg_blocks' => [
                'type' => 'select',
                'label' => __('Editor Format', 'data-machine'),
                'description' => __('Choose whether to format content for Gutenberg block editor or classic editor.', 'data-machine'),
                'options' => [
                    '1' => __('Gutenberg Block Editor (Recommended)', 'data-machine'),
                    '0' => __('Classic Editor', 'data-machine'),
                ],
                'default' => '1',
            ],
            'post_date_source' => [
                'type' => 'select',
                'label' => __('Post Date Setting', 'data-machine'),
                'description' => __('Choose whether to use the original date from the source (if available) or the current date when publishing.', 'data-machine'),
                'options' => [
                    'current_date' => __('Use Current Date', 'data-machine'),
                    'source_date' => __('Use Source Date (if available)', 'data-machine'),
                ],
                'default' => 'current_date',
            ],
        ];
    }

    /**
     * Sanitize WordPress output handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [];

        // Destination type is required
        $sanitized['destination_type'] = sanitize_text_field($raw_settings['destination_type'] ?? 'local');
        if (!in_array($sanitized['destination_type'], ['local', 'remote'])) {
            $sanitized['destination_type'] = 'local';
        }

        // Sanitize based on destination type
        switch ($sanitized['destination_type']) {
            case 'local':
                $sanitized = array_merge($sanitized, self::sanitize_local_settings($raw_settings));
                break;

            case 'remote':
                $sanitized = array_merge($sanitized, self::sanitize_remote_settings($raw_settings));
                break;
        }

        // Sanitize common fields
        $sanitized['use_gutenberg_blocks'] = in_array($raw_settings['use_gutenberg_blocks'] ?? '1', ['0', '1']) ? $raw_settings['use_gutenberg_blocks'] : '1';
        $valid_date_sources = ['current_date', 'source_date'];
        $date_source = sanitize_text_field($raw_settings['post_date_source'] ?? 'current_date');
        $sanitized['post_date_source'] = in_array($date_source, $valid_date_sources) ? $date_source : 'current_date';

        return $sanitized;
    }

    /**
     * Sanitize local WordPress settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     */
    private static function sanitize_local_settings(array $raw_settings): array {
        $sanitized = [
            'post_type' => sanitize_text_field($raw_settings['post_type'] ?? 'post'),
            'post_status' => sanitize_text_field($raw_settings['post_status'] ?? 'draft'),
        ];

        // Sanitize Category ID/Mode
        $cat_val = $raw_settings['selected_local_category_id'] ?? 'instruct_model';
        if ($cat_val === 'instruct_model') {
            $sanitized['selected_local_category_id'] = $cat_val;
        } else {
            $sanitized['selected_local_category_id'] = intval($cat_val);
        }

        // Sanitize Tag ID/Mode
        $tag_val = $raw_settings['selected_local_tag_id'] ?? 'instruct_model';
        if ($tag_val === 'instruct_model') {
            $sanitized['selected_local_tag_id'] = $tag_val;
        } else {
            $sanitized['selected_local_tag_id'] = intval($tag_val);
        }

        return $sanitized;
    }

    /**
     * Sanitize remote WordPress settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     */
    private static function sanitize_remote_settings(array $raw_settings): array {
        $sanitized = [
            'location_id' => absint($raw_settings['location_id'] ?? 0),
            'selected_remote_post_type' => sanitize_text_field($raw_settings['selected_remote_post_type'] ?? 'post'),
            'remote_post_status' => sanitize_text_field($raw_settings['remote_post_status'] ?? 'draft'),
        ];

        // Sanitize Remote Category ID/Mode
        $cat_val = $raw_settings['selected_remote_category_id'] ?? 'instruct_model';
        if ($cat_val === 'instruct_model') {
            $sanitized['selected_remote_category_id'] = $cat_val;
        } else {
            $sanitized['selected_remote_category_id'] = intval($cat_val);
        }

        // Sanitize Remote Tag ID/Mode
        $tag_val = $raw_settings['selected_remote_tag_id'] ?? 'instruct_model';
        if ($tag_val === 'instruct_model') {
            $sanitized['selected_remote_tag_id'] = $tag_val;
        } else {
            $sanitized['selected_remote_tag_id'] = intval($tag_val);
        }

        return $sanitized;
    }

    /**
     * Get default values for all settings.
     *
     * @return array Default values for all settings.
     */
    public static function get_defaults(): array {
        return [
            'destination_type' => 'local',
            'post_type' => 'post',
            'post_status' => 'draft',
            'selected_local_category_id' => 'instruct_model',
            'selected_local_tag_id' => 'instruct_model',
            'use_gutenberg_blocks' => '1',
            'post_date_source' => 'current_date',
        ];
    }
}
