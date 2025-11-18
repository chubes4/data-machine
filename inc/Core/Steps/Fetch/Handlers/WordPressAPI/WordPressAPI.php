<?php
/**
 * WordPress REST API fetch handler with timeframe and keyword filtering.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\WordPressAPI
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPressAPI;

use DataMachine\Core\Steps\Fetch\Handlers\FetchHandler;

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
class WordPressAPI extends FetchHandler {

	public function __construct() {
		parent::__construct( 'rest_api' );
	}

	/**
	 * Fetch WordPress posts via REST API with timeframe and keyword filtering.
	 * Engine data (source_url, image_file_path) stored via datamachine_engine_data filter.
	 */
	protected function executeFetch(
		int $pipeline_id,
		array $config,
		?string $flow_step_id,
		int $flow_id,
		?string $job_id
	): array {
		if (empty($pipeline_id)) {
			$this->log('error', 'Missing pipeline ID.', ['pipeline_id' => $pipeline_id]);
			return $this->emptyResponse();
		}

		if ($flow_step_id === null) {
			$this->log('debug', 'WordPress API fetch called without flow_step_id - processed items tracking disabled');
		}

		// Configuration validation
		$endpoint_url = trim($config['endpoint_url'] ?? '');
		if (empty($endpoint_url)) {
			$this->log('error', 'Endpoint URL is required.', ['pipeline_id' => $pipeline_id]);
			return $this->emptyResponse();
		}

		if (!filter_var($endpoint_url, FILTER_VALIDATE_URL)) {
			$this->log('error', 'Invalid endpoint URL format.', ['pipeline_id' => $pipeline_id, 'endpoint_url' => $endpoint_url]);
			return $this->emptyResponse();
		}

		// Get filtering settings
		$timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
		$search = trim($config['search'] ?? '');

		// Fetch from API endpoint
		$item = $this->fetch_from_endpoint($pipeline_id, $endpoint_url, $timeframe_limit, $search, $flow_step_id, $flow_id, $job_id);

		return $item ?: $this->emptyResponse();
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
     * @param int $flow_id Flow ID for remote file download context
     * @param string|null $job_id Job ID for item tracking
     * @return array Array containing single eligible item data packet or empty array
     */
    private function fetch_from_endpoint(int $pipeline_id, string $endpoint_url, string $timeframe_limit, string $search, ?string $flow_step_id = null, int $flow_id = 0, ?string $job_id = null): array {
        // WordPress REST APIs - try server-side search, fallback to client-side
        if (strpos($endpoint_url, '/wp-json/') !== false && !empty($search)) {
            $endpoint_url = add_query_arg('search', $search, $endpoint_url);
            $this->log('debug', 'Added server-side search parameter to WordPress endpoint', [
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
            $this->log('error', 'Failed to fetch from endpoint.', [
                'pipeline_id' => $pipeline_id,
                'error' => $result['error'],
                'endpoint_url' => $endpoint_url
            ]);
            return null;
        }

        $response_data = $result['data'];
        if (empty($response_data)) {
            $this->log('error', 'Empty response from endpoint.', ['pipeline_id' => $pipeline_id, 'endpoint_url' => $endpoint_url]);
            return null;
        }

        // Parse JSON response
        $items = json_decode($response_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('error', 'Invalid JSON response.', [
                'pipeline_id' => $pipeline_id,
                'json_error' => json_last_error_msg(),
                'endpoint_url' => $endpoint_url
            ]);
            return null;
        }

        if (!is_array($items) || empty($items)) {
            return null;
        }

        // Find first unprocessed item
        foreach ($items as $item) {
            $item_id = $item['id'] ?? 0;
            if (empty($item_id)) {
                continue;
            }

            $unique_id = md5($endpoint_url . '_' . $item_id);

            if ($this->isItemProcessed($unique_id, $flow_step_id)) {
                continue;
            }

            // Found first eligible item - mark as processed
            $this->markItemProcessed($unique_id, $flow_step_id, $job_id);

            // Extract item data flexibly
            $title = $this->extract_title($item);
            $content = $this->extract_content($item);
            $excerpt = $this->extract_excerpt($item);
            $source_link = $this->extract_source_link($item);
            $item_date = $this->extract_date($item);

            // Apply timeframe filtering
            if ($item_date) {
                $item_timestamp = strtotime($item_date);
                if ($item_timestamp && !$this->applyTimeframeFilter($item_timestamp, $timeframe_limit)) {
                    continue; // Skip items outside timeframe
                }
            }

            // Apply keyword search filter
            $search_text = $title . ' ' . wp_strip_all_tags($content . ' ' . $excerpt);
            if (!$this->applyKeywordSearch($search_text, $search)) {
                continue; // Skip items that don't match search keywords
            }

            // Extract image URL
            $image_url = $this->extract_image_url($item);

            // Download remote image if present
            $file_info = null;
            $download_result = null;
            if (!empty($image_url) && $flow_step_id) {
                // Generate filename from URL
                $url_path = wp_parse_url($image_url, PHP_URL_PATH);
                $extension = $url_path ? pathinfo($url_path, PATHINFO_EXTENSION) : 'jpg';
                if (empty($extension)) {
                    $extension = 'jpg';
                }
                $filename = 'wp_api_image_' . time() . '_' . sanitize_file_name(basename($url_path ?: 'image')) . '.' . $extension;

                $download_result = $this->downloadRemoteFile($image_url, $filename, $pipeline_id, $flow_id);

                if ($download_result) {
                    $file_check = wp_check_filetype($filename);
                    $mime_type = $file_check['type'] ?: 'image/jpeg';

                    $file_info = [
                        'file_path' => $download_result['path'],
                        'mime_type' => $mime_type,
                        'file_size' => $download_result['size']
                    ];

                    $this->log('debug', 'Downloaded remote image for AI processing', [
                        'item_id' => $unique_id,
                        'source_url' => $image_url,
                        'local_path' => $download_result['path'],
                        'file_size' => $download_result['size']
                    ]);
                } else {
                    $this->log('warning', 'Failed to download remote image', [
                        'item_id' => $unique_id,
                        'image_url' => $image_url
                    ]);
                }
            }

            $site_name = $this->extract_site_name_from_url($endpoint_url);

            // Prepare raw data for DataPacket creation
            $raw_data = [
                'title' => $title,
                'content' => wp_strip_all_tags($content),
                'metadata' => [
                    'source_type' => 'rest_api',
                    'item_identifier_to_log' => $unique_id,
                    'original_id' => $item_id,
                    'original_title' => $title,
                    'original_date_gmt' => $item_date,
                    'site_name' => $site_name
                ]
            ];

            // Add excerpt if present
            if (!empty($excerpt)) {
                $raw_data['content'] .= "\n\nExcerpt: " . wp_strip_all_tags($excerpt);
            }

            // Add file_info if image was downloaded
            if ($file_info) {
                $raw_data['file_info'] = $file_info;
            }

            // Store URLs and file path in engine_data via centralized filter
            $image_file_path = '';
            if ($download_result) {
                $image_file_path = $download_result['path'];
            }

            $this->storeEngineData($job_id, [
                'source_url' => $source_link,
                'image_file_path' => $image_file_path
            ]);

            // Return raw data for DataPacket creation
            return $raw_data;
        }

        // No eligible items found
        return null;
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