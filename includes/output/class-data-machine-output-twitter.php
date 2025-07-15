<?php
/**
 * Handles the 'Twitter' output type.
 *
 * Posts content to a specified Twitter account.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/output
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Abraham\TwitterOAuth\TwitterOAuth;

class Data_Machine_Output_Twitter implements Data_Machine_Output_Handler_Interface {

    use Data_Machine_Base_Output_Handler;

    /** @var Data_Machine_OAuth_Twitter */
    private $oauth_twitter;

    /** @var ?Data_Machine_Logger */
    private $logger;

    /**
	 * Constructor.
	 *
     * @param Data_Machine_OAuth_Twitter $oauth_twitter Twitter OAuth service.
     * @param Data_Machine_Logger|null $logger Optional Logger instance.
	 */
	public function __construct(Data_Machine_OAuth_Twitter $oauth_twitter, ?Data_Machine_Logger $logger = null) {
        $this->oauth_twitter = $oauth_twitter;
        $this->logger = $logger;
	}

    /**
	 * Handles posting the AI output to Twitter.
	 *
	 * @param string $ai_output_string The finalized string from the AI.
	 * @param array $module_job_config Configuration specific to this output job.
	 * @param int|null $user_id The ID of the user whose Twitter account should be used.
	 * @param array $input_metadata Metadata from the original input source (e.g., original URL, timestamp).
	 * @return array|WP_Error Result array on success (e.g., ['tweet_id' => '...', 'message' => '...']), WP_Error on failure.
	 */
	public function handle( string $ai_output_string, array $module_job_config, ?int $user_id, array $input_metadata ): array|WP_Error {
        $this->logger?->info('Starting Twitter output handling.', ['user_id' => $user_id]);
        
        // 1. Get config
        $output_config = $module_job_config['output_config']['twitter'] ?? []; // Use 'twitter' sub-key
        $char_limit = $output_config['twitter_char_limit'] ?? 280;
        $include_source = $output_config['twitter_include_source'] ?? true;
        $enable_images = $output_config['twitter_enable_images'] ?? true;

        // 2. Ensure user_id is provided
        if (empty($user_id)) {
            $this->logger?->error('Twitter Output: User ID context is missing.');
            return new WP_Error('twitter_missing_user_id', __('Cannot post to Twitter without a specified user account.', 'data-machine'));
        }

        // 3. Get authenticated connection using injected service
        if (!$this->oauth_twitter) {
             $this->logger?->error('Twitter Output: OAuth Twitter service not available.', ['user_id' => $user_id]);
            return new WP_Error('twitter_service_unavailable', __('Twitter authentication service is unavailable.', 'data-machine'));
        }
        $connection = $this->oauth_twitter->get_connection_for_user($user_id);

        // 4. Handle connection errors
        if (is_wp_error($connection)) {
             $this->logger?->error('Twitter Output Error: Failed to get authenticated connection.', [
                'user_id' => $user_id,
                'error_code' => $connection->get_error_code(),
                'error_message' => $connection->get_error_message(),
             ]);
             return $connection; // Return the WP_Error from the auth handler
        }

        // 5. Parse AI output
        $parser_path = DATA_MACHINE_PATH . 'includes/helpers/class-data-machine-ai-response-parser.php';
        if (!class_exists('Data_Machine_AI_Response_Parser') && file_exists($parser_path)) {
            require_once $parser_path;
        }
        if (!class_exists('Data_Machine_AI_Response_Parser')) {
            $this->logger?->error('Data_Machine_AI_Response_Parser class not found.', ['path' => $parser_path]);
            return new WP_Error('twitter_parser_missing', __('AI Response Parser is missing.', 'data-machine'));
        }

        $parser = new Data_Machine_AI_Response_Parser( $ai_output_string );
        $parser->parse();
        $title = $parser->get_title();
        $content = $parser->get_content(); // Assuming content is the main text for the tweet

        if (empty($title) && empty($content)) {
            $this->logger?->warning('Twitter Output: Parsed AI output is empty.', ['user_id' => $user_id]);
            return new WP_Error('twitter_empty_content', __('Cannot post empty content to Twitter.', 'data-machine'));
        }

        // --- 6. Format tweet content --- 
        $tweet_text = $title ? $title . ": " . $content : $content;
        $source_url = $input_metadata['source_url'] ?? null;
        $ellipsis = '…';
        $ellipsis_len = mb_strlen($ellipsis, 'UTF-8');
        $link = ($include_source && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL)) ? ' ' . $source_url : '';
        $link_length = $link ? 24 : 0; // Always use 24 chars for t.co links (23 + 1 space)
        $available_chars = $char_limit - $link_length;
        if ($available_chars < $ellipsis_len) {
            // If the link itself is too long, hard truncate it (rare)
            $tweet_text = mb_substr($link, 0, $char_limit);
        } else {
            if (mb_strlen($tweet_text, 'UTF-8') > $available_chars) {
                $tweet_text = mb_substr($tweet_text, 0, $available_chars - $ellipsis_len) . $ellipsis;
            }
            $tweet_text .= $link;
        }
        $tweet_text = trim($tweet_text);
        // --- End Formatting ---

        if (empty($tweet_text)) {
             $this->logger?->error('Twitter Output: Formatted tweet content is empty after processing.', ['user_id' => $user_id]);
             return new WP_Error('twitter_empty_formatted_content', __('Formatted tweet content is empty after processing.', 'data-machine'));
        }

        // --- 7. Post tweet --- 
        try {
            $tweet_params = ['status' => $tweet_text];
            $media_id = null;
            $temp_image_path = null; // Variable to hold temporary path

            // --- Image Upload Logic ---
            $image_source_url = $input_metadata['image_source_url'] ?? null;
            $image_alt_text = $parser->get_title() ?: $parser->get_content_summary(50); // Use title or summary as alt text

            if ($enable_images && !empty($image_source_url) && filter_var($image_source_url, FILTER_VALIDATE_URL)) {
                $this->logger?->info('Attempting to upload image to Twitter.', ['image_url' => $image_source_url, 'user_id' => $user_id]);
                
                if (!function_exists('download_url')) {
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                }
                
                $temp_image_path = download_url($image_source_url, 15); // 15 second timeout

                if (is_wp_error($temp_image_path)) {
                    $this->logger?->warning('Failed to download image for Twitter upload.', ['url' => $image_source_url, 'error' => $temp_image_path->get_error_message(), 'user_id' => $user_id]);
                    $temp_image_path = null; // Ensure path is null on error
                } else {
                    try {
                        // Determine MIME type for media_type parameter
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_type = finfo_file($finfo, $temp_image_path);
                        finfo_close($finfo);

                        if (empty($mime_type) || !preg_match('/^image\/(jpeg|png|gif|webp)$/', $mime_type)) {
                            $this->logger?->warning('Could not determine a valid image MIME type for Twitter upload, or type not supported.', ['path' => $temp_image_path, 'detected_mime' => $mime_type ?: 'N/A', 'user_id' => $user_id]);
                            throw new Exception('Invalid or unsupported image type for Twitter.');
                        }

                        $this->logger?->info('Attempting chunked media upload to Twitter.', ['path' => $temp_image_path, 'mime_type' => $mime_type, 'user_id' => $user_id]);

                        // Pass media_type and media_category directly in the parameters for the upload method.
                        // The library should handle chunking automatically for the media/upload endpoint.
                        $media_upload_params = [
                            'media' => $temp_image_path,
                            'media_type' => $mime_type,
                            'media_category' => 'tweet_image' // For standard image tweets
                        ];
                        $media_upload = $connection->upload('media/upload', $media_upload_params);

                        $this->logger?->debug('Twitter chunked media/upload response', [ // Enhanced logging
                            'user_id' => $user_id,
                            'http_code' => $connection->getLastHttpCode(),
                            'response_body' => $media_upload // Log the entire response object
                        ]);
                        
                        if ($connection->getLastHttpCode() === 200 && isset($media_upload->media_id_string)) {
                            $media_id = $media_upload->media_id_string;

                            // Check for media processing information
                            if (isset($media_upload->processing_info)) {
                                $this->logger?->info('Twitter media/upload processing_info found', [
                                    'user_id' => $user_id,
                                    'media_id' => $media_id,
                                    'processing_state' => $media_upload->processing_info->state ?? 'N/A',
                                    'check_after_secs' => $media_upload->processing_info->check_after_secs ?? 'N/A',
                                    'progress_percent' => $media_upload->processing_info->progress_percent ?? 'N/A'
                                ]);
                                if (isset($media_upload->processing_info->state) && $media_upload->processing_info->state === 'failed') {
                                    $processing_error_message = 'Twitter media processing failed after upload.';
                                    if (!empty($media_upload->processing_info->error->message)) {
                                        $processing_error_message .= ' Reason: ' . $media_upload->processing_info->error->message;
                                    }
                                    $this->logger?->error($processing_error_message, [
                                        'user_id' => $user_id,
                                        'media_id' => $media_id,
                                        'processing_error_details' => $media_upload->processing_info->error ?? null
                                    ]);
                                    // Throw an exception to stop further processing if media definitely failed
                                    throw new Exception($processing_error_message);
                                }
                                // If state is 'pending' or 'in_progress', it might ideally need a waiting loop with STATUS checks,
                                // but for now, we'll log and let it proceed to see if v2 handles it or still fails.
                            }
                            
                             // --- Add Alt Text (v1.1) --- 
                             if (!empty($media_id) && !empty($image_alt_text)) {
                                 $alt_text_params = ['media_id' => $media_id, 'alt_text' => ['text' => mb_substr($image_alt_text, 0, 1000)]]; // Limit alt text
                                 $alt_response = $connection->post('media/metadata/create', json_encode($alt_text_params), true); // Use JSON payload
                                 if ($connection->getLastHttpCode() !== 200) {
                                     $this->logger?->warning('Failed to add alt text to Twitter image.', [
                                         'media_id' => $media_id,
                                         'http_code' => $connection->getLastHttpCode(),
                                         'response' => $alt_response
                                     ]);
                                 } else {
                                     $this->logger?->info('Successfully added alt text to Twitter image.', ['media_id' => $media_id]);
                                 }
                             }                            
                             // --- End Alt Text --- 
                            $this->logger?->info('Successfully uploaded image to Twitter.', ['media_id' => $media_id, 'user_id' => $user_id]);
                        } else {
                             $upload_error_message = 'Twitter API Error: Failed to upload media.';
                             if (isset($media_upload->errors)) {
                                 $first_error = reset($media_upload->errors);
                                 $upload_error_message .= ' Reason: ' . ($first_error->message ?? 'Unknown') . ' (Code: ' . ($first_error->code ?? 'N/A') . ')';
                             }
                            $this->logger?->error($upload_error_message, [
                                'user_id' => $user_id,
                                'http_code' => $connection->getLastHttpCode(),
                                'api_response' => $media_upload,
                                'image_url' => $image_source_url
                             ]);
                        }
                    } catch (\Exception $e) {
                        $this->logger?->error('Twitter Output Exception during image upload: ' . $e->getMessage(), ['user_id' => $user_id]);
                        $temp_image_path = null; // Ensure path is null on error
                    } finally {
                        if ($temp_image_path && file_exists($temp_image_path)) {
                            @unlink($temp_image_path);
                            $this->logger?->debug('Temporary image file cleaned up.', ['image_url' => $image_source_url]);
                        }
                    }
                }
            }
            // --- End Image Upload Logic ---

            // --- Post tweet using API v2 ---
            $this->logger?->info('Preparing to post tweet via API v2.', ['user_id' => $user_id]);
            $v2_payload = [
                'text' => $tweet_text
            ];
            if ($media_id !== null) {
                $v2_payload['media'] = [
                    'media_ids' => [$media_id]
                ];
                $this->logger?->info('Adding v1.1 media ID to v2 payload.', ['media_id' => $media_id, 'user_id' => $user_id]);
            } else {
                 $this->logger?->info('No media ID to add to v2 payload.', ['user_id' => $user_id]);
            }

            $this->logger?->info('Posting tweet to v2 endpoint with payload keys:', ['payload_keys' => array_keys($v2_payload), 'user_id' => $user_id]);
            // Sending Twitter API v2 request
            $response = $connection->post('tweets', $v2_payload, ['json' => true]); // Use JSON payload for V2

            // 8. Check for API errors (V2 response format differs)
            $http_code = $connection->getLastHttpCode();
            $this->logger?->debug('Twitter API v2 response received', ['http_code' => $http_code, 'user_id' => $user_id]);

            if ($http_code == 201 && isset($response->data->id)) {
                $tweet_id = $response->data->id;
                $this->logger?->info('Tweet posted successfully (v2).', ['user_id' => $user_id, 'tweet_id' => $tweet_id, 'link' => "https://twitter.com/anyuser/status/".$tweet_id]);

                 $tweet_url = "https://twitter.com/anyuser/status/".$tweet_id;

                 return [
                     'status' => 'success', // Use 'status' key for consistency
                     'tweet_id' => $tweet_id,
                     'output_url' => $tweet_url, // Use 'output_url' key
                     'message' => sprintf(__( 'Successfully posted tweet: %s', 'data-machine' ), $tweet_id),
                     'raw_response' => $response
                 ];
            } else {
                $error_message = 'Twitter API Error: Failed to post tweet.';
                $error_code = 'twitter_post_failed_v2';
                $api_errors = [];

                if (isset($response->title) && isset($response->detail)) { 
                    $error_message = "Twitter API v2 Error: {$response->title} - {$response->detail}";
                    $error_code = $response->type ?? 'twitter_v2_error';
                     $this->logger?->warning('Received structured V2 API error.', [
                         'title' => $response->title,
                         'detail' => $response->detail,
                         'type' => $response->type ?? 'N/A',
                         'status' => $response->status ?? $http_code,
                         'user_id' => $user_id
                     ]);
                } 
                elseif (isset($response->errors) && is_array($response->errors) && !empty($response->errors)) {
                    $api_errors = $response->errors;
                    $first_error = reset($api_errors); 
                    $error_message .= ' Reason: ' . ($first_error->message ?? 'Unknown') . ' (Code: ' . ($first_error->code ?? 'N/A') . ')';
                    if (isset($first_error->code)) {
                        $error_code = 'twitter_api_error_' . $first_error->code;
                    }
                    $this->logger?->warning('Received V1.1-style API error structure.', [
                         'errors' => $api_errors,
                         'user_id' => $user_id
                     ]);                    
                }
                elseif ($http_code !== 201) {
                     $error_message .= ' HTTP Status: ' . $http_code;
                     $error_code = 'twitter_http_error_' . $http_code;
                }

                $this->logger?->error($error_message, [
                    'user_id' => $user_id,
                    'http_code' => $http_code,
                    'api_response' => $response // Log full response on error
                 ]);

                 return new WP_Error($error_code, $error_message, ['api_response' => $response]);
            }

        } catch (\Exception $e) {
            $this->logger?->error('Twitter Output Exception: ' . $e->getMessage(), ['user_id' => $user_id]);
            return new WP_Error('twitter_exception', $e->getMessage());
        }
	}

    /**
     * Defines the settings fields for this output handler.
	 *
     * @param array $current_config Current configuration values for this handler (optional).
     * @return array An associative array defining the settings fields.
	 */
	public function get_settings_fields(array $current_config = []): array {
        // Note: Actual authentication is handled on the API Keys page.
        return [
            'twitter_char_limit' => [
                'type' => 'number',
                'label' => __('Character Limit Override', 'data-machine'),
                'description' => __('Set a custom character limit for tweets (default: 280). Text will be truncated if necessary.', 'data-machine'),
                'default' => 280,
                'min' => 50,
                'max' => 280, // Twitter's standard limit
            ],
            'twitter_include_source' => [
                'type' => 'checkbox',
                'label' => __('Include Source Link', 'data-machine'),
                'description' => __('Append the original source URL to the tweet (if available and fits within character limits).', 'data-machine'),
                'default' => true,
            ],
             'twitter_enable_images' => [
                 'type' => 'checkbox',
                 'label' => __('Enable Image Posting', 'data-machine'),
                 'description' => __('Attempt to find and upload an image from the source data (if available).', 'data-machine'),
                 'default' => true,
             ],
        ];
	}

    /**
	 * Returns the user-friendly label for this output handler.
	 *
	 * @return string The label.
	 */
	public static function get_label(): string {
        return __('Post to Twitter', 'data-machine');
	}

    /**
     * Sanitizes the settings specific to the Twitter output handler.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        $sanitized['twitter_char_limit'] = min(280, max(50, absint($raw_settings['twitter_char_limit'] ?? 280)));
        $sanitized['twitter_include_source'] = isset($raw_settings['twitter_include_source']) && $raw_settings['twitter_include_source'] == '1';
        $sanitized['twitter_enable_images'] = isset($raw_settings['twitter_enable_images']) && $raw_settings['twitter_enable_images'] == '1';
        return $sanitized;
    }
} 