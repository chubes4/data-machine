<?php
/**
 * WordPress local content fetch handler with timeframe and keyword filtering.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\WordPress
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPress;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WordPress {

    public function __construct() {
    }

    /**
     * Fetch local WordPress content with timeframe and keyword filtering.
     * Engine data (source_url, image_url) stored via dm_engine_data filter.
     */
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        if (empty($pipeline_id)) {
            do_action('dm_log', 'error', 'WordPress Input: Missing pipeline ID.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }
        
        $flow_step_id = $handler_config['flow_step_id'] ?? null;

        if ($flow_step_id === null) {
            do_action('dm_log', 'debug', 'WordPress fetch called without flow_step_id - processed items tracking disabled');
        }
        
        $user_id = get_current_user_id();

        $config = $handler_config['wordpress_posts'] ?? [];
        return $this->fetch_local_data($pipeline_id, $config, $user_id, $flow_step_id, $job_id, $handler_config);
    }

    /**
     * Fetch WordPress content with convergence pattern for URL-specific and query-based access.
     */
    private function fetch_local_data(int $pipeline_id, array $config, int $user_id, ?string $flow_step_id = null, ?string $job_id = null, array $handler_config = []): array {
        $source_url = sanitize_url($config['source_url'] ?? '');
        
        // URL-specific access
        if (!empty($source_url)) {
            $post_id = url_to_postid($source_url);
            if ($post_id > 0) {
                return $this->process_single_post($post_id, $flow_step_id, $job_id, $handler_config);
            } else {
                do_action('dm_log', 'warning', 'WordPress Fetch: Could not extract post ID from URL', [
                    'source_url' => $source_url
                ]);
                return ['processed_items' => []];
            }
        }
        $post_type = sanitize_text_field($config['post_type'] ?? 'post');
        $post_status = sanitize_text_field($config['post_status'] ?? 'publish');
        
        $randomize = !empty($config['randomize_selection']);
        $orderby = $randomize ? 'rand' : 'modified';
        $order = $randomize ? 'ASC' : 'DESC';

        $timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
        $search = trim($config['search'] ?? '');
        $cutoff_timestamp = apply_filters('dm_timeframe_limit', null, $timeframe_limit);
        $date_query = [];
        if ($cutoff_timestamp !== null) {
            $date_query = [
                [
                    'after' => gmdate('Y-m-d H:i:s', $cutoff_timestamp),
                    'inclusive' => true,
                ]
            ];
        }

        // Build WP_Query arguments
        $query_args = [
            'post_type' => $post_type,
            'post_status' => $post_status,
            'posts_per_page' => 10, // Simple limit to find first eligible item
            'orderby' => $orderby,
            'order' => $order,
            'no_found_rows' => true, // Performance optimization
            'update_post_meta_cache' => false, // Performance optimization
            'update_post_term_cache' => false, // Performance optimization
        ];

        $tax_query = [];
        foreach ($config as $field_key => $field_value) {
            if (strpos($field_key, 'taxonomy_') === 0 && strpos($field_key, '_filter') !== false) {
                $term_id = intval($field_value);
                if ($term_id > 0) {
                    $taxonomy_slug = str_replace(['taxonomy_', '_filter'], '', $field_key);
                    $tax_query[] = [
                        'taxonomy' => $taxonomy_slug,
                        'field'    => 'term_id',
                        'terms'    => [$term_id],
                    ];
                }
            }
        }
        if (!empty($tax_query)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $query_args['tax_query'] = $tax_query;
        }

        $use_client_side_search = false;
        if (!empty($search)) {
            if (strpos($search, ',') !== false) {
                $use_client_side_search = true;
            } else {
                $query_args['s'] = $search;
            }
        }

        if (!empty($date_query)) {
            $query_args['date_query'] = $date_query;
        }

        $wp_query = new WP_Query($query_args);
        $posts = $wp_query->posts;

        if (empty($posts)) {
            return ['processed_items' => []];
        }
        foreach ($posts as $post) {
            $post_id = $post->ID;
            $is_processed = ($flow_step_id !== null) ? apply_filters('dm_is_item_processed', false, $flow_step_id, 'wordpress_local', $post_id) : false;
            if ($is_processed) {
                continue;
            }

            if ($use_client_side_search && !empty($search)) {
                $search_text = $post->post_title . ' ' . wp_strip_all_tags($post->post_content . ' ' . $post->post_excerpt);
                $matches = apply_filters('dm_keyword_search_match', false, $search_text, $search);
                if (!$matches) {
                    continue;
                }
            }

            return $this->process_single_post($post_id, $flow_step_id, $job_id, $handler_config);
        }
        return ['processed_items' => []];
    }


    /**
     * Process single post with engine data storage via dm_engine_data filter.
     */
    private function process_single_post(int $post_id, ?string $flow_step_id, ?string $job_id, array $handler_config): array {
        $post = get_post($post_id);

        if (!$post || $post->post_status === 'trash') {
            do_action('dm_log', 'warning', 'WordPress fetch: Post not found or trashed', [
                'post_id' => $post_id,
                'flow_step_id' => $flow_step_id
            ]);
            return ['processed_items' => []];
        }

        if ($flow_step_id) {
            do_action('dm_mark_item_processed', $flow_step_id, 'wordpress_local', $post_id, $job_id);
        }

        $title = $post->post_title ?: 'N/A';
        $content = $post->post_content ?: '';
        $image_url = $this->extract_image_url($post_id);
        $site_name = get_bloginfo('name') ?: 'Local WordPress';

        $content_data = [
            'title' => $title,
            'content' => $content,
            'excerpt' => $post->post_excerpt ?: ''
        ];

        $metadata = [
            'source_type' => 'wordpress_local',
            'item_identifier_to_log' => $post_id,
            'original_id' => $post_id,
            'original_title' => $title,
            'original_date_gmt' => $post->post_date_gmt,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'site_name' => $site_name
        ];

        $input_data = [
            'data' => $content_data,
            'metadata' => $metadata
        ];

        // Store URLs in engine_data via centralized filter
        if ($job_id) {
            apply_filters('dm_engine_data', null, $job_id, get_permalink($post_id) ?: '', $image_url ?: '');
        }

        return [
            'processed_items' => [$input_data]
        ];
    }

    /**
     * Extract featured image URL from post.
     */
    private function extract_image_url(int $post_id): ?string {
        $featured_image_id = get_post_thumbnail_id($post_id);
        return $featured_image_id ? wp_get_attachment_image_url($featured_image_id, 'full') : null;
    }


    public static function get_label(): string {
        return __('Local WordPress Posts', 'data-machine');
    }
}
