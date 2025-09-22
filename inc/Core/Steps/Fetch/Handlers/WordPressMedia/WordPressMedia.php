<?php
/**
 * WordPress Media fetch handler.
 *
 * Handles WordPress media library content using WP_Query for attachment post type.
 * Specialized for media files with clean content generation and URL storage via engine data.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\WordPressMedia
 * @since      1.0.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPressMedia;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WordPressMedia {

    public function __construct() {
    }

    /**
     * Fetch WordPress Media content with clean data for AI processing.
     * Returns processed items while storing engine data (source_url, image_url) in database.
     *
     * @param int $pipeline_id Pipeline ID for logging context.
     * @param array $handler_config Handler configuration including media settings and flow_step_id.
     * @param string|null $job_id Job ID for deduplication tracking.
     * @return array Array with 'processed_items' containing clean data for AI processing.
     *               Engine parameters (source_url, image_url) are stored in database via store_engine_data().
     */
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        if (empty($pipeline_id)) {
            do_action('dm_log', 'error', 'WordPress Media: Missing pipeline ID.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }
        
        // Extract flow_step_id from handler config for processed items tracking
        $flow_step_id = $handler_config['flow_step_id'] ?? null;
        
        // Handle null flow_step_id gracefully - skip processed items tracking when flow context missing
        if ($flow_step_id === null) {
            do_action('dm_log', 'debug', 'WordPress Media fetch called without flow_step_id - processed items tracking disabled');
        }
        
        $user_id = get_current_user_id();

        // Access config from handler config structure
        $config = $handler_config['wordpress_media'] ?? [];
        
        // Fetch from local WordPress media library
        $items = $this->fetch_media_data($pipeline_id, $config, $user_id, $flow_step_id, $job_id);
        
        return ['processed_items' => $items];
    }

    /**
     * Fetch media data from local WordPress installation.
     *
     * @param int   $pipeline_id Module ID for tracking processed items.
     * @param array $config Configuration array.
     * @param int   $user_id User ID for context.
     * @param string|null $flow_step_id Flow step ID for processed items tracking.
     * @param string|null $job_id Job ID for processed items tracking.
     * @return array Array of item data packets.
     * @throws Exception If data cannot be retrieved.
     */
    private function fetch_media_data(int $pipeline_id, array $config, int $user_id, ?string $flow_step_id = null, ?string $job_id = null): array {
        // Media-specific configuration
        $file_types = $config['file_types'] ?? ['image'];
        $include_parent_content = !empty($config['include_parent_content']);
        
        // Check for randomize option
        $randomize = !empty($config['randomize_selection']);
        $orderby = $randomize ? 'rand' : 'modified';
        $order = $randomize ? 'ASC' : 'DESC';

        // Direct config parsing for common fields
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

        // Build mime type query for file types
        $mime_types = $this->build_mime_type_query($file_types);

        // Build WP_Query arguments for attachments
        $query_args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit', // Attachments use 'inherit' status
            'post_parent__not_in' => [0], // Only get attached media (exclude orphaned)
            'posts_per_page' => 10, // Simple limit to find first eligible item
            'orderby' => $orderby,
            'order' => $order,
            'no_found_rows' => true, // Performance optimization
            'update_post_meta_cache' => false, // Performance optimization
            'update_post_term_cache' => false, // Performance optimization
        ];

        // Add mime type filter if specified
        if (!empty($mime_types)) {
            $query_args['post_mime_type'] = $mime_types;
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

        // Find first unprocessed media item
        foreach ($posts as $post) {
            $post_id = $post->ID;
            $is_processed = ($flow_step_id !== null) ? apply_filters('dm_is_item_processed', false, $flow_step_id, 'wordpress_media', $post_id) : false;
            if ($is_processed) {
                continue;
            }
            
            
            // Found first eligible item - mark as processed and return
            if ($flow_step_id) {
                do_action('dm_mark_item_processed', $flow_step_id, 'wordpress_media', $post_id, $job_id);
            }

            // Store URLs in engine_data for centralized access via dm_engine_data filter
            if ($job_id) {
                $image_url = wp_get_attachment_url($post_id);

                // Get parent post permalink for source_url when include_parent_content is enabled
                $source_url = '';
                if ($include_parent_content && $post->post_parent > 0) {
                    $source_url = get_permalink($post->post_parent) ?: '';
                }

                $engine_data = [
                    'source_url' => $source_url,
                    'image_url' => $image_url ?: ''
                ];

                // Store engine_data via database service
                $all_databases = apply_filters('dm_db', []);
                $db_jobs = $all_databases['jobs'] ?? null;
                if ($db_jobs) {
                    $db_jobs->store_engine_data($job_id, $engine_data);
                    do_action('dm_log', 'debug', 'WordPress Media: Stored URLs in engine_data', [
                        'job_id' => $job_id,
                        'image_url' => $image_url,
                        'post_id' => $post_id
                    ]);
                }
            }

            return ['processed_items' => [$this->create_media_data_packet($post, $include_parent_content)]];
        }

        // No eligible items found
        return ['processed_items' => []];
    }

    /**
     * Create data packet for media item.
     *
     * @param \WP_Post $post Media post object.
     * @param bool $include_parent_content Whether to include parent post content.
     * @return array Formatted data packet.
     */
    private function create_media_data_packet(\WP_Post $post, bool $include_parent_content = false): array {
        $post_id = $post->ID;
        
        // Get media-specific data
        $title = $post->post_title ?: 'N/A';
        $caption = $post->post_excerpt ?: ''; // WordPress stores captions in post_excerpt
        $description = $post->post_content ?: '';
        $alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true) ?: '';
        
        // Get media URL and file info
        $media_url = wp_get_attachment_url($post_id);
        $file_type = get_post_mime_type($post_id) ?: 'unknown';
        $file_path = get_attached_file($post_id);
        $file_size = $file_path && file_exists($file_path) ? filesize($file_path) : 0;
        
        // Get parent post for URL and optional content
        $parent_post = null;
        $parent_content = '';
        $parent_content_included = false;
        if ($post->post_parent > 0) {
            $parent_post = get_post($post->post_parent);
            
            // Add parent content if requested - enhanced formatting for better AI comprehension
            if ($include_parent_content && $parent_post) {
                $parent_title = $parent_post->post_title ?: 'Untitled';
                $parent_body = $parent_post->post_content ?: '';

                // Store source post data as structured object
                $source_post_data = [
                    'title' => $parent_title,
                    'content' => $parent_body,
                    'id' => $post->post_parent
                ];
                $parent_content_included = true;
            }
        }
        
        // Extract site name for metadata only
        $site_name = get_bloginfo('name') ?: 'Local WordPress';

        // Build content data with parent post content when enabled (raw structured data)
        $content_data = [];
        if ($parent_content_included && isset($source_post_data)) {
            $content_data = [
                'title' => $source_post_data['title'],
                'content' => $source_post_data['content']  // Raw content, no string processing
            ];
        }

        // Create structured media data for AI processing
        $media_data = [
            'title' => $title,
            'alt_text' => $alt_text,
            'caption' => $caption,
            'description' => $description,
            'file_type' => $file_type,
            'file_size' => $file_size,
            'file_size_formatted' => $file_size > 0 ? size_format($file_size) : null
        ];

        // Create standardized packet with file data at root level for AI processing
        $input_data = [
            'file_path' => $file_path,
            'file_name' => basename($file_path),
            'mime_type' => $file_type,
            'file_size' => $file_size,
            'data' => array_merge($content_data, ['file_info' => $media_data]),
            'metadata' => [
                'source_type' => 'wordpress_media',
                'item_identifier_to_log' => $post_id,
                'original_id' => $post_id,
                'parent_post_id' => $post->post_parent,
                'original_title' => $title,
                'original_date_gmt' => $post->post_date_gmt,
                'mime_type' => $file_type,
                'file_size' => $file_size,
                'site_name' => $site_name
            ]
        ];

        // Debug logging for data flow tracking with parent content details
        do_action('dm_log', 'debug', 'WordPress Media: Data packet created (attached media only)', [
            'post_id' => $post_id,
            'image_url' => wp_get_attachment_url($post_id),
            'parent_post_id' => $parent_post ? $parent_post->ID : null,
            'parent_post_title' => $parent_post ? $parent_post->post_title : null,
            'file_path' => $file_path,
            'file_exists' => $file_path ? file_exists($file_path) : false,
            'attached_media_confirmed' => true,
            'include_parent_content_setting' => $include_parent_content,
            'parent_content_included' => $parent_content_included
        ]);

        return $input_data;
    }

    /**
     * Build mime type query array from file type selections.
     *
     * @param array $file_types Selected file types.
     * @return array Mime type patterns.
     */
    private function build_mime_type_query(array $file_types): array {
        $mime_patterns = [];
        
        foreach ($file_types as $file_type) {
            switch ($file_type) {
                case 'image':
                    $mime_patterns[] = 'image/*';
                    break;
                case 'video':
                    $mime_patterns[] = 'video/*';
                    break;
                case 'audio':
                    $mime_patterns[] = 'audio/*';
                    break;
                case 'document':
                    $mime_patterns = array_merge($mime_patterns, [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'text/plain'
                    ]);
                    break;
            }
        }
        
        return $mime_patterns;
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
        return __('WordPress Media', 'data-machine');
    }
}