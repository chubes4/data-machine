<?php
/**
 * Handles RSS Feeds as a data source.
 *
 * Fetches items from an RSS feed using WordPress core functions.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/input
 * @since      0.14.0 // Or next version
 */

namespace DataMachine\Handlers\Input;

use DataMachine\Database\{Modules, Projects};
use DataMachine\Engine\ProcessedItemsManager;
use DataMachine\Handlers\HttpService;
use DataMachine\Helpers\Logger;
use DataMachine\DataPacket;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Rss extends BaseInputHandler {

	/**
	 * Constructor.
	 * Uses service locator pattern for dependency injection.
	 */
	public function __construct() {
		// Call parent constructor to initialize common dependencies via service locator
		parent::__construct();
	}

	/**
	 * Fetches and prepares the RSS input data into a standardized format.
	 *
	 * @param object $module The full module object containing configuration and context.
	 * @param array  $source_config Decoded data_source_config specific to this handler.
	 * @param int    $user_id The ID of the user initiating the process (for ownership/context checks).
	 * @return DataPacket A standardized data packet for RSS data.
	 * @throws Exception If data cannot be retrieved or is invalid.
	 */
	public function get_input_data(object $module, array $source_config, int $user_id): DataPacket {
		// Use base class validation - replaces ~20 lines of duplicated code
		$validated = $this->validate_basic_requirements($module, $user_id);
		$module_id = $validated['module_id'];
		$project = $validated['project'];
		
		$logger = $this->get_logger();
		$logger?->info('RSS Input: Entering get_input_data. Raw source_config received:', $source_config);

		// --- Configuration --- 
		// Access settings from nested config structure
		$config = $source_config['rss'] ?? [];
		$feed_url = trim( $config['feed_url'] ?? '' );
		
		// Use base class common config parsing
		$common_config = $this->parse_common_config($config);
		$process_limit = $common_config['process_limit'];
		$timeframe_limit = $common_config['timeframe_limit'];
		$search_keywords = $common_config['search_keywords'];
		
		// More robust URL validation
		if (empty($feed_url)) {
			$this->logger?->error('RSS Input: Empty feed URL provided');
			throw new Exception(esc_html__('Missing RSS Feed URL. Please enter a valid URL.', 'data-machine'));
		}
		if (!preg_match('~^(https?:)?//~i', $feed_url)) {
			$feed_url = 'https://' . ltrim($feed_url, '/');
			$this->logger?->info("RSS Input: Added https:// protocol to URL: {$feed_url}");
		}
		if (!filter_var($feed_url, FILTER_VALIDATE_URL)) {
			$this->logger?->error("RSS Input: Invalid URL format: {$feed_url}");
			throw new Exception(esc_html__('Invalid RSS Feed URL format. Please check the URL.', 'data-machine'));
		}

		// Fetch the feed with browser User-Agent
		$feed = $this->fetch_feed_with_user_agent($feed_url);

		if ( is_wp_error( $feed ) ) {
			$error_message = __( 'Error fetching RSS feed:', 'data-machine' ) . ' ' . $feed->get_error_message();
			$this->logger?->error("RSS Input Error: {$error_message}", ['feed_url' => $feed_url]);
			throw new Exception(esc_html($error_message));
		}

		// Get all available items from the feed object
		$feed_items = $feed->get_items();
		$feed_title = $feed->get_title() ?: parse_url($feed_url, PHP_URL_HOST);
		$total_items_fetched = count($feed_items);
		$this->logger?->info("RSS Input: Fetched {$total_items_fetched} total items from feed: {$feed_url}");

		if ( empty($feed_items) ) {
			$logger?->info("RSS Input: Feed found but contains no items: {$feed_url}");
			return new DataPacket('No Data', 'No items found in the RSS feed', 'rss');
		}

		// Calculate cutoff timestamp using base class method
		$cutoff_timestamp = $this->calculate_cutoff_timestamp($timeframe_limit);
		// --- End Configuration ---

		$eligible_items_packets = [];
		$items_checked = 0;

		$this->logger?->info("RSS Input: Starting loop through {$total_items_fetched} fetched items. Process limit: {$process_limit}");

		foreach ($feed_items as $item) {
			$items_checked++;

			// 1. Check timeframe limit using base class method
			$item_timestamp = $item->get_date('U');
			if (!$this->filter_by_timeframe($cutoff_timestamp, $item_timestamp)) {
				continue;
			}

			// 2. Check search term filter using base class method
			$title = $item->get_title() ?? '';
			$content = $item->get_content() ?? '';
			$text_to_search = $title . ' ' . wp_strip_all_tags($content);
			if (!$this->filter_by_search_terms($text_to_search, $search_keywords)) {
				$logger?->debug("RSS Input: Skipping item (search filter). Title: {$title}");
				continue;
			}

			// 3. Check if processed
			$id_from_permalink = $item->get_permalink();
			$id_from_get_id = $item->get_id(true);
			$current_item_id = null;
			if (!empty($id_from_permalink)) {
				$current_item_id = $id_from_permalink;
			} elseif (!empty($id_from_get_id)) {
				$current_item_id = $id_from_get_id;
				$this->logger?->warning("RSS Input: Used get_id(true) as fallback ID for item #{$items_checked}. Permalink was empty.");
			}
			if (empty($current_item_id)) {
				$logger?->error("RSS Input: Skipping item #{$items_checked} due to empty ID/Permalink.", ['feed_url' => $feed_url]);
				continue;
			}

			if ( $this->check_if_processed($module_id, 'rss', $current_item_id) ) {
				$logger?->debug("RSS Input: Skipping item (already processed). ID: {$current_item_id}");
				continue;
			}

			// --- Item is ELIGIBLE! --- 
			$link = $item->get_permalink() ?? $feed_url;
			$content_body = "Source: " . $feed_title . "\n\n" . wp_strip_all_tags($content);

			// Create standardized DataPacket directly
			$additional_metadata = [
				'item_identifier_to_log' => $current_item_id,
				'original_id' => $current_item_id,
				'feed_url' => $feed_url,
				'original_date_gmt' => gmdate('Y-m-d\TH:i:s', $item->get_date('U')),
			];
			
			$data_packet = $this->create_data_packet($title, $content_body, 'rss', $link, $additional_metadata);
			array_push($eligible_items_packets, $data_packet);

			if (count($eligible_items_packets) >= $process_limit) {
				break;
			}
		} // End foreach

		$logger?->info("RSS Input: Finished loop. Checked {$items_checked} items. Found " . count($eligible_items_packets) . " eligible items.");

		// --- Return Results ---
		if (empty($eligible_items_packets)) {
			return new DataPacket('No Data', 'No new items found matching the criteria in the feed', 'rss');
		}

		// Return only the first item for closed-door philosophy
		return $eligible_items_packets[0];
	}

	/**
	 * Test if an RSS feed URL is valid and accessible.
	 * 
	 * @param string $feed_url The RSS feed URL to test
	 * @return array Result with status and message
	 */
	public function test_feed_url($feed_url) {
		$result = [
			'success' => false,
			'message' => '',
			'items_found' => 0
		];
		
		// Get logger
		$logger = $this->logger;
		
		// Validate format first
		if (empty($feed_url)) {
			$result['message'] = __('Empty feed URL provided', 'data-machine');
			return $result;
		}
		
		// Make sure URL uses a valid protocol
		if (!preg_match('~^(https?:)?//~i', $feed_url)) {
			$feed_url = 'https://' . ltrim($feed_url, '/');
		}
		
		// Validate URL format
		if (!filter_var($feed_url, FILTER_VALIDATE_URL)) {
			$result['message'] = __('Invalid URL format', 'data-machine');
			return $result;
		}
		
		// Attempt to fetch the feed with browser User-Agent
		$feed = $this->fetch_feed_with_user_agent($feed_url);
		
		if (is_wp_error($feed)) {
			$result['message'] = $feed->get_error_message();
			return $result;
		}
		
		// Successfully fetched feed
		$items = $feed->get_items();
		$result['success'] = true;
		$result['items_found'] = count($items);
		/* translators: %d: Number of items found in the RSS feed */
		$result['message'] = sprintf(__('Successfully fetched feed with %d items', 'data-machine'), count($items));
		
		return $result;
	}

	/**
	 * Get settings fields for the RSS Feed input handler.
	 *
	 * @return array Associative array of field definitions.
	 */
	public static function get_settings_fields() {
		return [
			'feed_url' => [
				'type' => 'url',
				'label' => __('Feed URL', 'data-machine'),
				'description' => __('The URL of the RSS or Atom feed.', 'data-machine'),
				'placeholder' => 'https://example.com/feed/',
				'default' => '',
			],
			'item_count' => [
				'type' => 'number',
				'label' => __('Items to Fetch', 'data-machine'),
				'description' => __('Number of recent feed items to check per run. The system will process the first new item found.', 'data-machine'),
				'default' => 10,
				'min' => 1,
				'max' => 100,
			],
			'timeframe_limit' => [
				'type' => 'select',
				'label' => __('Process Items Within', 'data-machine'),
				'description' => __('Only consider items published within this timeframe. Helps ensure freshness and avoid processing very old items.', 'data-machine'),
				'options' => [
					'all_time' => __('All Time', 'data-machine'),
					'24_hours' => __('Last 24 Hours', 'data-machine'),
					'72_hours' => __('Last 72 Hours', 'data-machine'),
					'7_days'   => __('Last 7 Days', 'data-machine'),
					'30_days'  => __('Last 30 Days', 'data-machine'),
				],
				'default' => 'all_time',
			],
			// Add search term setting
			'search' => [
				'type' => 'text',
				'label' => __('Search Term Filter', 'data-machine'),
				'description' => __('Optional: Filter items locally by keywords (comma-separated). Only items containing at least one keyword in their title or content (text only) will be considered.', 'data-machine'),
				'default' => '',
			],
			// Future: Order (Newest/Oldest) and Offset options could be added
		];
	}

	/**
	 * Sanitize settings for the RSS Feed input handler.
	 *
	 * @param array $raw_settings
	 * @return array
	 */
	public function sanitize_settings(array $raw_settings): array {
		$sanitized = [];
		$sanitized['feed_url'] = esc_url_raw($raw_settings['feed_url'] ?? '');
		$sanitized['item_count'] = max(1, absint($raw_settings['item_count'] ?? 10));
		$sanitized['timeframe_limit'] = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
		$sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? ''); // Sanitize search term
		return $sanitized;
	}

	/**
	 * Set browser User-Agent for RSS feed requests to avoid 403 blocking.
	 *
	 * @param array $args HTTP request arguments
	 * @param string $url Request URL
	 * @return array Modified arguments
	 */
	public function set_feed_user_agent($args, $url) {
		$args['user-agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
		return $args;
	}

	/**
	 * Fetch RSS feed with browser User-Agent to avoid 403 errors.
	 *
	 * @param string $feed_url The RSS feed URL
	 * @return mixed Feed object or WP_Error
	 */
	private function fetch_feed_with_user_agent($feed_url) {
		// Include WordPress feed functions
		if (!function_exists('fetch_feed')) {
			include_once(ABSPATH . WPINC . '/feed.php');
		}
		
		// Set browser User-Agent to avoid 403 blocking
		add_filter('http_request_args', [$this, 'set_feed_user_agent'], 10, 2);
		
		// Fetch the feed
		$feed = fetch_feed($feed_url);
		
		// Remove the filter after fetching
		remove_filter('http_request_args', [$this, 'set_feed_user_agent'], 10);
		
		return $feed;
	}


	/**
	 * Get the user-friendly label for this handler.
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return 'RSS Feed';
	}
} // End class \\DataMachine\\Handlers\\Input\\Rss
