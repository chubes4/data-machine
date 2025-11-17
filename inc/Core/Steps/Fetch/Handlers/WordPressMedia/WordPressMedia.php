<?php
/**
 * WordPress media library fetch handler with parent content integration.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\WordPressMedia
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPressMedia;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WordPressMedia {

    /**
     * Fetch WordPress media with optional parent content inclusion.
     * Engine data (source_url, image_url) stored via datamachine_engine_data filter.
     */
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        if (empty($pipeline_id)) {
            do_action('datamachine_log', 'error', 'WordPress Media: Missing pipeline ID.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }
        
        // Extract flow_step_id from handler config for processed items tracking
        $flow_step_id = $handler_config['flow_step_id'] ?? null;
        
        // Handle null flow_step_id gracefully - skip processed items tracking when flow context missing
        if ($flow_step_id === null) {
            do_action('datamachine_log', 'debug', 'WordPress Media fetch called without flow_step_id - processed items tracking disabled');
        }
        
        $user_id = get_current_user_id();

        // Access config from handler config structure
        $config = $handler_config['wordpress_media'] ?? [];

        return $this->fetch_media_data($pipeline_id, $config, $user_id, $flow_step_id, $job_id);
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
        $cutoff_timestamp = apply_filters('datamachine_timeframe_limit', null, $timeframe_limit);
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

        // Add search term if specified - use server-side for single terms, client-side for comma-separated
        $use_client_side_search = false;
        if (!empty($search)) {
            if (strpos($search, ',') !== false) {
                // Comma-separated keywords detected - use client-side filtering
                $use_client_side_search = true;
            } else {
                // Single term - use efficient server-side search
                $query_args['s'] = $search;
            }
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
            $is_processed = ($flow_step_id !== null) ? apply_filters('datamachine_is_item_processed', false, $flow_step_id, 'wordpress_media', $post_id) : false;
            if ($is_processed) {
                continue;
            }

            // Apply client-side keyword search filter if needed
            if ($use_client_side_search && !empty($search)) {
                $search_text = $post->post_title . ' ' . wp_strip_all_tags($post->post_content . ' ' . $post->post_excerpt);
                $matches = apply_filters('datamachine_keyword_search_match', false, $search_text, $search);
                if (!$matches) {
                    continue; // Skip media that don't match search keywords
                }
            }

            // Found first eligible item - mark as processed and return
            if ($flow_step_id) {
                do_action('datamachine_mark_item_processed', $flow_step_id, 'wordpress_media', $post_id, $job_id);
            }

            // Extract media data using universal pattern (identical to all other handlers)
            $post_id = $post->ID;
            $title = $post->post_title ?: 'N/A';
            $caption = $post->post_excerpt ?: '';
            $description = $post->post_content ?: '';
            $alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true) ?: '';
            $file_type = get_post_mime_type($post_id) ?: 'unknown';
            $file_path = get_attached_file($post_id);
            $file_size = $file_path && file_exists($file_path) ? filesize($file_path) : 0;
            $site_name = get_bloginfo('name') ?: 'Local WordPress';

            // Handle parent post content if enabled
            $content_data = [];
            $parent_post = null;
            if ($include_parent_content && $post->post_parent > 0) {
                $parent_post = get_post($post->post_parent);
                if ($parent_post) {
                    $content_data = [
                        'title' => $parent_post->post_title ?: 'Untitled',
                        'content' => $parent_post->post_content ?: ''
                    ];
                }
            }

            // Create file info for AI processing (contains actual media data)
            $file_info = [
                'file_path' => $file_path,
                'file_name' => basename($file_path),
                'title' => $title,
                'alt_text' => $alt_text,
                'caption' => $caption,
                'description' => $description,
                'file_type' => $file_type,
                'mime_type' => $file_type,
                'file_size' => $file_size,
                'file_size_formatted' => $file_size > 0 ? size_format($file_size) : null
            ];

            // Create metadata (no URLs, clean for AI)
            $metadata = [
                'source_type' => 'wordpress_media',
                'item_identifier_to_log' => $post_id,
                'original_id' => $post_id,
                'parent_post_id' => $post->post_parent,
                'original_title' => $title,
                'original_date_gmt' => $post->post_date_gmt,
                'mime_type' => $file_type,
                'file_size' => $file_size,
                'site_name' => $site_name
            ];

            // Create clean data packet for AI processing (matches datamachine_create_data_packet action output)
            $input_data = [
                'data' => array_merge($content_data, ['file_info' => $file_info]),
                'metadata' => $metadata
            ];

            // Store URLs in engine_data via centralized filter
            if ($job_id) {
                $image_url = wp_get_attachment_url($post_id);
                $source_url = '';
                if ($include_parent_content && $post->post_parent > 0) {
                    $source_url = get_permalink($post->post_parent) ?: '';
                }
                apply_filters('datamachine_engine_data', null, $job_id, [
                    'source_url' => $source_url,
                    'image_url' => $image_url ?: ''
                ]);
            }

            return ['processed_items' => [$input_data]];
        }

        // No eligible items found
        return ['processed_items' => []];
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
     * Get the user-friendly label for this handler.
     *
     * @return string Handler label.
     */
    public static function get_label(): string {
        return __('WordPress Media', 'datamachine');
    }
}