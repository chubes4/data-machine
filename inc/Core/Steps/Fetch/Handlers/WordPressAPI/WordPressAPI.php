<?php
/**
 * WordPress REST API Fetch Handler
 *
 * Fetches content from public WordPress sites via REST API endpoints.
 * Provides a modern alternative to RSS feeds with structured data access.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\WordPressAPI
 * @since      1.0.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPressAPI;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WordPressAPI {

    public function __construct() {
    }

    /**
     * Fetch WordPress API content with clean data for AI processing.
     * Returns processed items while storing engine data (source_url, image_url) in database.
     *
     * @param int $pipeline_id Pipeline ID for logging context.
     * @param array $handler_config Handler configuration including site_url, post_type, flow_step_id.
     * @param string|null $job_id Job ID for deduplication tracking.
     * @return array Array with 'processed_items' containing clean data for AI processing.
     *               Engine parameters (source_url, image_url) are stored in database via store_engine_data().
     */
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        if (empty($pipeline_id)) {
            do_action('dm_log', 'error', 'WordPress API Input: Missing pipeline ID.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }
        
        // Extract flow_step_id from handler config for processed items tracking
        $flow_step_id = $handler_config['flow_step_id'] ?? null;
        
        // Handle null flow_step_id gracefully - skip processed items tracking when flow context missing
        if ($flow_step_id === null) {
            do_action('dm_log', 'debug', 'WordPress API fetch called without flow_step_id - processed items tracking disabled');
        }

        // Access config from handler config structure
        $config = $handler_config['wordpress_api'] ?? [];
        
        // Configuration validation
        $site_url = trim($config['site_url'] ?? '');
        if (empty($site_url)) {
            do_action('dm_log', 'error', 'WordPress API Input: Site URL is required.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }
        
        // Clean up site URL
        $site_url = untrailingslashit($site_url);
        if (!filter_var($site_url, FILTER_VALIDATE_URL)) {
            do_action('dm_log', 'error', 'WordPress API Input: Invalid site URL format.', ['pipeline_id' => $pipeline_id, 'site_url' => $site_url]);
            return ['processed_items' => []];
        }

        // Fetch from remote WordPress site
        $items = $this->fetch_remote_data($pipeline_id, $config, $site_url, $flow_step_id, $job_id);
        
        return ['processed_items' => $items];
    }

    /**
     * Fetch data from remote WordPress site via REST API
     *
     * Makes HTTP request to WordPress REST API endpoint and processes response.
     * Returns first unprocessed item with automatic deduplication tracking.
     *
     * @param int $pipeline_id Pipeline execution identifier
     * @param array $config Handler configuration with site_url and filtering options
     * @param string $site_url Base URL of target WordPress site
     * @param string|null $flow_step_id Flow step ID for deduplication tracking
     * @param string|null $job_id Job ID for item tracking
     * @return array Array containing single eligible post data packet or empty array
     */
    private function fetch_remote_data(int $pipeline_id, array $config, string $site_url, ?string $flow_step_id = null, ?string $job_id = null): array {
        $post_type = sanitize_text_field($config['post_type'] ?? 'posts');
        $post_status = sanitize_text_field($config['post_status'] ?? 'publish');
        $timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
        $search = trim($config['search'] ?? '');
        $orderby = sanitize_text_field($config['orderby'] ?? 'date');
        $order = sanitize_text_field($config['order'] ?? 'desc');

        // Build REST API URL
        $api_url = $site_url . '/wp-json/wp/v2/' . $post_type;
        
        // Build query parameters
        $query_params = [
            'status' => $post_status,
            'orderby' => $orderby,
            'order' => $order,
            'per_page' => 10, // Fixed at 10 items per request
            '_embed' => 'true' // Include embedded data (featured images, etc.)
        ];

        // Add search parameter if specified
        if (!empty($search)) {
            $query_params['search'] = $search;
        }

        // Add date filtering if specified
        if ($timeframe_limit !== 'all_time') {
            $cutoff_timestamp = $this->calculate_cutoff_timestamp($timeframe_limit);
            if ($cutoff_timestamp !== null) {
                $query_params['after'] = gmdate('c', $cutoff_timestamp);
            }
        }

        // Build full URL with query parameters
        $request_url = add_query_arg($query_params, $api_url);

        // Make HTTP request using dm_request filter
        $args = [
            'user-agent' => 'DataMachine WordPress Plugin/' . DATA_MACHINE_VERSION
        ];

        $result = apply_filters('dm_request', null, 'GET', $request_url, $args, 'WordPress REST API');
        
        if (!$result['success']) {
            do_action('dm_log', 'error', 'WordPress API Input: Failed to fetch from REST API.', [
                'pipeline_id' => $pipeline_id,
                'error' => $result['error'],
                'request_url' => $request_url
            ]);
            return [];
        }

        $response_data = $result['data'];
        if (empty($response_data)) {
            do_action('dm_log', 'error', 'WordPress API Input: Empty response from REST API.', ['pipeline_id' => $pipeline_id, 'request_url' => $request_url]);
            return [];
        }

        // Parse JSON response
        $posts = json_decode($response_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            do_action('dm_log', 'error', 'WordPress API Input: Invalid JSON response.', [
                'pipeline_id' => $pipeline_id,
                'json_error' => json_last_error_msg(),
                'request_url' => $request_url
            ]);
            return [];
        }

        if (!is_array($posts) || empty($posts)) {
            return [];
        }

        // Find first unprocessed post
        foreach ($posts as $post) {
            $post_id = $post['id'] ?? 0;
            if (empty($post_id)) {
                continue;
            }

            // Create unique identifier combining site URL and post ID
            $unique_id = md5($site_url . '_' . $post_id);
            
            $is_processed = ($flow_step_id !== null) ? apply_filters('dm_is_item_processed', false, $flow_step_id, 'wordpress_api', $unique_id) : false;
            if ($is_processed) {
                continue;
            }
            
            // Found first eligible item - mark as processed and return
            if ($flow_step_id) {
                do_action('dm_mark_item_processed', $flow_step_id, 'wordpress_api', $unique_id, $job_id);
            }

            // Extract post data
            $title = $post['title']['rendered'] ?? 'N/A';
            $content = $post['content']['rendered'] ?? '';
            $excerpt = $post['excerpt']['rendered'] ?? '';
            $source_link = $post['link'] ?? '';
            $post_date = $post['date_gmt'] ?? null;

            // Extract featured image URL
            $image_url = $this->extract_featured_image_url($post);

            // Extract site name from site URL or post data
            $site_name = $this->extract_site_name($site_url, $post);
            $content_string = "Source: " . $site_name . "\n\nTitle: " . $title . "\n\n" . wp_strip_all_tags($content);

            // Create standardized packet and return immediately
            $input_data = [
                'data' => [
                    'content_string' => $content_string,
                    'file_info' => null
                ],
                'metadata' => [
                    'source_type' => 'wordpress_api',
                    'item_identifier_to_log' => $unique_id,
                    'original_id' => $post_id,
                    'original_title' => $title,
                    'original_date_gmt' => $post_date,
                    'excerpt' => wp_strip_all_tags($excerpt)
                ]
            ];

            // Store URLs in engine_data for centralized parameter injection
            if ($job_id) {
                $engine_data = [
                    'source_url' => $source_link,
                    'image_url' => $image_url ?: '',
                    'site_url' => $site_url
                ];

                // Store engine_data via database service
                $all_databases = apply_filters('dm_db', []);
                $db_jobs = $all_databases['jobs'] ?? null;
                if ($db_jobs) {
                    $db_jobs->store_engine_data($job_id, $engine_data);
                    do_action('dm_log', 'debug', 'WordPress API: Stored URLs in engine_data', [
                        'job_id' => $job_id,
                        'source_url' => $engine_data['source_url'],
                        'has_image_url' => !empty($engine_data['image_url']),
                        'site_url' => $engine_data['site_url']
                    ]);
                }
            }

            // Return clean data packet (no URLs in metadata for AI)
            return [
                'processed_items' => [$input_data]
            ];
        }

        // No eligible items found
        return ['processed_items' => []];
    }

    /**
     * Extract featured image URL from post data.
     *
     * @param array $post Post data from REST API.
     * @return string|null Featured image URL or null if none found.
     */
    private function extract_featured_image_url(array $post): ?string {
        // Try embedded media first
        if (isset($post['_embedded']['wp:featuredmedia'][0]['source_url'])) {
            return $post['_embedded']['wp:featuredmedia'][0]['source_url'];
        }

        // Fallback to featured_media ID (would require additional API call)
        $featured_media_id = $post['featured_media'] ?? 0;
        if ($featured_media_id > 0) {
            // For now, return null - we could implement additional API call here if needed
            return null;
        }

        return null;
    }

    /**
     * Extract site name from URL or post data.
     *
     * @param string $site_url Site URL.
     * @param array $post Post data.
     * @return string Site name.
     */
    private function extract_site_name(string $site_url, array $post): string {
        // Try to extract from URL
        $parsed_url = wp_parse_url($site_url);
        if (isset($parsed_url['host'])) {
            return $parsed_url['host'];
        }

        return $site_url;
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
        return __('WordPress REST API', 'data-machine');
    }
}