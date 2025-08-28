<?php
/**
 * Twitter publishing handler
 *
 * Posts content to Twitter with media support and authentication.
 *
 * @package DataMachine
 * @subpackage Core\Steps\Publish\Handlers\Twitter
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Twitter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Twitter {

    /**
     * @var TwitterAuth Authentication handler instance
     */
    private $auth;

    /**
     * Initialize handler with authentication
     */
    public function __construct() {
        // Use filter-based auth access following pure discovery architectural standards
        $all_auth = apply_filters('dm_auth_providers', []);
        $this->auth = $all_auth['twitter'] ?? null;
    }

    /**
     * Get authentication handler
     * 
     * @return TwitterAuth
     */
    private function get_auth() {
        return $this->auth;
    }

    /**
     * Handle AI tool call for publishing
     *
     * @param array $parameters Tool parameters
     * @param array $tool_def Tool definition
     * @return array Tool execution result
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        do_action('dm_log', 'debug', 'Twitter Tool: Handling tool call', [
            'parameters' => $parameters,
            'parameter_keys' => array_keys($parameters),
            'has_handler_config' => !empty($tool_def['handler_config']),
            'handler_config_keys' => array_keys($tool_def['handler_config'] ?? [])
        ]);

        // Validate required parameters
        if (empty($parameters['content'])) {
            $error_msg = 'Twitter tool call missing required content parameter';
            do_action('dm_log', 'error', $error_msg, [
                'provided_parameters' => array_keys($parameters),
                'required_parameters' => ['content']
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'twitter_publish'
            ];
        }

        // Get handler configuration from tool definition and extract Twitter-specific config
        $handler_config = $tool_def['handler_config'] ?? [];
        $twitter_config = $handler_config['twitter'] ?? $handler_config;
        
        do_action('dm_log', 'debug', 'Twitter Tool: Using handler configuration', [
            'include_source' => $twitter_config['twitter_include_source'] ?? true,
            'enable_images' => $twitter_config['twitter_enable_images'] ?? true,
            'url_as_reply' => $twitter_config['twitter_url_as_reply'] ?? false
        ]);

        // Extract parameters
        $content = $parameters['content'] ?? '';
        $source_url = $parameters['source_url'] ?? null;
        
        // Get config from Twitter-specific settings (280 character limit is hardcoded)
        $include_source = $twitter_config['twitter_include_source'] ?? true;
        $enable_images = $twitter_config['twitter_enable_images'] ?? true;
        $url_as_reply = $twitter_config['twitter_url_as_reply'] ?? false;

        // Get authenticated connection
        $connection = $this->auth->get_connection();
        if (is_wp_error($connection)) {
            $error_msg = 'Twitter authentication failed: ' . $connection->get_error_message();
            do_action('dm_log', 'error', $error_msg, [
                'error_code' => $connection->get_error_code()
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'twitter_publish'
            ];
        }

        // Format tweet content (Twitter's character limit is 280)
        $tweet_text = $content;
        $ellipsis = '…';
        $ellipsis_len = mb_strlen($ellipsis, 'UTF-8');
        
        // Handle URL based on configuration
        $should_append_url = $include_source && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL) && !$url_as_reply;
        $link = $should_append_url ? ' ' . $source_url : '';
        $link_length = $link ? 24 : 0; // t.co link length
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

        if (empty($tweet_text)) {
            return [
                'success' => false,
                'error' => 'Formatted tweet content is empty',
                'tool_name' => 'twitter_publish'
            ];
        }

        try {
            // Ensure we're using API v2 for tweet posting
            $connection->setApiVersion('2');
            
            do_action('dm_log', 'debug', 'Twitter Tool: Using X API v2 for tweet posting', [
                'api_version' => '2',
                'tweet_length' => mb_strlen($tweet_text, 'UTF-8')
            ]);
            
            // Post tweet using API v2
            $v2_payload = ['text' => $tweet_text];
            
            // Handle image upload if provided
            $media_id = null;
            
            // Debug logging for image parameter
            do_action('dm_log', 'debug', 'Twitter Handler: Image upload processing', [
                'enable_images' => $enable_images,
                'image_url_provided' => isset($parameters['image_url']),
                'image_url' => $parameters['image_url'] ?? 'not_provided',
                'image_url_empty' => empty($parameters['image_url']),
                'image_url_is_string' => is_string($parameters['image_url'] ?? null),
                'image_url_length' => isset($parameters['image_url']) ? strlen($parameters['image_url']) : 0
            ]);
            
            if ($enable_images && !empty($parameters['image_url'])) {
                $image_url = $parameters['image_url'];
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
                
                do_action('dm_log', 'debug', 'Twitter Tool: Tweet posted successfully', [
                    'tweet_id' => $tweet_id,
                    'tweet_url' => $tweet_url
                ]);

                // Handle URL as reply if configured
                $reply_result = null;
                if ($url_as_reply && $include_source && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL)) {
                    $reply_result = $this->post_reply_tweet($connection, $tweet_id, $source_url, $screen_name);
                }

                $result_data = [
                    'tweet_id' => $tweet_id,
                    'tweet_url' => $tweet_url,
                    'content' => $tweet_text
                ];

                // Add reply information if a reply was posted
                if ($reply_result && $reply_result['success']) {
                    $result_data['reply_tweet_id'] = $reply_result['reply_tweet_id'];
                    $result_data['reply_tweet_url'] = $reply_result['reply_tweet_url'];
                } elseif ($reply_result && !$reply_result['success']) {
                    // Reply failed but main tweet succeeded - log but don't fail the whole operation
                    do_action('dm_log', 'warning', 'Twitter Tool: Main tweet posted but reply failed', [
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
                
                do_action('dm_log', 'error', $error_msg, [
                    'http_code' => $http_code,
                    'api_response' => $response
                ]);

                return [
                    'success' => false,
                    'error' => $error_msg,
                    'tool_name' => 'twitter_publish'
                ];
            }
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Twitter Tool: Exception during posting', [
                'exception' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tool_name' => 'twitter_publish'
            ];
        }
    }

    /**
     * Post a reply tweet with URL.
     *
     * @param object $connection Twitter connection object.
     * @param string $original_tweet_id ID of the tweet to reply to.
     * @param string $source_url URL to post in the reply.
     * @param string $screen_name Screen name for generating reply URL.
     * @return array Result of reply posting operation.
     */
    private function post_reply_tweet($connection, string $original_tweet_id, string $source_url, string $screen_name): array {
        do_action('dm_log', 'debug', 'Twitter Tool: Posting URL as reply tweet', [
            'original_tweet_id' => $original_tweet_id,
            'source_url' => $source_url
        ]);

        try {
            // Ensure we're using API v2 for reply tweet
            $connection->setApiVersion('2');
            
            // Create reply tweet with just the URL
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
                
                do_action('dm_log', 'debug', 'Twitter Tool: Reply tweet posted successfully', [
                    'reply_tweet_id' => $reply_tweet_id,
                    'reply_tweet_url' => $reply_tweet_url,
                    'original_tweet_id' => $original_tweet_id
                ]);

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
                
                do_action('dm_log', 'warning', 'Twitter Tool: Reply tweet failed', [
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
            do_action('dm_log', 'warning', 'Twitter Tool: Exception during reply posting', [
                'exception' => $e->getMessage(),
                'original_tweet_id' => $original_tweet_id
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build OAuth 1.0a authorization header for manual API requests.
     *
     * @param string $consumer_key Consumer key
     * @param string $consumer_secret Consumer secret
     * @param string $access_token Access token
     * @param string $access_token_secret Access token secret
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array $params Additional parameters
     * @return string OAuth authorization header
     */

    /**
     * Upload image to Twitter and return media ID.
     *
     * @param object $connection Twitter connection object.
     * @param string $image_url Image URL to upload.
     * @param string $alt_text Alt text for the image.
     * @return string|null Media ID or null on failure.
     */
    private function upload_image_to_twitter($connection, string $image_url, string $alt_text): ?string {
        do_action('dm_log', 'debug', 'Attempting to upload image to X/Twitter using v2 API.', ['image_url' => $image_url]);
        
        if (!function_exists('download_url')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $temp_image_path = download_url($image_url);
        if (is_wp_error($temp_image_path)) {
            do_action('dm_log', 'warning', 'Failed to download image for Twitter upload.', [
                'url' => $image_url, 
                'error' => $temp_image_path->get_error_message()
            ]);
            return null;
        }

        try {
            // Determine MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $temp_image_path);
            finfo_close($finfo);

            if (empty($mime_type) || !preg_match('/^image\/(jpeg|png|gif|webp)$/', $mime_type)) {
                do_action('dm_log', 'warning', 'Invalid or unsupported image type for Twitter.', [
                    'path' => $temp_image_path, 
                    'detected_mime' => $mime_type
                ]);
                return null;
            }

            do_action('dm_log', 'debug', 'Using TwitterOAuth library for media upload.', [
                'temp_image_path' => $temp_image_path,
                'mime_type' => $mime_type
            ]);

            // Use TwitterOAuth library's upload method
            $media_result = $connection->upload('media/upload', [
                'media' => $temp_image_path,
                'media_category' => 'tweet_image'
            ]);

            $http_code = $connection->getLastHttpCode();
            do_action('dm_log', 'debug', 'TwitterOAuth media upload response received.', [
                'http_code' => $http_code,
                'has_media_id' => isset($media_result->media_id_string),
                'response_type' => gettype($media_result)
            ]);

            if ($http_code == 200 && isset($media_result->media_id_string)) {
                $media_id = $media_result->media_id_string;

                // Note: Alt text would need to be added via separate API call
                // TwitterOAuth library upload method doesn't directly support alt text
                if (!empty($alt_text)) {
                    do_action('dm_log', 'debug', 'Alt text provided but not yet implemented with TwitterOAuth method.', [
                        'media_id' => $media_id,
                        'alt_text_length' => strlen($alt_text)
                    ]);
                }

                do_action('dm_log', 'debug', 'Successfully uploaded image to Twitter using TwitterOAuth library.', ['media_id' => $media_id]);
                return $media_id;
            } else {
                $upload_error = 'Twitter media upload failed via TwitterOAuth library.';
                $error_details = [
                    'http_code' => $http_code,
                    'image_url' => $image_url
                ];
                
                if (isset($media_result->errors)) {
                    $first_error = $media_result->errors[0];
                    $upload_error .= ' API Error: ' . $first_error->message;
                    $error_details['api_errors'] = $media_result->errors;
                } else {
                    $upload_error .= ' HTTP ' . $http_code;
                }
                
                do_action('dm_log', 'error', $upload_error, $error_details);
                return null;
            }
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Twitter image upload exception: ' . $e->getMessage(), [
                'exception_type' => get_class($e),
                'image_url' => $image_url
            ]);
            return null;
        } finally {
            if ($temp_image_path && file_exists($temp_image_path)) {
                wp_delete_file($temp_image_path);
                do_action('dm_log', 'debug', 'Temporary image file cleaned up.', ['image_url' => $image_url]);
            }
        }
    }


    /**
     * Returns the user-friendly label for this publish handler.
     *
     * @return string The label.
     */
    public static function get_label(): string {
        return __('Post to Twitter', 'data-machine');
    }


    /**
     * Check if an image URL is accessible by making a HEAD request
     *
     * @param string $image_url The image URL to check
     * @return bool True if accessible, false otherwise
     */
    private function is_image_accessible(string $image_url): bool {
        // Skip certain problematic domains/patterns
        $problematic_patterns = [
            'preview.redd.it', // Reddit preview URLs often have access restrictions
            'i.redd.it'        // Reddit image URLs may have restrictions
        ];
        
        foreach ($problematic_patterns as $pattern) {
            if (strpos($image_url, $pattern) !== false) {
                do_action('dm_log', 'warning', 'Twitter: Skipping problematic image URL pattern', ['url' => $image_url, 'pattern' => $pattern]);
                return false;
            }
        }

        // Test accessibility with HEAD request
        $response = wp_remote_head($image_url, [
            'user-agent' => 'Mozilla/5.0 (compatible; DataMachine/1.0; +https://github.com/chubes/data-machine)'
        ]);

        if (is_wp_error($response)) {
            do_action('dm_log', 'warning', 'Twitter: Image URL not accessible', ['url' => $image_url, 'error' => $response->get_error_message()]);
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        // Check for successful response and image content type
        if ($http_code >= 200 && $http_code < 300 && strpos($content_type, 'image/') === 0) {
            return true;
        }

        do_action('dm_log', 'warning', 'Twitter: Image URL validation failed', [
            'url' => $image_url, 
            'http_code' => $http_code, 
            'content_type' => $content_type
        ]);
        return false;
    }
}


