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
		$logger = $this->locator->get('logger');

		// --- ADD THIS LOG ---
		if ($logger) {
			$module_id_check = isset( $post_data['module_id'] ) ? absint( $post_data['module_id'] ) : 'MISSING';
			$logger->info('!!! PUBLIC REST API HANDLER: Entered get_input_data. Logger obtained. Module ID from post_data: ' . $module_id_check . ' User ID: ' . $user_id);
		} else {
			// If the logger itself failed, we can't log, but this indicates a major setup issue.
			// We could try a basic error_log as a fallback?
			error_log('!!! PUBLIC REST API HANDLER: Failed to obtain logger instance in get_input_data.');
			// Let the code proceed to likely fail later when logger is used, or throw exception now?
			// Throwing now might be clearer.
			throw new Exception('Failed to obtain logger service.');
		}
		// --- END ADDITION ---

		// Get module ID from context data
		$module_id = isset( $post_data['module_id'] ) ? absint( $post_data['module_id'] ) : 0;
		if ( empty( $module_id ) || empty( $user_id ) ) {
			$logger && $logger->add_admin_error(__('Missing module ID or user ID.', 'data-machine'));
			throw new Exception(__( 'Missing module ID or user ID.', 'data-machine' ));
		}

		// Get dependencies
		$db_processed_items = $this->locator->get('database_processed_items');
		$db_modules = $this->locator->get('database_modules');
		$db_projects = $this->locator->get('database_projects');
		if (!$db_processed_items || !$db_modules || !$db_projects) {
			$logger && $logger->add_admin_error(__('Required database service not available.', 'data-machine'));
			throw new Exception(__( 'Required database service not available.', 'data-machine' ));
		}

		// Need to check ownership via project
		$module = $db_modules->get_module( $module_id );
		if (!$module || !isset($module->project_id)) {
			$logger && $logger->add_admin_error(__('Invalid module or project association missing.', 'data-machine'));
			throw new Exception(__( 'Invalid module or project association missing.', 'data-machine' ));
		}
		$project = $db_projects->get_project($module->project_id, $user_id);
		if (!$project) {
			$logger && $logger->add_admin_error(__('Permission denied for this module.', 'data-machine'));
			throw new Exception(__( 'Permission denied for this module.', 'data-machine' ));
		}

		// --- Configuration ---
		$api_config = $source_config['public_rest_api'] ?? [];
		// No need to check for site_base_url or endpoint_url; only api_endpoint_url is required now.

		// Get the API endpoint URL from config
		$api_endpoint_url = $api_config['api_endpoint_url'] ?? '';
		if (empty($api_endpoint_url)) {
			$logger && $logger->add_admin_error(__('No API endpoint URL provided. Please enter the full URL to the REST API endpoint.', 'data-machine'));
			throw new Exception(__('No API endpoint URL provided. Please enter the full URL to the REST API endpoint.', 'data-machine'));
		}

		// Validate the URL
		if (!filter_var($api_endpoint_url, FILTER_VALIDATE_URL)) {
			$logger && $logger->add_admin_error(__('The provided API endpoint URL is not a valid URL.', 'data-machine'));
			throw new Exception(__('The provided API endpoint URL is not a valid URL.', 'data-machine'));
		}

		// --- Fetching Parameters ---
		$process_limit = max(1, absint( $api_config['item_count'] ?? 1 ));
		$timeframe_limit = $api_config['timeframe_limit'] ?? 'all_time';
		$fetch_batch_size = min(100, max(10, $process_limit * 2));
		$orderby = $api_config['orderby'] ?? 'date';
		$order = strtolower($api_config['order'] ?? 'desc');
		$search_term = $api_config['search'] ?? null;
		// Note: Category/Tag filtering might not work reliably on custom endpoints
		$category_id = ($api_config['category'] ?? 0) ?: null;
		$tag_id = ($api_config['tag'] ?? 0) ?: null;

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

		$query_params = [
			'per_page' => $fetch_batch_size,
			'orderby' => $orderby,
			'order' => $order,
			'_embed' => 'true'
		];
		$next_page_url = add_query_arg( array_filter($query_params, function($value) { return $value !== null && $value !== ''; }), $api_endpoint_url );

		$eligible_items_packets = [];
		$pages_fetched = 0;
		$max_pages = 10;
		$hit_time_limit_boundary = false;

		$logger && $logger->info('Data Machine Public REST Input: Initial fetch URL: ' . $next_page_url);

		while ($next_page_url && count($eligible_items_packets) < $process_limit && $pages_fetched < $max_pages) {
			$pages_fetched++;
			if ($pages_fetched > 1) {
				$logger && $logger->info('Data Machine Public REST Input: Fetching next page URL: ' . $next_page_url);
			}

			$args = array('timeout' => 60);
			$response = wp_remote_get( $next_page_url, $args );

			if ( is_wp_error( $response ) ) {
				$error_message = __( 'Failed to connect to the public REST API endpoint.', 'data-machine' ) . ' ' . $response->get_error_message();
				$logger && $logger->add_admin_error($error_message, ['url' => $next_page_url]);
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
				$logger && $logger->add_admin_error($error_message, ['url' => $next_page_url, 'body' => $body]);
				if ($pages_fetched === 1) throw new Exception($error_message);
				else break;
			}

			$response_data = json_decode( $body, true );
			// Log the top-level keys and a sample of the response for debugging
			$logger && $logger->info('Public REST API: Top-level keys in response: ' . implode(', ', is_array($response_data) ? array_keys($response_data) : []));
			$logger && $logger->debug('Public REST API: Sample of response: ' . substr(json_encode($response_data), 0, 1000));

			// Extract items array using data_path if provided, or auto-detect first array
			$data_path = $api_config['data_path'] ?? '';
			$items = [];

			if (!empty($data_path)) {
				// Traverse the data_path (e.g., "chunks.all.items")
				$parts = explode('.', $data_path);
				$items_ref = $response_data;
				foreach ($parts as $part) {
					if (is_array($items_ref) && isset($items_ref[$part])) {
						$items_ref = $items_ref[$part];
					} else {
						$items_ref = [];
						break;
					}
				}
				if (is_array($items_ref)) {
					$logger && $logger->info('Public REST API: Used custom data_path "' . $data_path . '" to extract items array. Count: ' . count($items_ref));
					$items = $items_ref;
				}
			} else {
				// Auto-detect: find the first array of objects in the response
				$items = self::find_first_array_of_objects($response_data);
				$logger && $logger->info('Public REST API: Auto-detected items array path. Count: ' . count($items));
			}

			if (empty($items) || !is_array($items)) {
				break;
			}

			foreach ($items as $item) {
				if (!is_array($item)) {
					// Log keys instead of full item
					$logger && $logger->info('Public REST API: Skipping item (not an array). Keys: ' . implode(', ', array_keys($item)));
					continue;
				}
				// Try to extract a unique ID, title, content, link, and date fields as flexibly as possible
				// MODIFICATION: Prioritize 'uuid' for ID
				$current_item_id = $item['uuid'] ?? $item['id'] ?? $item['ID'] ?? null;
				$logger && $logger->info('Public REST API: Attempting to process item with extracted ID: ' . var_export($current_item_id, true));
				if (empty($current_item_id)) {
					// Log keys instead of full item
					$logger && $logger->info('Public REST API: Skipping item (missing uuid/id/ID). Keys: ' . implode(', ', array_keys($item)));
					continue;
				}

				// --- Date Handling ---
				$item_timestamp = false;
				$original_date_value = null; // Store the original date string

				// MODIFICATION: Prioritize 'starttime' object for date
				if (isset($item['starttime']) && is_array($item['starttime'])) {
					if (!empty($item['starttime']['iso8601'])) {
						$original_date_value = $item['starttime']['iso8601'];
						$item_timestamp = strtotime($original_date_value);
					} elseif (!empty($item['starttime']['rfc2822'])) {
						$original_date_value = $item['starttime']['rfc2822'];
						$item_timestamp = strtotime($original_date_value);
					} elseif (!empty($item['starttime']['utc'])) {
						// UTC timestamp is often in milliseconds, convert to seconds
						$original_date_value = $item['starttime']['utc'];
						$item_timestamp = is_numeric($original_date_value) ? (int)($original_date_value / 1000) : false;
						// Store original value, but format timestamp as ISO for consistency if parsed
						if ($item_timestamp !== false) $original_date_value = gmdate('Y-m-d\TH:i:s\Z', $item_timestamp);
					}
				}

				// Fallback to existing date logic if 'starttime' is not found/parsed
				if ($item_timestamp === false) {
					$date_gmt = $item['date_gmt'] ?? $item['post_date_gmt'] ?? $item['post_date'] ?? null;
					if (empty($date_gmt) && isset($item['meta_parts']['post_date_formatted'])) {
						$date_gmt = $item['meta_parts']['post_date_formatted'];
						$item_timestamp = strtotime($date_gmt);
					} else {
						$item_timestamp = $date_gmt ? strtotime($date_gmt) : false;
					}
					$original_date_value = $date_gmt; // Store the fallback date value
				}
				// --- End Date Handling ---

				if ($cutoff_timestamp !== null) {
					$logger && $logger->info('Public REST API: Checking date for item ID ' . $current_item_id . '. Extracted original date string: ' . var_export($original_date_value, true) . ', Parsed timestamp: ' . var_export($item_timestamp, true) . ', Cutoff timestamp: ' . $cutoff_timestamp);
					if ($item_timestamp === false) {
						// Log keys instead of full item
						$logger && $logger->info('Public REST API: Skipping item (missing or unparsable date). Keys: ' . implode(', ', array_keys($item)));
						continue;
					}
					if ($item_timestamp < $cutoff_timestamp) {
						if ($orderby === 'date' && $order === 'desc') {
							$hit_time_limit_boundary = true;
						}
						// Log keys instead of full item
						$logger && $logger->info('Public REST API: Skipping item (date before cutoff). Keys: ' . implode(', ', array_keys($item)));
						continue;
					}
				}

				// --- Local Search Term Filtering ---
				if (!empty($search_term)) {
					$keywords = array_map('trim', explode(',', $search_term));
					$keywords = array_filter($keywords); // Remove empty elements

					if (!empty($keywords)) {
						$title_to_check = $item['title']['rendered'] ?? $item['title'] ?? $item['headline'] ?? '';
						// MODIFICATION: Handle content potentially being an array or simple string
						$content_raw = $item['content']['rendered'] ?? $item['content'] ?? $item['excerpt'] ?? '';
						$prologue_raw = $item['prologue'] ?? '';
						$content_html = $prologue_raw;
						if (is_array($content_raw)) {
							$content_html .= implode("\n", $content_raw);
						} elseif (is_string($content_raw)) {
							$content_html .= $content_raw;
						}
						$content_to_check = $content_html; // Keep HTML structure for search? Or strip? Let's strip for consistency.
						$text_to_search = $title_to_check . ' ' . strip_tags($content_to_check); // Combine title and content

						$found_keyword = false;

						foreach ($keywords as $keyword) {
							if (mb_stripos($text_to_search, $keyword) !== false) {
								$found_keyword = true;
								break;
							}
						}

						if (!$found_keyword) {
							$logger && $logger->info('Public REST API: Skipping item (does not match search terms): ' . $current_item_id . ' | Title: ' . $title_to_check);
							continue;
						}
					}
				}
				// --- End Local Search Term Filtering ---

				$logger && $logger->info('Public REST API: Checking if item ID ' . $current_item_id . ' has been processed.');
				if ( $db_processed_items->has_item_been_processed($module_id, 'public_rest_api', $current_item_id) ) {
					$logger && $logger->info('Public REST API: Skipping item (already processed): ' . $current_item_id);
					continue;
				}

				// --- Data Extraction ---
				$title = $item['title']['rendered'] ?? $item['title'] ?? $item['headline'] ?? 'N/A';
				// MODIFICATION: Handle 'content' array and 'prologue'
				$content_parts = $item['content'] ?? []; // Assume 'content' is the array field
				$prologue = $item['prologue'] ?? '';
				$full_content_html = $prologue;
				if (is_array($content_parts)) {
					$full_content_html .= implode("\n", $content_parts);
				} elseif (is_string($content_parts)) { // Handle case where content might just be a string
					$full_content_html .= $content_parts;
				}
				// Fallback if 'content' wasn't the right key
				if (empty(trim(strip_tags($full_content_html)))) {
					$content_fallback = $item['content']['rendered'] ?? $item['excerpt'] ?? '';
					if(is_string($content_fallback)) {
						$full_content_html = $content_fallback;
					}
				}
				// MODIFICATION: Prioritize 'url' for source link
				$source_link = $item['url'] ?? $item['link'] ?? $item['permalink'] ?? $api_endpoint_url; // Use API URL as last resort
				// Keep the originally extracted date value (string)
				$original_date_string_for_meta = is_string($original_date_value) ? $original_date_value : null;

				$content_string = "Title: " . $title . "\n\n" . wp_strip_all_tags($full_content_html);
				// --- End Data Extraction ---

				$input_data_packet = [
					'content_string' => $content_string,
					'file_info' => null,
					'metadata' => [
						'source_type' => 'public_rest_api',
						'item_identifier_to_log' => $current_item_id,
						'original_id' => $current_item_id,
						'source_url' => $source_link,
						'original_title' => $title,
						// MODIFICATION: Store the original date string we found
						'original_date_gmt' => $original_date_string_for_meta, // Keep field name, but store the extracted value
					]
				];
				$logger && $logger->info('Public REST API: Adding eligible item: ' . $current_item_id . ' | Title: ' . $title);
				array_push($eligible_items_packets, $input_data_packet);
				if (count($eligible_items_packets) >= $process_limit) {
					break;
				}
			}

			$next_page_url = null;
			if (isset($response_headers['link'])) {
				$links = explode(',', $response_headers['link']);
				foreach ($links as $link_header) {
					if (preg_match('/<([^>]+)>;\s*rel="next"/i', $link_header, $matches)) {
						$next_page_url = trim($matches[1]);
						break;
					}
				}
			}
			if (count($eligible_items_packets) >= $process_limit || $hit_time_limit_boundary) {
				break;
			}
		}

		if (empty($eligible_items_packets)) {
			$logger && $logger->add_admin_info(__('No new items found matching the criteria from the API endpoint.', 'data-machine'));
			return ['status' => 'no_new_items', 'message' => __('No new items found matching the criteria from the API endpoint.', 'data-machine')];
		}

		return $eligible_items_packets;
	}

	/**
	 * Helper to get module and check ownership.
	 */
	/**
	 * Recursively find the first array of objects in a JSON structure.
	 */
	private static function find_first_array_of_objects($data) {
		// Recursively search for the first array of objects where at least one object has a "title" (or similar) key
		$title_keys = ['title', 'title.rendered', 'headline'];
		if (is_array($data)) {
			// If this is an array of objects, check for "title" key
			if (!empty($data) && is_array(reset($data)) && array_keys(reset($data)) !== range(0, count(reset($data)) - 1)) {
				foreach ($data as $obj) {
					if (is_array($obj)) {
						foreach ($title_keys as $key) {
							if (isset($obj[$key])) {
								return $data;
							}
							// Check for nested "title.rendered"
							if ($key === 'title.rendered' && isset($obj['title']) && is_array($obj['title']) && isset($obj['title']['rendered'])) {
								return $data;
							}
						}
					}
				}
			}
			// Otherwise, search recursively
			foreach ($data as $value) {
				$result = self::find_first_array_of_objects($value);
				if (!empty($result)) {
					return $result;
				}
			}
		}
		return [];
	}
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
			'api_endpoint_url' => [
				'type' => 'text',
				'label' => __('API Endpoint URL', 'data-machine'),
				'description' => __('Enter the full URL to the REST API endpoint you want to fetch data from. Example: https://example.com/wp-json/wp/v2/posts', 'data-machine'),
				'placeholder' => 'https://example.com/wp-json/wp/v2/posts',
				'default' => '',
			],
			'data_path' => [
				'type' => 'text',
				'label' => __('Data Path (JSON)', 'data-machine'),
				'description' => __('Specify the path to the items array in the JSON response (e.g., chunks.all.items). Leave blank to auto-detect the first array.', 'data-machine'),
				'placeholder' => 'chunks.all.items',
				'default' => '',
			],
			'search' => [
				'type' => 'text',
				'label' => __('Search Term', 'data-machine'),
				'description' => __('Optionally filter results locally by keywords (comma-separated). Only items containing at least one keyword in their title or content will be processed.', 'data-machine'),
				'default' => '',
			],
			'orderby' => [
				'type' => 'select',
				'label' => __('Order By', 'data-machine'),
				'description' => __('Select the field to order results by (if supported by the endpoint).', 'data-machine'),
				'options' => $orderby_options,
				'default' => 'date',
			],
			'order' => [
				'type' => 'select',
				'label' => __('Order', 'data-machine'),
				'description' => __('Select the order direction (if supported by the endpoint).', 'data-machine'),
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
				'max' => 100,
			],
			'timeframe_limit' => [
				'type' => 'select',
				'label' => __('Process Items Within', 'data-machine'),
				'description' => __('Only consider items published within this timeframe (applied after fetching).', 'data-machine'),
				'options' => [
					'all_time' => __('All Time', 'data-machine'),
					'24_hours' => __('Last 24 Hours', 'data-machine'),
					'72_hours' => __('Last 72 Hours', 'data-machine'),
					'7_days'   => __('Last 7 Days', 'data-machine'),
					'30_days'  => __('Last 30 Days', 'data-machine'),
				],
				'default' => 'all_time',
			],
		];
	}

	/**
	 * Sanitize settings for the Public REST API input handler.
	 *
	 * @param array $raw_settings
	 * @return array
	 */
	public function sanitize_settings(array $raw_settings): array {
		$sanitized = [];
		$sanitized['api_endpoint_url'] = sanitize_url($raw_settings['api_endpoint_url'] ?? '');
		$sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? '');
		$sanitized['data_path'] = sanitize_text_field($raw_settings['data_path'] ?? '');
		$sanitized['orderby'] = sanitize_text_field($raw_settings['orderby'] ?? 'date');
		$sanitized['order'] = sanitize_text_field($raw_settings['order'] ?? 'desc');
		$sanitized['item_count'] = max(1, absint($raw_settings['item_count'] ?? 1));
		$sanitized['timeframe_limit'] = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
		return $sanitized;
	}

	/**
	 * Get the user-friendly label for this handler.
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return 'Public REST API (WordPress)';
	}

} // End class Data_Machine_Input_Public_Rest_Api