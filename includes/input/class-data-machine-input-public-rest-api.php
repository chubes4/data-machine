<?php
/**
 * Handles generic Public REST API (e.g., WordPress) as a data source.
 *
 * Fetches posts or other data from a public REST API endpoint.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/input
 * @since      0.13.0
 */
class Data_Machine_Input_Public_Rest_Api implements Data_Machine_Input_Handler_Interface {

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
	 * Processes the input data.
	 *
	 * NOTE: In the current architecture (v0.12+), the core logic is handled by
	 * Module_Ajax_Handler::process_data_source_ajax_handler, which calls get_input_data().
	 * This method is implemented only to satisfy the interface requirement.
	 *
	 * @param array $post_data Data from the $_POST superglobal.
	 * @param array $files_data Data from the $_FILES superglobal (if applicable).
	 * @return void
	 */
	public function process_input( $post_data, $files_data ) {
		// This method is required by the interface but the primary logic
		// now resides in Module_Ajax_Handler::process_data_source_ajax_handler
		// which directly calls get_input_data() and handles job enqueueing.
		error_log('Data Machine: Input_Public_Rest_Api::process_input called unexpectedly.');
	}

	/**
	 * Fetches and prepares the Public REST API input data into a standardized format.
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
		// Access settings from the nested array keyed by the handler's slug
		$api_config = $source_config['public_rest_api'] ?? [];

		$site_base_url = trim( $api_config['endpoint_url'] ?? '' );
		if ( empty( $site_base_url ) ) {
			// Log the specific sub-array for debugging if needed
			error_log('Data Machine Public REST Input: Missing site_base_url within api_config. $api_config content: ' . print_r($api_config, true));
			throw new Exception(__( 'WordPress Site Base URL is not configured in the data source settings.', 'data-machine' ));
		}
		// Construct the full API base URL
		$api_url_base = rtrim($site_base_url, '/') . '/wp-json/wp/v2/';

		$process_limit = max(1, absint( $api_config['item_count'] ?? 1 )); // Process limit
		$timeframe_limit = $api_config['timeframe_limit'] ?? 'all_time';
		$fetch_batch_size = min(100, max(10, $process_limit * 2)); // Fetch reasonable batches, max 100
		$post_type = $api_config['post_type'] ?? 'posts';
		$orderby = $api_config['orderby'] ?? 'date';
		$order = strtolower($api_config['order'] ?? 'desc');
		$search_term = $api_config['search'] ?? null;
		$category_id = ($api_config['category'] ?? 0) ?: null;
		$tag_id = ($api_config['tag'] ?? 0) ?: null;

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
				$cutoff_timestamp = strtotime($interval_map[$timeframe_limit], current_time('timestamp', true)); // GMT
			}
		}
		// --- End Configuration ---

		// Build initial query parameters
		$query_params = [
			'per_page' => $fetch_batch_size,
			'orderby' => $orderby,
			'order' => $order,
			'search' => $search_term,
			'categories' => $category_id,
			'tags' => $tag_id,
			'_embed' => 'true' // Embed linked resources (like featured image, author)
		];
		// Construct the initial resource URL
		$resource_url = trailingslashit($api_url_base) . $post_type;
		$next_page_url = add_query_arg( array_filter($query_params, function($value) { return $value !== null; }), $resource_url );

		$eligible_items_packets = [];
		$pages_fetched = 0;
		$max_pages = 10; // Safety limit for pagination
		$hit_time_limit_boundary = false;

		// Log the initial URL for debugging
		error_log('Data Machine Public REST Input: Initial fetch URL: ' . $next_page_url);

		while ($next_page_url && count($eligible_items_packets) < $process_limit && $pages_fetched < $max_pages) {
			$pages_fetched++;

			// Log subsequent page URLs if pagination occurs
			if ($pages_fetched > 1) {
				error_log('Data Machine Public REST Input: Fetching next page URL: ' . $next_page_url);
			}

			// Prepare arguments for wp_remote_get
			$args = array(
				// 'headers' => [], // TODO: Add auth if needed later
				'timeout' => 60,
			);

			// Make the API request
			$response = wp_remote_get( $next_page_url, $args );

			// Handle Fetch Response Errors
			if ( is_wp_error( $response ) ) {
				$error_message = __( 'Failed to connect to the public REST API endpoint.', 'data-machine' ) . ' ' . $response->get_error_message();
				error_log( 'Data Machine Public REST Input: API Request Failed. URL: ' . $next_page_url . ' Error: ' . $response->get_error_message() );
				// If first page fails, throw. Otherwise, break loop and return what we have.
				if ($pages_fetched === 1) throw new Exception($error_message);
				else break;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_headers = wp_remote_retrieve_headers( $response );
			$body = wp_remote_retrieve_body( $response );

			if ( $response_code !== 200 ) {
				$error_data = json_decode( $body, true );
				$error_message_detail = isset( $error_data['message'] ) ? $error_data['message'] : __( 'Unknown error occurred on the remote API.', 'data-machine' );
				$error_message = sprintf( __( 'Public REST API returned an error (Code: %d).', 'data-machine' ), $response_code ) . ' ' . $error_message_detail;
				error_log( 'Data Machine Public REST Input: API Error Response. URL: ' . $next_page_url . ' Code: ' . $response_code . ' Body: ' . $body );
				// If first page fails, throw. Otherwise, break loop.
				if ($pages_fetched === 1) throw new Exception($error_message);
				else break;
			}

			$response_data = json_decode( $body, true );

			if ( empty( $response_data ) || ! is_array( $response_data ) ) {
				break; // No items found on this page, stop pagination
			}

			// --- Process items in the current batch --- 
			foreach ($response_data as $item) {
				if (!is_array($item) || empty($item['id'])) {
					continue; // Skip invalid items
				}

				// 1. Check timeframe limit first (using date_gmt)
				if ($cutoff_timestamp !== null) {
					if (empty($item['date_gmt'])) {
						continue; // Skip if no GMT date available
					}
					$item_timestamp = strtotime($item['date_gmt']);
					if ($item_timestamp === false || $item_timestamp < $cutoff_timestamp) {
						// If sorting by date desc, hitting an old item means we can stop entirely
						if ($orderby === 'date' && $order === 'desc') {
							$hit_time_limit_boundary = true;
						}
						continue; // Skip this old item regardless
					}
				}

				// 2. Check if processed
				$current_item_id = $item['id'];
				if ( $db_processed_items->has_item_been_processed($module_id, 'public_rest_api', $current_item_id) ) {
					continue; // Skip if already processed
				}

				// --- Item is ELIGIBLE! --- 
				// Extract data (assuming WP REST API posts structure)
				$title = $item['title']['rendered'] ?? ($item['title'] ?? 'N/A');
				$content = $item['content']['rendered'] ?? ($item['content'] ?? '');
				$source_link = $item['link'] ?? $next_page_url; // Fallback to the API URL used
				$original_date_gmt = $item['date_gmt'] ?? null; // Extract original GMT date

				// Basic HTML to Text conversion for content
				$content_string = "Title: " . $title . "\n\n" . wp_strip_all_tags($content);
				
				$input_data_packet = [
					'content_string' => $content_string,
					'file_info' => null,
					'metadata' => [
						'source_type' => 'public_rest_api',
						'item_identifier_to_log' => $current_item_id,
						'original_id' => $current_item_id,
						'source_url' => $source_link,
						'original_title' => $title,
						'original_date_gmt' => $original_date_gmt, // Add the original date here
						// Optionally add more metadata from the $item array if needed
					]
				];
				array_push($eligible_items_packets, $input_data_packet);
				// --- End Eligible Item Handling ---

				// Check if we have reached the process limit
				if (count($eligible_items_packets) >= $process_limit) {
					break; // Exit the inner foreach loop
				}

			} // End foreach ($response_data as $item)

			// --- Handle Pagination --- 
			$next_page_url = null; // Assume no next page unless found in headers
			if (isset($response_headers['link'])) {
				$links = explode(',', $response_headers['link']);
				foreach ($links as $link_header) {
					if (preg_match('/<([^>]+)>;\s*rel="next"/i', $link_header, $matches)) {
						$next_page_url = trim($matches[1]);
						break;
					}
				}
			}

			// Stop outer loop if process limit reached OR if we hit the time boundary (and sorting by date desc)
			if (count($eligible_items_packets) >= $process_limit || $hit_time_limit_boundary) {
				break;
			}

		} // End while ($next_page_url ... )

		// --- Return Results --- 
		if (empty($eligible_items_packets)) {
			return ['status' => 'no_new_items', 'message' => __('No new items found matching the criteria from the API endpoint.', 'data-machine')];
		}

		return $eligible_items_packets;
	}

	/**
	 * Helper to get module and check ownership.
	 */
	private function get_module_with_ownership_check(int $module_id, int $user_id): ?object {
		$db_modules = $this->locator->get('database_modules');
		$db_projects = $this->locator->get('database_projects');
		if (!$db_modules || !$db_projects) return null;

		$module = $db_modules->get_module($module_id);
		if (!$module || !isset($module->project_id)) return null;

		$project = $db_projects->get_project($module->project_id, $user_id);
		return $project ? $module : null;
	}

	/**
	 * Get settings fields for the Public REST API input handler.
	 */
	public static function get_settings_fields() {
		// Define common orderby options
		$orderby_options = [
			'date' => __('Date'),
			'modified' => __('Modified Date'),
			'title' => __('Title'),
			'id' => __('ID'),
			'relevance' => __('Relevance (Search)'),
			'rand' => __('Random'),
		];

		return [
			'endpoint_url' => [
				'type' => 'url',
				'label' => __('WordPress Site Base URL', 'data-machine'),
				'description' => __('Enter the base URL of the target WordPress site (e.g., https://example.com). The REST API path (/wp-json/wp/v2/) will be added automatically.', 'data-machine'),
				'placeholder' => 'https://example.com',
				'default' => '',
			],
			'sync_button' => [
				'type' => 'button',
				'button_id' => 'adc-sync-public-api-data',
				'button_text' => __('Sync API Info (WP Only)', 'data-machine'),
				'description' => __('Click to fetch available Post Types, Categories, and Tags from the WordPress site. Save settings after syncing.', 'data-machine'),
				'feedback_id' => 'adc-sync-feedback-public-api',
			],
			'post_type' => [
				'type' => 'select',
				'label' => __('Post Type', 'data-machine'),
				'description' => __('Select the post type to fetch (e.g., posts, pages, custom types). Sync first for options.', 'data-machine'),
				'options' => ['posts' => 'Posts', 'pages' => 'Pages'], // Default, updated by sync
				'default' => 'posts',
			],
			'category' => [
				'type' => 'select',
				'label' => __('Category', 'data-machine'),
				'description' => __('Optionally filter by a category. Sync first for options.', 'data-machine'),
				'options' => [0 => '-- All Categories --'], // Default, updated by sync
				'default' => 0,
			],
			'tag' => [
				'type' => 'select',
				'label' => __('Tag', 'data-machine'),
				'description' => __('Optionally filter by a tag. Sync first for options.', 'data-machine'),
				'options' => [0 => '-- All Tags --'], // Default, updated by sync
				'default' => 0,
			],
			'search' => [
				'type' => 'text',
				'label' => __('Search Term', 'data-machine'),
				'description' => __('Optionally filter results by a search keyword.', 'data-machine'),
				'default' => '',
			],
			'orderby' => [
				'type' => 'select',
				'label' => __('Order By', 'data-machine'),
				'description' => __('Select the field to order results by.', 'data-machine'),
				'options' => $orderby_options,
				'default' => 'date',
			],
			'order' => [
				'type' => 'select',
				'label' => __('Order', 'data-machine'),
				'description' => __('Select the order direction.', 'data-machine'),
				'options' => [
					'desc' => __('Descending (Newest First)', 'data-machine'),
					'asc' => __('Ascending (Oldest First)', 'data-machine'),
				],
				'default' => 'desc',
			],
			'item_count' => [
				'type' => 'number',
				'label' => __('Items to Process', 'data-machine'),
				'description' => __('Maximum number of *new* items to find and queue for processing per run.', 'data-machine'),
				'default' => 1,
				'min' => 1,
				'max' => 100, // Sensible max? Maybe adjust.
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
				'default' => 'all_time',
			],
			/* TODO: Add Auth/Header settings if needed later
			'request_headers' => [
				'type' => 'textarea',
				'label' => __('Request Headers (JSON)', 'data-machine'),
				'description' => __('Enter required headers as a JSON object (e.g., {"Authorization": "Bearer YOUR_TOKEN"}).', 'data-machine'),
				'default' => '',
			],
			*/
		];
	}

	/**
	 * Get the user-friendly label for this handler.
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __('Public REST API (WordPress)', 'data-machine');
	}

} // End class Data_Machine_Input_Public_Rest_Api