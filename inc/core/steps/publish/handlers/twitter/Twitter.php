<?php
/**
 * Modular Twitter publish handler.
 *
 * Posts content to a specified Twitter account using the self-contained
 * TwitterAuth class for authentication. This modular approach separates
 * concerns between main handler logic and authentication functionality.
 *
 * @package    DataMachine
 * @subpackage Core\Steps\Publish\Handlers\Twitter
 * @since      0.1.0
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
     * Constructor - direct auth initialization for security
     */
    public function __construct() {
        // Use filter-based auth access following pure discovery architectural standards
        $all_auth = apply_filters('dm_auth_providers', []);
        $this->auth = $all_auth['twitter'] ?? null;
    }

    /**
     * Get Twitter auth handler - internal implementation.
     * 
     * @return TwitterAuth
     */
    private function get_auth() {
        return $this->auth;
    }

    /**
     * Handle AI tool call for Twitter publishing.
     *
     * @param array $parameters Structured parameters from AI tool call.
     * @param array $tool_def Tool definition including handler configuration.
     * @return array Tool execution result.
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

        // Get handler configuration from tool definition
        $handler_config = $tool_def['handler_config'] ?? [];
        
        do_action('dm_log', 'debug', 'Twitter Tool: Using handler configuration', [
            'include_source' => $handler_config['twitter_include_source'] ?? true,
            'enable_images' => $handler_config['twitter_enable_images'] ?? true,
            'url_as_reply' => $handler_config['twitter_url_as_reply'] ?? false
        ]);

        // Extract parameters
        $title = $parameters['title'] ?? '';
        $content = $parameters['content'] ?? '';
        $source_url = $parameters['source_url'] ?? null;
        
        // Get config from handler settings (280 character limit is hardcoded)
        $include_source = $handler_config['twitter_include_source'] ?? true;
        $enable_images = $handler_config['twitter_enable_images'] ?? true;
        $url_as_reply = $handler_config['twitter_url_as_reply'] ?? false;

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
        $tweet_text = $title ? $title . ": " . $content : $content;
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
            // Post tweet using API v2
            $v2_payload = ['text' => $tweet_text];
            
            // Handle image upload if provided
            $media_id = null;
            if ($enable_images && !empty($parameters['image_url'])) {
                $image_url = $parameters['image_url'];
                if (filter_var($image_url, FILTER_VALIDATE_URL) && $this->is_image_accessible($image_url)) {
                    $media_id = $this->upload_image_to_twitter($connection, $image_url, $title ?: substr($content, 0, 50));
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
     * Upload image to Twitter and return media ID.
     *
     * @param object $connection Twitter connection object.
     * @param string $image_url Image URL to upload.
     * @param string $alt_text Alt text for the image.
     * @return string|null Media ID or null on failure.
     */
    private function upload_image_to_twitter($connection, string $image_url, string $alt_text): ?string {
        do_action('dm_log', 'debug', 'Attempting to upload image to Twitter.', ['image_url' => $image_url]);
        
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
                    'detected_mime' => $mime_type ?: 'N/A'
                ]);
                return null; // Return null to indicate upload failure
            }

            // Upload media
            $media_upload_params = [
                'media' => $temp_image_path,
                'media_type' => $mime_type,
                'media_category' => 'tweet_image'
            ];
            $media_upload = $connection->upload('media/upload', $media_upload_params);

            if ($connection->getLastHttpCode() === 200 && isset($media_upload->media_id_string)) {
                $media_id = $media_upload->media_id_string;

                // Add alt text if provided
                if (!empty($media_id) && !empty($alt_text)) {
                    $alt_text_params = [
                        'media_id' => $media_id, 
                        'alt_text' => ['text' => mb_substr($alt_text, 0, 1000)]
                    ];
                    $alt_response = $connection->post('media/metadata/create', wp_json_encode($alt_text_params), true);
                    if ($connection->getLastHttpCode() !== 200) {
                        do_action('dm_log', 'warning', 'Failed to add alt text to Twitter image.', [
                            'media_id' => $media_id,
                            'http_code' => $connection->getLastHttpCode()
                        ]);
                    } else {
                        do_action('dm_log', 'debug', 'Successfully added alt text to Twitter image.', ['media_id' => $media_id]);
                    }
                }

                do_action('dm_log', 'debug', 'Successfully uploaded image to Twitter.', ['media_id' => $media_id]);
                return $media_id;
            } else {
                $upload_error = 'Twitter API Error: Failed to upload media.';
                if (isset($media_upload->errors)) {
                    $first_error = reset($media_upload->errors);
                    $upload_error .= ' Reason: ' . ($first_error->message ?? 'Unknown');
                }
                do_action('dm_log', 'error', $upload_error, [
                    'http_code' => $connection->getLastHttpCode(),
                    'api_response' => $media_upload,
                    'image_url' => $image_url
                ]);
                return null;
            }
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Twitter image upload exception: ' . $e->getMessage());
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


