<?php
/**
 * Unified WordPress input handler.
 *
 * Handles WordPress content from multiple sources:
 * - Local WordPress (WP_Query)
 * - Remote WordPress (Standard REST API)
 * - Remote WordPress (Airdrop Helper Plugin)
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/inc/core/handlers/input
 * @since      1.0.0
 */

namespace DataMachine\Core\Handlers\Input\WordPress;

use DataMachine\Core\Database\RemoteLocations;
use Exception;
use InvalidArgumentException;
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
        return apply_filters('dm_get_database_service', null, 'remote_locations');
    }

    /**
     * Fetches and prepares WordPress input data from various sources into a standardized format.
     *
     * @param object $module The full module object containing configuration and context.
     * @param array  $source_config Decoded data_source_config for the specific module run.
     * @param int    $user_id The ID of the user context.
     * @return array Array with 'processed_items' key containing eligible items.
     * @throws Exception If input data is invalid or cannot be retrieved.
     */
    public function get_input_data(object $module, array $source_config, int $user_id): array {
        // Direct filter-based validation
        $module_id = isset($module->module_id) ? absint($module->module_id) : 0;
        if (empty($module_id)) {
            throw new Exception(esc_html__('Missing module ID.', 'data-machine'));
        }
        
        // Validate user ID
        if (empty($user_id)) {
            throw new Exception(esc_html__('User ID not provided.', 'data-machine'));
        }

        // Access config from nested structure
        $config = $source_config['wordpress'] ?? [];
        
        // Determine source type
        $source_type = $config['source_type'] ?? 'local';
        
        switch ($source_type) {
            case 'local':
                $items = $this->fetch_local_data($module_id, $config, $user_id);
                break;
            
            case 'remote_rest':
                $items = $this->fetch_remote_rest_data($module_id, $config, $user_id);
                break;
            
            case 'remote_airdrop':
                $items = $this->fetch_remote_airdrop_data($module_id, $config, $user_id);
                break;
            
            default:
                throw new Exception(esc_html__('Invalid WordPress source type specified.', 'data-machine'));
        }
        
        return ['processed_items' => $items];
    }

    /**
     * Fetch data from local WordPress installation.
     *
     * @param int   $module_id Module ID for tracking processed items.
     * @param array $config Configuration array.
     * @param int   $user_id User ID for context.
     * @return array Array of item data packets.
     * @throws Exception If data cannot be retrieved.
     */
    private function fetch_local_data(int $module_id, array $config, int $user_id): array {
        $post_type = sanitize_text_field($config['post_type'] ?? 'post');
        $post_status = sanitize_text_field($config['post_status'] ?? 'publish');
        $category_id = absint($config['category_id'] ?? 0);
        $tag_id = absint($config['tag_id'] ?? 0);
        $orderby = sanitize_text_field($config['orderby'] ?? 'date');
        $order = sanitize_text_field($config['order'] ?? 'DESC');

        // Direct config parsing
        $process_limit = max(1, absint($config['item_count'] ?? 1));
        $timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
        $search = trim($config['search'] ?? '');

        // Calculate date query parameters
        $cutoff_timestamp = $this->calculate_cutoff_timestamp($timeframe_limit);
        $date_query = [];
        if ($cutoff_timestamp !== null) {
            $date_query = [
                [
                    'after' => date('Y-m-d H:i:s', $cutoff_timestamp),
                    'inclusive' => true,
                ]
            ];
        }

        // Build WP_Query arguments
        $query_args = [
            'post_type' => $post_type,
            'post_status' => $post_status,
            'posts_per_page' => $process_limit * 2, // Fetch more to account for already processed items
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
        $eligible_items_packets = [];
        foreach ($posts as $post) {
            if (count($eligible_items_packets) >= $process_limit) {
                break;
            }

            $post_id = $post->ID;
            $processed_items_manager = apply_filters('dm_get_processed_items_manager', null);
            if ($processed_items_manager && $processed_items_manager->is_item_processed($module_id, 'wordpress_local', $post_id)) {
                continue;
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
            $input_data_packet = [
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
            $eligible_items_packets[] = $input_data_packet;
        }

        if (empty($eligible_items_packets)) {
            return [];
        }

        // Return only the first item for "one coin, one operation" model
        return [$eligible_items_packets[0]];
    }

    /**
     * Fetch data from remote WordPress via standard REST API.
     *
     * @param int   $module_id Module ID for tracking processed items.
     * @param array $config Configuration array.
     * @param int   $user_id User ID for context.
     * @return array Array of item data packets.
     * @throws Exception If data cannot be retrieved.
     */
    private function fetch_remote_rest_data(int $module_id, array $config, int $user_id): array {
        $api_endpoint_url = $config['api_endpoint_url'] ?? '';
        $data_path = $config['data_path'] ?? '';
        
        // Direct config parsing
        $process_limit = max(1, absint($config['item_count'] ?? 1));
        $timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
        $search_term = trim($config['search'] ?? '');
        
        // Always order by date descending
        $orderby = 'date';
        $order = 'desc';
        $fetch_batch_size = min(100, max(10, $process_limit * 2));

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

        $eligible_items_packets = [];
        $pages_fetched = 0;
        $max_pages = 10;
        $hit_time_limit_boundary = false;

        $logger = apply_filters('dm_get_logger', null);
        $logger && $logger->info('WordPress REST Input: Initial fetch URL', ['url' => $next_page_url, 'module_id' => $module_id]);

        while ($next_page_url && count($eligible_items_packets) < $process_limit && $pages_fetched < $max_pages) {
            $pages_fetched++;
            $logger && $logger->debug('WordPress REST Input: Fetching page', ['page' => $pages_fetched, 'url' => $next_page_url, 'module_id' => $module_id]);

            // Use HTTP service via filter
            $http_service = apply_filters('dm_get_http_service', null);
            $http_response = $http_service->get($next_page_url, [], 'WordPress REST API');
            if (is_wp_error($http_response)) {
                if ($pages_fetched === 1) throw new Exception(esc_html($http_response->get_error_message()));
                else break;
            }

            // Parse JSON response with error handling
            $response_data = $http_service->parse_json($http_response['body'], 'WordPress REST API');
            if (is_wp_error($response_data)) {
                if ($pages_fetched === 1) throw new Exception(esc_html($response_data->get_error_message()));
                else break;
            }

            $response_headers = $http_response['headers'];

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
                
                $processed_items_manager = apply_filters('dm_get_processed_items_manager', null);
                if ($processed_items_manager && $processed_items_manager->is_item_processed($module_id, 'wordpress_remote_rest', $current_item_id)) {
                    continue;
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
                
                $input_data_packet = [
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
                $logger && $logger->debug('WordPress REST Input: Adding eligible item', ['item_id' => $current_item_id, 'title' => $title, 'module_id' => $module_id]);
                array_push($eligible_items_packets, $input_data_packet);
                if (count($eligible_items_packets) >= $process_limit) {
                    break;
                }
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
            if (count($eligible_items_packets) >= $process_limit || $hit_time_limit_boundary) {
                break;
            }
        }
        
        if (empty($eligible_items_packets)) {
            $logger && $logger->add_admin_info(__('No new items found matching the criteria from the API endpoint.', 'data-machine'));
            return [];
        }

        // Return only the first item for "one coin, one operation" model
        return [$eligible_items_packets[0]];
    }

    /**
     * Fetch data from remote WordPress via Airdrop helper plugin.
     *
     * @param int   $module_id Module ID for tracking processed items.
     * @param array $config Configuration array.
     * @param int   $user_id User ID for context.
     * @return array Array of item data packets.
     * @throws Exception If data cannot be retrieved.
     */
    private function fetch_remote_airdrop_data(int $module_id, array $config, int $user_id): array {
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
            throw new Exception(sprintf(esc_html__('Could not retrieve details for Remote Location ID: %d.', 'data-machine'), esc_html($location_id)));
        }

        // Extract connection details from the location object
        $endpoint_url_base = trim($location->target_site_url ?? '');
        $remote_user = trim($location->target_username ?? '');
        $remote_password = $location->password ?? null;

        // Direct config parsing
        $process_limit = max(1, absint($config['item_count'] ?? 1));
        $timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
        $fetch_batch_size = min(100, max(10, $process_limit * 2));

        if (empty($endpoint_url_base) || !filter_var($endpoint_url_base, FILTER_VALIDATE_URL)) {
            throw new Exception(sprintf(esc_html__('Invalid Target Site URL configured for Remote Location: %s.', 'data-machine'), esc_html($location->location_name ?? $location_id)));
        }
        if (empty($remote_user) || empty($remote_password)) {
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

        $eligible_items_packets = [];
        $current_page = 1;
        $max_pages = 10;
        $hit_time_limit_boundary = false;
        $items_added_this_page = 0;
        $auth_header = 'Basic ' . base64_encode($remote_user . ':' . $remote_password);

        while (count($eligible_items_packets) < $process_limit && $current_page <= $max_pages && !$hit_time_limit_boundary) {
            $items_added_this_page = 0; // Reset counter for each page
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

            // Use HTTP service via filter
            $http_service = apply_filters('dm_get_http_service', null);
            $http_response = $http_service->get($current_api_url, $args, 'Airdrop REST API');
            if (is_wp_error($http_response)) {
                if ($current_page === 1) throw new Exception(esc_html($http_response->get_error_message()));
                else break;
            }

            $response_headers = $http_response['headers'];
            $body = $http_response['body'];

            // Parse JSON response with error handling
            $response_data = $http_service->parse_json($body, 'Airdrop REST API');
            if (is_wp_error($response_data)) {
                if ($current_page === 1) throw new Exception(esc_html($response_data->get_error_message()));
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
                $processed_items_manager = apply_filters('dm_get_processed_items_manager', null);
                if ($processed_items_manager && $processed_items_manager->is_item_processed($module_id, 'wordpress_remote_airdrop', $current_item_id)) {
                    continue;
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
                            $logger = apply_filters('dm_get_logger', null);
                            $logger && $logger->debug('WordPress Airdrop Input: Using first image from content as fallback.', ['found_url' => $image_url, 'item_id' => $current_item_id]);
                        }
                    }
                }

                // Extract source name from URL host
                $source_host = wp_parse_url($source_link, PHP_URL_HOST);
                $source_name = $source_host ? ucwords(str_replace(['www.', '.com', '.org', '.net'], '', $source_host)) : 'Unknown Source';
                $content_string = "Source: " . $source_name . "\n\nTitle: " . $title . "\n\n" . $content;

                // Create standardized packet
                $input_data_packet = [
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
                array_push($eligible_items_packets, $input_data_packet);
                $items_added_this_page++;

                if (count($eligible_items_packets) >= $process_limit) {
                    break;
                }
            }

            // Check if we should stop pagination early
            $total_pages = $response_data['max_num_pages'] ?? ($response_headers['x-wp-totalpages'] ?? null);
            if ($total_pages !== null && $current_page >= (int)$total_pages) {
                $logger = apply_filters('dm_get_logger', null);
                $logger && $logger->info('Handler: Reached max pages from API response', ['current_page' => $current_page, 'max_pages' => $total_pages]);
                break;
            }

            // Stop if we've hit process limit or time boundary
            if (count($eligible_items_packets) >= $process_limit || $hit_time_limit_boundary) {
                break;
            }

            // Stop after 1 empty page (no new items added) for efficiency
            if ($items_added_this_page === 0) {
                $logger = apply_filters('dm_get_logger', null);
                $logger && $logger->info('Handler: No new items on page, stopping pagination for efficiency', ['current_page' => $current_page, 'items_found_so_far' => count($eligible_items_packets)]);
                break;
            }

            $current_page++;
        }

        if (empty($eligible_items_packets)) {
            return [];
        }

        // Return only the first item for "one coin, one operation" model
        return [$eligible_items_packets[0]];
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
                    $logger = apply_filters('dm_get_logger', null);
                    $logger && $logger->debug('WordPress Input: Using first image from content as fallback.', ['found_url' => $image_url, 'post_id' => $post_id]);
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
     * Get settings fields specific to local WordPress.
     *
     * @return array Settings fields.
     */
    private static function get_local_fields(): array {
        // Get available post types
        $post_types = get_post_types(['public' => true], 'objects');
        $post_type_options = [];
        foreach ($post_types as $post_type) {
            $post_type_options[$post_type->name] = $post_type->label;
        }

        // Get categories
        $categories = get_categories(['hide_empty' => false]);
        $category_options = [0 => __('All Categories', 'data-machine')];
        foreach ($categories as $category) {
            $category_options[$category->term_id] = $category->name;
        }

        // Get tags
        $tags = get_tags(['hide_empty' => false]);
        $tag_options = [0 => __('All Tags', 'data-machine')];
        foreach ($tags as $tag) {
            $tag_options[$tag->term_id] = $tag->name;
        }

        return [
            'post_type' => [
                'type' => 'select',
                'label' => __('Post Type', 'data-machine'),
                'description' => __('Select the post type to fetch from the local site.', 'data-machine'),
                'options' => $post_type_options,
            ],
            'post_status' => [
                'type' => 'select',
                'label' => __('Post Status', 'data-machine'),
                'description' => __('Select the post status to fetch.', 'data-machine'),
                'options' => [
                    'publish' => __('Published', 'data-machine'),
                    'draft' => __('Draft', 'data-machine'),
                    'pending' => __('Pending', 'data-machine'),
                    'private' => __('Private', 'data-machine'),
                    'any' => __('Any', 'data-machine'),
                ],
            ],
            'category_id' => [
                'type' => 'select',
                'label' => __('Category', 'data-machine'),
                'description' => __('Optional: Filter by a specific category.', 'data-machine'),
                'options' => $category_options,
            ],
            'tag_id' => [
                'type' => 'select',
                'label' => __('Tag', 'data-machine'),
                'description' => __('Optional: Filter by a specific tag.', 'data-machine'),
                'options' => $tag_options,
            ],
            'orderby' => [
                'type' => 'select',
                'label' => __('Order By', 'data-machine'),
                'description' => __('Select the field to order results by.', 'data-machine'),
                'options' => [
                    'date' => __('Date', 'data-machine'),
                    'modified' => __('Modified Date', 'data-machine'),
                    'title' => __('Title', 'data-machine'),
                    'ID' => __('ID', 'data-machine'),
                ],
            ],
            'order' => [
                'type' => 'select',
                'label' => __('Order', 'data-machine'),
                'description' => __('Select the order direction.', 'data-machine'),
                'options' => [
                    'DESC' => __('Descending', 'data-machine'),
                    'ASC' => __('Ascending', 'data-machine'),
                ],
            ],
        ];
    }

    /**
     * Get settings fields specific to remote REST API.
     *
     * @return array Settings fields.
     */
    private static function get_remote_rest_fields(): array {
        return [
            'api_endpoint_url' => [
                'type' => 'url',
                'label' => __('API Endpoint URL', 'data-machine'),
                'description' => __('Enter the full URL of the WordPress REST API endpoint (e.g., https://example.com/wp-json/wp/v2/posts).', 'data-machine'),
                'required' => true,
            ],
            'data_path' => [
                'type' => 'text',
                'label' => __('Data Path (Optional)', 'data-machine'),
                'description' => __('If the items are nested within the JSON response, specify the path using dot notation (e.g., `data.items`). Leave empty to auto-detect the first array of objects.', 'data-machine'),
            ],
        ];
    }

    /**
     * Get settings fields specific to remote Airdrop.
     *
     * @param array $current_config Current configuration.
     * @return array Settings fields.
     */
    private static function get_remote_airdrop_fields(array $current_config = []): array {
        // Get remote locations service via filter system
        $db_remote_locations = apply_filters('dm_get_database_service', null, 'remote_locations');
        if (!$db_remote_locations) {
            throw new \Exception(esc_html__('Remote locations service not available. This indicates a core filter registration issue.', 'data-machine'));
        }
        $locations = $db_remote_locations->get_locations_for_current_user();

        $options = [0 => __('Select a Remote Location', 'data-machine')];
        foreach ($locations as $loc) {
            $options[$loc->location_id] = $loc->location_name . ' (' . $loc->target_site_url . ')';
        }

        $remote_post_types = ['post' => 'Posts', 'page' => 'Pages'];
        $remote_categories = [0 => __('All Categories', 'data-machine')];
        $remote_tags = [0 => __('All Tags', 'data-machine')];

        $selected_location_id = $current_config['location_id'] ?? 0;
        $sync_button_disabled = empty($selected_location_id);

        return [
            'location_id' => [
                'type' => 'select',
                'label' => __('Remote Location', 'data-machine'),
                'description' => __('Select the pre-configured remote WordPress site (using the Data Machine Airdrop helper plugin) to fetch data from.', 'data-machine'),
                'options' => $options,
            ],
            'sync_details' => [
                'type' => 'button',
                'label' => __('Sync Remote Details', 'data-machine'),
                'description' => __('Click to fetch available Post Types, Categories, and Tags from the selected remote location. Save settings after syncing.', 'data-machine'),
                'button_id' => 'dm-sync-airdrop-details-button',
                'button_text' => __('Sync Now', 'data-machine'),
                'button_class' => 'button dm-sync-button' . ($sync_button_disabled ? ' disabled' : ''),
                'feedback_id' => 'dm-sync-airdrop-feedback'
            ],
            'rest_post_type' => [
                'type' => 'select',
                'wrapper_id' => 'dm-airdrop-post-type-wrapper',
                'label' => __('Post Type', 'data-machine'),
                'description' => __('Select the post type to fetch from the remote site.', 'data-machine'),
                'options' => $remote_post_types,
            ],
            'rest_post_status' => [
                'type' => 'select',
                'label' => __('Post Status', 'data-machine'),
                'description' => __('Select the post status to fetch.', 'data-machine'),
                'options' => [
                    'publish' => __('Published', 'data-machine'),
                    'draft' => __('Draft', 'data-machine'),
                    'pending' => __('Pending', 'data-machine'),
                    'private' => __('Private', 'data-machine'),
                    'any' => __('Any', 'data-machine'),
                ],
            ],
            'rest_category' => [
                'type' => 'select',
                'wrapper_id' => 'dm-airdrop-category-wrapper',
                'label' => __('Category', 'data-machine'),
                'description' => __('Optional: Filter by a specific category ID from the remote site.', 'data-machine'),
                'options' => $remote_categories,
            ],
            'rest_tag' => [
                'type' => 'select',
                'wrapper_id' => 'dm-airdrop-tag-wrapper',
                'label' => __('Tag', 'data-machine'),
                'description' => __('Optional: Filter by a specific tag ID from the remote site.', 'data-machine'),
                'options' => $remote_tags,
            ],
            'rest_orderby' => [
                'type' => 'select',
                'label' => __('Order By', 'data-machine'),
                'description' => __('Select the field to order results by.', 'data-machine'),
                'options' => [
                    'date' => __('Date', 'data-machine'),
                    'modified' => __('Modified Date', 'data-machine'),
                    'title' => __('Title', 'data-machine'),
                    'ID' => __('ID', 'data-machine'),
                ],
            ],
            'rest_order' => [
                'type' => 'select',
                'label' => __('Order', 'data-machine'),
                'description' => __('Select the order direction.', 'data-machine'),
                'options' => [
                    'DESC' => __('Descending', 'data-machine'),
                    'ASC' => __('Ascending', 'data-machine'),
                ],
            ],
        ];
    }

    /**
     * Get common settings fields for all source types.
     *
     * @return array Settings fields.
     */
    private static function get_common_fields(): array {
        return [
            'item_count' => [
                'type' => 'number',
                'label' => __('Items to Process', 'data-machine'),
                'description' => __('Maximum number of *new* items to process per run.', 'data-machine'),
                'min' => 1,
                'max' => 100,
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
                'description' => __('Optional: Filter items using a search term.', 'data-machine'),
            ],
        ];
    }

    /**
     * Sanitize settings for the unified WordPress input handler.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     * @throws InvalidArgumentException If validation fails.
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        
        // Source type is required
        $sanitized['source_type'] = sanitize_text_field($raw_settings['source_type'] ?? 'local');
        if (!in_array($sanitized['source_type'], ['local', 'remote_rest', 'remote_airdrop'])) {
            throw new InvalidArgumentException(esc_html__('Invalid source type specified for WordPress handler.', 'data-machine'));
        }

        // Sanitize based on source type
        switch ($sanitized['source_type']) {
            case 'local':
                $sanitized = array_merge($sanitized, $this->sanitize_local_settings($raw_settings));
                break;
                
            case 'remote_rest':
                $sanitized = array_merge($sanitized, $this->sanitize_remote_rest_settings($raw_settings));
                break;
                
            case 'remote_airdrop':
                $sanitized = array_merge($sanitized, $this->sanitize_remote_airdrop_settings($raw_settings));
                break;
        }

        // Sanitize common fields
        $sanitized['item_count'] = max(1, absint($raw_settings['item_count'] ?? 1));
        $sanitized['timeframe_limit'] = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
        $sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? '');

        return $sanitized;
    }

    /**
     * Sanitize local WordPress settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     */
    private function sanitize_local_settings(array $raw_settings): array {
        return [
            'post_type' => sanitize_text_field($raw_settings['post_type'] ?? 'post'),
            'post_status' => sanitize_text_field($raw_settings['post_status'] ?? 'publish'),
            'category_id' => absint($raw_settings['category_id'] ?? 0),
            'tag_id' => absint($raw_settings['tag_id'] ?? 0),
            'orderby' => sanitize_text_field($raw_settings['orderby'] ?? 'date'),
            'order' => sanitize_text_field($raw_settings['order'] ?? 'DESC'),
        ];
    }

    /**
     * Sanitize remote REST API settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     * @throws InvalidArgumentException If API endpoint URL is missing.
     */
    private function sanitize_remote_rest_settings(array $raw_settings): array {
        $api_endpoint_url = esc_url_raw($raw_settings['api_endpoint_url'] ?? '');
        if (empty($api_endpoint_url)) {
            throw new InvalidArgumentException(esc_html__('API Endpoint URL is required for Remote REST API source type.', 'data-machine'));
        }

        return [
            'api_endpoint_url' => $api_endpoint_url,
            'data_path' => sanitize_text_field($raw_settings['data_path'] ?? ''),
        ];
    }

    /**
     * Sanitize remote Airdrop settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     * @throws InvalidArgumentException If location ID is missing.
     */
    private function sanitize_remote_airdrop_settings(array $raw_settings): array {
        $location_id = absint($raw_settings['location_id'] ?? 0);
        if (empty($location_id)) {
            throw new InvalidArgumentException(esc_html__('Remote Location is required for Airdrop source type.', 'data-machine'));
        }

        $sanitized = [
            'location_id' => $location_id,
            'rest_post_type' => sanitize_text_field($raw_settings['rest_post_type'] ?? 'post'),
            'rest_post_status' => sanitize_text_field($raw_settings['rest_post_status'] ?? 'publish'),
            'rest_category' => absint($raw_settings['rest_category'] ?? 0),
            'rest_tag' => absint($raw_settings['rest_tag'] ?? 0),
            'rest_orderby' => sanitize_text_field($raw_settings['rest_orderby'] ?? 'date'),
            'rest_order' => sanitize_text_field($raw_settings['rest_order'] ?? 'DESC'),
        ];

        // Sanitize custom_taxonomies if present
        if (!empty($raw_settings['custom_taxonomies']) && is_array($raw_settings['custom_taxonomies'])) {
            $sanitized_custom_taxonomies = [];
            foreach ($raw_settings['custom_taxonomies'] as $tax_slug => $term_id) {
                $sanitized_custom_taxonomies[sanitize_key($tax_slug)] = absint($term_id);
            }
            $sanitized['custom_taxonomies'] = $sanitized_custom_taxonomies;
        }

        return $sanitized;
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

// Self-register via universal parameter-based handler system
add_filter('dm_get_handlers', function($handlers, $type) {
    if ($type === 'input') {
        $handlers['wordpress'] = [
            'has_auth' => true,
            'label' => __('WordPress', 'data-machine')
        ];
    }
    return $handlers;
}, 10, 2);
