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
class Data_Machine_Input_Public_Rest_Api {

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
	 * Fetches and prepares the Public REST API input data into a standardized format.
	 *
     * @param object $module The full module object containing configuration and context.
     * @param array  $source_config Decoded data_source_config specific to this handler.
     * @param int    $user_id The ID of the user initiating the process (for ownership/context checks).
     * @return array An array of standardized input data packets, or an array indicating no new items (e.g., ['status' => 'no_new_items']).
     * @throws Exception If data cannot be retrieved or is invalid.
	 */
	public function get_input_data(object $module, array $source_config, int $user_id): array {
        $this->logger?->info('Public REST API Input: Entering get_input_data.', ['module_id' => $module->module_id ?? null]);

		$module_id = isset($module->module_id) ? absint($module->module_id) : 0;
		if (empty($module_id) || empty($user_id)) {
            $this->logger?->add_admin_error(__('Missing module ID or user ID provided to Public REST handler.', 'data-machine'));
			throw new Exception(esc_html__('Missing module ID or user ID provided to Public REST handler.', 'data-machine'));
		}
		if (!$this->db_processed_items || !$this->db_modules || !$this->db_projects) {
            $this->logger?->add_admin_error(__('Required database service not available in Public REST handler.', 'data-machine'));
			throw new Exception(esc_html__('Required database service not available in Public REST handler.', 'data-machine'));
		}
		$project = $this->get_module_with_ownership_check($module, $user_id, $this->db_projects);

		// --- Configuration ---
		$api_endpoint_url = $source_config['api_endpoint_url'] ?? '';
		$data_path = $source_config['data_path'] ?? '';
		$process_limit = max(1, absint($source_config['item_count'] ?? 1));
		$timeframe_limit = $source_config['timeframe_limit'] ?? 'all_time';
		$search_term = trim($source_config['search'] ?? '');
		// Always order by date descending
		$orderby = 'date';
		$order = 'desc';
		$fetch_batch_size = min(100, max(10, $process_limit * 2));

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
		if (!empty($search_term)) {
			$query_params['search'] = $search_term;
		}
		$next_page_url = add_query_arg(array_filter($query_params, function($value) { return $value !== null && $value !== ''; }), $api_endpoint_url);

		$eligible_items_packets = [];
		$pages_fetched = 0;
		$max_pages = 10;
		$hit_time_limit_boundary = false;

        $this->logger?->info('Public REST Input: Initial fetch URL', ['url' => $next_page_url, 'module_id' => $module_id]);

		while ($next_page_url && count($eligible_items_packets) < $process_limit && $pages_fetched < $max_pages) {
			$pages_fetched++;
            $this->logger?->debug('Public REST Input: Fetching page', ['page' => $pages_fetched, 'url' => $next_page_url, 'module_id' => $module_id]);

			$args = array('timeout' => 60);
			$response = wp_remote_get($next_page_url, $args);

			if (is_wp_error($response)) {
				$error_message = __('Failed to connect to the public REST API endpoint.', 'data-machine') . ' ' . $response->get_error_message();
                $this->logger?->add_admin_error($error_message, ['url' => $next_page_url, 'module_id' => $module_id]);
				if ($pages_fetched === 1) throw new Exception(esc_html($error_message));
				else break;
			}

			$response_code = wp_remote_retrieve_response_code($response);
			$response_headers = wp_remote_retrieve_headers($response);
			$body = wp_remote_retrieve_body($response);

            $this->logger?->debug('Public REST Input: Response code', ['code' => $response_code, 'url' => $next_page_url, 'module_id' => $module_id]);

			if ($response_code !== 200) {
				$error_data = json_decode($body, true);
				$error_message_detail = isset($error_data['message']) ? $error_data['message'] : __('Unknown error occurred on the remote API.', 'data-machine');
				/* translators: %d: HTTP response code */
				$error_message = sprintf(__('Public REST API returned an error (Code: %d).', 'data-machine'), $response_code) . ' ' . $error_message_detail;
                $this->logger?->add_admin_error($error_message, ['url' => $next_page_url, 'body' => substr($body, 0, 500), 'module_id' => $module_id]);
				if ($pages_fetched === 1) throw new Exception(esc_html($error_message));
				else break;
			}

			$response_data = json_decode($body, true);
			$this->logger?->debug('Public REST Input: Response body sample', ['body_sample' => substr($body, 0, 1000), 'url' => $next_page_url, 'module_id' => $module_id]);

			// Extract items array using data_path if provided, or auto-detect first array
			$items = [];
			if (!empty($data_path)) {
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
					$items = $items_ref;
				}
			} else {
				$items = self::find_first_array_of_objects($response_data);
			}
			if (empty($items) || !is_array($items)) {
				break;
			}
			foreach ($items as $item) {
				if (!is_array($item)) {
					continue;
				}
				$current_item_id = $item['uuid'] ?? $item['id'] ?? $item['ID'] ?? null;
				if (empty($current_item_id)) {
					continue;
				}
				// --- Date Handling ---
				$item_timestamp = false;
				$original_date_value = null;
				if (isset($item['starttime']) && is_array($item['starttime'])) {
					if (!empty($item['starttime']['iso8601'])) {
						$original_date_value = $item['starttime']['iso8601'];
						$item_timestamp = strtotime($original_date_value);
					} elseif (!empty($item['starttime']['rfc2822'])) {
						$original_date_value = $item['starttime']['rfc2822'];
						$item_timestamp = strtotime($original_date_value);
					} elseif (!empty($item['starttime']['utc'])) {
						$original_date_value = $item['starttime']['utc'];
						$item_timestamp = is_numeric($original_date_value) ? (int)($original_date_value / 1000) : false;
						if ($item_timestamp !== false) $original_date_value = gmdate('Y-m-d\TH:i:s\Z', $item_timestamp);
					}
				}
				if ($item_timestamp === false) {
					$date_gmt = $item['date_gmt'] ?? $item['post_date_gmt'] ?? $item['post_date'] ?? null;
					if (empty($date_gmt) && isset($item['meta_parts']['post_date_formatted'])) {
						$date_gmt = $item['meta_parts']['post_date_formatted'];
						$item_timestamp = strtotime($date_gmt);
					} else {
						$item_timestamp = $date_gmt ? strtotime($date_gmt) : false;
					}
					$original_date_value = $date_gmt;
				}
				if ($cutoff_timestamp !== null) {
					if ($item_timestamp === false) {
						continue;
					}
					if ($item_timestamp < $cutoff_timestamp) {
						if ($orderby === 'date' && $order === 'desc') {
							$hit_time_limit_boundary = true;
						}
						continue;
					}
				}
				// --- Local Search Term Filtering ---
				if (!empty($search_term)) {
					$keywords = array_map('trim', explode(',', $search_term));
					$keywords = array_filter($keywords);
					if (!empty($keywords)) {
						$title_to_check = $item['title']['rendered'] ?? $item['title'] ?? $item['headline'] ?? '';
						$content_raw = $item['content']['rendered'] ?? $item['content'] ?? $item['excerpt'] ?? '';
						$prologue_raw = $item['prologue'] ?? '';
						$content_html = $prologue_raw;
						if (is_array($content_raw)) {
							$content_html .= implode("\n", $content_raw);
						} elseif (is_string($content_raw)) {
							$content_html .= $content_raw;
						}
						$content_to_check = $content_html;
						$text_to_search = $title_to_check . ' ' . strip_tags($content_to_check);
						$found_keyword = false;
						foreach ($keywords as $keyword) {
							if (mb_stripos($text_to_search, $keyword) !== false) {
								$found_keyword = true;
								break;
							}
						}
						if (!$found_keyword) {
							continue;
						}
					}
				}
				if ($this->db_processed_items->has_item_been_processed($module_id, 'public_rest_api', $current_item_id)) {
					continue;
				}
				// --- Data Extraction ---
				$title = $item['title']['rendered'] ?? $item['title'] ?? $item['headline'] ?? 'N/A';
				$content_parts = $item['content'] ?? [];
				$prologue = $item['prologue'] ?? '';
				$full_content_html = $prologue;
				if (is_array($content_parts)) {
					$full_content_html .= implode("\n", $content_parts);
				} elseif (is_string($content_parts)) {
					$full_content_html .= $content_parts;
				}
				if (empty(trim(strip_tags($full_content_html)))) {
					$content_fallback = $item['content']['rendered'] ?? $item['excerpt'] ?? '';
					if(is_string($content_fallback)) {
						$full_content_html = $content_fallback;
					}
				}
				$source_link = $item['url'] ?? $item['link'] ?? $item['permalink'] ?? $api_endpoint_url;
				$original_date_string_for_meta = is_string($original_date_value) ? $original_date_value : null;
				// Extract source name from API endpoint URL
				$api_host = parse_url($api_endpoint_url, PHP_URL_HOST);
				$source_name = $api_host ? ucwords(str_replace(['www.', '.com', '.org', '.net'], '', $api_host)) : 'Unknown Source';
				$content_string = "Source: " . $source_name . "\n\nTitle: " . $title . "\n\n" . wp_strip_all_tags($full_content_html);
				$input_data_packet = [
					'data' => [
						'content_string' => $content_string,
						'file_info' => null
					],
					'metadata' => [
						'source_type' => 'public_rest_api',
						'item_identifier_to_log' => $current_item_id,
						'original_id' => $current_item_id,
						'source_url' => $source_link,
						'original_title' => $title,
						'original_date_gmt' => $original_date_string_for_meta,
					]
				];
				$this->logger?->debug('Public REST Input: Adding eligible item', ['item_id' => $current_item_id, 'title' => $title, 'module_id' => $module_id]);
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
			$this->logger?->add_admin_info(__('No new items found matching the criteria from the API endpoint.', 'data-machine'));
			return ['status' => 'no_new_items', 'message' => __('No new items found matching the criteria from the API endpoint.', 'data-machine')];
		}
		return $eligible_items_packets;
	}

    /**
     * Recursively find the first array of objects in a JSON structure.
     */
    private static function find_first_array_of_objects($data) {
        $title_keys = ['title', 'title.rendered', 'headline'];
        if (is_array($data)) {
            if (!empty($data) && is_array(reset($data)) && array_keys(reset($data)) !== range(0, count(reset($data)) - 1)) {
                foreach ($data as $obj) {
                    if (is_array($obj)) {
                        foreach ($title_keys as $key) {
                            if (isset($obj[$key])) {
                                return $data;
                            }
                            if ($key === 'title.rendered' && isset($obj['title']) && is_array($obj['title']) && isset($obj['title']['rendered'])) {
                                return $data;
                            }
                        }
                    }
                }
            }
            foreach ($data as $value) {
                $result = self::find_first_array_of_objects($value);
                if (!empty($result)) {
                    return $result;
                }
            }
        }
        return [];
    }

	/**
	 * Get settings fields specific to the Public REST API handler.
	 *
	 * @return array Associative array of field definitions.
	 */
	public static function get_settings_fields(): array {
		return [
			'api_endpoint_url' => [
				'type' => 'url',
				'label' => __('API Endpoint URL', 'data-machine'),
				'description' => __('Enter the full URL of the public REST API endpoint (e.g., https://example.com/wp-json/wp/v2/posts). Standard WP REST API query parameters like ?per_page=X&orderby=date&order=desc are usually supported and added automatically, but you can add custom ones here if needed.', 'data-machine'),
				'required' => true,
				'default' => '',
			],
            'data_path' => [
                'type' => 'text',
                'label' => __('Data Path (Optional)', 'data-machine'),
                'description' => __('If the items are nested within the JSON response, specify the path using dot notation (e.g., `data.items`). Leave empty to auto-detect the first array of objects.', 'data-machine'),
                'default' => '',
            ],
            'search' => [
                'type' => 'text',
                'label' => __('Search Term', 'data-machine'),
                'description' => __('Optionally filter results locally by keywords (comma-separated). Only items containing at least one keyword in their title or content will be processed.', 'data-machine'),
                'default' => '',
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
				'description' => __('Only consider items published within this timeframe. Requires a parsable date field in the API response.', 'data-machine'),
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
		$sanitized['api_endpoint_url'] = esc_url_raw($raw_settings['api_endpoint_url'] ?? '');
        $sanitized['data_path'] = sanitize_text_field($raw_settings['data_path'] ?? '');
        $sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? '');
		$sanitized['item_count'] = max(1, absint($raw_settings['item_count'] ?? 1));
		$sanitized['timeframe_limit'] = sanitize_key($raw_settings['timeframe_limit'] ?? 'all_time');
		return $sanitized;
	}

	/**
	 * Get the user-friendly label for this handler.
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __('Public REST API', 'data-machine');
	}
}