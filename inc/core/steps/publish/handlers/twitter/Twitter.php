<?php
/**
 * Modular Twitter publish handler.
 *
 * Posts content to a specified Twitter account using the self-contained
 * TwitterAuth class for authentication. This modular approach separates
 * concerns between main handler logic and authentication functionality.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/publish/twitter
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Publish\Twitter;

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
        $all_auth = apply_filters('dm_get_auth_providers', []);
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
     * Handles posting the AI output to Twitter.
     *
     * @param object $data_packet Universal DataPacket JSON object with all content and metadata.
     * @return array Result array on success or failure.
     */
    public function handle_publish($data_packet): array {
        // Access structured content directly from DataPacket (no parsing needed)
        $title = $data_packet->content->title ?? '';
        $content = $data_packet->content->body ?? '';
        
        // Get publish config from DataPacket (set by PublishStep)
        $publish_config = $data_packet->publish_config ?? [];
        
        // Extract metadata from DataPacket
        $input_metadata = [
            'source_url' => $data_packet->metadata->source_url ?? null,
            'image_source_url' => !empty($data_packet->attachments->images) ? $data_packet->attachments->images[0]->url : null
        ];
        
        // Get logger service via filter
        $logger = apply_filters('dm_get_logger', null);
        $logger && $logger->debug('Starting Twitter output handling.');
        
        // 1. Get config - publish_config is the handler_config directly
        $char_limit = $publish_config['twitter_char_limit'] ?? 280;
        $include_source = $publish_config['twitter_include_source'] ?? true;
        $enable_images = $publish_config['twitter_enable_images'] ?? true;

        // 2. Get authenticated connection using internal TwitterAuth
        $connection = $this->auth->get_connection();

        // 3. Handle connection errors
        if (is_wp_error($connection)) {
             $logger && $logger->error('Twitter Output Error: Failed to get authenticated connection.', [
                'error_code' => $connection->get_error_code(),
                'error_message' => $connection->get_error_message(),
             ]);
             return [
                 'success' => false,
                 'error' => $connection->get_error_message()
             ];
        }

        // 5. Validate content from DataPacket

        if (empty($title) && empty($content)) {
            $logger && $logger->warning('Twitter Output: DataPacket content is empty.');
            return [
                'success' => false,
                'error' => __('Cannot post empty content to Twitter.', 'data-machine')
            ];
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
             $logger && $logger->error('Twitter Output: Formatted tweet content is empty after processing.');
             return [
                 'success' => false,
                 'error' => __('Formatted tweet content is empty after processing.', 'data-machine')
             ];
        }

        // --- 7. Post tweet --- 
        try {
            $tweet_params = ['status' => $tweet_text];
            $media_id = null;
            $temp_image_path = null; // Variable to hold temporary path

            // --- Image Upload Logic ---
            $image_source_url = $input_metadata['image_source_url'] ?? null;
            $image_alt_text = $title ?: substr($content, 0, 50); // Use title or content summary as alt text

            if ($enable_images && !empty($image_source_url) && filter_var($image_source_url, FILTER_VALIDATE_URL) && $this->is_image_accessible($image_source_url, $logger)) {
                $logger && $logger->debug('Attempting to upload image to Twitter.', ['image_url' => $image_source_url]);
                
                if (!function_exists('download_url')) {
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                }
                
                $temp_image_path = download_url($image_source_url, 15); // 15 second timeout

                if (is_wp_error($temp_image_path)) {
                    $logger && $logger->warning('Failed to download image for Twitter upload.', ['url' => $image_source_url, 'error' => $temp_image_path->get_error_message()]);
                    $temp_image_path = null; // Ensure path is null on error
                } else {
                    try {
                        // Determine MIME type for media_type parameter
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_type = finfo_file($finfo, $temp_image_path);
                        finfo_close($finfo);

                        if (empty($mime_type) || !preg_match('/^image\/(jpeg|png|gif|webp)$/', $mime_type)) {
                            $logger && $logger->warning('Could not determine a valid image MIME type for Twitter upload, or type not supported.', ['path' => $temp_image_path, 'detected_mime' => $mime_type ?: 'N/A']);
                            throw new \Exception('Invalid or unsupported image type for Twitter.');
                        }

                        $logger && $logger->debug('Attempting chunked media upload to Twitter.', ['path' => $temp_image_path, 'mime_type' => $mime_type]);

                        // Pass media_type and media_category directly in the parameters for the upload method.
                        // The library should handle chunking automatically for the media/upload endpoint.
                        $media_upload_params = [
                            'media' => $temp_image_path,
                            'media_type' => $mime_type,
                            'media_category' => 'tweet_image' // For standard image tweets
                        ];
                        $media_upload = $connection->upload('media/upload', $media_upload_params);

                        $logger && $logger->debug('Twitter chunked media/upload response', [ // Enhanced logging
                            'http_code' => $connection->getLastHttpCode(),
                            'response_body' => $media_upload // Log the entire response object
                        ]);
                        
                        if ($connection->getLastHttpCode() === 200 && isset($media_upload->media_id_string)) {
                            $media_id = $media_upload->media_id_string;

                            // Check for media processing information
                            if (isset($media_upload->processing_info)) {
                                $logger && $logger->debug('Twitter media/upload processing_info found', [
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
                                    $logger && $logger->error($processing_error_message, [
                                        'media_id' => $media_id,
                                        'processing_error_details' => $media_upload->processing_info->error ?? null
                                    ]);
                                    // Throw an exception to stop further processing if media definitely failed
                                    throw new \Exception($processing_error_message);
                                }
                                // If state is 'pending' or 'in_progress', it might ideally need a waiting loop with STATUS checks,
                                // but for now, we'll log and let it proceed to see if v2 handles it or still fails.
                            }
                            
                             // --- Add Alt Text (v1.1) --- 
                             if (!empty($media_id) && !empty($image_alt_text)) {
                                 $alt_text_params = ['media_id' => $media_id, 'alt_text' => ['text' => mb_substr($image_alt_text, 0, 1000)]];
                                 $alt_response = $connection->post('media/metadata/create', wp_json_encode($alt_text_params), true); // Use JSON payload
                                 if ($connection->getLastHttpCode() !== 200) {
                                     $logger && $logger->warning('Failed to add alt text to Twitter image.', [
                                         'media_id' => $media_id,
                                         'http_code' => $connection->getLastHttpCode(),
                                         'response' => $alt_response
                                     ]);
                                 } else {
                                     $logger && $logger->debug('Successfully added alt text to Twitter image.', ['media_id' => $media_id]);
                                 }
                             }                            
                             // --- End Alt Text --- 
                            $logger && $logger->debug('Successfully uploaded image to Twitter.', ['media_id' => $media_id]);
                        } else {
                             $upload_error_message = 'Twitter API Error: Failed to upload media.';
                             if (isset($media_upload->errors)) {
                                 $first_error = reset($media_upload->errors);
                                 $upload_error_message .= ' Reason: ' . ($first_error->message ?? 'Unknown') . ' (Code: ' . ($first_error->code ?? 'N/A') . ')';
                             }
                            $logger && $logger->error($upload_error_message, [
                                'http_code' => $connection->getLastHttpCode(),
                                'api_response' => $media_upload,
                                'image_url' => $image_source_url
                             ]);
                        }
                    } catch (\Exception $e) {
                        $logger && $logger->error('Twitter Output Exception during image upload: ' . $e->getMessage());
                        $temp_image_path = null; // Ensure path is null on error
                    } finally {
                        if ($temp_image_path && file_exists($temp_image_path)) {
                            wp_delete_file($temp_image_path);
                            $logger && $logger->debug('Temporary image file cleaned up.', ['image_url' => $image_source_url]);
                        }
                    }
                }
            }
            // --- End Image Upload Logic ---

            // --- Post tweet using API v2 ---
            $logger && $logger->debug('Preparing to post tweet via API v2.');
            $v2_payload = [
                'text' => $tweet_text
            ];
            if ($media_id !== null) {
                $v2_payload['media'] = [
                    'media_ids' => [$media_id]
                ];
                $logger && $logger->debug('Adding v1.1 media ID to v2 payload.', ['media_id' => $media_id]);
            } else {
                 $logger && $logger->debug('No media ID to add to v2 payload.');
            }

            $logger && $logger->debug('Posting tweet to v2 endpoint with payload keys:', ['payload_keys' => array_keys($v2_payload)]);
            // Sending Twitter API v2 request
            $response = $connection->post('tweets', $v2_payload, ['json' => true]); // Use JSON payload for V2

            // 4. Check for API errors (V2 response format differs)
            $http_code = $connection->getLastHttpCode();
            $logger && $logger->debug('Twitter API v2 response received', ['http_code' => $http_code]);

            if ($http_code == 201 && isset($response->data->id)) {
                $tweet_id = $response->data->id;
                
                // Get authenticated account details to construct proper tweet URL
                $account_details = $this->auth->get_account_details();
                $screen_name = $account_details['screen_name'] ?? 'twitter';
                $tweet_url = "https://twitter.com/{$screen_name}/status/{$tweet_id}";
                
                $logger && $logger->debug('Tweet posted successfully (v2).', ['tweet_id' => $tweet_id, 'link' => $tweet_url]);

                 return [
                     'success' => true,
                     'status' => 'success', // Use 'status' key for consistency
                     'tweet_id' => $tweet_id,
                     'output_url' => $tweet_url, // Use 'output_url' key
                     /* translators: %s: Twitter tweet ID */
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
                     $logger && $logger->warning('Received structured V2 API error.', [
                         'title' => $response->title,
                         'detail' => $response->detail,
                         'type' => $response->type ?? 'N/A',
                         'status' => $response->status ?? $http_code
                     ]);
                } 
                elseif (isset($response->errors) && is_array($response->errors) && !empty($response->errors)) {
                    $api_errors = $response->errors;
                    $first_error = reset($api_errors); 
                    $error_message .= ' Reason: ' . ($first_error->message ?? 'Unknown') . ' (Code: ' . ($first_error->code ?? 'N/A') . ')';
                    if (isset($first_error->code)) {
                        $error_code = 'twitter_api_error_' . $first_error->code;
                    }
                    $logger && $logger->warning('Received V1.1-style API error structure.', [
                         'errors' => $api_errors
                     ]);                    
                }
                elseif ($http_code !== 201) {
                     $error_message .= ' HTTP Status: ' . $http_code;
                     $error_code = 'twitter_http_error_' . $http_code;
                }

                $logger && $logger->error($error_message, [
                    'http_code' => $http_code,
                    'api_response' => $response // Log full response on error
                 ]);

                 return [
                     'success' => false,
                     'error' => $error_message,
                     'api_response' => $response
                 ];
            }

        } catch (\Exception $e) {
            $logger && $logger->error('Twitter Output Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
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
     * Sanitizes the settings specific to the Twitter publish handler.
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

    /**
     * Check if an image URL is accessible by making a HEAD request
     *
     * @param string $image_url The image URL to check
     * @param object|null $logger Logger instance
     * @return bool True if accessible, false otherwise
     */
    private function is_image_accessible(string $image_url, $logger = null): bool {
        // Skip certain problematic domains/patterns
        $problematic_patterns = [
            'preview.redd.it', // Reddit preview URLs often have access restrictions
            'i.redd.it'        // Reddit image URLs may have restrictions
        ];
        
        foreach ($problematic_patterns as $pattern) {
            if (strpos($image_url, $pattern) !== false) {
                $logger && $logger->warning('Twitter: Skipping problematic image URL pattern', ['url' => $image_url, 'pattern' => $pattern]);
                return false;
            }
        }

        // Test accessibility with HEAD request
        $response = wp_remote_head($image_url, [
            'timeout' => 10,
            'user-agent' => 'Mozilla/5.0 (compatible; DataMachine/1.0; +https://github.com/chubes/data-machine)'
        ]);

        if (is_wp_error($response)) {
            $logger && $logger->warning('Twitter: Image URL not accessible', ['url' => $image_url, 'error' => $response->get_error_message()]);
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        // Check for successful response and image content type
        if ($http_code >= 200 && $http_code < 300 && strpos($content_type, 'image/') === 0) {
            return true;
        }

        $logger && $logger->warning('Twitter: Image URL validation failed', [
            'url' => $image_url, 
            'http_code' => $http_code, 
            'content_type' => $content_type
        ]);
        return false;
    }
}


