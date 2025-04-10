<?php
/**
 * Handles Reddit Subreddits as a data source.
 *
 * Fetches posts from a subreddit using the public Reddit JSON API.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/input
 * @since      0.15.0 // Or next version
 */
class Data_Machine_Input_Reddit implements Data_Machine_Input_Handler_Interface {

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
	 * Processes the input data. (Interface requirement)
	 *
	 * NOTE: Core logic is handled by Module_Ajax_Handler::process_data_source_ajax_handler.
	 *
	 * @param array $post_data Data from the $_POST superglobal.
	 * @param array $files_data Data from the $_FILES superglobal (if applicable).
	 * @return void
	 */
	public function process_input( $post_data, $files_data ) {
		error_log('Data Machine: Input_Reddit::process_input called unexpectedly.');
	}

	/**
	 * Fetches and prepares the Reddit input data into a standardized format.
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

		if ( empty( $module_id ) || empty( $user_id ) ) { // Check remains valid
			throw new Exception(__( 'Missing module ID or user ID.', 'data-machine' ));
		}

		// Get dependencies
		$db_modules = $this->locator->get('database_modules');
		$db_projects = $this->locator->get('database_projects');
		$db_processed_items = $this->locator->get('database_processed_items'); // Get processed items DB
		if (!$db_modules || !$db_projects || !$db_processed_items) {
			throw new Exception(__( 'Required database service not found.', 'data-machine' ));
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
		// Adjust to read from the nested 'reddit' key if present
		$config_data = $source_config['reddit'] ?? $source_config; // Use 'reddit' sub-array or the main array

		$subreddit = trim( $config_data['subreddit'] ?? '' );
		$sort = $config_data['sort_by'] ?? 'hot';
		// Rename for clarity: This is the target number of *new* items to find and process
		$process_limit = max(1, absint( $config_data['item_count'] ?? 1 )); // Ensure at least 1 
		$timeframe_limit = $config_data['timeframe_limit'] ?? 'all_time'; 
		$fetch_batch_size = 100; // Max items per Reddit API request

		if ( empty( $subreddit ) ) {
			throw new Exception(__( 'Subreddit name is not configured.', 'data-machine' ));
		}
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $subreddit)) {
			throw new Exception(__( 'Invalid subreddit name format.', 'data-machine' ));
		}
		$valid_sorts = ['hot', 'new', 'top', 'rising'];
		if (!in_array($sort, $valid_sorts)) {
			$sort = 'hot'; // Default to hot if invalid
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
		$after_param = null; // For Reddit API pagination
		$total_checked = 0; 
		$max_checks = 500; // Safety break to prevent infinite loops in weird scenarios

		// Loop to fetch pages until enough items are found or limits are hit
		while (count($eligible_items_packets) < $process_limit && $total_checked < $max_checks) {
			// Construct the Reddit JSON API URL with pagination
			$reddit_url = sprintf(
				'https://www.reddit.com/r/%s/%s.json?limit=%d%s',
				esc_attr($subreddit),
				esc_attr($sort),
				$fetch_batch_size,
				$after_param ? '&after=' . urlencode($after_param) : '' // Add 'after' if available
			);
			$args = [
				'user-agent' => 'WordPress Plugin AutoDataCollection/' . DATA_MACHINE_VERSION . ' (by /u/your_reddit_username)', // TODO: Proper User-Agent
				'timeout' => 15
			];

			// Make the API request
			$response = wp_remote_get( $reddit_url, $args );

			// --- Handle API Response & Errors ---
			if ( is_wp_error( $response ) ) {
				$error_message = __( 'Failed to connect to the Reddit API during pagination.', 'data-machine' ) . ' ' . $response->get_error_message();
				error_log( 'ADC Reddit Input: API Request Failed. URL: ' . $reddit_url . ' Error: ' . $response->get_error_message() );
				// If it's the first fetch and it fails, throw exception. Otherwise, just log and return what we have?
				if (empty($eligible_items_packets) && $after_param === null) throw new Exception($error_message);
				else break; // Stop fetching if subsequent page fails
			}
			$response_code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			if ( $response_code !== 200 ) {
				// Handle non-200 response similarly to above
				$error_data = json_decode( $body, true );
				$error_message_detail = isset( $error_data['message'] ) ? $error_data['message'] : __( 'Unknown error on Reddit API.', 'data-machine' );
				$error_message = sprintf( __( 'Reddit API error (Code: %d).', 'data-machine' ), $response_code ) . ' ' . $error_message_detail;
				error_log( 'ADC Reddit Input: API Error. URL: ' . $reddit_url . ' Code: ' . $response_code . ' Body: ' . $body );
				if (empty($eligible_items_packets) && $after_param === null) throw new Exception($error_message);
				else break; // Stop fetching
			}
			$response_data = json_decode( $body, true );
			if ( empty( $response_data['data']['children'] ) || ! is_array( $response_data['data']['children'] ) ) {
				// No more posts found on this page or invalid data
				break; // Stop fetching
			}
			// --- End API Response Handling ---

			// Process the items in the current batch
			$batch_hit_time_limit = false;
			foreach ($response_data['data']['children'] as $post_wrapper) {
				$total_checked++;
				if (empty($post_wrapper['data']) || empty($post_wrapper['data']['id']) || empty($post_wrapper['kind'])) {
					error_log("ADC Reddit Input: Skipping post with missing data in subreddit: " . $subreddit);
					continue; // Skip malformed posts
				}
				$item_data = $post_wrapper['data'];
				$current_item_id = $item_data['id'];
				// $current_item_fullname = $post_wrapper['kind'] . '_' . $current_item_id; // e.g., t3_abcdef // Not needed for processing check

				// 1. Check timeframe limit first
				if ($cutoff_timestamp !== null) {
					if (empty($item_data['created_utc'])) {
						continue; // Skip if no creation time available
					}
					$item_timestamp = (int) $item_data['created_utc'];
					if ($item_timestamp < $cutoff_timestamp) {
						$batch_hit_time_limit = true;
						continue; // Skip item if it's too old
					}
				}

				// 2. Check if processed (only if recent enough)
				if ( $db_processed_items->has_item_been_processed($module_id, 'reddit', $current_item_id) ) {
					continue; // Skip if already processed
				}

				// --- Item is ELIGIBLE! --- 
				// Extract data and create packet
				$title = $item_data['title'] ?? 'N/A';
				$content = $item_data['selftext'] ?? '';
				$link = 'https://www.reddit.com' . ($item_data['permalink'] ?? '');
				$source_url = $item_data['url'] ?? $link;
				if (empty(trim($content))) { $content = "Link Post: " . $source_url; }
				$content_string = "Title: " . html_entity_decode($title) . "\n\n" . html_entity_decode($content);

				$input_data_packet = [
					'content_string' => $content_string,
					'file_info' => null,
					'metadata' => [
						'source_type' => 'reddit',
						'item_identifier_to_log' => $current_item_id,
						'original_id' => $current_item_id,
						'source_url' => $link,
						'original_title' => $title,
						'subreddit' => $subreddit,
						'external_url' => ($source_url !== $link) ? $source_url : null,
						'original_creation_timestamp' => isset($item_data['created_utc']) ? (int) $item_data['created_utc'] : null
					]
				];
				// Use array_push as a workaround for potential 'temporary expression' error
				array_push($eligible_items_packets, $input_data_packet);
				// --- End Eligible Item Handling ---

				// Check if we have reached the process limit
				if (count($eligible_items_packets) >= $process_limit) {
					break; // Exit the inner foreach loop
				}

			} // End foreach ($response_data['data']['children'] as $post_wrapper)

			// Check stopping conditions for the outer while loop
			if (count($eligible_items_packets) >= $process_limit) {
				break; // Exit while loop: Found enough items
			}
			// If sorting by 'new', stop fetching if we hit the time limit boundary
			if ($batch_hit_time_limit && $sort === 'new' && $timeframe_limit !== 'all_time') {
				break; // Exit while loop: Reached posts older than timeframe (assuming 'new' sort)
			}
			if (empty($response_data['data']['after'])) {
				break; // Exit while loop: No more pages from Reddit
			}

			// Prepare for the next page fetch
			$after_param = $response_data['data']['after'];

		} // End while (count($eligible_items_packets) < $process_limit ... )

		// --- Return Results --- 
		// If no eligible items were found after all checks
		if (empty($eligible_items_packets)) {
			// Determine specific reason for no items if possible (e.g., checked max items, hit time limit early)
            $message = __('No new items found matching the criteria after checking.', 'data-machine');
            // TODO: Could potentially add more detail to the message based on why the loop stopped.
			return ['status' => 'no_new_items', 'message' => $message];
		}

		// Return the array of eligible item packets
		return $eligible_items_packets; 
		// Note: The structure has changed. It now returns an ARRAY of packets, 
		// or a status array like ['status' => 'no_new_items'].
		// The AJAX handler needs to be updated to handle this.
	}

	/**
	 * Get settings fields for the Reddit input handler.
	 *
	 * @return array Associative array of field definitions.
	 */
	public static function get_settings_fields() {
		return [
			'subreddit' => [
				'type' => 'text',
				'label' => __('Subreddit Name', 'data-machine'),
				'description' => __('Enter the name of the subreddit (e.g., news, programming) without "r/".', 'data-machine'),
				'placeholder' => 'news',
				'default' => '',
			],
			'sort_by' => [
				'type' => 'select',
				'label' => __('Sort By', 'data-machine'),
				'description' => __('Select how to sort the subreddit posts.', 'data-machine'),
				'options' => [
					'hot' => 'Hot',
					'new' => 'New',
					'top' => 'Top (All Time)', // TODO: Add time range options for 'top'?
					'rising' => 'Rising',
				],
				'default' => 'hot',
			],
			'item_count' => [
				'type' => 'number',
				'label' => __('Posts to Fetch', 'data-machine'),
				'description' => __('Number of recent posts to check per run. The system will process the first new post found. Max 100.', 'data-machine'),
				'default' => 25,
				'min' => 1,
				'max' => 100,
			],
			'timeframe_limit' => [
				'type' => 'select',
				'label' => __('Process Posts Within', 'data-machine'),
				'description' => __('Only consider posts created within this timeframe.', 'data-machine'),
				'options' => [
					'all_time' => __('All Time', 'data-machine'),
					'24_hours' => __('Last 24 Hours', 'data-machine'),
					'72_hours' => __('Last 72 Hours', 'data-machine'),
					'7_days'   => __('Last 7 Days', 'data-machine'),
					'30_days'  => __('Last 30 Days', 'data-machine'),
				],
				'default' => 'all_time',
			],
			// Note: No API key needed for basic public access. Add later if needed.
		];
	}

	/**
	 * Get the user-friendly label for this handler.
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __('Reddit Subreddit', 'data-machine');
	}

} // End class Data_Machine_Input_Reddit