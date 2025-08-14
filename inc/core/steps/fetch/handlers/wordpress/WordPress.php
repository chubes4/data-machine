<?php
/**
 * Unified WordPress fetch handler.
 *
 * Handles WordPress content from multiple sources:
 * - Local WordPress (WP_Query)
 * - Remote WordPress (Standard REST API)
 * - Remote WordPress (Airdrop Helper Plugin)
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/inc/core/steps/fetch/handlers
 * @since      1.0.0
 */

namespace DataMachine\Core\Handlers\Fetch\WordPress;

use DataMachine\Core\Database\RemoteLocations;
use Exception;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WordPress {

    /**
     * Parameter-less constructor for pure filter-based architecture.
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }

    /**
     * Get service via filter system.
     *
     * @param string $service_name Service name.
     * @return mixed Service instance.
     */
    private function get_service(string $service_name) {
        return apply_filters('dm_get_' . $service_name, null);
    }

    /**
     * Get the remote locations database service.
     *
     * @return RemoteLocations The remote locations database service.
     */
    protected function get_db_remote_locations() {
        $all_databases = apply_filters('dm_db', []);
        return $all_databases['remote_locations'] ?? null;
    }

    /**
     * Fetches and prepares WordPress fetch data from various sources into a standardized format.
     *
     * @param int $pipeline_id The pipeline ID for this execution context.
     * @param array  $handler_config Decoded handler configuration for the specific pipeline run.
     * @param int|null $flow_id The flow ID for processed items tracking.
     * @return array Array with 'processed_items' key containing eligible items.
     * @throws Exception If fetch data is invalid or cannot be retrieved.
     */
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?int $flow_id = null, int $job_id = 0): array {
        if (empty($pipeline_id)) {
            throw new Exception(esc_html__('Missing pipeline ID.', 'data-machine'));
        }
        
        // Extract flow_step_id from handler config for processed items tracking
        $flow_step_id = $handler_config['flow_step_id'] ?? null;
        
        // Handle null flow_id gracefully - skip processed items tracking when flow context missing
        if ($flow_id === null) {
            do_action('dm_log', 'debug', 'WordPress fetch called without flow_id - processed items tracking disabled');
        }
        
        $user_id = get_current_user_id();

        // Access config from handler config structure
        $config = $handler_config['wordpress'] ?? [];
        
        // Determine source type
        $source_type = $config['source_type'] ?? 'local';
        
        switch ($source_type) {
            case 'local':
                $items = $this->fetch_local_data($pipeline_id, $config, $user_id);
                break;
            
            case 'remote_rest':
                $items = $this->fetch_remote_rest_data($pipeline_id, $config, $user_id);
                break;
            
            case 'remote_airdrop':
                $items = $this->fetch_remote_airdrop_data($pipeline_id, $config, $user_id);
                break;
            
            default:
                throw new Exception(esc_html__('Invalid WordPress source type specified.', 'data-machine'));
        }
        
        return ['processed_items' => $items];
    }

    /**
     * Fetch data from local WordPress installation.
     *
     * @param int   $pipeline_id Module ID for tracking processed items.
     * @param array $config Configuration array.
     * @param int   $user_id User ID for context.
     * @return array Array of item data packets.
     * @throws Exception If data cannot be retrieved.
     */
    private function fetch_local_data(int $pipeline_id, array $config, int $user_id): array {
        $post_type = sanitize_text_field($config['post_type'] ?? 'post');
        $post_status = sanitize_text_field($config['post_status'] ?? 'publish');
        $category_id = absint($config['category_id'] ?? 0);
        $tag_id = absint($config['tag_id'] ?? 0);
        $orderby = sanitize_text_field($config['orderby'] ?? 'date');
        $order = sanitize_text_field($config['order'] ?? 'DESC');

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

        // Add category filter if specified
        if ($category_id > 0) {
            $query_args['cat'] = $category_id;
        }

        // Add tag filter if specified
        if ($tag_id > 0) {
            $query_args['tag_id'] = $tag_id;
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
            $is_processed = ($flow_id !== null) ? apply_filters('dm_is_item_processed', false, $flow_id, 'wordpress_local', $post_id) : false;
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
            $image_url = $this->extract_image_url($post_id, $content);

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
     * Fetch data from remote WordPress via standard REST API.
     *
     * @param int   $pipeline_id Module ID for tracking processed items.
     * @param array $config Configuration array.
     * @param int   $user_id User ID for context.
     * @return array Array of item data packets.
     * @throws Exception If data cannot be retrieved.
     */
    private function fetch_remote_rest_data(int $pipeline_id, array $config, int $user_id): array {
        $api_endpoint_url = $config['api_endpoint_url'] ?? '';
        $data_path = $config['data_path'] ?? '';
        
        // Direct config parsing
        $timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
        $search_term = trim($config['search'] ?? '');
        
        // Always order by date descending
        $orderby = 'date';
        $order = 'desc';
        $fetch_batch_size = 10; // Simple batch size for finding first eligible item

        $cutoff_timestamp = null;
        if ($timeframe_limit !== 'all_time') {
            $interval_map = [
                '24_hours' => '-24 hours',
                '72_hours' => '-72 hours',
                '7_days'   => '-7 days',
                '30_days'  => '-30 days'
            ];
            if (isset($interval_map[$timeframe_limit])) {
                $cutoff_timestamp = strtotime($interval_map[$timeframe_limit], current_time('timestamp', true));
            }
        }

        $query_params = [
            'per_page' => $fetch_batch_size,
            'orderby' => $orderby,
            'order' => $order,
            '_embed' => 'true'
        ];
        if (!empty($search_term)) {
            $query_params['search'] = $search_term;
        }
        $next_page_url = add_query_arg(array_filter($query_params, function($value) { return $value !== null && $value !== ''; }), $api_endpoint_url);

        $pages_fetched = 0;
        $max_pages = 5; // Simple pagination safety limit
        $hit_time_limit_boundary = false;

        do_action('dm_log', 'debug', 'WordPress REST Input: Initial fetch URL', ['url' => $next_page_url, 'module_id' => $pipeline_id]);

        while ($next_page_url && $pages_fetched < $max_pages) {
            $pages_fetched++;
            do_action('dm_log', 'debug', 'WordPress REST Input: Fetching page', ['page' => $pages_fetched, 'url' => $next_page_url, 'module_id' => $pipeline_id]);

            // Use dm_request filter for REST API call
            $result = apply_filters('dm_request', null, 'GET', $next_page_url, [], 'WordPress API');
            
            if (!$result['success']) {
                if ($pages_fetched === 1) throw new Exception(esc_html($result['error']));
                else break;
            }

            // Parse JSON response with error handling
            $response_data = json_decode($result['data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_message = sprintf(__('Invalid JSON from WordPress REST API: %s', 'data-machine'), json_last_error_msg());
                if ($pages_fetched === 1) throw new Exception(esc_html($error_message));
                else break;
            }

            $response_headers = $result['headers'];

            // Extract items array using data_path if provided, or auto-detect first array
            $items = [];
            if (!empty($data_path)) {
                $parts = explode('.', $data_path);
                $items_ref = $response_data;
                foreach ($parts as $part) {
                    if (is_array($items_ref) && isset($items_ref[$part])) {
                        $items_ref = $items_ref[$part];
                    } else {
                        $items_ref = [];
                        break;
                    }
                }
                if (is_array($items_ref)) {
                    $items = $items_ref;
                }
            } else {
                $items = $this->find_first_array_of_objects($response_data);
            }
            
            if (empty($items) || !is_array($items)) {
                break;
            }
            
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                
                $current_item_id = $item['uuid'] ?? $item['id'] ?? $item['ID'] ?? null;
                if (empty($current_item_id)) {
                    continue;
                }
                
                // Date handling
                $item_timestamp = $this->extract_item_timestamp($item);
                if ($cutoff_timestamp !== null) {
                    if ($item_timestamp === false) {
                        continue;
                    }
                    if ($item_timestamp < $cutoff_timestamp) {
                        if ($orderby === 'date' && $order === 'desc') {
                            $hit_time_limit_boundary = true;
                        }
                        continue;
                    }
                }
                
                // Local search term filtering
                if (!empty($search_term) && !$this->matches_search_terms($item, $search_term)) {
                    continue;
                }
                
                $is_processed = ($flow_id !== null) ? apply_filters('dm_is_item_processed', false, $flow_id, 'wordpress_remote_rest', $current_item_id) : false;
                if ($is_processed) {
                    continue;
                }
                
                // Mark item as processed immediately after confirming eligibility
                if ($flow_step_id) {
                    do_action('dm_mark_item_processed', $flow_step_id, 'wordpress_remote_rest', $current_item_id, $job_id);
                }
                
                // Data extraction
                $title = $item['title']['rendered'] ?? $item['title'] ?? $item['headline'] ?? 'N/A';
                $content_parts = $item['content'] ?? [];
                $prologue = $item['prologue'] ?? '';
                $full_content_html = $prologue;
                if (is_array($content_parts)) {
                    $full_content_html .= implode("\n", $content_parts);
                } elseif (is_string($content_parts)) {
                    $full_content_html .= $content_parts;
                }
                if (empty(trim(wp_strip_all_tags($full_content_html)))) {
                    $content_fallback = $item['content']['rendered'] ?? $item['excerpt'] ?? '';
                    if(is_string($content_fallback)) {
                        $full_content_html = $content_fallback;
                    }
                }
                $source_link = $item['url'] ?? $item['link'] ?? $item['permalink'] ?? $api_endpoint_url;
                $original_date_string_for_meta = $this->extract_original_date_string($item);
                
                // Extract source name from API endpoint URL
                $api_host = wp_parse_url($api_endpoint_url, PHP_URL_HOST);
                $source_name = $api_host ? ucwords(str_replace(['www.', '.com', '.org', '.net'], '', $api_host)) : 'Unknown Source';
                $content_string = "Source: " . $source_name . "\n\nTitle: " . $title . "\n\n" . wp_strip_all_tags($full_content_html);
                
                $input_data = [
                    'data' => [
                        'content_string' => $content_string,
                        'file_info' => null
                    ],
                    'metadata' => [
                        'source_type' => 'wordpress_remote_rest',
                        'item_identifier_to_log' => $current_item_id,
                        'original_id' => $current_item_id,
                        'source_url' => $source_link,
                        'original_title' => $title,
                        'original_date_gmt' => $original_date_string_for_meta,
                    ]
                ];
                do_action('dm_log', 'debug', 'WordPress REST Input: Found first eligible item', ['item_id' => $current_item_id, 'title' => $title, 'module_id' => $pipeline_id]);
                // Return first eligible item immediately
                return [$input_data];
            }
            
            $next_page_url = null;
            if (isset($response_headers['link'])) {
                $links = explode(',', $response_headers['link']);
                foreach ($links as $link_header) {
                    if (preg_match('/<([^>]+)>;\s*rel="next"/i', $link_header, $matches)) {
                        $next_page_url = trim($matches[1]);
                        break;
                    }
                }
            }
            if ($hit_time_limit_boundary) {
                break;
            }
        }
        
        // No eligible items found
        do_action('dm_log', 'debug', __('No new items found matching the criteria from the API endpoint.', 'data-machine'));
        return [];
    }

    /**
     * Fetch data from remote WordPress via Airdrop helper plugin.
     *
     * @param int   $pipeline_id Module ID for tracking processed items.
     * @param array $config Configuration array.
     * @param int   $user_id User ID for context.
     * @return array Array of item data packets.
     * @throws Exception If data cannot be retrieved.
     */
    private function fetch_remote_airdrop_data(int $pipeline_id, array $config, int $user_id): array {
        $location_id = absint($config['location_id'] ?? 0);
        if (empty($location_id)) {
            throw new Exception(esc_html__('No Remote Location selected for Airdrop REST API.', 'data-machine'));
        }

        // Get Remote Location details
        $db_remote_locations = $this->get_db_remote_locations();
        if (!$db_remote_locations) {
            throw new Exception(esc_html__('Remote Locations database service not available.', 'data-machine'));
        }
        $location = $db_remote_locations->get_location($location_id, $user_id, true);
        if (!$location) {
            /* translators: %d: Remote Location ID number */
            throw new Exception(sprintf(esc_html__('Could not retrieve details for Remote Location ID: %d.', 'data-machine'), esc_html($location_id)));
        }

        // Extract connection details from the location object
        $endpoint_url_base = trim($location->target_site_url ?? '');
        $remote_user = trim($location->target_username ?? '');
        $remote_password = $location->password ?? null;

        // Direct config parsing
        $timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
        $fetch_batch_size = 10; // Simple batch size for finding first eligible item

        if (empty($endpoint_url_base) || !filter_var($endpoint_url_base, FILTER_VALIDATE_URL)) {
            /* translators: %s: Remote Location name */
            throw new Exception(sprintf(esc_html__('Invalid Target Site URL configured for Remote Location: %s.', 'data-machine'), esc_html($location->location_name ?? $location_id)));
        }
        if (empty($remote_user) || empty($remote_password)) {
            /* translators: %s: Remote Location name */
            throw new Exception(sprintf(esc_html__('Missing username or application password for Remote Location: %s.', 'data-machine'), esc_html($location->location_name ?? $location_id)));
        }

        // Calculate cutoff timestamp
        $cutoff_timestamp = $this->calculate_cutoff_timestamp($timeframe_limit);

        $api_url_base = trailingslashit($endpoint_url_base) . 'wp-json/dma/v1/query-posts';

        $post_type = $config['rest_post_type'] ?? 'post';
        $post_status = $config['rest_post_status'] ?? 'publish';
        $category_id = $config['rest_category'] ?? 0;
        $tag_id = $config['rest_tag'] ?? 0;
        $orderby = $config['rest_orderby'] ?? 'date';
        $order = $config['rest_order'] ?? 'DESC';
        $search = $config['search'] ?? null;

        $current_page = 1;
        $max_pages = 5; // Simple pagination safety limit
        $hit_time_limit_boundary = false;
        $auth_header = 'Basic ' . base64_encode($remote_user . ':' . $remote_password);

        while ($current_page <= $max_pages && !$hit_time_limit_boundary) {
            $query_params = [
                'post_type' => $post_type,
                'post_status' => $post_status,
                'category' => $category_id ?: null,
                'tag' => $tag_id ?: null,
                'posts_per_page' => $fetch_batch_size,
                'paged' => $current_page,
                'orderby' => $orderby,
                'order' => $order,
                's' => $search
            ];
            $current_api_url = add_query_arg(array_filter($query_params, function($value) { return $value !== null; }), $api_url_base);

            $args = array(
                'headers' => array('Authorization' => $auth_header)
            );

            // Use dm_request filter for Airdrop API call
            $result = apply_filters('dm_request', null, 'GET', $current_api_url, $args, 'WordPress API');
            
            if (!$result['success']) {
                if ($current_page === 1) throw new Exception(esc_html($result['error']));
                else break;
            }

            $response_headers = $result['headers'];
            $body = $result['data'];

            // Parse JSON response with error handling
            $response_data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_message = sprintf(__('Invalid JSON from Airdrop REST API: %s', 'data-machine'), json_last_error_msg());
                if ($current_page === 1) throw new Exception(esc_html($error_message));
                else break;
            }
            $posts_data = $response_data['posts'] ?? [];
            $post_count = is_array($posts_data) ? count($posts_data) : 0;

            if (empty($posts_data) || !is_array($posts_data)) {
                break;
            }

            foreach ($posts_data as $post) {
                if (!is_array($post) || empty($post['ID'])) {
                    continue;
                }

                // Check timeframe limit
                if ($cutoff_timestamp !== null) {
                    if (empty($post['post_date_gmt'])) {
                        continue;
                    }
                    $item_timestamp = strtotime($post['post_date_gmt']);
                    if ($item_timestamp === false || $item_timestamp < $cutoff_timestamp) {
                        if ($orderby === 'date' && $order === 'DESC') {
                            $hit_time_limit_boundary = true;
                        }
                        continue;
                    }
                }

                $current_item_id = $post['ID'];
                $is_processed = ($flow_id !== null) ? apply_filters('dm_is_item_processed', false, $flow_id, 'wordpress_remote_airdrop', $current_item_id) : false;
                if ($is_processed) {
                    continue;
                }
                
                // Mark item as processed immediately after confirming eligibility
                if ($flow_step_id) {
                    do_action('dm_mark_item_processed', $flow_step_id, 'wordpress_remote_airdrop', $current_item_id, $job_id);
                }

                $title = $post['post_title'] ?? 'N/A';
                $content = $post['post_content'] ?? '';
                $source_link = $post['guid'] ?? $endpoint_url_base;
                $image_url = $post['featured_image_url'] ?? null;

                // Fallback: Try to get the first image from content if no featured image
                if (empty($image_url) && !empty($content)) {
                    if (preg_match('/<img.*?src=[\'"]([^\'"]*)[\'"]/i', $content, $matches)) {
                        $first_image_src = $matches[1];
                        // Basic validation - check if it looks like a URL
                        if (filter_var($first_image_src, FILTER_VALIDATE_URL)) {
                            $image_url = $first_image_src;
                            do_action('dm_log', 'debug', 'WordPress Airdrop Input: Using first image from content as fallback.', ['found_url' => $image_url, 'item_id' => $current_item_id]);
                        }
                    }
                }

                // Extract source name from URL host
                $source_host = wp_parse_url($source_link, PHP_URL_HOST);
                $source_name = $source_host ? ucwords(str_replace(['www.', '.com', '.org', '.net'], '', $source_host)) : 'Unknown Source';
                $content_string = "Source: " . $source_name . "\n\nTitle: " . $title . "\n\n" . $content;

                // Create standardized packet
                $input_data = [
                    'data' => [
                        'content_string' => $content_string,
                        'file_info' => null
                    ],
                    'metadata' => [
                        'source_type' => 'wordpress_remote_airdrop',
                        'item_identifier_to_log' => $current_item_id,
                        'original_id' => $current_item_id,
                        'source_url' => $source_link,
                        'original_title' => $title,
                        'image_source_url' => $image_url,
                        'original_date_gmt' => $post['post_date_gmt'] ?? null
                    ]
                ];
                // Found first eligible item - return immediately
                return [$input_data];
            }

            // Check if we should stop pagination early
            $total_pages = $response_data['max_num_pages'] ?? ($response_headers['x-wp-totalpages'] ?? null);
            if ($total_pages !== null && $current_page >= (int)$total_pages) {
                do_action('dm_log', 'debug', 'Handler: Reached max pages from API response', ['current_page' => $current_page, 'max_pages' => $total_pages]);
                break;
            }

            // Stop if we've hit time boundary
            if ($hit_time_limit_boundary) {
                break;
            }

            $current_page++;
        }

        // No eligible items found
        return [];
    }

    /**
     * Extract image URL from post (featured image with content fallback).
     *
     * @param int    $post_id Post ID.
     * @param string $content Post content.
     * @return string|null Image URL or null if none found.
     */
    private function extract_image_url(int $post_id, string $content): ?string {
        $featured_image_id = get_post_thumbnail_id($post_id);
        $image_url = $featured_image_id ? wp_get_attachment_image_url($featured_image_id, 'full') : null;

        // Fallback: Try to get the first image from content if no featured image
        if (empty($image_url) && !empty($content)) {
            if (preg_match('/<img.*?src=[\'"]([^\'"]*)[\'"]/i', $content, $matches)) {
                $first_image_src = $matches[1];
                // Basic validation - check if it looks like a URL
                if (filter_var($first_image_src, FILTER_VALIDATE_URL)) {
                    $image_url = $first_image_src;
                    do_action('dm_log', 'debug', 'WordPress Input: Using first image from content as fallback.', ['found_url' => $image_url, 'post_id' => $post_id]);
                }
            }
        }

        return $image_url;
    }

    /**
     * Extract timestamp from REST API item data.
     *
     * @param array $item Item data from REST API.
     * @return int|false Timestamp or false if not found.
     */
    private function extract_item_timestamp(array $item) {
        $item_timestamp = false;
        $original_date_value = null;
        
        if (isset($item['starttime']) && is_array($item['starttime'])) {
            if (!empty($item['starttime']['iso8601'])) {
                $original_date_value = $item['starttime']['iso8601'];
                $item_timestamp = strtotime($original_date_value);
            } elseif (!empty($item['starttime']['rfc2822'])) {
                $original_date_value = $item['starttime']['rfc2822'];
                $item_timestamp = strtotime($original_date_value);
            } elseif (!empty($item['starttime']['utc'])) {
                $original_date_value = $item['starttime']['utc'];
                $item_timestamp = is_numeric($original_date_value) ? (int)($original_date_value / 1000) : false;
                if ($item_timestamp !== false) $original_date_value = gmdate('Y-m-d\TH:i:s\Z', $item_timestamp);
            }
        }
        
        if ($item_timestamp === false) {
            $date_gmt = $item['date_gmt'] ?? $item['post_date_gmt'] ?? $item['post_date'] ?? null;
            if (empty($date_gmt) && isset($item['meta_parts']['post_date_formatted'])) {
                $date_gmt = $item['meta_parts']['post_date_formatted'];
                $item_timestamp = strtotime($date_gmt);
            } else {
                $item_timestamp = $date_gmt ? strtotime($date_gmt) : false;
            }
        }
        
        return $item_timestamp;
    }

    /**
     * Extract original date string from REST API item data.
     *
     * @param array $item Item data from REST API.
     * @return string|null Original date string or null if not found.
     */
    private function extract_original_date_string(array $item): ?string {
        if (isset($item['starttime']) && is_array($item['starttime'])) {
            if (!empty($item['starttime']['iso8601'])) {
                return $item['starttime']['iso8601'];
            } elseif (!empty($item['starttime']['rfc2822'])) {
                return $item['starttime']['rfc2822'];
            } elseif (!empty($item['starttime']['utc'])) {
                $original_date_value = $item['starttime']['utc'];
                $item_timestamp = is_numeric($original_date_value) ? (int)($original_date_value / 1000) : false;
                if ($item_timestamp !== false) return gmdate('Y-m-d\TH:i:s\Z', $item_timestamp);
            }
        }
        
        $date_gmt = $item['date_gmt'] ?? $item['post_date_gmt'] ?? $item['post_date'] ?? null;
        if (empty($date_gmt) && isset($item['meta_parts']['post_date_formatted'])) {
            return $item['meta_parts']['post_date_formatted'];
        }
        
        return $date_gmt;
    }

    /**
     * Check if item matches search terms.
     *
     * @param array  $item Item data.
     * @param string $search_term Search term(s).
     * @return bool True if matches, false otherwise.
     */
    private function matches_search_terms(array $item, string $search_term): bool {
        $keywords = array_map('trim', explode(',', $search_term));
        $keywords = array_filter($keywords);
        
        if (empty($keywords)) {
            return true;
        }
        
        $title_to_check = $item['title']['rendered'] ?? $item['title'] ?? $item['headline'] ?? '';
        $content_raw = $item['content']['rendered'] ?? $item['content'] ?? $item['excerpt'] ?? '';
        $prologue_raw = $item['prologue'] ?? '';
        $content_html = $prologue_raw;
        
        if (is_array($content_raw)) {
            $content_html .= implode("\n", $content_raw);
        } elseif (is_string($content_raw)) {
            $content_html .= $content_raw;
        }
        
        $text_to_search = $title_to_check . ' ' . wp_strip_all_tags($content_html);
        
        foreach ($keywords as $keyword) {
            if (mb_stripos($text_to_search, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Recursively find the first array of objects in a JSON structure.
     *
     * @param mixed $data Data to search.
     * @return array First array of objects found.
     */
    protected function find_first_array_of_objects($data): array {
        $title_keys = ['title', 'title.rendered', 'headline'];
        
        if (is_array($data)) {
            if (!empty($data) && is_array(reset($data)) && array_keys(reset($data)) !== range(0, count(reset($data)) - 1)) {
                foreach ($data as $obj) {
                    if (is_array($obj)) {
                        foreach ($title_keys as $key) {
                            if (isset($obj[$key])) {
                                return $data;
                            }
                            if ($key === 'title.rendered' && isset($obj['title']) && is_array($obj['title']) && isset($obj['title']['rendered'])) {
                                return $data;
                            }
                        }
                    }
                }
            }
            
            foreach ($data as $value) {
                $result = $this->find_first_array_of_objects($value);
                if (!empty($result)) {
                    return $result;
                }
            }
        }
        
        return [];
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
        return __('WordPress', 'data-machine');
    }
}

