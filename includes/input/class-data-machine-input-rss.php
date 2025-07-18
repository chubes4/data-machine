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

	use Data_Machine_Base_Input_Handler;

	/** @var Data_Machine_Database_Processed_Items */
	private $db_processed_items;

	/** @var Data_Machine_Database_Modules */
	private $db_modules;

	/** @var Data_Machine_Database_Projects */
	private $db_projects;

	/** @var ?Data_Machine_Logger */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Data_Machine_Database_Processed_Items $db_processed_items
	 * @param Data_Machine_Database_Modules $db_modules
	 * @param Data_Machine_Database_Projects $db_projects
	 * @param Data_Machine_Logger|null $logger
	 */
	public function __construct(
		Data_Machine_Database_Processed_Items $db_processed_items,
		Data_Machine_Database_Modules $db_modules,
		Data_Machine_Database_Projects $db_projects,
		?Data_Machine_Logger $logger = null
	) {
		$this->db_processed_items = $db_processed_items;
		$this->db_modules = $db_modules;
		$this->db_projects = $db_projects;
		$this->logger = $logger;
	}

	/**
	 * Fetches and prepares the RSS input data into a standardized format.
	 *
	 * @param object $module The full module object containing configuration and context.
	 * @param array  $source_config Decoded data_source_config specific to this handler.
	 * @param int    $user_id The ID of the user initiating the process (for ownership/context checks).
	 * @return array An array of standardized input data packets, or an array indicating no new items (e.g., ['status' => 'no_new_items']).
	 * @throws Exception If data cannot be retrieved or is invalid.
	 */
	public function get_input_data(object $module, array $source_config, int $user_id): array {
		// Log the raw source_config received
		$this->logger?->info('RSS Input: Entering get_input_data. Raw source_config received:', $source_config);

		// Get module ID from the module object
		$module_id = isset($module->module_id) ? absint($module->module_id) : 0;

		if ( empty( $module_id ) || empty( $user_id ) ) {
			throw new Exception(__( 'Missing module ID or user ID provided to RSS handler.', 'data-machine' ));
		}

		// Check if dependencies were injected correctly (basic check)
		if (!$this->db_processed_items || !$this->db_modules || !$this->db_projects) {
			throw new Exception(__( 'Required database service not available in RSS handler.', 'data-machine' ));
		}

		// Ownership Check (using the trait method)
		$project = $this->get_module_with_ownership_check($module, $user_id, $this->db_projects);

		// --- Configuration --- 
		// Access settings directly from $source_config (flat array)
		$feed_url = trim( $source_config['feed_url'] ?? '' );
		$process_limit = max(1, absint( $source_config['item_count'] ?? 1 ));
		$timeframe_limit = $source_config['timeframe_limit'] ?? 'all_time';
		$search_term = trim( $source_config['search'] ?? '' );
		$search_keywords = [];
		if (!empty($search_term)) {
			$search_keywords = array_map('trim', explode(',', $search_term));
			$search_keywords = array_filter($search_keywords);
		}
		
		// More robust URL validation
		if (empty($feed_url)) {
			$this->logger?->error('RSS Input: Empty feed URL provided');
			throw new Exception(__('Missing RSS Feed URL. Please enter a valid URL.', 'data-machine'));
		}
		if (!preg_match('~^(https?:)?//~i', $feed_url)) {
			$feed_url = 'https://' . ltrim($feed_url, '/');
			$this->logger?->info("RSS Input: Added https:// protocol to URL: {$feed_url}");
		}
		if (!filter_var($feed_url, FILTER_VALIDATE_URL)) {
			$this->logger?->error("RSS Input: Invalid URL format: {$feed_url}");
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
			$this->logger?->error("RSS Input Error: {$error_message}", ['feed_url' => $feed_url]);
			throw new Exception($error_message);
		}

		// Get all available items from the feed object
		$feed_items = $feed->get_items();
		$total_items_fetched = count($feed_items);
		$this->logger?->info("RSS Input: Fetched {$total_items_fetched} total items from feed: {$feed_url}");

		if ( empty($feed_items) ) {
			$this->logger?->info("RSS Input: Feed found but contains no items: {$feed_url}");
			return ['status' => 'no_new_items', 'message' => __('No items found in the RSS feed.', 'data-machine')];
		}

		// Calculate cutoff timestamp
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
				$this->logger?->info("RSS Input: Using cutoff timestamp: {$cutoff_timestamp} for timeframe: {$timeframe_limit}");
			}
		}
		// --- End Configuration ---

		$eligible_items_packets = [];
		$items_checked = 0;

		$this->logger?->info("RSS Input: Starting loop through {$total_items_fetched} fetched items. Process limit: {$process_limit}");

		foreach ($feed_items as $item) {
			$items_checked++;

			// 1. Check timeframe limit
			if ($cutoff_timestamp !== null) {
				$item_timestamp = $item->get_date('U');
				if (!$item_timestamp || $item_timestamp < $cutoff_timestamp) {
					continue;
				}
			}

			// 2. Check search term filter
			$title = $item->get_title() ?? '';
			$content = $item->get_content() ?? '';
			if (!empty($search_keywords)) {
				$text_to_search = $title . ' ' . wp_strip_all_tags($content);
				$found_keyword = false;
				foreach ($search_keywords as $keyword) {
					if (mb_stripos($text_to_search, $keyword) !== false) {
						$found_keyword = true;
						break;
					}
				}
				if (!$found_keyword) {
					$this->logger?->debug("Data Machine RSS Input: Skipping item (search filter). Title: {$title}");
					continue;
				}
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
				$this->logger?->error("RSS Input: Skipping item #{$items_checked} due to empty ID/Permalink.", ['feed_url' => $feed_url]);
				continue;
			}

			if ( $this->db_processed_items->has_item_been_processed($module_id, 'rss', $current_item_id) ) {
				$this->logger?->debug("RSS Input: Skipping item (already processed). ID: {$current_item_id}");
				continue;
			}

			// --- Item is ELIGIBLE! --- 
			$link = $item->get_permalink() ?? $feed_url;
			$content_string = "Title: " . $title . "\n\n" . wp_strip_all_tags($content);

			$input_data_packet = [
				'data' => [
					'content_string' => $content_string,
					'file_info' => null
				],
				'metadata' => [
					'source_type' => 'rss',
					'item_identifier_to_log' => $current_item_id,
					'original_id' => $current_item_id,
					'source_url' => $link,
					'original_title' => $title,
					'feed_url' => $feed_url,
					'original_date_gmt' => gmdate('Y-m-d\TH:i:s', $item->get_date('U')),
				]
			];
			array_push($eligible_items_packets, $input_data_packet);

			if (count($eligible_items_packets) >= $process_limit) {
				break;
			}
		} // End foreach

		$this->logger?->info("RSS Input: Finished loop. Checked {$items_checked} items. Found " . count($eligible_items_packets) . " eligible items.");

		// --- Return Results ---
		if (empty($eligible_items_packets)) {
			return ['status' => 'no_new_items', 'message' => __('No new items found matching the criteria in the feed.', 'data-machine')];
		}

		// Return the eligible items found (up to the limit)
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
