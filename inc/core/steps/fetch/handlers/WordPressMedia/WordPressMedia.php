<?php
/**
 * WordPress Media fetch handler.
 *
 * Handles WordPress media library content using WP_Query for attachment post type.
 * Specialized for media files with proper URL extraction and metadata handling.
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

    /**
     * Parameter-less constructor for pure filter-based architecture.
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }

    /**
     * Fetches and prepares WordPress media data from local installation into a standardized format.
     *
     * @param int $pipeline_id The pipeline ID for this execution context.
     * @param array  $handler_config Decoded handler configuration for the specific pipeline run.
     * @param string|null $job_id The job ID for processed items tracking.
     * @return array Array with 'processed_items' key containing eligible items.
     * @throws Exception If fetch data is invalid or cannot be retrieved.
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

            return [$this->create_media_data_packet($post, $include_parent_content)];
        }

        // No eligible items found
        return [];
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
        if ($post->post_parent > 0) {
            $parent_post = get_post($post->post_parent);
            
            // Add parent content if requested
            if ($include_parent_content && $parent_post) {
                $parent_title = $parent_post->post_title ?: 'Untitled';
                $parent_body = $parent_post->post_content ?: '';
                $parent_content = "\n\nAttached to: {$parent_title}\n{$parent_body}";
            }
        }
        
        // Extract source name
        $site_name = get_bloginfo('name');
        $source_name = $site_name ?: 'Local WordPress';
        
        // Build content string with media information
        $content_parts = [
            "Source: {$source_name}",
            "Media Type: {$file_type}",
            "Title: {$title}"
        ];
        
        if (!empty($alt_text)) {
            $content_parts[] = "Alt Text: {$alt_text}";
        }
        
        if (!empty($caption)) {
            $content_parts[] = "Caption: {$caption}";
        }
        
        if (!empty($description)) {
            $content_parts[] = "Description: {$description}";
        }
        
        $content_parts[] = "File URL: {$media_url}";
        
        if ($file_size > 0) {
            $file_size_formatted = size_format($file_size);
            $content_parts[] = "File Size: {$file_size_formatted}";
        }
        
        $content_string = implode("\n", $content_parts) . $parent_content;

        // Create standardized packet
        $input_data = [
            'data' => [
                'content_string' => $content_string,
                'file_info' => null
            ],
            'metadata' => [
                'source_type' => 'wordpress_media',
                'item_identifier_to_log' => $post_id,
                'original_id' => $post_id,
                'source_url' => get_permalink($parent_post),
                'original_title' => $title,
                'image_source_url' => $media_url && filter_var($media_url, FILTER_VALIDATE_URL) ? $media_url : wp_get_attachment_url($post_id), // URL for publish handlers
                'file_path' => $file_path,        // Local path for AI processing
                'mime_type' => $file_type,        // Required by AI Step
                'original_date_gmt' => $post->post_date_gmt,
                'file_type' => $file_type,        // Keep for compatibility
                'file_size' => $file_size
            ]
        ];
        
        // Debug logging for data flow tracking
        do_action('dm_log', 'debug', 'WordPress Media: Data packet created (attached media only)', [
            'post_id' => $post_id,
            'media_url' => $media_url,
            'parent_post_id' => $parent_post->ID, // Now guaranteed to exist due to query filter
            'parent_post_title' => $parent_post->post_title,
            'source_url' => $input_data['metadata']['source_url'],
            'image_source_url' => $input_data['metadata']['image_source_url'],
            'file_path' => $file_path,
            'file_exists' => $file_path ? file_exists($file_path) : false,
            'attached_media_confirmed' => true
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