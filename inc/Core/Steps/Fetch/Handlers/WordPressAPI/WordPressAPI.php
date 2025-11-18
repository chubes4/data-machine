<?php
/**
 * WordPress REST API fetch handler with timeframe and keyword filtering.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\WordPressAPI
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPressAPI;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * WordPress REST API Fetch Handler
 *
 * Fetches content from external WordPress sites via REST API.
 * Supports timeframe filtering, keyword search, and structured data extraction.
 * Stores source URLs and image URLs in engine data for downstream handlers.
 */
class WordPressAPI {

     /**
      * Fetch WordPress posts via REST API with timeframe and keyword filtering.
      * Engine data (source_url, image_file_path) stored via datamachine_engine_data filter.
      */
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        if (empty($pipeline_id)) {
            do_action('datamachine_log', 'error', 'WordPress API Input: Missing pipeline ID.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }
        
        $flow_step_id = $handler_config['flow_step_id'] ?? null;
        $flow_id = $handler_config['flow_id'] ?? 0;

        if ($flow_step_id === null) {
            do_action('datamachine_log', 'debug', 'WordPress API fetch called without flow_step_id - processed items tracking disabled');
        }

        $config = $handler_config['wordpress_api'] ?? [];

        // Configuration validation
        $endpoint_url = trim($config['endpoint_url'] ?? '');
        if (empty($endpoint_url)) {
            do_action('datamachine_log', 'error', 'WordPress API Input: Endpoint URL is required.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }

        if (!filter_var($endpoint_url, FILTER_VALIDATE_URL)) {
            do_action('datamachine_log', 'error', 'WordPress API Input: Invalid endpoint URL format.', ['pipeline_id' => $pipeline_id, 'endpoint_url' => $endpoint_url]);
            return ['processed_items' => []];
        }

        // Get filtering settings
        $timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
        $search = trim($config['search'] ?? '');

        // Fetch from API endpoint
        $items = $this->fetch_from_endpoint($pipeline_id, $endpoint_url, $timeframe_limit, $search, $flow_step_id, $job_id);
        
        return ['processed_items' => $items];
    }

    /**
     * Fetch data from API endpoint
     *
     * Makes HTTP request to provided endpoint URL and processes response.
     * Returns first unprocessed item with automatic deduplication tracking.
     *
     * @param int $pipeline_id Pipeline execution identifier
     * @param string $endpoint_url Complete API endpoint URL
     * @param string $timeframe_limit Timeframe filter setting
     * @param string $search Search term filter
     * @param string|null $flow_step_id Flow step ID for deduplication tracking
     * @param string|null $job_id Job ID for item tracking
     * @return array Array containing single eligible item data packet or empty array
     */
    private function fetch_from_endpoint(int $pipeline_id, string $endpoint_url, string $timeframe_limit, string $search, ?string $flow_step_id = null, ?string $job_id = null): array {
        // WordPress REST APIs - try server-side search, fallback to client-side
        if (strpos($endpoint_url, '/wp-json/') !== false && !empty($search)) {
            $endpoint_url = add_query_arg('search', $search, $endpoint_url);
            do_action('datamachine_log', 'debug', 'REST API: Added server-side search parameter to WordPress endpoint', [
                'search_term' => $search,
                'modified_url' => $endpoint_url
            ]);
        }

        // Make HTTP request using datamachine_request filter
        $args = [
            'user-agent' => 'DataMachine WordPress Plugin/' . DATAMACHINE_VERSION
        ];

        $result = apply_filters('datamachine_request', null, 'GET', $endpoint_url, $args, 'REST API');

        if (!$result['success']) {
            do_action('datamachine_log', 'error', 'REST API Input: Failed to fetch from endpoint.', [
                'pipeline_id' => $pipeline_id,
                'error' => $result['error'],
                'endpoint_url' => $endpoint_url
            ]);
            return [];
        }

        $response_data = $result['data'];
        if (empty($response_data)) {
            do_action('datamachine_log', 'error', 'REST API Input: Empty response from endpoint.', ['pipeline_id' => $pipeline_id, 'endpoint_url' => $endpoint_url]);
            return [];
        }

        // Parse JSON response
        $items = json_decode($response_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            do_action('datamachine_log', 'error', 'REST API Input: Invalid JSON response.', [
                'pipeline_id' => $pipeline_id,
                'json_error' => json_last_error_msg(),
                'endpoint_url' => $endpoint_url
            ]);
            return [];
        }

        if (!is_array($items) || empty($items)) {
            return [];
        }

        // Find first unprocessed item
        foreach ($items as $item) {
            $item_id = $item['id'] ?? 0;
            if (empty($item_id)) {
                continue;
            }

            $unique_id = md5($endpoint_url . '_' . $item_id);

            $is_processed = ($flow_step_id !== null) ? apply_filters('datamachine_is_item_processed', false, $flow_step_id, 'rest_api', $unique_id) : false;
            if ($is_processed) {
                continue;
            }

            // Found first eligible item - mark as processed and return
            if ($flow_step_id) {
                do_action('datamachine_mark_item_processed', $flow_step_id, 'rest_api', $unique_id, $job_id);
            }

            // Extract item data flexibly
            $title = $this->extract_title($item);
            $content = $this->extract_content($item);
            $excerpt = $this->extract_excerpt($item);
            $source_link = $this->extract_source_link($item);
            $item_date = $this->extract_date($item);

            // Apply timeframe filtering
            if ($timeframe_limit !== 'all_time' && $item_date) {
                $cutoff_timestamp = apply_filters('datamachine_timeframe_limit', null, $timeframe_limit);
                if ($cutoff_timestamp !== null) {
                    $item_timestamp = strtotime($item_date);
                    if ($item_timestamp && $item_timestamp < $cutoff_timestamp) {
                        continue; // Skip items outside timeframe
                    }
                }
            }

            // Apply keyword search filter
            $search_text = $title . ' ' . wp_strip_all_tags($content . ' ' . $excerpt);
            $matches = apply_filters('datamachine_keyword_search_match', false, $search_text, $search);
            if (!$matches) {
                continue; // Skip items that don't match search keywords
            }

            // Extract image URL
            $image_url = $this->extract_image_url($item);

            // Download remote image if present
            $file_info = null;
            if (!empty($image_url) && $flow_step_id) {
                $downloader = apply_filters('datamachine_get_remote_downloader', null);

                if ($downloader) {
                    // Generate filename from URL
                    $url_path = wp_parse_url($image_url, PHP_URL_PATH);
                    $extension = $url_path ? pathinfo($url_path, PATHINFO_EXTENSION) : 'jpg';
                    if (empty($extension)) {
                        $extension = 'jpg';
                    }
                    $filename = 'wp_api_image_' . time() . '_' . sanitize_file_name(basename($url_path ?: 'image')) . '.' . $extension;

                    // Build context with fallback names (no database queries)
                    $context = [
                        'pipeline_id' => $pipeline_id,
                        'pipeline_name' => "pipeline-{$pipeline_id}",
                        'flow_id' => $flow_id,
                        'flow_name' => "flow-{$flow_id}"
                    ];

                    $download_result = $downloader->download_remote_file($image_url, $filename, $context);

                    if ($download_result) {
                        $file_check = wp_check_filetype($filename);
                        $mime_type = $file_check['type'] ?: 'image/jpeg';

                        $file_info = [
                            'file_path' => $download_result['path'],
                            'mime_type' => $mime_type,
                            'file_size' => $download_result['size']
                        ];

                        do_action('datamachine_log', 'debug', 'WordPress API Input: Downloaded remote image for AI processing', [
                            'item_id' => $unique_id,
                            'source_url' => $image_url,
                            'local_path' => $download_result['path'],
                            'file_size' => $download_result['size']
                        ]);
                    } else {
                        do_action('datamachine_log', 'warning', 'WordPress API Input: Failed to download remote image', [
                            'item_id' => $unique_id,
                            'image_url' => $image_url
                        ]);
                    }
                }
            }

            $site_name = $this->extract_site_name_from_url($endpoint_url);

            // Create structured content data for AI processing
            $content_data = [
                'title' => $title,
                'content' => wp_strip_all_tags($content),
                'excerpt' => wp_strip_all_tags($excerpt)
            ];

            // Add file_info if image was downloaded
            if ($file_info) {
                $content_data['file_info'] = $file_info;
            }

            // Create standardized packet and return immediately
            $metadata = [
                'source_type' => 'rest_api',
                'item_identifier_to_log' => $unique_id,
                'original_id' => $item_id,
                'original_title' => $title,
                'original_date_gmt' => $item_date,
                'site_name' => $site_name
            ];

            // Create clean data packet for AI processing
            $input_data = [
                'data' => $content_data,
                'metadata' => $metadata
            ];

            // Store URLs and file path in engine_data via centralized filter
            $image_file_path = '';
            if ($download_result) {
                $image_file_path = $download_result['path'];
            }

            if ($job_id) {
                apply_filters('datamachine_engine_data', null, $job_id, [
                    'source_url' => $source_link,
                    'image_file_path' => $image_file_path
                ]);
            }

            // Return clean data packet (no URLs in metadata for AI)
            return [$input_data];
        }

        // No eligible items found
        return [];
    }

    /**
     * Extract title from item data with flexible field checking.
     *
     * @param array $item Item data from REST API.
     * @return string Title or 'N/A' if none found.
     */
    private function extract_title(array $item): string {
        // WordPress format
        if (isset($item['title']['rendered'])) {
            return $item['title']['rendered'];
        }

        // Direct title field
        if (isset($item['title']) && is_string($item['title'])) {
            return $item['title'];
        }

        // Other common fields
        if (isset($item['name'])) {
            return $item['name'];
        }

        if (isset($item['subject'])) {
            return $item['subject'];
        }

        return 'N/A';
    }

    /**
     * Extract content from item data with flexible field checking.
     *
     * @param array $item Item data from REST API.
     * @return string Content or empty string if none found.
     */
    private function extract_content(array $item): string {
        // WordPress format
        if (isset($item['content']['rendered'])) {
            return $item['content']['rendered'];
        }

        // Direct content field
        if (isset($item['content']) && is_string($item['content'])) {
            return $item['content'];
        }

        // Other common fields
        if (isset($item['body'])) {
            return $item['body'];
        }

        if (isset($item['description'])) {
            return $item['description'];
        }

        if (isset($item['text'])) {
            return $item['text'];
        }

        return '';
    }

    /**
     * Extract excerpt from item data with flexible field checking.
     *
     * @param array $item Item data from REST API.
     * @return string Excerpt or empty string if none found.
     */
    private function extract_excerpt(array $item): string {
        // WordPress format
        if (isset($item['excerpt']['rendered'])) {
            return $item['excerpt']['rendered'];
        }

        // Direct excerpt field
        if (isset($item['excerpt']) && is_string($item['excerpt'])) {
            return $item['excerpt'];
        }

        // Other common fields
        if (isset($item['summary'])) {
            return $item['summary'];
        }

        if (isset($item['description']) && strlen($item['description']) < 300) {
            return $item['description'];
        }

        return '';
    }

    /**
     * Extract source link from item data with flexible field checking.
     *
     * @param array $item Item data from REST API.
     * @return string Source link or empty string if none found.
     */
    private function extract_source_link(array $item): string {
        // WordPress format
        if (isset($item['link'])) {
            return $item['link'];
        }

        // Other common fields
        if (isset($item['url'])) {
            return $item['url'];
        }

        if (isset($item['permalink'])) {
            return $item['permalink'];
        }

        if (isset($item['href'])) {
            return $item['href'];
        }

        return '';
    }

    /**
     * Extract date from item data with flexible field checking.
     *
     * @param array $item Item data from REST API.
     * @return string|null Date in GMT format or null if none found.
     */
    private function extract_date(array $item): ?string {
        // WordPress format
        if (isset($item['date_gmt'])) {
            return $item['date_gmt'];
        }

        if (isset($item['date'])) {
            return $item['date'];
        }

        // Other common fields
        if (isset($item['created_at'])) {
            return $item['created_at'];
        }

        if (isset($item['published_at'])) {
            return $item['published_at'];
        }

        if (isset($item['timestamp'])) {
            return $item['timestamp'];
        }

        return null;
    }

    /**
     * Extract image URL from item data with flexible field checking.
     *
     * @param array $item Item data from REST API.
     * @return string|null Image URL or null if none found.
     */
    private function extract_image_url(array $item): ?string {
        // WordPress embedded media
        if (isset($item['_embedded']['wp:featuredmedia'][0]['source_url'])) {
            return $item['_embedded']['wp:featuredmedia'][0]['source_url'];
        }

        // Direct image fields
        if (isset($item['featured_image'])) {
            return $item['featured_image'];
        }

        if (isset($item['image'])) {
            return is_string($item['image']) ? $item['image'] : ($item['image']['url'] ?? null);
        }

        if (isset($item['thumbnail'])) {
            return is_string($item['thumbnail']) ? $item['thumbnail'] : ($item['thumbnail']['url'] ?? null);
        }

        if (isset($item['featured_media']) && is_string($item['featured_media'])) {
            return $item['featured_media'];
        }

        return null;
    }

    /**
     * Extract site name from endpoint URL.
     *
     * @param string $endpoint_url Complete endpoint URL.
     * @return string Site name extracted from URL.
     */
    private function extract_site_name_from_url(string $endpoint_url): string {
        $parsed_url = wp_parse_url($endpoint_url);
        if (isset($parsed_url['host'])) {
            return $parsed_url['host'];
        }

        return $endpoint_url;
    }


    /**
     * Get the user-friendly label for this handler.
     *
     * @return string Handler label.
     */
    public static function get_label(): string {
        return __('REST API Endpoint', 'datamachine');
    }
}