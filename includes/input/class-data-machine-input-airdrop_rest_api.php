<?php
/**
 * Handles REST API as a data source using the Data Machine Airdrop helper plugin.
 *
 * Fetches posts from a remote WordPress site via a custom endpoint
 * provided by the Data Machine Airdrop helper plugin, requiring authentication.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/input
 * @since      0.7.0 (Renamed/Refactored 0.13.0)
 */
class Data_Machine_Input_Airdrop_Rest_Api implements Data_Machine_Input_Handler_Interface {

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
	 * Fetches and prepares the Airdrop REST API input data into a standardized format.
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
		// The full config is nested under the handler's key
		$handler_config = $source_config['airdrop_rest_api'] ?? [];

		// Get remote location ID from source config
		$location_id = absint($handler_config['location_id'] ?? 0); // Access via $handler_config
		if (empty($location_id)) {
			// Updated error message for consistency
			throw new Exception(__('No Remote Location selected for Airdrop REST API.', 'data-machine'));
		}

		// Get Remote Location details
		$db_remote_locations = $this->locator->get('database_remote_locations');
		if (!$db_remote_locations) {
			throw new Exception(__('Remote Locations database service not available.', 'data-machine'));
		}
		// Fetch location data - User ID is required by the get_location method signature
		// for ownership check, even if module ownership was checked earlier.
		// Request decryption by setting the third argument to true
		$location = $db_remote_locations->get_location($location_id, $user_id, true); 
		if (!$location) {
			throw new Exception(sprintf(__('Could not retrieve details for Remote Location ID: %d.', 'data-machine'), $location_id));
		}

		// Extract connection details from the location object
		$endpoint_url_base = trim($location->target_site_url ?? '');
		$remote_user = trim($location->target_username ?? '');
		// Access the decrypted password property directly from the location object
		$remote_password = $location->password ?? null; // Password property is set when decryption is requested

		// Access other settings via $handler_config
		$process_limit = max(1, absint( $handler_config['item_count'] ?? 1 )); 
		$timeframe_limit = $handler_config['timeframe_limit'] ?? 'all_time';
		$fetch_batch_size = min(100, max(10, $process_limit * 2)); // Fetch reasonable batches, max 100

		if ( empty( $endpoint_url_base ) || ! filter_var( $endpoint_url_base, FILTER_VALIDATE_URL ) ) {
			throw new Exception(sprintf(__('Invalid Target Site URL configured for Remote Location: %s.', 'data-machine'), $location->location_name ?? $location_id));
		}
		if ( empty( $remote_user ) || empty( $remote_password ) ) {
			throw new Exception(sprintf(__('Missing username or application password for Remote Location: %s.', 'data-machine'), $location->location_name ?? $location_id));
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
				$cutoff_timestamp = strtotime($interval_map[$timeframe_limit], current_time('timestamp', true)); // GMT
			}
		}
		// --- End Configuration ---

		// Construct the base Helper Plugin Endpoint URL
		$api_url_base = trailingslashit($endpoint_url_base) . 'wp-json/dma/v1/query-posts';

		// Get query parameters from config for the initial request
		// Access via $handler_config
		$post_type = $handler_config['rest_post_type'] ?? 'post';
		$post_status = $handler_config['rest_post_status'] ?? 'publish';
		$category_id = $handler_config['rest_category'] ?? 0;
		$tag_id = $handler_config['rest_tag'] ?? 0;
		$orderby = $handler_config['rest_orderby'] ?? 'date';
		$order = $handler_config['rest_order'] ?? 'DESC';
		$search = $handler_config['search'] ?? null; // Added search term

		$eligible_items_packets = [];
		$current_page = 1;
		$max_pages = 10; // Safety limit for pagination
		$hit_time_limit_boundary = false;

		// Prepare authorization header once
		$auth_header = 'Basic ' . base64_encode( $remote_user . ':' . $remote_password );

		while (count($eligible_items_packets) < $process_limit && $current_page <= $max_pages) {
			// Build query parameters for the current page
			$query_params = [
				'post_type' => $post_type,
				'post_status' => $post_status,
				'category' => $category_id ?: null,
				'tag' => $tag_id ?: null,
				'posts_per_page' => $fetch_batch_size,
				'paged' => $current_page, // Use 'paged' for helper API pagination
				'orderby' => $orderby,
				'order' => $order,
                's' => $search // Use 's' for search in WP_Query used by helper
			];
			$current_api_url = add_query_arg( array_filter($query_params, function($value) { return $value !== null; }), $api_url_base );

			// --- DEBUG LOGGING START ---
			error_log("[DM Airdrop Debug] Requesting URL (Page {$current_page}): {$current_api_url}");
			// --- DEBUG LOGGING END ---

			// Prepare arguments for wp_remote_get
			$args = array(
				'headers' => array( 'Authorization' => $auth_header ),
				'timeout' => 60,
			);

			// Make the API request
			$response = wp_remote_get( $current_api_url, $args );

			// Handle Fetch Response Errors
			if ( is_wp_error( $response ) ) {
				$error_message = __( 'Failed to connect to the remote data source.', 'data-machine' ) . ' ' . $response->get_error_message();
				error_log( 'Data Machine Helper REST Input: API Request Failed. URL: ' . $current_api_url . ' Error: ' . $response->get_error_message() );
				if ($current_page === 1) throw new Exception($error_message);
				else break;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_headers = wp_remote_retrieve_headers( $response ); // Get headers for potential pagination info
			$body = wp_remote_retrieve_body( $response );

			// --- DEBUG LOGGING START ---
			error_log("[DM Airdrop Debug] Response Code: {$response_code}");
			// Optionally log the first part of the body for brevity, or full body if needed
			error_log("[DM Airdrop Debug] Response Body Snippet: " . substr($body, 0, 500)); 
			// --- DEBUG LOGGING END ---

			if ( $response_code !== 200 ) {
				$error_data = json_decode( $body, true );
				$error_message_detail = isset( $error_data['message'] ) ? $error_data['message'] : __( 'Unknown error occurred on the remote site.', 'data-machine' );
				$error_message = sprintf( __( 'Remote data source returned an error (Code: %d).', 'data-machine' ), $response_code ) . ' ' . $error_message_detail;
				error_log( 'Data Machine Helper REST Input: API Error Response. URL: ' . $current_api_url . ' Code: ' . $response_code . ' Body: ' . $body );
				if ($current_page === 1) throw new Exception($error_message);
				else break;
			}

			$response_data = json_decode( $body, true );

			// Helper returns array under 'posts' key
			$posts_data = $response_data['posts'] ?? [];

			// --- DEBUG LOGGING START ---
			$post_count = is_array($posts_data) ? count($posts_data) : 0;
			error_log("[DM Airdrop Debug] Found {$post_count} posts in response.");
			// --- DEBUG LOGGING END ---

			if ( empty( $posts_data ) || ! is_array( $posts_data ) ) {
				break; // No more items found on this page, stop pagination
			}

			// --- Process items in the current batch --- 
			foreach ($posts_data as $post) {
				if (!is_array($post) || empty($post['ID'])) {
					continue; // Skip invalid items
				}

				// 1. Check timeframe limit first (using post_date_gmt)
				if ($cutoff_timestamp !== null) {
					if (empty($post['post_date_gmt'])) {
						continue; // Skip if no GMT date available
					}
					$item_timestamp = strtotime($post['post_date_gmt']);
					if ($item_timestamp === false || $item_timestamp < $cutoff_timestamp) {
						// --- DEBUG LOGGING START ---
						error_log("[DM Airdrop Debug] Skipping Post ID {$post['ID']} due to timeframe limit.");
						// --- DEBUG LOGGING END ---
						// If sorting by date desc, hitting an old item means we can stop entirely
						if ($orderby === 'date' && $order === 'DESC') {
							$hit_time_limit_boundary = true;
						}
						continue; // Skip this old item regardless
					}
				}

				// 2. Check if processed
				$current_item_id = $post['ID'];
				// Note: The source_type 'helper_rest_api' should match the handler slug derived from the class name.
				// If the class name/slug changes, this might need updating.
				if ( $db_processed_items->has_item_been_processed($module_id, 'airdrop_rest_api', $current_item_id) ) {
					// --- DEBUG LOGGING START ---
					error_log("[DM Airdrop Debug] Skipping Post ID {$current_item_id} as already processed.");
					// --- DEBUG LOGGING END ---
					continue; // Skip if already processed
				}

				// --- Item is ELIGIBLE! --- 
				// --- DEBUG LOGGING START ---
				error_log("[DM Airdrop Debug] Adding Post ID {$current_item_id} as eligible.");
				// --- DEBUG LOGGING END ---
				// Extract data (assuming Helper API structure)
				$title = $post['post_title'] ?? 'N/A';
				$content = $post['post_content'] ?? '';
				$source_link = $post['guid'] ?? $endpoint_url_base; // Use guid, fallback to base URL
				$image_url = $post['featured_image_url'] ?? null; // Get image URL

				$content_string = "Title: " . $title . "\n\n" . $content; // Content is raw
				
				$input_data_packet = [
					'content_string' => $content_string,
					'file_info' => null,
					'metadata' => [
						'source_type' => 'airdrop_rest_api', // Match handler slug
						'item_identifier_to_log' => $current_item_id,
						'original_id' => $current_item_id,
						'source_url' => $source_link,
						'original_title' => $title,
						'image_source_url' => $image_url, // Add image URL to metadata
						'original_date_gmt' => $post['post_date_gmt'] ?? null // Ensure GMT date is passed for potential use
					]
				];
				array_push($eligible_items_packets, $input_data_packet);
				// --- End Eligible Item Handling ---

				// Check if we have reached the process limit
				if (count($eligible_items_packets) >= $process_limit) {
					break; // Exit the inner foreach loop
				}

			} // End foreach ($posts_data as $post)

			// --- Handle Pagination --- 
			// Helper API might indicate total pages in headers (e.g., X-WP-TotalPages) or response body
			$total_pages = $response_data['max_num_pages'] ?? ($response_headers['x-wp-totalpages'] ?? null);
			if ($total_pages !== null && $current_page >= (int)$total_pages) {
				break; // Reached the last page
			}

			// Stop outer loop conditions
			if (count($eligible_items_packets) >= $process_limit || $hit_time_limit_boundary) {
				break;
			}

			$current_page++; // Increment page for next request

		} // End while (count($eligible_items_packets) < $process_limit ... )

		// --- Return Results --- 
		if (empty($eligible_items_packets)) {
			return ['status' => 'no_new_items', 'message' => __('No new items found matching the criteria from the helper API endpoint.', 'data-machine')];
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
	 * Defines the settings fields for this input handler.
	 *
	 * @since 0.13.0
	 * @param array $current_config Current configuration values for this handler (optional).
	 * @param Data_Machine_Service_Locator|null $locator Service locator for dependency fetching.
	 * @return array An associative array defining the settings fields.
	 */
	public static function get_settings_fields(array $current_config = [], ?Data_Machine_Service_Locator $locator = null): array {
		// Default options
		$location_options = [
			'' => __('-- Select Location --', 'data-machine')
		];
		$post_type_options = [ '' => '-- Select Location First --' ];
		$category_options = [ '' => '-- Select Location First --' ]; // Keep only placeholder for categories/tags initially
		$tag_options = [ '' => '-- Select Location First --' ];

		// Populate locations from DB
		if ($locator) {
			try {
				$db_locations = $locator->get('database_remote_locations');
				// Fetch locations accessible by the current user
				$locations = $db_locations->get_locations_for_user(get_current_user_id());
				if ($locations) {
					foreach ($locations as $loc) {
						$location_options[$loc->location_id] = $loc->location_name;
					}
				}
			} catch (\Exception $e) {
				// Log error if locator fails
				error_log('Data Machine Error: Failed to get remote locations service: ' . $e->getMessage());
			}
		}

		// --- Pre-populate based on current config and synced info ---
		$site_info = [];
		$selected_location_id = $current_config['location_id'] ?? 0;

		if ($selected_location_id && $locator) {
			try {
				$db_locations = $locator->get('database_remote_locations');
				// Fetch the specific location - user ID check might not be strictly necessary here 
				// if we assume config belongs to the user, but it adds a layer of verification.
				$location = $db_locations->get_location($selected_location_id, get_current_user_id()); 
				if ($location && !empty($location->synced_site_info)) {
					$site_info = json_decode($location->synced_site_info, true);
					if (is_array($site_info)) {
						// Populate Post Types
						if (!empty($site_info['post_types']) && is_array($site_info['post_types'])) {
							$post_type_options = ['' => '-- Select Post Type --']; // Reset options
							foreach ($site_info['post_types'] as $pt) {
								if (isset($pt['name']) && isset($pt['label'])) {
									$post_type_options[$pt['name']] = $pt['label'];
								}
							}
						}
						// Populate Categories
						if (!empty($site_info['taxonomies']['category']['terms']) && is_array($site_info['taxonomies']['category']['terms'])) {
							$category_options = ['' => '-- Any Category --']; // Reset options
							foreach ($site_info['taxonomies']['category']['terms'] as $term) {
								if (isset($term['term_id']) && isset($term['name'])) {
									$category_options[$term['term_id']] = $term['name'];
								}
							}
						}
						// Populate Tags
						if (!empty($site_info['taxonomies']['post_tag']['terms']) && is_array($site_info['taxonomies']['post_tag']['terms'])) {
							$tag_options = ['' => '-- Any Tag --']; // Reset options
							foreach ($site_info['taxonomies']['post_tag']['terms'] as $term) {
								if (isset($term['term_id']) && isset($term['name'])) {
									$tag_options[$term['term_id']] = $term['name'];
								}
							}
						}
					}
				}
			} catch (\Exception $e) {
				error_log('Data Machine Error: Failed to get location/site info for pre-population: ' . $e->getMessage());
			}
		}
		// --- End Pre-population --- 

		return [
			'location_id' => [
				'label'       => __('Remote Location', 'data-machine'),
				'type'        => 'select',
				'options'     => $location_options, // Use populated options
				'required'    => true,
				'description' => __('Select the pre-configured Remote Location containing the Helper Plugin.', 'data-machine') . ' <a href="' . admin_url('admin.php?page=dm-remote-locations') . '" target="_blank">' . __('Manage Locations', 'data-machine') . '</a>',
				'attributes'  => ['data-target-for' => 'airdrop_rest_api'] // Match handler slug
			],
			'rest_post_type' => [
				'label'       => __('Post Type', 'data-machine'),
				'type'        => 'select',
				'options'     => $post_type_options, // Use pre-populated options
				'required'    => true,
				'default'     => 'post',
				'description' => __('Select the post type to fetch from the remote site. Populated after selecting a location.', 'data-machine'),
				'dependency'  => ['field' => 'location_id', 'value' => ''] // Dependency still needed for JS changes
			],
			'rest_post_status' => [
				'label'       => __('Post Status', 'data-machine'),
				'type'        => 'select',
				'options'     => [ // Standard post statuses (not dynamic)
                    'publish' => __('Published', 'data-machine'),
                    'pending' => __('Pending Review', 'data-machine'),
                    'draft'   => __('Draft', 'data-machine'),
                    'future'  => __('Scheduled', 'data-machine'),
                    'private' => __('Private', 'data-machine'),
                    'any'     => __('Any', 'data-machine'),
				],
				'default'     => 'publish',
				'required'    => true,
				'description' => __('Select the post status to fetch.', 'data-machine'),
			],
			'rest_category' => [
				'label'       => __('Category (Optional)', 'data-machine'),
				'type'        => 'select',
				'options'     => $category_options, // Use pre-populated options
				'required'    => false,
				'description' => __('Filter by a specific category from the remote site. Populated after selecting a location.', 'data-machine'),
				'dependency'  => ['field' => 'location_id', 'value' => ''] // Dependency still needed for JS changes
			],
			'rest_tag' => [
				'label'       => __('Tag (Optional)', 'data-machine'),
				'type'        => 'select',
				'options'     => $tag_options, // Use pre-populated options
				'required'    => false,
				'description' => __('Filter by a specific tag from the remote site. Populated after selecting a location.', 'data-machine'),
				'dependency'  => ['field' => 'location_id', 'value' => ''] // Dependency still needed for JS changes
			],
			'rest_orderby' => [
				'label'       => __('Order By', 'data-machine'),
				'type'        => 'select',
				'options'     => [
					'date'          => __('Date', 'data-machine'),
					'ID'            => __('ID', 'data-machine'),
					'author'        => __('Author', 'data-machine'),
					'title'         => __('Title', 'data-machine'),
					'modified'      => __('Modified Date', 'data-machine'),
					'rand'          => __('Random', 'data-machine'),
					'comment_count' => __('Comment Count', 'data-machine'),
					'menu_order'    => __('Menu Order', 'data-machine'),
				],
				'default'     => 'date',
				'description' => __('Select the field to order posts by.', 'data-machine'),
			],
			'rest_order' => [
				'label'       => __('Order', 'data-machine'),
				'type'        => 'select',
				'options'     => [
					'DESC' => __('Descending', 'data-machine'),
					'ASC'  => __('Ascending', 'data-machine'),
				],
				'default'     => 'DESC',
				'description' => __('Select the order direction.', 'data-machine'),
			],
            'search' => [
                'label'       => __('Search Term (Optional)', 'data-machine'),
                'type'        => 'text',
                'required'    => false,
                'description' => __('Enter a search term to filter posts by keyword.', 'data-machine'),
            ],
			'item_count' => [
				'label'       => __('Items to Process Per Run', 'data-machine'),
				'type'        => 'number',
				'required'    => true,
				'default'     => 1,
				'description' => __('Maximum number of new items to fetch and process in each execution cycle.', 'data-machine'),
				'attributes'  => ['min' => '1', 'step' => '1']
			],
			'timeframe_limit' => [
                'label' => __('Timeframe Limit', 'data-machine'),
                'type' => 'select',
                'options' => [
                    'all_time' => __('All Time', 'data-machine'),
                    '24_hours' => __('Last 24 Hours', 'data-machine'),
                    '72_hours' => __('Last 72 Hours', 'data-machine'),
                    '7_days'   => __('Last 7 Days', 'data-machine'),
                    '30_days'  => __('Last 30 Days', 'data-machine'),
                ],
                'default' => 'all_time',
                'required' => true,
                'description' => __('Only fetch items published within this timeframe.', 'data-machine'),
            ],
			'location_sync_info' => [
				'label'       => __('Location Sync Info', 'data-machine'),
				'type'        => 'html',
				'attributes'  => ['data-target-for' => 'airdrop_rest_api'] 
			],
		];
	}

	/**
	 * Sanitize settings for the Airdrop REST API input handler.
	 *
	 * @param array $raw_settings
	 * @return array
	 */
	public function sanitize_settings(array $raw_settings): array {
		$sanitized = [];
		$sanitized['location_id'] = absint($raw_settings['location_id'] ?? 0);
		$sanitized['rest_post_type'] = sanitize_text_field($raw_settings['rest_post_type'] ?? '');
		$sanitized['rest_post_status'] = sanitize_text_field($raw_settings['rest_post_status'] ?? '');
		$sanitized['rest_category'] = absint($raw_settings['rest_category'] ?? 0);
		$sanitized['rest_tag'] = absint($raw_settings['rest_tag'] ?? 0);
		$sanitized['rest_orderby'] = sanitize_text_field($raw_settings['rest_orderby'] ?? '');
		$sanitized['rest_order'] = sanitize_text_field($raw_settings['rest_order'] ?? '');
		$sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? '');
		$sanitized['item_count'] = max(1, absint($raw_settings['item_count'] ?? 1));
		$sanitized['timeframe_limit'] = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
		return $sanitized;
	}

	/**
	 * Returns the user-friendly label for this input handler.
	 *
	 * @since 0.13.0
	 * @return string
	 */
	public static function get_label(): string {
		return 'Airdrop REST API (via Remote Location)';
	}

} // End class Data_Machine_Input_Airdrop_Rest_Api