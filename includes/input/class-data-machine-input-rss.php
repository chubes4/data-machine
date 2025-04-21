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
class Data_Machine_Input_Rss implements Data_Machine_Input_Handler_Interface {

	/**
	 * Service Locator instance.
	 * @var Data_Machine_Service_Locator
	 */
	private $locator;

	/**
	 * Constructor.
	 *
	 * @param Data_Machine_Service_Locator $locator Service Locator instance.
	 */
	public function __construct(Data_Machine_Service_Locator $locator) {
		$this->locator = $locator;
	}

	/**
	 * Fetches and prepares the RSS input data into a standardized format.
	 *
	 * @param array $post_data Data from the $_POST superglobal (or equivalent context).
	 * @param array $files_data Data from the $_FILES superglobal (not used).
	 * @param array $source_config Decoded data_source_config for the specific module run.
	 * @param int   $user_id The ID of the user context.
	 * @return array The standardized input data packet.
	 * @throws Exception If input data is invalid or cannot be retrieved.
	 */
	public function get_input_data(array $post_data, array $files_data, array $source_config, int $user_id): array {
		// Log the raw source_config received early, getting logger instance temporarily if needed
		$temp_logger = $this->locator->get('logger');
		if ($temp_logger) {
			$temp_logger->info('RSS Input: Entering get_input_data. Raw source_config received:', $source_config);
		}
		unset($temp_logger); // Unset temporary logger

		// Get module ID from context data
		$module_id = isset( $post_data['module_id'] ) ? absint( $post_data['module_id'] ) : 0;
		// Use the passed $user_id for validation
		if ( empty( $module_id ) || empty( $user_id ) ) {
			throw new Exception(__( 'Missing module ID or user ID.', 'data-machine' ));
		}

		// Get dependencies
		$db_processed_items = $this->locator->get('database_processed_items');
		$db_modules = $this->locator->get('database_modules'); // Needed for ownership check
		$db_projects = $this->locator->get('database_projects'); // Needed for ownership check
		if (!$db_processed_items || !$db_modules || !$db_projects) {
			throw new Exception(__( 'Required database service not available.', 'data-machine' ));
		}

		// Need to check ownership via project
		$module = $db_modules->get_module( $module_id );
		if (!$module || !isset($module->project_id)) {
			throw new Exception(__( 'Invalid module or project association missing.', 'data-machine' ));
		}
		$project = $db_projects->get_project($module->project_id, $user_id); // Use passed $user_id for ownership check
		if (!$project) {
			throw new Exception(__( 'Permission denied for this module.', 'data-machine' ));
		}

		// --- Configuration --- 
		// Access settings from the 'rss' sub-array based on the logged config structure
		$rss_config = $source_config['rss'] ?? []; // Get the 'rss' sub-array or default to empty
		
		$feed_url = trim( $rss_config['feed_url'] ?? '' );
		// Interpret item_count as the target number of *new* items to find and process
		$process_limit = max(1, absint( $rss_config['item_count'] ?? 1 )); // Ensure at least 1
		$timeframe_limit = $rss_config['timeframe_limit'] ?? 'all_time';
		$search_term = trim( $rss_config['search'] ?? '' ); // Add search term config
		// Parse search terms
		$search_keywords = [];
		if (!empty($search_term)) {
			$search_keywords = array_map('trim', explode(',', $search_term));
			$search_keywords = array_filter($search_keywords); // Remove empty keywords
		}
		// We fetch all available items at once with fetch_feed, no explicit batch size needed here

		// Get logger for diagnostic purposes (defined here, before first use in validation)
		$logger = $this->locator->get('logger');
		
		// More robust URL validation with detailed error logging
		if (empty($feed_url)) {
			$logger && $logger->error('RSS Input: Empty feed URL provided');
			throw new Exception(__('Missing RSS Feed URL. Please enter a valid URL.', 'data-machine'));
		}
		
		// Make sure URL uses a valid protocol (http/https)
		if (!preg_match('~^(https?:)?//~i', $feed_url)) {
			$feed_url = 'https://' . ltrim($feed_url, '/');
			$logger && $logger->info("RSS Input: Added https:// protocol to URL: {$feed_url}");
		}
		
		// Validate URL format
		if (!filter_var($feed_url, FILTER_VALIDATE_URL)) {
			$logger && $logger->error("RSS Input: Invalid URL format: {$feed_url}");
			throw new Exception(__('Invalid RSS Feed URL format. Please check the URL.', 'data-machine'));
		}

		// Include WordPress feed functions
		if ( ! function_exists( 'fetch_feed' ) ) {
			include_once( ABSPATH . WPINC . '/feed.php' );
		}

		// Fetch the feed
		$feed = fetch_feed( $feed_url );

		if ( is_wp_error( $feed ) ) {
			$error_message = __( 'Error fetching RSS feed:', 'data-machine' ) . ' ' . $feed->get_error_message();
			error_log('Data Machine RSS Input: ' . $error_message . ' URL: ' . $feed_url);
			throw new Exception($error_message);
		}

		// Get all available items from the feed object
		$feed_items = $feed->get_items();
		$total_items_fetched = count($feed_items);
		$logger && $logger->info("RSS Input: Fetched {$total_items_fetched} total items from feed: {$feed_url}");

		if ( empty($feed_items) ) {
			$logger && $logger->info("RSS Input: Feed found but contains no items: {$feed_url}");
			// Return specific indicator that no items were found in the feed
			return ['status' => 'no_new_items', 'message' => __('No items found in the RSS feed.', 'data-machine')];
			// Changed status to no_new_items for consistency with AJAX handler
		}

		// Calculate cutoff timestamp if a timeframe limit is set
		$cutoff_timestamp = null;
		if ($timeframe_limit !== 'all_time') {
			$interval_map = [
				'24_hours' => '-24 hours',
				'72_hours' => '-72 hours',
				'7_days'   => '-7 days',
				'30_days'  => '-30 days'
			];
			if (isset($interval_map[$timeframe_limit])) {
				$cutoff_timestamp = strtotime($interval_map[$timeframe_limit], current_time('timestamp'));
				$logger && $logger->info("RSS Input: Using cutoff timestamp: {$cutoff_timestamp} for timeframe: {$timeframe_limit}");
			}
		}
		// --- End Configuration ---

		$eligible_items_packets = [];
		$items_checked = 0; // Track number checked for potential debug/limits

		$logger && $logger->info("RSS Input: Starting loop through {$total_items_fetched} fetched items. Process limit: {$process_limit}");
		// Loop through ALL fetched feed items until process_limit is reached or items run out
		foreach ($feed_items as $item) {
			$items_checked++;

			// 1. Check timeframe limit first
			if ($cutoff_timestamp !== null) {
				$item_timestamp = $item->get_date('U'); // Get Unix timestamp
				if (!$item_timestamp || $item_timestamp < $cutoff_timestamp) {
					continue; // Skip item if it's too old or has no date
				}
			}

			// 2. Check search term filter (if keywords are provided)
			$title = $item->get_title() ?? '';
			$content = $item->get_content() ?? '';
			if (!empty($search_keywords)) {
				$text_to_search = $title . ' ' . wp_strip_all_tags($content); // Combine title and stripped content
				$found_keyword = false;
				foreach ($search_keywords as $keyword) {
					if (mb_stripos($text_to_search, $keyword) !== false) {
						$found_keyword = true;
						break; // Found a match, no need to check other keywords
					}
				}
				if (!$found_keyword) {
					if ($logger) $logger->debug("Data Machine RSS Input: Skipping item (search filter). Title: {$title}");
					continue; // Skip if no keyword matched
				}
			}

			// 3. Check if processed (only if item passed other filters)
			// Prioritize permalink as it seems more reliable based on feed structure
			$id_from_permalink = $item->get_permalink();
			$id_from_get_id = $item->get_id(true);
			
			$current_item_id = null;
			if (!empty($id_from_permalink)) {
				$current_item_id = $id_from_permalink;
			} elseif (!empty($id_from_get_id)) {
				$current_item_id = $id_from_get_id;
				$logger && $logger->warning("RSS Input: Used get_id(true) as fallback ID for item #{$items_checked}. Permalink was empty.");
			}
			
			if (empty($current_item_id)) {
				if ($logger) {
					// Log the values we tried to get
					$permalink_val = var_export($id_from_permalink, true);
					$get_id_val = var_export($id_from_get_id, true);
					$logger->error("RSS Input: Skipping item #{$items_checked} due to empty ID/Permalink. Permalink value: {$permalink_val}, get_id(true) value: {$get_id_val}", ['feed_url' => $feed_url]);
				}
				continue; // Skip items without a usable identifier
			}

			if ( $db_processed_items->has_item_been_processed($module_id, 'rss', $current_item_id) ) {
				$logger && $logger->debug("RSS Input: Skipping item (already processed). ID: {$current_item_id}");
				continue; // Skip if already processed
			}

			// --- Item is ELIGIBLE! --- 
			// Extract data and create packet (Title/Content already extracted for search check)
			$link = $item->get_permalink() ?? $feed_url; // Use feed URL as ultimate fallback
			// Basic HTML to Text conversion for content
			$content_string = "Title: " . $title . "\n\n" . wp_strip_all_tags($content);

			// Structure the packet according to orchestrator expectations
			$input_data_packet = [
				'data' => [
					'content_string' => $content_string,
					'file_info' => null // Keep file_info within data, even if null for RSS
				],
				'metadata' => [
					'source_type' => 'rss', // Source type identifier
					'item_identifier_to_log' => $current_item_id, // Add the identifier to be logged later
					'original_id' => $current_item_id, // Keep original_id for potential other uses
					'source_url' => $link,
					'original_title' => $title,
					'feed_url' => $feed_url, // Include original feed URL
				]
			];
			array_push($eligible_items_packets, $input_data_packet); // Use array_push for safety
			// --- End Eligible Item Handling ---

			// Check if we have reached the process limit
			if (count($eligible_items_packets) >= $process_limit) {
				break; // Exit the foreach loop, we have enough items
			}
		} // End foreach ($feed_items as $item)

		$logger && $logger->info("RSS Input: Finished loop. Checked {$items_checked} items. Found " . count($eligible_items_packets) . " eligible items.");

		// --- Return Results --- 
		// If no eligible items were found after checking all feed items
		if (empty($eligible_items_packets)) {
			return ['status' => 'no_new_items', 'message' => __('No new items found matching the criteria in the feed.', 'data-machine')];
		}

		// Return the array of eligible item packets
		return $eligible_items_packets;
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
		$logger = $this->locator->get('logger');
		
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
		
		// Include WordPress feed functions
		if (!function_exists('fetch_feed')) {
			include_once(ABSPATH . WPINC . '/feed.php');
		}
		
		// Attempt to fetch the feed
		$feed = fetch_feed($feed_url);
		
		if (is_wp_error($feed)) {
			$result['message'] = $feed->get_error_message();
			return $result;
		}
		
		// Successfully fetched feed
		$items = $feed->get_items();
		$result['success'] = true;
		$result['items_found'] = count($items);
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
			// TODO: Add 'Order' (Newest/Oldest)? 'Offset'?
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
	 * Get the user-friendly label for this handler.
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return 'RSS Feed';
	}
} // End class Data_Machine_Input_Rss
