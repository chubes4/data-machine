<?php
/**
 * WordPress Local fetch handler.
 *
 * Fetches post/page content from local WordPress installation using WP_Query.
 * Provides source_url in metadata for Update step compatibility. For media files, use WordPressMedia handler.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\WordPress
 * @since      1.0.0
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
     * Fetches and prepares WordPress fetch data from various sources into a standardized format.
     *
     * @param int $pipeline_id The pipeline ID for this execution context.
     * @param array  $handler_config Decoded handler configuration for the specific pipeline run.
     * @param string|null $job_id The job ID for processed items tracking.
     * @return array Array with 'processed_items' key containing eligible items.
     * @throws Exception If fetch data is invalid or cannot be retrieved.
     */
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        if (empty($pipeline_id)) {
            do_action('dm_log', 'error', 'WordPress Input: Missing pipeline ID.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }
        
        // Extract flow_step_id from handler config for processed items tracking
        $flow_step_id = $handler_config['flow_step_id'] ?? null;
        
        // Handle null flow_step_id gracefully - skip processed items tracking when flow context missing
        if ($flow_step_id === null) {
            do_action('dm_log', 'debug', 'WordPress fetch called without flow_step_id - processed items tracking disabled');
        }
        
        $user_id = get_current_user_id();

        // Access config from handler config structure
        $config = $handler_config['wordpress_posts'] ?? [];
        
        // Fetch from local WordPress installation
        $items = $this->fetch_local_data($pipeline_id, $config, $user_id, $flow_step_id, $job_id);
        
        return ['processed_items' => $items];
    }

    /**
     * Fetch data from local WordPress installation.
     *
     * @param int   $pipeline_id Module ID for tracking processed items.
     * @param array $config Configuration array.
     * @param int   $user_id User ID for context.
     * @param string|null $flow_step_id Flow step ID for processed items tracking.
     * @param string|null $job_id Job ID for processed items tracking.
     * @return array Array of item data packets.
     * @throws Exception If data cannot be retrieved.
     */
    private function fetch_local_data(int $pipeline_id, array $config, int $user_id, ?string $flow_step_id = null, ?string $job_id = null): array {
        $source_url = sanitize_url($config['source_url'] ?? '');
        
        // If specific post URL is provided, fetch only that post
        if (!empty($source_url)) {
            $post_id = url_to_postid($source_url);
            if ($post_id > 0) {
                return $this->fetch_specific_post($post_id, $flow_step_id, $job_id);
            } else {
                do_action('dm_log', 'warning', 'WordPress Fetch: Could not extract post ID from URL', [
                    'source_url' => $source_url
                ]);
                return ['processed_items' => []];
            }
        }
        
        // Otherwise continue with normal query-based fetching
        $post_type = sanitize_text_field($config['post_type'] ?? 'post');
        $post_status = sanitize_text_field($config['post_status'] ?? 'publish');
        
        // Check for randomize option
        $randomize = !empty($config['randomize_selection']);
        $orderby = $randomize ? 'rand' : 'modified';
        $order = $randomize ? 'ASC' : 'DESC'; // Order irrelevant for rand, but WP expects it

        // Direct config parsing
        $timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
        $search = trim($config['search'] ?? '');

        // Calculate date query parameters
        $cutoff_timestamp = $this->calculate_cutoff_timestamp($timeframe_limit);
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

        // Handle dynamic taxonomy filters
        $tax_query = [];
        foreach ($config as $field_key => $field_value) {
            if (strpos($field_key, 'taxonomy_') === 0 && strpos($field_key, '_filter') !== false) {
                $term_id = intval($field_value);
                if ($term_id > 0) {
                    // Extract taxonomy name from field key: taxonomy_{slug}_filter  
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
            $query_args['tax_query'] = $tax_query;
        }

        // Add search term if specified
        if (!empty($search)) {
            $query_args['s'] = $search;
        }

        // Add date query if specified
        if (!empty($date_query)) {
            $query_args['date_query'] = $date_query;
        }

        // Execute query
        $wp_query = new WP_Query($query_args);
        $posts = $wp_query->posts;

        if (empty($posts)) {
            return [];
        }

        // Find first unprocessed post
        foreach ($posts as $post) {
            $post_id = $post->ID;
            $is_processed = ($flow_step_id !== null) ? apply_filters('dm_is_item_processed', false, $flow_step_id, 'wordpress_local', $post_id) : false;
            if ($is_processed) {
                continue;
            }
            
            // Found first eligible item - mark as processed and return
            if ($flow_step_id) {
                do_action('dm_mark_item_processed', $flow_step_id, 'wordpress_local', $post_id, $job_id);
            }

            $title = $post->post_title ?: 'N/A';
            $content = $post->post_content ?: '';
            $source_link = get_permalink($post_id);
            $image_url = $this->extract_image_url($post_id);

            // Extract source name
            $site_name = get_bloginfo('name');
            $source_name = $site_name ?: 'Local WordPress';
            $content_string = "Source: " . $source_name . "\n\nTitle: " . $title . "\n\n" . $content;

            // Create standardized packet and return immediately
            $input_data = [
                'data' => [
                    'content_string' => $content_string,
                    'file_info' => null
                ],
                'metadata' => [
                    'source_type' => 'wordpress_local',
                    'item_identifier_to_log' => $post_id,
                    'original_id' => $post_id,
                    'source_url' => $source_link,
                    'original_title' => $title,
                    'image_source_url' => $image_url,
                    'original_date_gmt' => $post->post_date_gmt
                ]
            ];
            
            // Return first eligible item immediately
            return [$input_data];
        }

        // No eligible items found
        return [];
    }

    /**
     * Fetch a specific post by ID (used for direct post targeting).
     *
     * @param int $post_id Specific post ID to fetch.
     * @param string|null $flow_step_id Flow step ID for processed items tracking.
     * @param string|null $job_id Job ID for processed items tracking.
     * @return array Array of item data packets (single post).
     */
    private function fetch_specific_post(int $post_id, ?string $flow_step_id = null, ?string $job_id = null): array {
        // Get the specific post
        $post = get_post($post_id);
        
        if (!$post || $post->post_status === 'trash') {
            do_action('dm_log', 'warning', 'WordPress fetch: Specific post not found or trashed', [
                'post_id' => $post_id,
                'flow_step_id' => $flow_step_id
            ]);
            return [];
        }
        
        // Skip processed items checking when targeting specific post ID - always process the target
        // Mark as processed for deduplication tracking
        if ($flow_step_id) {
            do_action('dm_mark_item_processed', $flow_step_id, 'wordpress_local', $post_id, $job_id);
        }

        $title = $post->post_title ?: 'N/A';
        $content = $post->post_content ?: '';
        $source_link = get_permalink($post_id);
        $image_url = $this->extract_image_url($post_id, $content);

        // Extract source name
        $site_name = get_bloginfo('name');
        $source_name = $site_name ?: 'Local WordPress';
        $content_string = "Source: " . $source_name . "\n\nTitle: " . $title . "\n\n" . $content;

        // Create standardized packet
        $input_data = [
            'data' => [
                'content_string' => $content_string,
                'file_info' => null
            ],
            'metadata' => [
                'source_type' => 'wordpress_local',
                'item_identifier_to_log' => $post_id,
                'original_id' => $post_id,
                'source_url' => $source_link,
                'original_title' => $title,
                'image_source_url' => $image_url,
                'original_date_gmt' => $post->post_date_gmt
            ]
        ];
        
        return [$input_data];
    }

    /**
     * Extract featured image URL from post.
     *
     * @param int $post_id Post ID.
     * @return string|null Featured image URL or null if none found.
     */
    private function extract_image_url(int $post_id): ?string {
        $featured_image_id = get_post_thumbnail_id($post_id);
        return $featured_image_id ? wp_get_attachment_image_url($featured_image_id, 'full') : null;
    }






    /**
     * Calculate cutoff timestamp based on timeframe limit.
     *
     * @param string $timeframe_limit Timeframe setting value.
     * @return int|null Cutoff timestamp or null for 'all_time'.
     */
    private function calculate_cutoff_timestamp($timeframe_limit) {
        if ($timeframe_limit === 'all_time') {
            return null;
        }
        
        $interval_map = [
            '24_hours' => '-24 hours',
            '72_hours' => '-72 hours',
            '7_days'   => '-7 days',
            '30_days'  => '-30 days'
        ];
        
        if (!isset($interval_map[$timeframe_limit])) {
            return null;
        }
        
        return strtotime($interval_map[$timeframe_limit], current_time('timestamp', true));
    }

    /**
     * Get the user-friendly label for this handler.
     *
     * @return string Handler label.
     */
    public static function get_label(): string {
        return __('Local WordPress Posts', 'data-machine');
    }
}

