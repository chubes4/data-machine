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
		$feed_url = trim( $source_config['feed_url'] ?? '' );
		// Interpret item_count as the target number of *new* items to find and process
		$process_limit = max(1, absint( $source_config['item_count'] ?? 1 )); // Ensure at least 1
		$timeframe_limit = $source_config['timeframe_limit'] ?? 'all_time';
		// We fetch all available items at once with fetch_feed, no explicit batch size needed here

		if ( empty( $feed_url ) || ! filter_var( $feed_url, FILTER_VALIDATE_URL ) ) {
			throw new Exception(__( 'Invalid or missing RSS Feed URL configured.', 'data-machine' ));
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

		if ( empty($feed_items) ) {
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
			}
		}
		// --- End Configuration ---

		$eligible_items_packets = [];
		$items_checked = 0; // Track number checked for potential debug/limits

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

			// 2. Check if processed (only if item is recent enough)
			// Use get_id(true) for potentially more stable ID (hashed content/link), fallback to permalink
			$current_item_id = $item->get_id(true) ?? $item->get_permalink();

			if (empty($current_item_id)) {
				error_log("Data Machine RSS Input: Skipping item #{$items_checked} with empty ID/Permalink in feed: " . $feed_url);
				continue; // Skip items without a usable identifier
			}

			if ( $db_processed_items->has_item_been_processed($module_id, 'rss', $current_item_id) ) {
				continue; // Skip if already processed
			}

			// --- Item is ELIGIBLE! --- 
			// Extract data and create packet
			$title = $item->get_title() ?? 'N/A';
			$content = $item->get_content() ?? '';
			$link = $item->get_permalink() ?? $feed_url; // Use feed URL as ultimate fallback
			// Basic HTML to Text conversion for content
			$content_string = "Title: " . $title . "\n\n" . wp_strip_all_tags($content);

			$input_data_packet = [
				'content_string' => $content_string,
				'file_info' => null, // No file involved
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

		// --- Return Results --- 
		// If no eligible items were found after checking all feed items
		if (empty($eligible_items_packets)) {
			return ['status' => 'no_new_items', 'message' => __('No new items found matching the criteria in the feed.', 'data-machine')];
		}

		// Return the array of eligible item packets
		return $eligible_items_packets;
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
		return $sanitized;
	}

	/**
	 * Get the user-friendly label for this handler.
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __('RSS Feed', 'data-machine');
	}
} // End class Data_Machine_Input_Rss
