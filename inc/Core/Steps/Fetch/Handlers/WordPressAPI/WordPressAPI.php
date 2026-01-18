<?php
/**
 * WordPress REST API fetch handler with timeframe and keyword filtering.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\WordPressAPI
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPressAPI;

use DataMachine\Core\ExecutionContext;
use DataMachine\Core\Steps\Fetch\Handlers\FetchHandler;
use DataMachine\Core\Steps\HandlerRegistrationTrait;

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

	use HandlerRegistrationTrait;

	public function __construct() {
		parent::__construct( 'rest_api' );

		// Self-register with filters
		self::registerHandler(
			'wordpress_api',
			'fetch',
			self::class,
			'WordPress REST API',
			'Fetch posts from external WordPress sites via REST API',
			false,
			null,
			WordPressAPISettings::class,
			null
		);
	}

	/**
	 * Fetch WordPress posts via REST API with timeframe and keyword filtering.
	 * Engine data (source_url, image_file_path) stored via datamachine_engine_data filter.
	 */
	protected function executeFetch( array $config, ExecutionContext $context ): array {
		// Configuration validation
		$endpoint_url = trim( $config['endpoint_url'] ?? '' );
		if ( empty( $endpoint_url ) ) {
			$context->log( 'error', 'WordPressAPI: Endpoint URL is required.' );
			return array();
		}

		if ( ! filter_var( $endpoint_url, FILTER_VALIDATE_URL ) ) {
			$context->log( 'error', 'WordPressAPI: Invalid endpoint URL format.', array( 'endpoint_url' => $endpoint_url ) );
			return array();
		}

		// Get filtering settings
		$timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
		$search          = trim( $config['search'] ?? '' );

		// Fetch from API endpoint
		$item = $this->fetch_from_endpoint( $endpoint_url, $timeframe_limit, $search, $context );

		return $item ?: array();
	}

	/**
	 * Fetch data from API endpoint
	 *
	 * Makes HTTP request to provided endpoint URL and processes response.
	 * Returns first unprocessed item with automatic deduplication tracking.
	 */
	private function fetch_from_endpoint( string $endpoint_url, string $timeframe_limit, string $search, ExecutionContext $context ): array {
		// WordPress REST APIs - try server-side search, fallback to client-side
		if ( strpos( $endpoint_url, '/wp-json/' ) !== false && ! empty( $search ) ) {
			$endpoint_url = add_query_arg( 'search', $search, $endpoint_url );
			$context->log(
				'debug',
				'WordPressAPI: Added server-side search parameter to WordPress endpoint',
				array(
					'search_term'  => $search,
					'modified_url' => $endpoint_url,
				)
			);
		}

		$result = $this->httpGet( $endpoint_url, array( 'context' => 'REST API' ) );

		if ( ! $result['success'] ) {
			$context->log(
				'error',
				'WordPressAPI: Failed to fetch from endpoint.',
				array(
					'error'        => $result['error'],
					'endpoint_url' => $endpoint_url,
				)
			);
			return array();
		}

		$response_data = $result['data'];
		if ( empty( $response_data ) ) {
			$context->log( 'error', 'WordPressAPI: Empty response from endpoint.', array( 'endpoint_url' => $endpoint_url ) );
			return array();
		}

		// Parse JSON response
		$items = json_decode( $response_data, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$context->log(
				'error',
				'WordPressAPI: Invalid JSON response.',
				array(
					'json_error'   => json_last_error_msg(),
					'endpoint_url' => $endpoint_url,
				)
			);
			return array();
		}

		if ( ! is_array( $items ) || empty( $items ) ) {
			return array();
		}

		// Find first unprocessed item
		foreach ( $items as $item ) {
			$item_id = $item['id'] ?? 0;
			if ( empty( $item_id ) ) {
				continue;
			}

			$unique_id = md5( $endpoint_url . '_' . $item_id );

			if ( $context->isItemProcessed( $unique_id ) ) {
				continue;
			}

			// Found first eligible item - mark as processed
			$context->markItemProcessed( $unique_id );

			// Extract item data flexibly
			$title       = $this->extract_title( $item );
			$content     = $this->extract_content( $item );
			$excerpt     = $this->extract_excerpt( $item );
			$source_link = $this->extract_source_link( $item );
			$item_date   = $this->extract_date( $item );

			// Apply timeframe filtering
			if ( $item_date ) {
				$item_timestamp = strtotime( $item_date );
				if ( $item_timestamp && ! $this->applyTimeframeFilter( $item_timestamp, $timeframe_limit ) ) {
					continue; // Skip items outside timeframe
				}
			}

			// Apply keyword search filter
			$search_text = $title . ' ' . wp_strip_all_tags( $content . ' ' . $excerpt );
			if ( ! $this->applyKeywordSearch( $search_text, $search ) ) {
				continue; // Skip items that don't match search keywords
			}

			// Extract image URL
			$image_url = $this->extract_image_url( $item );

			// Download remote image if present
			$file_info       = null;
			$download_result = null;
			if ( ! empty( $image_url ) ) {
				// Generate filename from URL
				$url_path  = wp_parse_url( $image_url, PHP_URL_PATH );
				$extension = $url_path ? pathinfo( $url_path, PATHINFO_EXTENSION ) : 'jpg';
				if ( empty( $extension ) ) {
					$extension = 'jpg';
				}
				$filename = 'wp_api_image_' . time() . '_' . sanitize_file_name( basename( $url_path ?: 'image' ) ) . '.' . $extension;

				$download_result = $context->downloadFile( $image_url, $filename );

				if ( $download_result ) {
					$file_check = wp_check_filetype( $filename );
					$mime_type  = $file_check['type'] ?: 'image/jpeg';

					$file_info = array(
						'file_path' => $download_result['path'],
						'mime_type' => $mime_type,
						'file_size' => $download_result['size'],
					);

					$context->log(
						'debug',
						'WordPressAPI: Downloaded remote image for AI processing',
						array(
							'item_id'    => $unique_id,
							'source_url' => $image_url,
							'local_path' => $download_result['path'],
							'file_size'  => $download_result['size'],
						)
					);
				} else {
					$context->log(
						'warning',
						'WordPressAPI: Failed to download remote image',
						array(
							'item_id'   => $unique_id,
							'image_url' => $image_url,
						)
					);
				}
			}

			$site_name = $this->extract_site_name_from_url( $endpoint_url );

			// Prepare raw data for DataPacket creation
			$raw_data = array(
				'title'    => $title,
				'content'  => wp_strip_all_tags( $content ),
				'metadata' => array(
					'source_type'            => 'rest_api',
					'item_identifier_to_log' => $unique_id,
					'original_id'            => $item_id,
					'original_title'         => $title,
					'original_date_gmt'      => $item_date,
					'site_name'              => $site_name,
				),
			);

			// Add excerpt if present
			if ( ! empty( $excerpt ) ) {
				$raw_data['content'] .= "\n\nExcerpt: " . wp_strip_all_tags( $excerpt );
			}

			// Add file_info if image was downloaded
			if ( $file_info ) {
				$raw_data['file_info'] = $file_info;
			}

			// Store URLs and file path in engine_data via centralized filter
			$image_file_path = '';
			if ( $download_result ) {
				$image_file_path = $download_result['path'];
			}

			$context->storeEngineData(
				array(
					'source_url'      => $source_link,
					'image_file_path' => $image_file_path,
				)
			);

			// Return raw data for DataPacket creation
			return $raw_data;
		}

		// No eligible items found
		return array();
	}

	/**
	 * Extract title from item data with flexible field checking.
	 *
	 * @param array $item Item data from REST API.
	 * @return string Title or 'N/A' if none found.
	 */
	private function extract_title( array $item ): string {
		// WordPress format
		if ( isset( $item['title']['rendered'] ) ) {
			return $item['title']['rendered'];
		}

		// Direct title field
		if ( isset( $item['title'] ) && is_string( $item['title'] ) ) {
			return $item['title'];
		}

		// Other common fields
		if ( isset( $item['name'] ) ) {
			return $item['name'];
		}

		if ( isset( $item['subject'] ) ) {
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
	private function extract_content( array $item ): string {
		// WordPress format
		if ( isset( $item['content']['rendered'] ) ) {
			return $item['content']['rendered'];
		}

		// Direct content field
		if ( isset( $item['content'] ) && is_string( $item['content'] ) ) {
			return $item['content'];
		}

		// Other common fields
		if ( isset( $item['body'] ) ) {
			return $item['body'];
		}

		if ( isset( $item['description'] ) ) {
			return $item['description'];
		}

		if ( isset( $item['text'] ) ) {
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
	private function extract_excerpt( array $item ): string {
		// WordPress format
		if ( isset( $item['excerpt']['rendered'] ) ) {
			return $item['excerpt']['rendered'];
		}

		// Direct excerpt field
		if ( isset( $item['excerpt'] ) && is_string( $item['excerpt'] ) ) {
			return $item['excerpt'];
		}

		// Other common fields
		if ( isset( $item['summary'] ) ) {
			return $item['summary'];
		}

		if ( isset( $item['description'] ) && strlen( $item['description'] ) < 300 ) {
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
	private function extract_source_link( array $item ): string {
		// WordPress format
		if ( isset( $item['link'] ) ) {
			return $item['link'];
		}

		// Other common fields
		if ( isset( $item['url'] ) ) {
			return $item['url'];
		}

		if ( isset( $item['permalink'] ) ) {
			return $item['permalink'];
		}

		if ( isset( $item['href'] ) ) {
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
	private function extract_date( array $item ): ?string {
		// WordPress format
		if ( isset( $item['date_gmt'] ) ) {
			return $item['date_gmt'];
		}

		if ( isset( $item['date'] ) ) {
			return $item['date'];
		}

		// Other common fields
		if ( isset( $item['created_at'] ) ) {
			return $item['created_at'];
		}

		if ( isset( $item['published_at'] ) ) {
			return $item['published_at'];
		}

		if ( isset( $item['timestamp'] ) ) {
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
	private function extract_image_url( array $item ): ?string {
		// WordPress embedded media
		if ( isset( $item['_embedded']['wp:featuredmedia'][0]['source_url'] ) ) {
			return $item['_embedded']['wp:featuredmedia'][0]['source_url'];
		}

		// Direct image fields
		if ( isset( $item['featured_image'] ) ) {
			return $item['featured_image'];
		}

		if ( isset( $item['image'] ) ) {
			return is_string( $item['image'] ) ? $item['image'] : ( $item['image']['url'] ?? null );
		}

		if ( isset( $item['thumbnail'] ) ) {
			return is_string( $item['thumbnail'] ) ? $item['thumbnail'] : ( $item['thumbnail']['url'] ?? null );
		}

		if ( isset( $item['featured_media'] ) && is_string( $item['featured_media'] ) ) {
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
	private function extract_site_name_from_url( string $endpoint_url ): string {
		$parsed_url = wp_parse_url( $endpoint_url );
		if ( isset( $parsed_url['host'] ) ) {
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
		return __( 'REST API Endpoint', 'data-machine' );
	}
}
