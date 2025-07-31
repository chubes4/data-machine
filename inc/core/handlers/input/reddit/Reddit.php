<?php
/**
 * Handles Reddit Subreddits as a data source.
 *
 * Fetches posts from a subreddit using the public Reddit JSON API.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/input
 * @since      0.15.0
 */

namespace DataMachine\Core\Handlers\Input\Reddit;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Reddit {

	/**
	 * @var RedditAuth OAuth Reddit authentication handler instance
	 */
	private $oauth_reddit;

	/**
	 * Constructor - direct auth initialization for security
	 */
	public function __construct() {
		// Initialize auth directly - auth is internal implementation detail
		$this->oauth_reddit = new RedditAuth();
	}

	/**
	 * Get the OAuth Reddit service - internal implementation.
	 *
	 * @return \DataMachine\Core\Handlers\Input\Reddit\RedditAuth The OAuth Reddit service.
	 */
	private function get_oauth_reddit() {
		return $this->oauth_reddit;
	}

	/**
	 * Fetches and prepares input data packets from a specified subreddit.
	 *
	 * @param object $module The full module object containing configuration and context.
	 * @param array  $source_config Decoded data_source_config specific to this handler.
	 * @param int    $user_id The ID of the user initiating the process (for ownership/context checks).
	 * @return array Array containing 'processed_items' key with standardized data packets for Reddit data.
	 * @throws Exception If data cannot be retrieved or is invalid.
	 */
	public function get_input_data(object $module, array $source_config, int $user_id): array {
		$logger = apply_filters('dm_get_logger', null);
		$logger?->info('Reddit Input: Entering get_input_data.', ['module_id' => $module->module_id ?? null]);

		// Get module ID from the passed module object
		$module_id = isset($module->module_id) ? absint($module->module_id) : 0;

		if ( empty( $module_id ) || empty( $user_id ) ) {
			$logger?->error('Reddit Input: Missing module ID or user ID.', ['module_id' => $module_id, 'user_id' => $user_id]);
			throw new Exception(esc_html__( 'Missing module ID or user ID provided to Reddit handler.', 'data-machine' ));
		}

		// Get services via filter-based access (current architecture)
		$oauth_reddit = $this->oauth_reddit; // Internal auth instance
		$db_processed_items = apply_filters('dm_get_database_service', null, 'processed_items');

		// Check if essential dependencies are available
		if (!$db_processed_items) {
			$logger?->error('Reddit Input: Required service dependency missing.', [
				'module_id' => $module_id,
				'processed_items_missing' => !$db_processed_items
			]);
			throw new Exception(esc_html__( 'Required service not available in Reddit handler.', 'data-machine' ));
		}

		// --- Retrieve Reddit OAuth Token & Refresh if needed ---
		$reddit_account = get_user_meta($user_id, 'data_machine_reddit_account', true);
		$needs_refresh = false;
		if (empty($reddit_account) || !is_array($reddit_account) || empty($reddit_account['access_token'])) {
			 if (!empty($reddit_account['refresh_token'])) {
				$logger?->info('Reddit Input: Token missing or empty, refresh needed.', ['module_id' => $module_id, 'user_id' => $user_id]);
				  $needs_refresh = true;
			 } else {
				$logger?->error('Reddit Input: Reddit account not authenticated or token/refresh token missing.', ['module_id' => $module_id, 'user_id' => $user_id]);
				throw new Exception(esc_html__( 'Reddit account not authenticated or token missing. Please authenticate on the API Keys page.', 'data-machine' ));
			}
		} else {
			 $token_expires_at = $reddit_account['token_expires_at'] ?? 0;
			if (time() >= ($token_expires_at - 300)) { // Check if expired or within 5 mins
				$logger?->info('Reddit Input: Token expired or expiring soon, refresh needed.', ['module_id' => $module_id, 'user_id' => $user_id, 'expiry' => $token_expires_at]);
				$needs_refresh = true;
			 }
		}

		if ($needs_refresh) {
			$logger?->info('Reddit Input: Attempting token refresh.', ['module_id' => $module_id, 'user_id' => $user_id]);
			// Use the OAuth service
			$refreshed = $oauth_reddit->refresh_token($user_id);

			if (!$refreshed) {
				// Error already logged by refresh_token method
				$logger?->error('Reddit Input: Token refresh failed.', ['module_id' => $module_id, 'user_id' => $user_id]);
				throw new Exception(esc_html__( 'Failed to refresh expired Reddit access token. Please re-authenticate the Reddit account on the API Keys page.', 'data-machine' ));
			}

			// Re-fetch updated account data after successful refresh
			$reddit_account = get_user_meta($user_id, 'data_machine_reddit_account', true);
			if (empty($reddit_account['access_token'])) {
				$logger?->error('Reddit Input: Token refresh successful, but failed to retrieve new token data.', ['module_id' => $module_id, 'user_id' => $user_id]);
				 throw new Exception(esc_html__( 'Reddit token refresh seemed successful, but failed to retrieve new token data.', 'data-machine' ));
			}
			$logger?->info('Reddit Input: Token refresh successful.', ['module_id' => $module_id, 'user_id' => $user_id]);
		}

		// Decrypt the access token
		$encrypted_access_token = $reddit_account['access_token'] ?? null;
		if (empty($encrypted_access_token)) {
			$logger?->error('Reddit Input: Access token is still empty after checks/refresh.', ['module_id' => $module_id, 'user_id' => $user_id]);
			throw new Exception(esc_html__( 'Could not obtain valid Reddit access token.', 'data-machine' ));
		}
		
		$encryption_helper = apply_filters('dm_get_encryption_helper', null);
		$access_token = $encryption_helper->decrypt($encrypted_access_token);
		if ($access_token === false) {
			$logger?->error('Reddit Input: Failed to decrypt access token.', ['module_id' => $module_id, 'user_id' => $user_id]);
			throw new Exception(esc_html__( 'Failed to decrypt Reddit access token. Please re-authenticate.', 'data-machine' ));
		}
		// --- End Token Retrieval & Refresh ---

		$logger?->debug('Reddit Input: Token check complete.', [
			'module_id' => $module_id,
			'token_present' => !empty($access_token),
			'token_expiry_ts' => $reddit_account['token_expires_at'] ?? 'N/A'
		]);

		// --- Legacy ownership verification removed ---
		// Note: Ownership verification now handled at the flow/job level
		// Individual handlers no longer need to verify permissions

		// --- Configuration (from nested config structure) ---
		$config = $source_config['reddit'] ?? [];
		$subreddit = trim( $config['subreddit'] ?? '' );
		$sort = $config['sort_by'] ?? 'hot';
		$process_limit = max(1, absint( $config['item_count'] ?? 1 ));
		$timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
		$min_upvotes = isset($config['min_upvotes']) ? absint($config['min_upvotes']) : 0;
		$fetch_batch_size = 100; // Max items per Reddit API request
		$min_comment_count = isset($config['min_comment_count']) ? absint($config['min_comment_count']) : 0;
		$comment_count_setting = isset($config['comment_count']) ? absint($config['comment_count']) : 0;
		$search_term = trim( $config['search'] ?? '' );
		$search_keywords = [];
		if (!empty($search_term)) {
			$search_keywords = array_map('trim', explode(',', $search_term));
			$search_keywords = array_filter($search_keywords); // Remove empty keywords
		}

		if ( empty( $subreddit ) ) {
			$logger?->error('Reddit Input: Subreddit name not configured.', ['module_id' => $module_id]);
			throw new Exception(esc_html__( 'Subreddit name is not configured.', 'data-machine' ));
		}
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $subreddit)) {
			$logger?->error('Reddit Input: Invalid subreddit name format.', ['module_id' => $module_id, 'subreddit' => $subreddit]);
			throw new Exception(esc_html__( 'Invalid subreddit name format.', 'data-machine' ));
		}
		$valid_sorts = ['hot', 'new', 'top', 'rising'];
		if (!in_array($sort, $valid_sorts)) {
			$logger?->error('Reddit Input: Invalid sort parameter.', ['module_id' => $module_id, 'invalid_sort' => $sort, 'valid_sorts' => $valid_sorts]);
			throw new Exception(esc_html__('Invalid sort parameter provided. Please check configuration.', 'data-machine'));
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
		$max_checks = 500; // Safety break
		$pages_fetched = 0;
		$max_pages = 10; // Limit pages to prevent excessive calls

		// Loop to fetch pages until enough items are found or limits are hit
		while (count($eligible_items_packets) < $process_limit && $total_checked < $max_checks && $pages_fetched < $max_pages) {
			$pages_fetched++;
			// Construct the Reddit JSON API URL with pagination using the OAuth domain
			$reddit_url = sprintf(
				'https://oauth.reddit.com/r/%s/%s.json?limit=%d%s',
				esc_attr($subreddit),
				esc_attr($sort),
				$fetch_batch_size,
				$after_param ? '&after=' . urlencode($after_param) : ''
			);
			$args = [
				'user-agent' => 'php:DataMachineWPPlugin:v' . DATA_MACHINE_VERSION . ' (by /u/sailnlax04)', // Use constant
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token
				]
			];

			$log_headers = $args['headers'];
			if (isset($log_headers['Authorization'])) {
				$log_headers['Authorization'] = preg_replace('/(Bearer )(.{4}).+(.{4})/', '$1$2...$3', $log_headers['Authorization']);
			}
			$logger?->info('Reddit Input: Making API call.', [
				'module_id' => $module_id,
				'page' => $pages_fetched,
				'url' => $reddit_url,
				'headers' => $log_headers
			]);

			// Use HTTP service - replaces ~25 lines of duplicated HTTP code
			$http_service = apply_filters('dm_get_http_service', null);
			$http_response = $http_service->get($reddit_url, $args, 'Reddit API');
			if (is_wp_error($http_response)) {
				if ($pages_fetched === 1) throw new Exception(esc_html($http_response->get_error_message()));
				else break;
			}

			$body = $http_response['body'];
			$logger?->debug('Reddit Input: API Response Code', ['code' => $http_response['status_code'], 'url' => $reddit_url, 'module_id' => $module_id]);

			// Parse JSON response with error handling
			$response_data = $http_service->parse_json($body, 'Reddit API');
			if (is_wp_error($response_data)) {
				if ($pages_fetched === 1) throw new Exception(esc_html($response_data->get_error_message()));
				else break;
			}
			if ( empty( $response_data['data']['children'] ) || ! is_array( $response_data['data']['children'] ) ) {
				$logger?->info('Reddit Input: No more posts found or invalid data structure.', ['url' => $reddit_url, 'module_id' => $module_id]);
				break; // Stop fetching
			}
			// --- End API Response Handling ---

			// Process the items in the current batch
			$batch_hit_time_limit = false;
			foreach ($response_data['data']['children'] as $post_wrapper) {
				$total_checked++;
				if (empty($post_wrapper['data']) || empty($post_wrapper['data']['id']) || empty($post_wrapper['kind'])) {
					$logger?->warning('Reddit Input: Skipping post with missing data.', ['subreddit' => $subreddit, 'module_id' => $module_id]);
					continue; // Skip malformed posts
				}
				$item_data = $post_wrapper['data'];
				$current_item_id = $item_data['id'];

				// 1. Check timeframe limit first
				if ($cutoff_timestamp !== null) {
					if (empty($item_data['created_utc'])) {
						$logger?->debug('Reddit Input: Skipping item (missing creation date for timeframe check).', ['item_id' => $current_item_id, 'module_id' => $module_id]);
						continue; // Skip if no creation time available
					}
					$item_timestamp = (int) $item_data['created_utc'];
					if ($item_timestamp < $cutoff_timestamp) {
						$logger?->debug('Reddit Input: Skipping item (timeframe limit).', ['item_id' => $current_item_id, 'item_date' => gmdate('Y-m-d H:i:s', $item_timestamp), 'cutoff' => gmdate('Y-m-d H:i:s', $cutoff_timestamp), 'module_id' => $module_id]);
						$batch_hit_time_limit = true;
						continue; // Skip item if it's too old
					}
				}

				// 2. Check minimum upvotes (score)
				if ($min_upvotes > 0) {
					if (!isset($item_data['score']) || $item_data['score'] < $min_upvotes) {
						$logger?->debug('Reddit Input: Skipping item (min upvotes).', ['item_id' => $current_item_id, 'score' => $item_data['score'] ?? 'N/A', 'min_required' => $min_upvotes, 'module_id' => $module_id]);
						continue; // Skip if not enough upvotes
					}
				}

				// 3. Check if already processed
				if ($db_processed_items->is_processed($module_id, 'reddit', $current_item_id)) {
					$logger?->debug('Reddit Input: Skipping item (already processed).', ['item_id' => $current_item_id, 'module_id' => $module_id]);
					continue; // Skip if already processed
				}

				// 4. Check minimum comment count
				if ($min_comment_count > 0) {
					if (!isset($item_data['num_comments']) || $item_data['num_comments'] < $min_comment_count) {
						$logger?->debug('Reddit Input: Skipping item (min comment count).', [
							'item_id' => $current_item_id,
							'comments' => $item_data['num_comments'] ?? 'N/A',
							'min_required' => $min_comment_count,
							'module_id' => $module_id
						]);
						continue; // Skip if not enough comments
					}
				}

				// 5. Check search term filter
				if (!empty($search_keywords)) {
					$title_to_check = $item_data['title'] ?? '';
					$selftext_to_check = $item_data['selftext'] ?? '';
					$text_to_search = $title_to_check . ' ' . $selftext_to_check;
					$found_keyword = false;
					foreach ($search_keywords as $keyword) {
						if (mb_stripos($text_to_search, $keyword) !== false) {
							$found_keyword = true;
							break;
						}
					}
					if (!$found_keyword) {
						$logger?->debug('Reddit Input: Skipping item (search filter).', ['item_id' => $current_item_id, 'module_id' => $module_id]);
						continue; // Skip if no keyword found
					}
				}

				// --- Item is ELIGIBLE! ---
				$logger?->debug('Reddit Input: Found eligible item.', ['item_id' => $current_item_id, 'module_id' => $module_id]);

				// Prepare content string (Title and selftext/body)
				$title = $item_data['title'] ?? '';
				$selftext = $item_data['selftext'] ?? ''; // For self-posts
				$body = $item_data['body'] ?? ''; // For comments (if fetching comments later)

				$content_string = "Source: Reddit (r/{$subreddit})\n\nTitle: " . trim($title) . "\n\n";
				if (!empty($selftext)) {
					$content_string .= "Content:\n" . trim($selftext) . "\n";
				} elseif (!empty($body)) {
					$content_string .= "Content:\n" . trim($body) . "\n";
				}

				// Add URL if it's not a self-post
				if (!($item_data['is_self'] ?? false) && !empty($item_data['url'])) {
					$content_string .= "\nSource URL: " . $item_data['url'];
				}

				// --- Fetch and append top comments if requested ---
				if ($comment_count_setting > 0 && !empty($item_data['permalink'])) {
					$comments_url = 'https://oauth.reddit.com' . $item_data['permalink'] . '.json?limit=' . $comment_count_setting . '&sort=top';
					$comment_args = [
						'user-agent' => $args['user-agent'],
						'headers' => [
							'Authorization' => 'Bearer ' . $access_token
						]
					];
					try {
						$comments_response = $http_service->get($comments_url, $comment_args, 'Reddit Comments');
						if (!is_wp_error($comments_response)) {
							$comments_data = $http_service->parse_json($comments_response['body'], 'Reddit Comments');
							if (!is_wp_error($comments_data)) {
							if (is_array($comments_data) && isset($comments_data[1]['data']['children'])) {
								$top_comments = array_slice($comments_data[1]['data']['children'], 0, $comment_count_setting);
								if (!empty($top_comments)) {
									$content_string .= "\n\nTop Comments:\n";
									$comment_num = 1;
									foreach ($top_comments as $comment_wrapper) {
										if (isset($comment_wrapper['data']['body']) && !$comment_wrapper['data']['stickied']) {
											$author = $comment_wrapper['data']['author'] ?? '[deleted]';
											$body = trim($comment_wrapper['data']['body']);
											if ($body !== '') {
												$content_string .= "- {$author}: {$body}\n";
												$comment_num++;
											}
										}
										if ($comment_num > $comment_count_setting) break;
									}
								}
							}
							} else {
								$logger?->warning('Reddit Input: Failed to parse comments JSON.', [
									'item_id' => $current_item_id,
									'comments_url' => $comments_url,
									'error' => $comments_data->get_error_message(),
									'module_id' => $module_id
								]);
							}
						} else {
							$logger?->warning('Reddit Input: Failed to fetch comments for post.', [
								'item_id' => $current_item_id,
								'comments_url' => $comments_url,
								'error' => $comments_response->get_error_message(),
								'module_id' => $module_id
							]);
						}
					} catch (Exception $e) {
						$logger?->error('Reddit Input: Exception while fetching comments.', [
							'item_id' => $current_item_id,
							'comments_url' => $comments_url,
							'exception' => $e->getMessage(),
							'module_id' => $module_id
						]);
					}
				}
				// --- End fetch/append comments ---

				// --- Detect image post and set file_info if applicable ---
				$file_info = null;
				$url = $item_data['url'] ?? '';
				$is_imgur = preg_match('#^https?://(www\.)?imgur\.com/([^./]+)$#i', $url, $imgur_matches);

				// 1. Gallery support (Reddit API: is_gallery + media_metadata)
				if (!empty($item_data['is_gallery']) && !empty($item_data['media_metadata']) && is_array($item_data['media_metadata'])) {
					// Get the first image in the gallery
					$first_media = reset($item_data['media_metadata']);
					if (!empty($first_media['s']['u'])) {
						$direct_url = html_entity_decode($first_media['s']['u']);
						$mime_type = 'image/jpeg'; // Most Reddit gallery images are JPEG
						$file_info = [
							'url' => $direct_url,
							'type' => $mime_type,
							'mime_type' => $mime_type,
						];
					}
				}
				// 2. Imgur or direct image
				elseif (
					!empty($url) &&
					(
						(isset($item_data['post_hint']) && $item_data['post_hint'] === 'image') ||
						preg_match('/\\.(jpg|jpeg|png|webp|gif)$/i', $url) ||
						$is_imgur
					)
				) {
					if ($is_imgur) {
						// Convert to direct image link
						$direct_url = $url . '.jpg';
						$mime_type = 'image/jpeg';
					} else {
						$direct_url = $url;
						$ext = strtolower(pathinfo(wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
						$mime_map = [
							'jpg' => 'image/jpeg',
							'jpeg' => 'image/jpeg',
							'png' => 'image/png',
							'webp' => 'image/webp',
							'gif' => 'image/gif',
						];
						$mime_type = $mime_map[$ext] ?? 'application/octet-stream';
					}
					$file_info = [
						'url' => $direct_url,
						'type' => $mime_type,
						'mime_type' => $mime_type,
					];
				}
				// --- End image detection ---

				// Format metadata
				$metadata = [
					'source_type' => 'reddit',
					'item_identifier_to_log' => (string) $current_item_id,
					'original_id' => $current_item_id,
					'source_url' => 'https://www.reddit.com' . ($item_data['permalink'] ?? ''),
					'original_title' => $title,
					'original_date_gmt' => gmdate('Y-m-d\TH:i:s\Z', (int)($item_data['created_utc'] ?? time())),
					'subreddit' => $subreddit,
					'upvotes' => $item_data['score'] ?? 0,
					'comment_count' => $item_data['num_comments'] ?? 0,
					'author' => $item_data['author'] ?? '[deleted]',
					'is_self_post' => $item_data['is_self'] ?? false,
					'external_url' => (!($item_data['is_self'] ?? false) && !empty($item_data['url'])) ? $item_data['url'] : null,
					'image_source_url' => $file_info['url'] ?? null,
				];
				$metadata['raw_reddit_data'] = $item_data; // Include raw data

				// Create the standardized packet
				$input_data_packet = [
					'data' => [
						'content_string' => $content_string,
						'file_info' => $file_info
					],
					'metadata' => $metadata
				];
				array_push($eligible_items_packets, $input_data_packet);

				if (count($eligible_items_packets) >= $process_limit) {
					$logger?->debug('Reddit Input: Reached process limit.', ['limit' => $process_limit, 'module_id' => $module_id]);
					break; // Stop processing this batch
				}
			} // End foreach ($response_data...)

			// Stop pagination if we hit the time limit boundary in the batch
			if ($batch_hit_time_limit) {
				$logger?->debug('Reddit Input: Stopping pagination due to hitting time limit within batch.', ['module_id' => $module_id]);
				break;
			}

			// Prepare for the next page fetch
			$after_param = $response_data['data']['after'] ?? null;
			if (!$after_param) {
				$logger?->debug("Reddit Input: No 'after' parameter found, ending pagination.", ['module_id' => $module_id]);
				break; // No more pages indicated by Reddit
			}

		} // End while loop

		$found_count = count($eligible_items_packets);
		$logger?->info('Reddit Input: Finished fetching.', ['found_count' => $found_count, 'total_checked' => $total_checked, 'pages_fetched' => $pages_fetched, 'module_id' => $module_id]);

		if (empty($eligible_items_packets)) {
			return [
				'processed_items' => []
			];
		}

		// Return processed items array
		return [
			'processed_items' => $eligible_items_packets
		];
	}


	/**
	 * Sanitize settings for the Reddit input handler.
	 *
	 * @param array $raw_settings
	 * @return array
	 */
	public function sanitize_settings(array $raw_settings): array {
		$sanitized = [];
		$subreddit = sanitize_text_field($raw_settings['subreddit'] ?? '');
		$sanitized['subreddit'] = (preg_match('/^[a-zA-Z0-9_]+$/', $subreddit)) ? $subreddit : '';
		$valid_sorts = ['hot', 'new', 'top', 'rising'];
		$sort_by = sanitize_text_field($raw_settings['sort_by'] ?? 'hot');
		if (!in_array($sort_by, $valid_sorts)) {
			throw new Exception(esc_html__('Invalid sort parameter provided in settings.', 'data-machine'));
		}
		$sanitized['sort_by'] = $sort_by;
		$sanitized['item_count'] = min(100, max(1, absint($raw_settings['item_count'] ?? 1)));
		$valid_timeframes = ['all_time', '24_hours', '72_hours', '7_days', '30_days'];
		$timeframe = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
		if (!in_array($timeframe, $valid_timeframes)) {
			throw new Exception(esc_html__('Invalid timeframe parameter provided in settings.', 'data-machine'));
		}
		$sanitized['timeframe_limit'] = $timeframe;
		$min_upvotes = isset($raw_settings['min_upvotes']) ? absint($raw_settings['min_upvotes']) : 0;
		$sanitized['min_upvotes'] = max(0, $min_upvotes);
		$min_comment_count = isset($raw_settings['min_comment_count']) ? absint($raw_settings['min_comment_count']) : 0;
		$sanitized['min_comment_count'] = max(0, $min_comment_count);
		$comment_count = isset($raw_settings['comment_count']) ? absint($raw_settings['comment_count']) : 0;
		$sanitized['comment_count'] = max(0, $comment_count);
		$sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? ''); // Sanitize search term
		return $sanitized;
	}

	/**
	 * Get the user-friendly label for this handler.
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return 'Reddit Subreddit';
	}

} // End class \DataMachine\Core\Handlers\Input\Reddit\Reddit

