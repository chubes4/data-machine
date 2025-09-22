<?php
/**
 * Reddit Fetch Handler - OAuth2-authenticated subreddit content fetcher.
 *
 * Provides clean content processing without URL pollution - source URLs maintained
 * in metadata only. Features comprehensive filtering, deduplication tracking,
 * and flow-isolated image storage.
 *
 * Features:
 * - OAuth2 authentication with automatic token refresh
 * - Subreddit posts and comments retrieval with filtering
 * - Clean content extraction without URL injection
 * - Flow-isolated image storage in files repository
 * - Comprehensive deduplication tracking per execution
 * - Reddit-specific rate limiting and error handling
 *
 * @package DataMachine
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Reddit;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Reddit {

	private $oauth_reddit;

	public function __construct() {
		$all_auth = apply_filters('dm_auth_providers', []);
		$this->oauth_reddit = $all_auth['reddit'] ?? null;
	}

	/**
	 * Get files repository instance for flow-isolated file storage.
	 *
	 * @return \DataMachine\Engine\FilesRepository|null Repository instance or null if unavailable
	 */
	private function get_repository(): ?\DataMachine\Engine\FilesRepository {
		$repositories = apply_filters('dm_files_repository', []);
		return $repositories['files'] ?? null;
	}

	/**
	 * Download and store Reddit image in flow-isolated repository.
	 * Uses Reddit-specific user agent and comprehensive error handling.
	 *
	 * @param string $image_url Remote image URL to download
	 * @param string $flow_step_id Flow step ID for isolation
	 * @param string $item_id Reddit item ID for filename generation
	 * @return array|null Image attachment data with path/filename/size or null on failure
	 */
	private function store_reddit_image(string $image_url, string $flow_step_id, string $item_id): ?array {
		$repository = $this->get_repository();
		if (!$repository) {
			do_action('dm_log', 'error', 'Reddit: FilesRepository not available for image storage', [
				'image_url' => $image_url,
				'item_id' => $item_id
			]);
			return null;
		}

		$url_path = wp_parse_url($image_url, PHP_URL_PATH);
		$extension = $url_path ? pathinfo($url_path, PATHINFO_EXTENSION) : 'jpg';
		if (empty($extension)) {
			$extension = 'jpg';
		}
		$filename = "reddit_image_{$item_id}.{$extension}";

		// Store image with Reddit-specific user agent
		$options = [
			'timeout' => 30,
			'user_agent' => 'php:DataMachineWPPlugin:v' . DATA_MACHINE_VERSION . ' (by /u/sailnlax04)'
		];

		return $repository->store_remote_file($image_url, $filename, $flow_step_id, $options);
	}


	/**
	 * Fetch Reddit content with clean data for AI processing.
	 * Returns processed items while storing engine data (source_url, image_url) in database.
	 *
	 * @param int $pipeline_id Pipeline ID for logging context.
	 * @param array $handler_config Handler configuration including subreddit, filters, flow_step_id.
	 * @param string|null $job_id Job ID for deduplication tracking.
	 * @return array Array with 'processed_items' containing clean data for AI processing.
	 *               Engine parameters (source_url, image_url) are stored via centralized dm_engine_data filter.
	 */
	public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {

		$flow_step_id = $handler_config['flow_step_id'] ?? null;
		$oauth_reddit = $this->oauth_reddit;

		$reddit_account = apply_filters('dm_retrieve_oauth_account', [], 'reddit');
		$needs_refresh = false;
		if (empty($reddit_account) || !is_array($reddit_account) || empty($reddit_account['access_token'])) {
			 if (!empty($reddit_account['refresh_token'])) {
				do_action('dm_log', 'debug', 'Reddit Input: Token missing or empty, refresh needed.', ['pipeline_id' => $pipeline_id]);
				  $needs_refresh = true;
			 } else {
				do_action('dm_log', 'error', 'Reddit Input: Reddit account not authenticated or token/refresh token missing.', ['pipeline_id' => $pipeline_id]);
				return ['processed_items' => []];
			}
		} else {
			 $token_expires_at = $reddit_account['token_expires_at'] ?? 0;
			if (time() >= ($token_expires_at - 300)) { // Check if expired or within 5 mins
				do_action('dm_log', 'debug', 'Reddit Input: Token expired or expiring soon, refresh needed.', ['pipeline_id' => $pipeline_id, 'expiry' => $token_expires_at]);
				$needs_refresh = true;
			 }
		}

		if ($needs_refresh) {
			do_action('dm_log', 'debug', 'Reddit Input: Attempting token refresh.', ['pipeline_id' => $pipeline_id]);
			$refreshed = $oauth_reddit->refresh_token();

			if (!$refreshed) {
				do_action('dm_log', 'error', 'Reddit Input: Token refresh failed.', ['pipeline_id' => $pipeline_id]);
				return ['processed_items' => []];
			}

			$reddit_account = apply_filters('dm_retrieve_oauth_account', [], 'reddit');
			if (empty($reddit_account['access_token'])) {
				do_action('dm_log', 'error', 'Reddit Input: Token refresh successful, but failed to retrieve new token data.', ['pipeline_id' => $pipeline_id]);
				return ['processed_items' => []];
			}
			do_action('dm_log', 'debug', 'Reddit Input: Token refresh successful.', ['pipeline_id' => $pipeline_id]);
		}

		$access_token = $reddit_account['access_token'] ?? null;
		if (empty($access_token)) {
			do_action('dm_log', 'error', 'Reddit Input: Access token is still empty after checks/refresh.', ['pipeline_id' => $pipeline_id]);
			return ['processed_items' => []];
		}

		do_action('dm_log', 'debug', 'Reddit Input: Token check complete.', [
			'pipeline_id' => $pipeline_id,
			'token_present' => !empty($access_token),
			'token_expiry_ts' => $reddit_account['token_expires_at'] ?? 'N/A'
		]);
		$config = $handler_config['reddit'] ?? [];
		$subreddit = trim( $config['subreddit'] ?? '' );
		$sort = $config['sort_by'] ?? 'hot';
		$timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
		$min_upvotes = isset($config['min_upvotes']) ? absint($config['min_upvotes']) : 0;
		$fetch_batch_size = 100;
		$min_comment_count = isset($config['min_comment_count']) ? absint($config['min_comment_count']) : 0;
		$comment_count_setting = isset($config['comment_count']) ? absint($config['comment_count']) : 0;
		$search_term = trim( $config['search'] ?? '' );
		$search_keywords = [];
		if (!empty($search_term)) {
			$search_keywords = array_map('trim', explode(',', $search_term));
			$search_keywords = array_filter($search_keywords);
		}

		if ( empty( $subreddit ) ) {
			do_action('dm_log', 'error', 'Reddit Input: Subreddit name not configured.', ['pipeline_id' => $pipeline_id]);
			return ['processed_items' => []];
		}
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $subreddit)) {
			do_action('dm_log', 'error', 'Reddit Input: Invalid subreddit name format.', ['pipeline_id' => $pipeline_id, 'subreddit' => $subreddit]);
			return ['processed_items' => []];
		}
		$valid_sorts = ['hot', 'new', 'top', 'rising', 'controversial'];
		if (!in_array($sort, $valid_sorts)) {
			do_action('dm_log', 'error', 'Reddit Input: Invalid sort parameter.', ['pipeline_id' => $pipeline_id, 'invalid_sort' => $sort, 'valid_sorts' => $valid_sorts]);
			return ['processed_items' => []];
		}

		$cutoff_timestamp = apply_filters('dm_timeframe_limit', null, $timeframe_limit);

		$after_param = null;
		$total_checked = 0;
		$max_pages = 5;
		$pages_fetched = 0;

		while ($pages_fetched < $max_pages) {
			$pages_fetched++;

			$time_param = '';
			if (in_array($sort, ['top', 'controversial']) && $timeframe_limit !== 'all_time') {
				$reddit_time_map = [
					'24_hours' => 'day',
					'72_hours' => 'week',   // Reddit's closest option (no native 72h support)
					'7_days'   => 'week',
					'30_days'  => 'month'
				];
				if (isset($reddit_time_map[$timeframe_limit])) {
					$time_param = '&t=' . $reddit_time_map[$timeframe_limit];
					do_action('dm_log', 'debug', 'Reddit Input: Using native API time filtering.', [
						'sort' => $sort,
						'timeframe_limit' => $timeframe_limit,
						'reddit_time_param' => $reddit_time_map[$timeframe_limit],
						'pipeline_id' => $pipeline_id
					]);
				}
			}

			$reddit_url = sprintf(
				'https://oauth.reddit.com/r/%s/%s.json?limit=%d%s%s',
				esc_attr($subreddit),
				esc_attr($sort),
				$fetch_batch_size,
				$after_param ? '&after=' . urlencode($after_param) : '',
				$time_param
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
			do_action('dm_log', 'debug', 'Reddit Input: Making API call.', [
				'pipeline_id' => $pipeline_id,
				'page' => $pages_fetched,
				'url' => $reddit_url,
				'headers' => $log_headers
			]);

			$result = apply_filters('dm_request', null, 'GET', $reddit_url, $args, 'Reddit API');
			
			if (!$result['success']) {
				if ($pages_fetched === 1) {
					do_action('dm_log', 'error', 'Reddit Input: API request failed.', ['pipeline_id' => $pipeline_id, 'error' => $result['error']]);
					return ['processed_items' => []];
				}
				else break;
			}

			$body = $result['data'];
			do_action('dm_log', 'debug', 'Reddit Input: API Response Code', ['code' => $result['status_code'], 'url' => $reddit_url, 'pipeline_id' => $pipeline_id]);

			$response_data = json_decode($body, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				/* translators: %s: JSON error message */
				$error_message = sprintf(__('Invalid JSON from Reddit API: %s', 'data-machine'), json_last_error_msg());
				if ($pages_fetched === 1) {
					do_action('dm_log', 'error', 'Reddit Input: Invalid JSON response.', ['pipeline_id' => $pipeline_id, 'error' => $error_message]);
					return ['processed_items' => []];
				}
				else break;
			}
			if ( empty( $response_data['data']['children'] ) || ! is_array( $response_data['data']['children'] ) ) {
				do_action('dm_log', 'debug', 'Reddit Input: No more posts found or invalid data structure.', ['url' => $reddit_url, 'pipeline_id' => $pipeline_id]);
				break; // Stop fetching
			}
			$batch_hit_time_limit = false;
			foreach ($response_data['data']['children'] as $post_wrapper) {
				$total_checked++;
				if (empty($post_wrapper['data']) || empty($post_wrapper['data']['id']) || empty($post_wrapper['kind'])) {
					do_action('dm_log', 'warning', 'Reddit Input: Skipping post with missing data.', ['subreddit' => $subreddit, 'pipeline_id' => $pipeline_id]);
					continue;
				}
				$item_data = $post_wrapper['data'];
				$current_item_id = $item_data['id'];

				// Skip pinned/stickied posts (old but shown at top)
				$is_stickied = $item_data['stickied'] ?? false;
				$is_pinned = $item_data['pinned'] ?? false;
				if ($is_stickied || $is_pinned) {
					do_action('dm_log', 'debug', 'Reddit Input: Skipping pinned/stickied post.', [
						'item_id' => $current_item_id,
						'stickied' => $is_stickied,
						'pinned' => $is_pinned,
						'pipeline_id' => $pipeline_id
					]);
					continue;
				}

				// Timeframe filtering with consecutive post logic
				if ($cutoff_timestamp !== null) {
					if (empty($item_data['created_utc'])) {
						do_action('dm_log', 'debug', 'Reddit Input: Skipping item (missing creation date for timeframe check).', ['item_id' => $current_item_id, 'pipeline_id' => $pipeline_id]);
						continue;
					}
					$item_timestamp = (int) $item_data['created_utc'];
					if ($item_timestamp < $cutoff_timestamp) {
						if (!isset($consecutive_old_posts)) {
							$consecutive_old_posts = 0;
						}
						$consecutive_old_posts++;
						do_action('dm_log', 'debug', 'Reddit Input: Found old post - consecutive count.', [
							'item_id' => $current_item_id,
							'item_date' => gmdate('Y-m-d H:i:s', $item_timestamp),
							'cutoff' => gmdate('Y-m-d H:i:s', $cutoff_timestamp),
							'consecutive_count' => $consecutive_old_posts,
							'pipeline_id' => $pipeline_id
						]);

						if ($consecutive_old_posts >= 3) {
							do_action('dm_log', 'debug', 'Reddit Input: Hit timeframe limit after 3 consecutive old posts - stopping.', [
								'consecutive_old_posts' => $consecutive_old_posts,
								'pipeline_id' => $pipeline_id
							]);
							$batch_hit_time_limit = true;
							break;
						}
						continue;
					} else {
						$consecutive_old_posts = 0;
					}
				}

				if ($min_upvotes > 0) {
					if (!isset($item_data['score']) || $item_data['score'] < $min_upvotes) {
						do_action('dm_log', 'debug', 'Reddit Input: Skipping item (min upvotes).', ['item_id' => $current_item_id, 'score' => $item_data['score'] ?? 'N/A', 'min_required' => $min_upvotes, 'pipeline_id' => $pipeline_id]);
						continue;
					}
				}

				if ($flow_step_id) {
					$is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, 'reddit', $current_item_id);
					if ($is_processed) {
						do_action('dm_log', 'debug', 'Reddit Input: Skipping item (already processed).', ['item_id' => $current_item_id, 'pipeline_id' => $pipeline_id]);
						continue;
					}
				}

				if ($min_comment_count > 0) {
					if (!isset($item_data['num_comments']) || $item_data['num_comments'] < $min_comment_count) {
						do_action('dm_log', 'debug', 'Reddit Input: Skipping item (min comment count).', [
							'item_id' => $current_item_id,
							'comments' => $item_data['num_comments'] ?? 'N/A',
							'min_required' => $min_comment_count,
							'pipeline_id' => $pipeline_id
						]);
						continue;
					}
				}

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
						do_action('dm_log', 'debug', 'Reddit Input: Skipping item (search filter).', ['item_id' => $current_item_id, 'pipeline_id' => $pipeline_id]);
						continue;
					}
				}

				do_action('dm_log', 'debug', 'Reddit Input: Found eligible item.', ['item_id' => $current_item_id, 'pipeline_id' => $pipeline_id]);

				if ($flow_step_id && $job_id) {
					do_action('dm_mark_item_processed', $flow_step_id, 'reddit', $current_item_id, $job_id);
				}

				$title = $item_data['title'] ?? '';
				$selftext = $item_data['selftext'] ?? '';
				$body = $item_data['body'] ?? '';

				// Create structured content data for AI processing
				$content_data = [
					'title' => trim($title),
					'content' => !empty($selftext) ? trim($selftext) : (!empty($body) ? trim($body) : '')
				];

				// Fetch and structure comments if configured
				$comments_array = [];
				if ($comment_count_setting > 0 && !empty($item_data['permalink'])) {
					$comments_url = 'https://oauth.reddit.com' . $item_data['permalink'] . '.json?limit=' . $comment_count_setting . '&sort=top';
					$comment_args = [
						'user-agent' => $args['user-agent'],
						'headers' => [
							'Authorization' => 'Bearer ' . $access_token
						]
					];
					$comments_result = apply_filters('dm_request', null, 'GET', $comments_url, $comment_args, 'Reddit API');

					if ($comments_result['success']) {
						$comments_data = json_decode($comments_result['data'], true);
						if (json_last_error() === JSON_ERROR_NONE) {
						if (is_array($comments_data) && isset($comments_data[1]['data']['children'])) {
							$top_comments = array_slice($comments_data[1]['data']['children'], 0, $comment_count_setting);
							foreach ($top_comments as $comment_wrapper) {
								if (isset($comment_wrapper['data']['body']) && !$comment_wrapper['data']['stickied']) {
									$comment_author = $comment_wrapper['data']['author'] ?? '[deleted]';
									$comment_body = trim($comment_wrapper['data']['body']);
									if ($comment_body !== '') {
										$comments_array[] = [
											'author' => $comment_author,
											'body' => $comment_body
										];
									}
								}
								if (count($comments_array) >= $comment_count_setting) break;
							}
						}
						} else {
							do_action('dm_log', 'warning', 'Reddit Input: Failed to parse comments JSON.', [
								'item_id' => $current_item_id,
								'comments_url' => $comments_url,
								'error' => json_last_error_msg(),
								'pipeline_id' => $pipeline_id
							]);
						}
					} else {
						do_action('dm_log', 'warning', 'Reddit Input: Failed to fetch comments for post.', [
							'item_id' => $current_item_id,
							'comments_url' => $comments_url,
							'error' => $comments_result['error'],
							'pipeline_id' => $pipeline_id
						]);
					}
				}
				// --- End fetch/append comments ---

				// --- Detect image post and download to repository if found ---
				$stored_image = null;
				$image_info = null;
				$url = $item_data['url'] ?? '';
				$is_imgur = preg_match('#^https?://(www\.)?imgur\.com/([^./]+)$#i', $url, $imgur_matches);

				// 1. Gallery support (Reddit API: is_gallery + media_metadata)
				if (!empty($item_data['is_gallery']) && !empty($item_data['media_metadata']) && is_array($item_data['media_metadata'])) {
					// Get the first image in the gallery
					$first_media = reset($item_data['media_metadata']);
					if (!empty($first_media['s']['u'])) {
						$direct_url = html_entity_decode($first_media['s']['u']);
						$mime_type = 'image/jpeg';
						$image_info = [
							'url' => $direct_url,
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
					$image_info = [
						'url' => $direct_url,
						'mime_type' => $mime_type,
					];
				}

				// Download and store image in repository if detected
				if ($image_info) {
					$stored_image = $this->store_reddit_image($image_info['url'], $flow_step_id, $current_item_id);
				}

				// Create clean metadata for AI consumption (URLs stored separately via engine data)
				$metadata = [
					'source_type' => 'reddit',
					'item_identifier_to_log' => (string) $current_item_id,
					'original_id' => $current_item_id,
					'original_title' => $title,
					'original_date_gmt' => gmdate('Y-m-d\TH:i:s\Z', (int)($item_data['created_utc'] ?? time())),
					'subreddit' => $subreddit,
					'upvotes' => $item_data['score'] ?? 0,
					'comment_count' => $item_data['num_comments'] ?? 0,
					'author' => $item_data['author'] ?? '[deleted]',
					'is_self_post' => $item_data['is_self'] ?? false,
				];

				// Add comments to content data if available
				if (!empty($comments_array)) {
					$content_data['comments'] = $comments_array;
				}

				// Create data packet using universal pattern
				if ($stored_image) {
					// Image post - use universal pattern with file_info
					$file_info = [
						'file_path' => $stored_image['path'],
						'file_name' => $stored_image['filename'],
						'mime_type' => $image_info['mime_type'],
						'file_size' => $stored_image['size']
					];
					$metadata = array_merge($metadata, [
						'original_title' => $title,
						'original_id' => $current_item_id,
						'original_date_gmt' => gmdate('Y-m-d H:i:s', (int)($item_data['created_utc'] ?? time())),
						'item_identifier_to_log' => $current_item_id
					]);

					// Create clean data packet for AI processing
					$input_data = [
						'data' => array_merge($content_data, ['file_info' => $file_info]),
						'metadata' => $metadata
					];
				} else {
					// Text-only post - use universal pattern with null file_info
					$metadata = array_merge($metadata, [
						'original_title' => $title,
						'original_id' => $current_item_id,
						'original_date_gmt' => gmdate('Y-m-d H:i:s', (int)($item_data['created_utc'] ?? time())),
						'item_identifier_to_log' => $current_item_id
					]);

					// Create clean data packet for AI processing
					$input_data = [
						'data' => $content_data,
						'metadata' => $metadata
					];
				}

				// Store URLs in engine_data via centralized filter
				if ($job_id) {
					$source_url = $item_data['permalink'] ? 'https://www.reddit.com' . $item_data['permalink'] : '';

					// Generate image URL if file was stored
					$image_url = '';
					if ($stored_image && $flow_step_id) {
						$repositories = apply_filters('dm_files_repository', []);
						$file_repository = $repositories['files'] ?? null;
						if ($file_repository && !empty($stored_image['filename'])) {
							$image_url = trailingslashit($file_repository->get_repository_url($flow_step_id)) . $stored_image['filename'];
						}
					}

					apply_filters('dm_engine_data', null, $job_id, $source_url, $image_url);
				}

				// Return clean data packet (no URLs in metadata for AI)
				return ['processed_items' => [$input_data]];
			} // End foreach ($response_data...)

			if ($batch_hit_time_limit) {
				do_action('dm_log', 'debug', 'Reddit Input: Stopping pagination due to hitting time limit within batch.', ['pipeline_id' => $pipeline_id]);
				break;
			}

			$after_param = $response_data['data']['after'] ?? null;
			if (!$after_param) {
				do_action('dm_log', 'debug', "Reddit Input: No 'after' parameter found, ending pagination.", ['pipeline_id' => $pipeline_id]);
				break;
			}

		} // End while loop

		do_action('dm_log', 'debug', 'Reddit Input: No eligible items found.', [
			'total_checked' => $total_checked,
			'pages_fetched' => $pages_fetched,
			'pipeline_id' => $pipeline_id
		]);

		return [
			'processed_items' => []
		];
	}


	/**
	 * Sanitize Reddit handler settings with comprehensive validation.
	 * Validates subreddit names, sort parameters, timeframes, and numeric filters.
	 *
	 * @param array $raw_settings Raw handler settings from user input
	 * @return array Sanitized settings or empty array on validation failure
	 */
	public function sanitize_settings(array $raw_settings): array {
		$sanitized = [];
		$subreddit = sanitize_text_field($raw_settings['subreddit'] ?? '');
		$sanitized['subreddit'] = (preg_match('/^[a-zA-Z0-9_]+$/', $subreddit)) ? $subreddit : '';
		$valid_sorts = ['hot', 'new', 'top', 'rising', 'controversial'];
		$sort_by = sanitize_text_field($raw_settings['sort_by'] ?? 'hot');
		if (!in_array($sort_by, $valid_sorts)) {
			do_action('dm_log', 'error', 'Reddit Settings: Invalid sort parameter provided in settings.', ['sort_by' => $sort_by]);
			return [];
		}
		$sanitized['sort_by'] = $sort_by;
		$sanitized['timeframe_limit'] = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
		$min_upvotes = isset($raw_settings['min_upvotes']) ? absint($raw_settings['min_upvotes']) : 0;
		$sanitized['min_upvotes'] = max(0, $min_upvotes);
		$min_comment_count = isset($raw_settings['min_comment_count']) ? absint($raw_settings['min_comment_count']) : 0;
		$sanitized['min_comment_count'] = max(0, $min_comment_count);
		$comment_count = isset($raw_settings['comment_count']) ? absint($raw_settings['comment_count']) : 0;
		$sanitized['comment_count'] = max(0, $comment_count);
		$sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? '');
		return $sanitized;
	}

	public static function get_label(): string {
		return 'Reddit Subreddit';
	}

}
