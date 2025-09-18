<?php
/**
 * WordPress Local fetch handler.
 *
 * Fetches post/page content from local WordPress installation using WP_Query.
 * Generates clean content for AI and source_url via engine parameters for Update step compatibility.
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
     * Fetch WordPress content with explicit data/parameter separation.
     * Returns clean content for AI consumption and separate engine parameters for handlers.
     *
     * @param int $pipeline_id Pipeline ID for logging context.
     * @param array $handler_config Handler configuration including flow_step_id and WordPress settings.
     * @param string|null $job_id Job ID for deduplication tracking.
     * @return array Array with 'processed_items' (clean data) and 'engine_parameters' (URLs/metadata).
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
        $result = $this->fetch_local_data($pipeline_id, $config, $user_id, $flow_step_id, $job_id, $handler_config);

        // Check if result is already in new format with engine_parameters
        if (isset($result['processed_items']) && isset($result['engine_parameters'])) {
            return $result; // Return complete result with separated data
        }

        // Legacy format - wrap in processed_items for compatibility
        return ['processed_items' => $result];
    }

    /**
     * Fetch WordPress content using convergence pattern for both URL-specific and query-based access.
     * Implements explicit data/parameter separation architecture.
     *
     * @param int $pipeline_id Pipeline ID for logging context.
     * @param array $config WordPress-specific configuration (post_type, status, timeframe, etc.).
     * @param int $user_id User ID for WordPress query context.
     * @param string|null $flow_step_id Flow step ID for deduplication tracking.
     * @param string|null $job_id Job ID for processed items tracking.
     * @param array $handler_config Complete handler configuration for engine parameter generation.
     * @return array Array with 'processed_items' and 'engine_parameters' separation.
     */
    private function fetch_local_data(int $pipeline_id, array $config, int $user_id, ?string $flow_step_id = null, ?string $job_id = null, array $handler_config = []): array {
        $source_url = sanitize_url($config['source_url'] ?? '');
        
        // Path 1: URL-specific access - convergence to process_single_post()
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
        
        // Path 2: Query-based fetching - will also converge to process_single_post()
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
            return ['processed_items' => []];
        }

        // Query-based processing - find first eligible post and converge to single processing method
        foreach ($posts as $post) {
            $post_id = $post->ID;
            $is_processed = ($flow_step_id !== null) ? apply_filters('dm_is_item_processed', false, $flow_step_id, 'wordpress_local', $post_id) : false;
            if ($is_processed) {
                continue;
            }

            // Converge to single processing method - eliminates code duplication
            return $this->process_single_post($post_id, $flow_step_id, $job_id, $handler_config);
        }

        // No eligible items found
        return ['processed_items' => []];
    }


    /**
     * Convergence method for single post processing with explicit parameter generation.
     * Eliminates code duplication between URL-specific and query-based fetching paths.
     * Generates clean content for AI and separate engine parameters for publish/update handlers.
     *
     * @param int $post_id WordPress post ID to process.
     * @param string|null $flow_step_id Flow step ID for deduplication tracking.
     * @param string|null $job_id Job ID for processed items tracking.
     * @param array $handler_config Handler configuration for engine parameter generation.
     * @return array Explicit separation: processed_items (clean) + engine_parameters (source_url, image_url).
     */
    private function process_single_post(int $post_id, ?string $flow_step_id, ?string $job_id, array $handler_config): array {
        // Get the post
        $post = get_post($post_id);

        if (!$post || $post->post_status === 'trash') {
            do_action('dm_log', 'warning', 'WordPress fetch: Post not found or trashed', [
                'post_id' => $post_id,
                'flow_step_id' => $flow_step_id
            ]);
            return ['processed_items' => []];
        }

        // Mark as processed for deduplication tracking
        if ($flow_step_id) {
            do_action('dm_mark_item_processed', $flow_step_id, 'wordpress_local', $post_id, $job_id);
        }

        // Extract post data
        $title = $post->post_title ?: 'N/A';
        $content = $post->post_content ?: '';
        $source_link = get_permalink($post_id);
        $image_url = $this->extract_image_url($post_id);

        // Extract source name
        $site_name = get_bloginfo('name');
        $source_name = $site_name ?: 'Local WordPress';
        $content_string = "Source: " . $source_name . "\n\nTitle: " . $title . "\n\n" . $content;

        // Create clean data packet for AI consumption (URLs removed)
        $input_data = [
            'data' => [
                'content_string' => $content_string,
                'file_info' => null
            ],
            'metadata' => [
                'source_type' => 'wordpress_local',
                'item_identifier_to_log' => $post_id,
                'original_id' => $post_id,
                'original_title' => $title,
                'original_date_gmt' => $post->post_date_gmt,
                'post_type' => $post->post_type,
                'post_status' => $post->post_status
            ]
        ];

        // Store URLs in engine_data for centralized parameter injection
        if ($job_id) {
            $engine_data = [
                'source_url' => get_permalink($post_id) ?: '',
                'image_url' => $image_url ?: ''
            ];

            // Store engine_data via database service
            $all_databases = apply_filters('dm_db', []);
            $db_jobs = $all_databases['jobs'] ?? null;
            if ($db_jobs) {
                $db_jobs->store_engine_data($job_id, $engine_data);
                do_action('dm_log', 'debug', 'WordPress Local: Stored URLs in engine_data', [
                    'job_id' => $job_id,
                    'source_url' => $engine_data['source_url'],
                    'has_image_url' => !empty($engine_data['image_url']),
                    'post_id' => $post_id
                ]);
            }
        }

        return [
            'processed_items' => [$input_data]
        ];
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

