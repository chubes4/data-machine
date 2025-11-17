<?php
/**
 * Twitter publishing handler with OAuth 1.0a and AI tool integration.
 *
 * Handles posting content to Twitter with support for:
 * - Text content (280 character limit)
 * - Media uploads (images)
 * - URL handling and link shortening
 * - Thread creation for long content
 *
 * @package DataMachine\Core\Steps\Publish\Handlers\Twitter
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Twitter;

defined('ABSPATH') || exit;

/**
 * Twitter Publishing Handler
 *
 * Publishes content to Twitter via OAuth 1.0a authentication.
 * Supports media uploads and handles URL shortening automatically.
 */
class Twitter {

    /** @var TwitterAuth OAuth authentication handler */
    private $auth;

    /**
     * Constructor - Initialize Twitter authentication.
     */
    public function __construct() {
        $all_auth = apply_filters('datamachine_auth_providers', []);
        $this->auth = $all_auth['twitter'] ?? null;
    }

    /**
     * Handle AI tool call for Twitter publishing.
     *
     * @param array $parameters Tool parameters including content and configuration
     * @param array $tool_def Tool definition with handler config
     * @return array {
     *     @type bool $success Whether the post was successful
     *     @type string $error Error message if failed
     *     @type string $tool_name Tool identifier
     *     @type string $url Twitter post URL if successful
     *     @type string $id Twitter post ID if successful
     * }
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {

        $handler_config = $parameters['handler_config'] ?? [];
        $twitter_config = $handler_config['twitter'] ?? $handler_config;
        $content = $parameters['content'] ?? '';

        $job_id = $parameters['job_id'] ?? null;
        $engine_data = apply_filters('datamachine_engine_data', [], $job_id);

        $source_url = $engine_data['source_url'] ?? null;
        $image_url = $engine_data['image_url'] ?? null;

        $include_images = $twitter_config['include_images'] ?? true;
        $link_handling = $twitter_config['link_handling'] ?? 'append';

        $connection = $this->auth->get_connection();
        if (is_wp_error($connection)) {
            $error_msg = 'Twitter authentication failed: ' . $connection->get_error_message();
            do_action('datamachine_log', 'error', $error_msg, [
                'error_code' => $connection->get_error_code()
            ]);

            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'twitter_publish'
            ];
        }

        $tweet_text = $content;
        $ellipsis = '…';
        $ellipsis_len = mb_strlen($ellipsis, 'UTF-8');

        $should_append_url = $link_handling === 'append' && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL);
        $link = $should_append_url ? ' ' . $source_url : '';
        $link_length = $link ? 24 : 0;
        $available_chars = 280 - $link_length;

        if ($available_chars < $ellipsis_len) {
            $tweet_text = mb_substr($link, 0, 280);
        } else {
            if (mb_strlen($tweet_text, 'UTF-8') > $available_chars) {
                $tweet_text = mb_substr($tweet_text, 0, $available_chars - $ellipsis_len) . $ellipsis;
            }
            $tweet_text .= $link;
        }
        $tweet_text = trim($tweet_text);

        try {
            $connection->setApiVersion('2');

            $v2_payload = ['text' => $tweet_text];
            $media_id = null;

            if ($include_images && !empty($image_url)) {
                if (filter_var($image_url, FILTER_VALIDATE_URL) && $this->is_image_accessible($image_url)) {
                    $media_id = $this->upload_image_to_twitter($connection, $image_url, substr($content, 0, 50));
                }
            }
            
            if ($media_id) {
                $v2_payload['media'] = ['media_ids' => [$media_id]];
            }

            $response = $connection->post('tweets', $v2_payload, ['json' => true]);
            $http_code = $connection->getLastHttpCode();

            if ($http_code == 201 && isset($response->data->id)) {
                $tweet_id = $response->data->id;
                $account_details = $this->auth->get_account_details();
                $screen_name = $account_details['screen_name'] ?? 'twitter';
                $tweet_url = "https://twitter.com/{$screen_name}/status/{$tweet_id}";

                $reply_result = null;
                if ($link_handling === 'reply' && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL)) {
                    $reply_result = $this->post_reply_tweet($connection, $tweet_id, $source_url, $screen_name);
                }

                $result_data = [
                    'tweet_id' => $tweet_id,
                    'tweet_url' => $tweet_url,
                    'content' => $tweet_text
                ];

                if ($reply_result && $reply_result['success']) {
                    $result_data['reply_tweet_id'] = $reply_result['reply_tweet_id'];
                    $result_data['reply_tweet_url'] = $reply_result['reply_tweet_url'];
                } elseif ($reply_result && !$reply_result['success']) {
                    do_action('datamachine_log', 'warning', 'Twitter Tool: Main tweet posted but reply failed', [
                        'tweet_id' => $tweet_id,
                        'reply_error' => $reply_result['error']
                    ]);
                }

                return [
                    'success' => true,
                    'data' => $result_data,
                    'tool_name' => 'twitter_publish'
                ];
            } else {
                $error_msg = 'Twitter API error: Failed to post tweet';
                if (isset($response->title)) {
                    $error_msg = "Twitter API error: {$response->title}";
                }

                do_action('datamachine_log', 'error', $error_msg, [
                    'http_code' => $http_code,
                    'raw_api_response' => json_encode($response, JSON_PRETTY_PRINT),
                    'response_headers' => $connection->getLastXHeaders() ?? 'unavailable'
                ]);

                return [
                    'success' => false,
                    'error' => $error_msg,
                    'tool_name' => 'twitter_publish'
                ];
            }
        } catch (\Exception $e) {
            do_action('datamachine_log', 'error', 'Twitter Tool: Exception during posting', [
                'exception' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tool_name' => 'twitter_publish'
            ];
        }
    }

    private function post_reply_tweet($connection, string $original_tweet_id, string $source_url, string $screen_name): array {
        try {
            $connection->setApiVersion('2');
            
            $reply_payload = [
                'text' => $source_url,
                'reply' => [
                    'in_reply_to_tweet_id' => $original_tweet_id
                ]
            ];

            $response = $connection->post('tweets', $reply_payload, ['json' => true]);
            $http_code = $connection->getLastHttpCode();

            if ($http_code == 201 && isset($response->data->id)) {
                $reply_tweet_id = $response->data->id;
                $reply_tweet_url = "https://twitter.com/{$screen_name}/status/{$reply_tweet_id}";

                return [
                    'success' => true,
                    'reply_tweet_id' => $reply_tweet_id,
                    'reply_tweet_url' => $reply_tweet_url
                ];
            } else {
                $error_msg = 'Twitter API error: Failed to post reply tweet';
                if (isset($response->title)) {
                    $error_msg = "Twitter API error: {$response->title}";
                }
                
                do_action('datamachine_log', 'warning', 'Twitter Tool: Reply tweet failed', [
                    'http_code' => $http_code,
                    'api_response' => $response,
                    'original_tweet_id' => $original_tweet_id
                ]);

                return [
                    'success' => false,
                    'error' => $error_msg
                ];
            }
        } catch (\Exception $e) {
            do_action('datamachine_log', 'warning', 'Twitter Tool: Exception during reply posting', [
                'exception' => $e->getMessage(),
                'original_tweet_id' => $original_tweet_id
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }


    private function upload_image_to_twitter($connection, string $image_url, string $alt_text): ?string {
        if (!function_exists('download_url')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $temp_image_path = download_url($image_url);
        if (is_wp_error($temp_image_path)) {
            do_action('datamachine_log', 'warning', 'Failed to download image for Twitter upload.', [
                'url' => $image_url, 
                'error' => $temp_image_path->get_error_message()
            ]);
            return null;
        }

        try {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $temp_image_path);
            finfo_close($finfo);

            if (empty($mime_type) || !preg_match('/^image\/(jpeg|png|gif|webp)$/', $mime_type)) {
                do_action('datamachine_log', 'warning', 'Invalid or unsupported image type for Twitter.', [
                    'path' => $temp_image_path, 
                    'detected_mime' => $mime_type
                ]);
                return null;
            }

            $file_size = filesize($temp_image_path);

            $connection->setApiVersion('1.1');

            $media_id = $this->upload_image_chunked($connection, $temp_image_path, $mime_type);

            $connection->setApiVersion('2');

            if ($media_id) {
                return $media_id;
            } else {
                do_action('datamachine_log', 'error', 'Twitter media upload failed.', [
                    'image_url' => $image_url,
                    'file_size' => $file_size,
                    'mime_type' => $mime_type
                ]);
                return null;
            }
        } catch (\Exception $e) {
            $connection->setApiVersion('2');
            
            do_action('datamachine_log', 'error', 'Twitter image upload exception: ' . $e->getMessage(), [
                'exception_type' => get_class($e),
                'image_url' => $image_url
            ]);
            return null;
        } finally {
            if ($temp_image_path && file_exists($temp_image_path)) {
                wp_delete_file($temp_image_path);
            }
        }
    }

    private function upload_image_chunked($connection, string $temp_image_path, string $mime_type): ?string {
        try {
            $file_size = filesize($temp_image_path);

            $init_response = $connection->post('media/upload', [
                'command' => 'INIT',
                'media_type' => $mime_type,
                'media_category' => 'tweet_image',
                'total_bytes' => $file_size
            ]);

            $http_code = $connection->getLastHttpCode();
            if ($http_code !== 200 || !isset($init_response->media_id_string)) {
                do_action('datamachine_log', 'error', 'Twitter: Chunked upload INIT failed.', [
                    'http_code' => $http_code,
                    'response' => $init_response
                ]);
                return null;
            }

            $media_id = $init_response->media_id_string;

            if (!function_exists('WP_Filesystem')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }

            $filesystem_init = WP_Filesystem();
            if (!$filesystem_init) {
                do_action('datamachine_log', 'error', 'Twitter: WP_Filesystem initialization failed', [
                    'temp_image_path' => $temp_image_path
                ]);
                return null;
            }

            global $wp_filesystem;

            $file_contents = $wp_filesystem->get_contents($temp_image_path);
            if (false === $file_contents) {
                do_action('datamachine_log', 'error', 'Twitter: Cannot read image file for chunked upload.', [
                    'temp_image_path' => $temp_image_path
                ]);
                return null;
            }

            $segment_index = 0;
            $chunk_size = 1048576; // 1MB chunks
            $file_length = strlen($file_contents);
            $offset = 0;

            while ($offset < $file_length) {
                $chunk = substr($file_contents, $offset, $chunk_size);

                $append_response = $connection->post('media/upload', [
                    'command' => 'APPEND',
                    'media_id' => $media_id,
                    'segment_index' => $segment_index,
                    'media_data' => base64_encode($chunk)
                ]);

                $http_code = $connection->getLastHttpCode();
                if ($http_code !== 204) {
                    do_action('datamachine_log', 'error', 'Twitter: Chunked upload APPEND failed.', [
                        'http_code' => $http_code,
                        'segment_index' => $segment_index,
                        'response' => $append_response
                    ]);
                    return null;
                }

                $offset += $chunk_size;
                $segment_index++;
            }

            $finalize_response = $connection->post('media/upload', [
                'command' => 'FINALIZE',
                'media_id' => $media_id
            ]);

            $http_code = $connection->getLastHttpCode();
            if ($http_code !== 200 || !isset($finalize_response->media_id_string)) {
                do_action('datamachine_log', 'error', 'Twitter: Chunked upload FINALIZE failed.', [
                    'http_code' => $http_code,
                    'response' => $finalize_response
                ]);
                return null;
            }

            return $finalize_response->media_id_string;

        } catch (\Exception $e) {
            do_action('datamachine_log', 'error', 'Twitter: Chunked upload exception.', [
                'exception' => $e->getMessage(),
                'exception_type' => get_class($e)
            ]);
            return null;
        }
    }


    public static function get_label(): string {
        return __('Post to Twitter', 'datamachine');
    }


    private function is_image_accessible(string $image_url): bool {
        $problematic_patterns = [
            'preview.redd.it', // Reddit preview URLs often have access restrictions
            'i.redd.it'        // Reddit image URLs may have restrictions
        ];
        
        foreach ($problematic_patterns as $pattern) {
            if (strpos($image_url, $pattern) !== false) {
                do_action('datamachine_log', 'warning', 'Twitter: Skipping problematic image URL pattern', ['url' => $image_url, 'pattern' => $pattern]);
                return false;
            }
        }

        $response = wp_remote_head($image_url, [
            'user-agent' => 'Mozilla/5.0 (compatible; DataMachine/1.0; +https://github.com/chubes/datamachine)'
        ]);

        if (is_wp_error($response)) {
            do_action('datamachine_log', 'warning', 'Twitter: Image URL not accessible', ['url' => $image_url, 'error' => $response->get_error_message()]);
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        if ($http_code >= 200 && $http_code < 300 && strpos($content_type, 'image/') === 0) {
            return true;
        }

        do_action('datamachine_log', 'warning', 'Twitter: Image URL validation failed', [
            'url' => $image_url,
            'http_code' => $http_code,
            'content_type' => $content_type
        ]);
        return false;
    }


}
