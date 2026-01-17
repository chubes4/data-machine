<?php
/**
 * Fetch Reddit posts with timeframe and keyword filtering.
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Reddit;

use DataMachine\Core\ExecutionContext;
use DataMachine\Core\Steps\Fetch\Handlers\FetchHandler;
use DataMachine\Core\Steps\HandlerRegistrationTrait;
use DataMachine\Services\AuthProviderService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Reddit extends FetchHandler {

	use HandlerRegistrationTrait;

	private $oauth_reddit;

	public function __construct() {
		parent::__construct( 'reddit' );

		// Self-register with filters
		self::registerHandler(
			'reddit',
			'fetch',
			self::class,
			'Reddit',
			'Fetch posts from Reddit subreddits',
			true,
			RedditAuth::class,
			RedditSettings::class,
			null
		);
	}

	/**
	 * Lazy-load auth provider when needed.
	 *
	 * @return object|null Auth provider instance or null if unavailable
	 */
	private function get_oauth_reddit() {
		if ($this->oauth_reddit === null) {
			$this->oauth_reddit = $this->getAuthProvider('reddit');

			if ($this->oauth_reddit === null) {
				$auth_service = new AuthProviderService();
				do_action('datamachine_log', 'error', 'Reddit Handler: Authentication service not available', [
					'agent_type' => 'system',
					'handler' => 'reddit',
					'missing_service' => 'reddit',
					'available_providers' => array_keys($auth_service->getAll())
				]);
			}
		}
		return $this->oauth_reddit;
	}

	/**
	 * Store Reddit image to file repository.
	 */
	private function store_reddit_image(string $image_url, ExecutionContext $context, string $item_id): ?array {
		$url_path = wp_parse_url($image_url, PHP_URL_PATH);
		$extension = $url_path ? pathinfo($url_path, PATHINFO_EXTENSION) : 'jpg';
		if (empty($extension)) {
			$extension = 'jpg';
		}
		$filename = "reddit_image_{$item_id}.{$extension}";

		$options = [
			'timeout' => 30,
			'user_agent' => 'php:DataMachineWPPlugin:v' . DATAMACHINE_VERSION
		];

		return $context->downloadFile($image_url, $filename, $options);
	}

	/**
	 * Fetch Reddit posts with timeframe and keyword filtering.
	 */
	protected function executeFetch( array $config, ExecutionContext $context ): array {
		$oauth_reddit = $this->get_oauth_reddit();
		if (!$oauth_reddit) {
			$context->log('error', 'Reddit: Authentication not configured');
			return [];
		}

		$reddit_account = $oauth_reddit->get_account();
		$access_token = $reddit_account['access_token'] ?? null;
		$token_expires_at = $reddit_account['token_expires_at'] ?? 0;
		$needs_refresh = empty($access_token) || time() >= ($token_expires_at - 300);

		if ($needs_refresh && empty($reddit_account['refresh_token'])) {
			$context->log('error', 'Reddit: No refresh token available');
			return [];
		}

		if ($needs_refresh) {
			$context->log('debug', 'Reddit: Attempting token refresh.');
			$refreshed = $oauth_reddit->refresh_token();

			if (!$refreshed) {
				$context->log('error', 'Reddit: Token refresh failed.');
				return [];
			}

			$reddit_account = $oauth_reddit->get_account();
			if (empty($reddit_account['access_token'])) {
				$context->log('error', 'Reddit: Token refresh successful, but failed to retrieve new token data.');
				return [];
			}
			$context->log('debug', 'Reddit: Token refresh successful.');
		}

		$access_token = $reddit_account['access_token'] ?? null;
		if (empty($access_token)) {
			$context->log('error', 'Reddit: Access token is still empty after checks/refresh.');
			return [];
		}

		$context->log('debug', 'Reddit: Token check complete.', [
			'token_present' => !empty($access_token),
			'token_expiry_ts' => $reddit_account['token_expires_at'] ?? 'N/A'
		]);

		if ( !isset( $config['subreddit'] ) || empty( trim( $config['subreddit'] ) ) ) {
			$context->log('error', 'Reddit: Subreddit name not configured.');
			return [];
		}

		$subreddit = trim( $config['subreddit'] );
		$sort = $config['sort_by'] ?? 'hot';
		$timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
		$min_upvotes = isset($config['min_upvotes']) ? absint($config['min_upvotes']) : 0;
		$fetch_batch_size = 100;
		$min_comment_count = isset($config['min_comment_count']) ? absint($config['min_comment_count']) : 0;
		$comment_count_setting = isset($config['comment_count']) ? absint($config['comment_count']) : 0;
		$search_term = trim( $config['search'] ?? '' );

		if (!preg_match('/^[a-zA-Z0-9_]+$/', $subreddit)) {
			$context->log('error', 'Reddit: Invalid subreddit name format.', ['subreddit' => $subreddit]);
			return [];
		}

		$valid_sorts = ['hot', 'new', 'top', 'rising', 'controversial'];
		if (!in_array($sort, $valid_sorts)) {
			$context->log('error', 'Reddit: Invalid sort parameter.', ['invalid_sort' => $sort, 'valid_sorts' => $valid_sorts]);
			return [];
		}

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
					'72_hours' => 'week',
					'7_days'   => 'week',
					'30_days'  => 'month'
				];
				if (isset($reddit_time_map[$timeframe_limit])) {
					$time_param = '&t=' . $reddit_time_map[$timeframe_limit];
					$context->log('debug', 'Reddit: Using native API time filtering.', [
						'sort' => $sort,
						'timeframe_limit' => $timeframe_limit,
						'reddit_time_param' => $reddit_time_map[$timeframe_limit]
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
			$headers = [
				'Authorization' => 'Bearer ' . $access_token
			];

			$log_headers = $headers;
			$log_headers['Authorization'] = preg_replace('/(Bearer )(.{4}).+(.{4})/', '$1$2...$3', $log_headers['Authorization']);
			$context->log('debug', 'Reddit: Making API call.', [
				'page' => $pages_fetched,
				'url' => $reddit_url,
				'headers' => $log_headers
			]);

			$result = $this->httpGet($reddit_url, [
				'headers' => $headers,
				'context' => 'Reddit API'
			]);

			if (!$result['success']) {
				if ($pages_fetched === 1) {
					$context->log('error', 'Reddit: API request failed.', ['error' => $result['error']]);
					return [];
				}
				else break;
			}

			$body = $result['data'];
			$context->log('debug', 'Reddit: API Response Code', ['code' => $result['status_code'], 'url' => $reddit_url]);

			$response_data = json_decode($body, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				$error_message = sprintf(__('Invalid JSON from Reddit API: %s', 'data-machine'), json_last_error_msg());
				if ($pages_fetched === 1) {
					$context->log('error', 'Reddit: Invalid JSON response.', ['error' => $error_message]);
					return [];
				}
				else break;
			}

			if ( empty( $response_data['data']['children'] ) || ! is_array( $response_data['data']['children'] ) ) {
				$context->log('debug', 'Reddit: No more posts found or invalid data structure.', ['url' => $reddit_url]);
				break;
			}

			$batch_hit_time_limit = false;
			foreach ($response_data['data']['children'] as $post_wrapper) {
				$total_checked++;
				if (empty($post_wrapper['data']) || empty($post_wrapper['data']['id']) || empty($post_wrapper['kind'])) {
					$context->log('warning', 'Reddit: Skipping post with missing data.', ['subreddit' => $subreddit]);
					continue;
				}
				$item_data = $post_wrapper['data'];
				$current_item_id = $item_data['id'];

				if (($item_data['stickied'] ?? false) || ($item_data['pinned'] ?? false)) {
					$context->log('debug', 'Reddit: Skipping pinned/stickied post.', ['item_id' => $current_item_id]);
					continue;
				}

				$item_timestamp = (int) ($item_data['created_utc'] ?? 0);
				if (!$this->applyTimeframeFilter($item_timestamp, $timeframe_limit)) {
					continue;
				}

				if ($min_upvotes > 0) {
					if (!isset($item_data['score']) || $item_data['score'] < $min_upvotes) {
						$context->log('debug', 'Reddit: Skipping item (min upvotes).', ['item_id' => $current_item_id, 'score' => $item_data['score'] ?? 'N/A', 'min_required' => $min_upvotes]);
						continue;
					}
				}

				if ($context->isItemProcessed($current_item_id)) {
					$context->log('debug', 'Reddit: Skipping item (already processed).', ['item_id' => $current_item_id]);
					continue;
				}

				if ($min_comment_count > 0) {
					if (!isset($item_data['num_comments']) || $item_data['num_comments'] < $min_comment_count) {
						$context->log('debug', 'Reddit: Skipping item (min comment count).', [
							'item_id' => $current_item_id,
							'comments' => $item_data['num_comments'] ?? 'N/A',
							'min_required' => $min_comment_count
						]);
						continue;
					}
				}

				$title_to_check = $item_data['title'] ?? '';
				$selftext_to_check = $item_data['selftext'] ?? '';
				$text_to_search = $title_to_check . ' ' . $selftext_to_check;
				if (!$this->applyKeywordSearch($text_to_search, $search_term)) {
					$context->log('debug', 'Reddit: Skipping item (search filter).', ['item_id' => $current_item_id]);
					continue;
				}

				$context->markItemProcessed($current_item_id);

				$title = $item_data['title'] ?? '';
				$selftext = $item_data['selftext'] ?? '';
				$post_body = $item_data['body'] ?? '';

				$content_data = [
					'title' => trim($title),
					'content' => !empty($selftext) ? trim($selftext) : (!empty($post_body) ? trim($post_body) : '')
				];

				$comments_array = [];
				if ($comment_count_setting > 0 && !empty($item_data['permalink'])) {
					$comments_url = 'https://oauth.reddit.com' . $item_data['permalink'] . '.json?limit=' . $comment_count_setting . '&sort=top';
					$comments_result = $this->httpGet($comments_url, [
						'headers' => $headers,
						'context' => 'Reddit API'
					]);

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
							$context->log('warning', 'Reddit: Failed to parse comments JSON.', [
								'item_id' => $current_item_id,
								'comments_url' => $comments_url,
								'error' => json_last_error_msg()
							]);
						}
					} else {
						$context->log('warning', 'Reddit: Failed to fetch comments for post.', [
							'item_id' => $current_item_id,
							'comments_url' => $comments_url,
							'error' => $comments_result['error']
						]);
					}
				}

				$stored_image = null;
				$image_info = null;
				$url = $item_data['url'] ?? '';
				$is_imgur = preg_match('#^https?://(www\.)?imgur\.com/([^./]+)$#i', $url, $imgur_matches);

				if (!empty($item_data['is_gallery']) && !empty($item_data['media_metadata']) && is_array($item_data['media_metadata'])) {
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

				if ($image_info) {
					$stored_image = $this->store_reddit_image($image_info['url'], $context, $current_item_id);
				}

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

				if (!empty($comments_array)) {
					$content_data['comments'] = $comments_array;
				}

				// Prepare raw data for DataPacket creation
				$raw_data = [
					'title' => $content_data['title'],
					'content' => $content_data['content'],
					'metadata' => $metadata
				];

				// Add comments to content if present
				if (!empty($content_data['comments'])) {
					$raw_data['content'] .= "\n\nComments:\n" . implode("\n", array_map(function($comment) {
						return "- {$comment['author']}: {$comment['body']}";
					}, $content_data['comments']));
				}

				// Add file_info if image was stored
				if ($stored_image) {
					$file_info = [
						'file_path' => $stored_image['path'],
						'file_name' => $stored_image['filename'],
						'mime_type' => $image_info['mime_type'],
						'file_size' => $stored_image['size']
					];
					$raw_data['file_info'] = $file_info;
				}

				$source_url = $item_data['permalink'] ? 'https://www.reddit.com' . $item_data['permalink'] : '';
				$image_file_path = $stored_image['path'] ?? '';

				$context->storeEngineData([
					'source_url' => $source_url,
					'image_file_path' => $image_file_path
				]);

				$context->log('debug', 'Reddit: Fetched data successfully', [
					'source_type' => 'reddit',
					'item_id' => $current_item_id,
					'has_image' => !empty($image_info),
					'image_url_domain' => !empty($image_info['url']) ? wp_parse_url($image_info['url'], PHP_URL_HOST) : null,
					'content_length' => strlen($title . ' ' . $selftext . ' ' . $post_body),
					'file_info_status' => $stored_image ? 'downloaded' : 'none'
				]);

				return $raw_data;
			}

			if ($batch_hit_time_limit) {
				$context->log('debug', 'Reddit: Stopping pagination due to hitting time limit within batch.');
				break;
			}

			$after_param = $response_data['data']['after'] ?? null;
			if (!$after_param) {
				$context->log('debug', "Reddit: No 'after' parameter found, ending pagination.");
				break;
			}

		}

		$context->log('debug', 'Reddit: No eligible items found.', [
			'total_checked' => $total_checked,
			'pages_fetched' => $pages_fetched
		]);

		return [];
	}

	public static function get_label(): string {
		return 'Reddit Subreddit';
	}

}
