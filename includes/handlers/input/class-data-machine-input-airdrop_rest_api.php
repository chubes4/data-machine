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
class Data_Machine_Input_Airdrop_Rest_Api extends Data_Machine_Base_Input_Handler {

	/** @var Data_Machine_Database_Remote_Locations */
	private $db_remote_locations;

    /** @var Data_Machine_Handler_HTTP_Service */
    private $http_service;

	/**
	 * Constructor.
	 * Calls parent constructor and adds handler-specific dependencies.
	 *
	 * @param Data_Machine_Database_Modules $db_modules
	 * @param Data_Machine_Database_Projects $db_projects
	 * @param Data_Machine_Database_Processed_Items $db_processed_items
	 * @param Data_Machine_Database_Remote_Locations $db_remote_locations
	 * @param Data_Machine_Handler_HTTP_Service $http_service
	 * @param Data_Machine_Logger|null $logger
	 */
	public function __construct(
		Data_Machine_Database_Modules $db_modules,
		Data_Machine_Database_Projects $db_projects,
		Data_Machine_Database_Processed_Items $db_processed_items,
		Data_Machine_Database_Remote_Locations $db_remote_locations,
		Data_Machine_Handler_HTTP_Service $http_service,
		?Data_Machine_Logger $logger = null
	) {
		// Call parent constructor with common dependencies
		parent::__construct($db_modules, $db_projects, $db_processed_items, $logger);
		
		// Set handler-specific dependencies
		$this->db_remote_locations = $db_remote_locations;
		$this->http_service = $http_service;
	}

	/**
	 * Fetches and prepares the Airdrop REST API input data into a standardized format.
	 *
	 * @param object $module The full module object containing configuration and context.
	 * @param array  $source_config Decoded data_source_config for the specific module run.
	 * @param int    $user_id The ID of the user context.
	 * @return array The standardized input data packet.
	 * @throws Exception If input data is invalid or cannot be retrieved.
	 */
	public function get_input_data(object $module, array $source_config, int $user_id): array {
		// Get module ID from the module object
		$module_id = isset($module->module_id) ? absint($module->module_id) : 0;
		// Use the passed $user_id for validation
		if (empty($module_id) || empty($user_id)) {
			throw new Exception(esc_html__('Missing module ID or user ID.', 'data-machine'));
		}

		// Get dependencies
		$db_processed_items = $this->db_processed_items;
		$db_modules = $this->db_modules; // Needed for ownership check
		$db_projects = $this->db_projects; // Needed for ownership check
		if (!$db_processed_items || !$db_modules || !$db_projects) {
			throw new Exception(esc_html__('Required database service not available.', 'data-machine'));
		}

		// Need to check ownership via project
		if (!$module || !isset($module->project_id)) {
			throw new Exception(esc_html__('Invalid module or project association missing.', 'data-machine'));
		}
		$project = $db_projects->get_project($module->project_id, $user_id); // Use passed $user_id for ownership check
		if (!$project) {
			throw new Exception(esc_html__('Permission denied for this module.', 'data-machine'));
		}

		// --- Configuration ---
		// Access config from nested structure
		$config = $source_config['airdrop_rest_api'] ?? [];
		$location_id = absint($config['location_id'] ?? 0);
		if (empty($location_id)) {
			throw new Exception(esc_html__('No Remote Location selected for Airdrop REST API.', 'data-machine'));
		}

		// Get Remote Location details
		$db_remote_locations = $this->db_remote_locations;
		if (!$db_remote_locations) {
			throw new Exception(esc_html__('Remote Locations database service not available.', 'data-machine'));
		}
		$location = $db_remote_locations->get_location($location_id, $user_id, true);
		if (!$location) {
			// translators: %d is the Remote Location ID number
			throw new Exception(sprintf(esc_html__('Could not retrieve details for Remote Location ID: %d.', 'data-machine'), esc_html($location_id)));
		}

		// Extract connection details from the location object
		$endpoint_url_base = trim($location->target_site_url ?? '');
		$remote_user = trim($location->target_username ?? '');
		$remote_password = $location->password ?? null;

		// Access other settings from config
		$process_limit = max(1, absint($config['item_count'] ?? 1));
		$timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
		$fetch_batch_size = min(100, max(10, $process_limit * 2));

		if (empty($endpoint_url_base) || !filter_var($endpoint_url_base, FILTER_VALIDATE_URL)) {
			// translators: %s is the Remote Location name or ID
			throw new Exception(sprintf(esc_html__('Invalid Target Site URL configured for Remote Location: %s.', 'data-machine'), esc_html($location->location_name ?? $location_id)));
		}
		if (empty($remote_user) || empty($remote_password)) {
			// translators: %s is the Remote Location name or ID
			throw new Exception(sprintf(esc_html__('Missing username or application password for Remote Location: %s.', 'data-machine'), esc_html($location->location_name ?? $location_id)));
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
				$cutoff_timestamp = strtotime($interval_map[$timeframe_limit], current_time('timestamp', true));
			}
		}
		// --- End Configuration ---

		$api_url_base = trailingslashit($endpoint_url_base) . 'wp-json/dma/v1/query-posts';

		$post_type = $config['rest_post_type'] ?? 'post';
		$post_status = $config['rest_post_status'] ?? 'publish';
		$category_id = $config['rest_category'] ?? 0;
		$tag_id = $config['rest_tag'] ?? 0;
		$orderby = $config['rest_orderby'] ?? 'date';
		$order = $config['rest_order'] ?? 'DESC';
		$search = $config['search'] ?? null;

		$eligible_items_packets = [];
		$current_page = 1;
		$max_pages = 10;
		$hit_time_limit_boundary = false;
		$auth_header = 'Basic ' . base64_encode($remote_user . ':' . $remote_password);

		while (count($eligible_items_packets) < $process_limit && $current_page <= $max_pages) {
			$query_params = [
				'post_type' => $post_type,
				'post_status' => $post_status,
				'category' => $category_id ?: null,
				'tag' => $tag_id ?: null,
				'posts_per_page' => $fetch_batch_size,
				'paged' => $current_page,
				'orderby' => $orderby,
				'order' => $order,
                's' => $search
			];
			$current_api_url = add_query_arg(array_filter($query_params, function($value) { return $value !== null; }), $api_url_base);

			$args = array(
				'headers' => array('Authorization' => $auth_header)
			);

			// Use HTTP service - replaces ~20 lines of duplicated HTTP code
			$http_response = $this->http_service->get($current_api_url, $args, 'Airdrop REST API');
			if (is_wp_error($http_response)) {
				if ($current_page === 1) throw new Exception($http_response->get_error_message());
				else break;
			}

			$response_headers = $http_response['headers'];
			$body = $http_response['body'];

			// Parse JSON response with error handling
			$response_data = $this->http_service->parse_json($body, 'Airdrop REST API');
			if (is_wp_error($response_data)) {
				if ($current_page === 1) throw new Exception($response_data->get_error_message());
				else break;
			}
			$posts_data = $response_data['posts'] ?? [];
			$post_count = is_array($posts_data) ? count($posts_data) : 0;

			if (empty($posts_data) || !is_array($posts_data)) {
				break;
			}

			foreach ($posts_data as $post) {
				if (!is_array($post) || empty($post['ID'])) {
					continue;
				}

				if ($cutoff_timestamp !== null) {
					if (empty($post['post_date_gmt'])) {
						continue;
					}
					$item_timestamp = strtotime($post['post_date_gmt']);
					if ($item_timestamp === false || $item_timestamp < $cutoff_timestamp) {
						if ($orderby === 'date' && $order === 'DESC') {
							$hit_time_limit_boundary = true;
						}
						continue;
					}
				}

				$current_item_id = $post['ID'];
				if ($db_processed_items->has_item_been_processed($module_id, 'airdrop_rest_api', $current_item_id)) {
					continue;
				}

				$title = $post['post_title'] ?? 'N/A';
				$content = $post['post_content'] ?? '';
				$source_link = $post['guid'] ?? $endpoint_url_base;
				$image_url = $post['featured_image_url'] ?? null;

				// --- Fallback: Try to get the first image from content if no featured image ---
				if (empty($image_url) && !empty($content)) {
					if (preg_match('/<img.*?src=[\'"](.*?)[\'"].*?>/i', $content, $matches)) {
						$first_image_src = $matches[1];
						// Basic validation - check if it looks like a URL
						if (filter_var($first_image_src, FILTER_VALIDATE_URL)) {
							$image_url = $first_image_src;
							$this->logger?->debug('Airdrop Input: Using first image from content as fallback.', ['found_url' => $image_url, 'item_id' => $current_item_id]);
						}
					}
				}
				// --- End Fallback ---

				// Extract source name from URL host
				$source_host = parse_url($source_link, PHP_URL_HOST);
				$source_name = $source_host ? ucwords(str_replace(['www.', '.com', '.org', '.net'], '', $source_host)) : 'Unknown Source';
				$content_string = "Source: " . $source_name . "\n\nTitle: " . $title . "\n\n" . $content;
				$input_data_packet = [
					'data' => [
						'content_string' => $content_string,
						'file_info' => null
					],
					'metadata' => [
						'source_type' => 'airdrop_rest_api',
						'item_identifier_to_log' => $current_item_id,
						'original_id' => $current_item_id,
						'source_url' => $source_link,
						'original_title' => $title,
						'image_source_url' => $image_url,
						'original_date_gmt' => $post['post_date_gmt'] ?? null
					]
				];
				array_push($eligible_items_packets, $input_data_packet);

				if (count($eligible_items_packets) >= $process_limit) {
					break;
				}
			}

			$total_pages = $response_data['max_num_pages'] ?? ($response_headers['x-wp-totalpages'] ?? null);
			if ($total_pages !== null && $current_page >= (int)$total_pages) {
				break;
			}

			if (count($eligible_items_packets) >= $process_limit || $hit_time_limit_boundary) {
				break;
			}

			$current_page++;
		}

		if (empty($eligible_items_packets)) {
			return ['status' => 'no_new_items', 'message' => __('No new items found matching the criteria from the helper API endpoint.', 'data-machine')];
		}

		return $eligible_items_packets;
	}

	/**
	 * Get settings fields specific to the Airdrop REST API handler.
	 *
	 * @param array $current_config The current configuration values for the module.
	 * @param Data_Machine_Database_Remote_Locations $db_remote_locations The injected remote locations database service.
	 * @return array An array defining the settings fields for this input handler.
	 */
	public static function get_settings_fields(array $current_config = [], Data_Machine_Database_Remote_Locations $db_remote_locations = null): array {
		$locations = $db_remote_locations ? $db_remote_locations->get_locations_for_current_user() : [];

		$options = [0 => __('Select a Remote Location', 'data-machine')];
		foreach ($locations as $loc) {
			$options[$loc->location_id] = $loc->location_name . ' (' . $loc->target_site_url . ')';
		}

		$remote_post_types = ['post' => 'Posts', 'page' => 'Pages'];
		$remote_categories = [0 => __('All Categories', 'data-machine')];
		$remote_tags = [0 => __('All Tags', 'data-machine')];

		// --- START: Dynamic population based on selected location --- 
		$selected_location_id = $current_config['location_id'] ?? 0;
		$sync_button_disabled = empty($selected_location_id);
		
		// We can't fetch dynamic CPTs/Taxonomies here easily without making an API call
		// during settings page load. This might be slow or unreliable.
		// A better approach is an AJAX-based sync button.
		// Let's add a button to sync these details.

		// --- END: Dynamic population --- 

		return [
			'location_id' => [
				'type' => 'select',
				'label' => __('Remote Location', 'data-machine'),
				'description' => __('Select the pre-configured remote WordPress site (using the Data Machine Airdrop helper plugin) to fetch data from.', 'data-machine'),
				'options' => $options,
				'default' => 0,
			],
			// Sync Button
			'sync_details' => [
				'type' => 'button',
				'label' => __('Sync Remote Details', 'data-machine'),
				'description' => __('Click to fetch available Post Types, Categories, and Tags from the selected remote location. Save settings after syncing.', 'data-machine'),
				'button_id' => 'dm-sync-airdrop-details-button', 
				'button_text' => __('Sync Now', 'data-machine'),
				'button_class' => 'button dm-sync-button' . ($sync_button_disabled ? ' disabled' : ''), // Add disabled class dynamically
				'feedback_id' => 'dm-sync-airdrop-feedback' // ID for showing sync status
			],
			'rest_post_type' => [
				'type' => 'select',
				'wrapper_id' => 'dm-airdrop-post-type-wrapper', // Add wrapper ID
				'label' => __('Post Type', 'data-machine'),
				'description' => __('Select the post type to fetch from the remote site.', 'data-machine'),
				'options' => $remote_post_types, // Initially basic, populated by sync
				'default' => 'post',
			],
			'rest_post_status' => [
				'type' => 'select',
				'label' => __('Post Status', 'data-machine'),
				'description' => __('Select the post status to fetch.', 'data-machine'),
				'options' => [
					'publish' => __('Published', 'data-machine'),
					'draft' => __('Draft', 'data-machine'),
					'pending' => __('Pending', 'data-machine'),
					'private' => __('Private', 'data-machine'),
					'any' => __('Any', 'data-machine'), // Add 'any' option
				],
				'default' => 'publish',
			],
			'rest_category' => [
				'type' => 'select',
				'wrapper_id' => 'dm-airdrop-category-wrapper', // Add wrapper ID
				'label' => __('Category', 'data-machine'),
				'description' => __('Optional: Filter by a specific category ID from the remote site.', 'data-machine'),
				'options' => $remote_categories, // Initially basic, populated by sync
				'default' => 0,
			],
			'rest_tag' => [
				'type' => 'select',
				'wrapper_id' => 'dm-airdrop-tag-wrapper', // Add wrapper ID
				'label' => __('Tag', 'data-machine'),
				'description' => __('Optional: Filter by a specific tag ID from the remote site.', 'data-machine'),
				'options' => $remote_tags, // Initially basic, populated by sync
				'default' => 0,
			],
			'rest_orderby' => [
				'type' => 'select',
				'label' => __('Order By', 'data-machine'),
				'description' => __('Select the field to order results by.', 'data-machine'),
				'options' => [
					'date' => __('Date', 'data-machine'),
					'modified' => __('Modified Date', 'data-machine'),
					'title' => __('Title', 'data-machine'),
					'ID' => __('ID', 'data-machine'),
				],
				'default' => 'date',
			],
			'rest_order' => [
				'type' => 'select',
				'label' => __('Order', 'data-machine'),
				'description' => __('Select the order direction.', 'data-machine'),
				'options' => [
					'DESC' => __('Descending', 'data-machine'),
					'ASC' => __('Ascending', 'data-machine'),
				],
				'default' => 'DESC',
            ],
			'item_count' => [
				'type' => 'number',
				'label' => __('Items to Process', 'data-machine'),
				'description' => __('Maximum number of *new* items to process per run.', 'data-machine'),
				'default' => 1,
				'min' => 1,
				'max' => 100,
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
			'search' => [
				'type' => 'text',
				'label' => __('Search Term Filter', 'data-machine'),
				'description' => __('Optional: Filter items on the remote site using a search term.', 'data-machine'),
				'default' => '',
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
		
		// Validate required location_id
		if (empty($sanitized['location_id'])) {
			throw new InvalidArgumentException(__('Remote Location is required for Airdrop REST API input handler.', 'data-machine'));
		}
		$sanitized['rest_post_type'] = sanitize_text_field($raw_settings['rest_post_type'] ?? 'post');
		$sanitized['rest_post_status'] = sanitize_text_field($raw_settings['rest_post_status'] ?? 'publish');
		$sanitized['rest_category'] = absint($raw_settings['rest_category'] ?? 0);
		$sanitized['rest_tag'] = absint($raw_settings['rest_tag'] ?? 0);
		$sanitized['rest_orderby'] = sanitize_text_field($raw_settings['rest_orderby'] ?? 'date');
		$sanitized['rest_order'] = sanitize_text_field($raw_settings['rest_order'] ?? 'DESC');
		$sanitized['item_count'] = max(1, absint($raw_settings['item_count'] ?? 1));
		$sanitized['timeframe_limit'] = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
		$sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? ''); // Sanitize search term

		// Sanitize custom_taxonomies if present
		if (!empty($raw_settings['custom_taxonomies']) && is_array($raw_settings['custom_taxonomies'])) {
			$sanitized_custom_taxonomies = [];
			foreach ($raw_settings['custom_taxonomies'] as $tax_slug => $term_id) {
				$sanitized_custom_taxonomies[sanitize_key($tax_slug)] = absint($term_id);
			}
			$sanitized['custom_taxonomies'] = $sanitized_custom_taxonomies;
		}

		return $sanitized;
	}

	/**
	 * Get the user-friendly label for this handler.
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __('Airdrop REST API (Helper Plugin)', 'data-machine');
	}
}