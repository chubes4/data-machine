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

    /**
	 * Service Locator instance.
	 * @var Data_Machine_Service_Locator
	 */
	private $locator;

    /**
	 * Constructor.
	 * @param Data_Machine_Service_Locator $locator Service Locator instance.
	 */
	public function __construct(Data_Machine_Service_Locator $locator) {
		$this->locator = $locator;
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
        
        // 1. Get config
        $config = $module_job_config['output_config'] ?? [];
        $char_limit = $config['twitter_char_limit'] ?? 280;
        $include_source = $config['twitter_include_source'] ?? true;

        // 2. Ensure user_id is provided
        if (empty($user_id)) {
            return new WP_Error('twitter_missing_user_id', __('Cannot post to Twitter without a specified user account.', 'data-machine'));
        }

        // 3. Get authenticated connection
        if (!class_exists('Data_Machine_OAuth_Twitter')) {
            require_once DATA_MACHINE_PATH . 'admin/oauth/class-data-machine-oauth-twitter.php';
        }
        $connection = Data_Machine_OAuth_Twitter::get_authenticated_connection($user_id);

        // 4. Handle connection errors
        if (is_wp_error($connection)) {
             $this->locator->get('logger')->error('Twitter Output Error: Failed to get authenticated connection.', [
                'user_id' => $user_id,
                'error_code' => $connection->get_error_code(),
                'error_message' => $connection->get_error_message(),
             ]);
             return $connection; // Return the WP_Error from the auth handler
        }

        // 5. Parse AI output
        require_once DATA_MACHINE_PATH . 'includes/helpers/class-ai-response-parser.php';
        $parser = new Data_Machine_AI_Response_Parser( $ai_output_string );
        $parser->parse();
        $title = $parser->get_title();
        $content = $parser->get_content(); // Assuming content is the main text for the tweet

        if (empty($title) && empty($content)) {
            return new WP_Error('twitter_empty_content', __('Cannot post empty content to Twitter.', 'data-machine'));
        }

        // --- 6. Format tweet content --- 
        $tweet_text = $title ? $title . ": " . $content : $content;
        $source_url = $input_metadata['source_url'] ?? null;
        $link_placeholder = ' [LINK]'; // Placeholder for length calculation
        $link_length = 0;
        $available_chars = $char_limit;

        // Calculate length needed for the source link (Twitter shortens links, but we estimate)
        if ($include_source && !empty($source_url)) {
             // Twitter uses t.co which has a fixed length, but let's estimate based on placeholder
             // A more accurate approach would involve Twitter's configuration endpoint, but this is simpler.
             $link_length = mb_strlen($link_placeholder);
             $available_chars -= $link_length;
        }

        // Truncate main text if needed
        if (mb_strlen($tweet_text) > $available_chars) {
            $tweet_text = mb_substr($tweet_text, 0, $available_chars - 1) . '…'; // Add ellipsis
        }

        // Append the actual source link if applicable
        if ($include_source && !empty($source_url)) {
             $tweet_text .= " " . $source_url;
        }
        // Final trim
        $tweet_text = trim($tweet_text);
        // --- End Formatting ---

        if (empty($tweet_text)) {
             return new WP_Error('twitter_empty_formatted_content', __('Formatted tweet content is empty after processing.', 'data-machine'));
        }

        // --- 7. Post tweet --- 
        try {
            $tweet_params = ['status' => $tweet_text];
            $media_id = null;
            $temp_image_path = null; // Variable to hold temporary path

            // --- Image Upload Logic ---
            $image_source_url = $input_metadata['image_source_url'] ?? null;
            if (!empty($image_source_url)) {
                $this->locator->get('logger')->info('Attempting to upload image to Twitter.', ['image_url' => $image_source_url, 'user_id' => $user_id]);
                
                // Ensure the file containing download_url() is loaded, especially for non-admin contexts like cron
                if (!function_exists('download_url')) {
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                }
                
                // Download the image to a temporary file
                $temp_image_path = download_url($image_source_url, 15); // 15 second timeout

                if (is_wp_error($temp_image_path)) {
                    $this->locator->get('logger')->warning('Failed to download image for Twitter upload.', ['url' => $image_source_url, 'error' => $temp_image_path->get_error_message()]);
                    // Proceed without image
                } else {
                    try {
                        // Upload the downloaded image file
                        $media_upload = $connection->upload('media/upload', ['media' => file_get_contents($temp_image_path)]);
                        
                        if ($connection->getLastHttpCode() === 200 && isset($media_upload->media_id_string)) {
                            $media_id = $media_upload->media_id_string;
                            $this->locator->get('logger')->info('Successfully uploaded image to Twitter.', ['media_id' => $media_id, 'user_id' => $user_id]);
                        } else {
                             $upload_error_message = 'Twitter API Error: Failed to upload media.';
                             if (isset($media_upload->errors)) {
                                 $first_error = reset($media_upload->errors);
                                 $upload_error_message .= ' Reason: ' . ($first_error->message ?? 'Unknown') . ' (Code: ' . ($first_error->code ?? 'N/A') . ')';
                             }
                            $this->locator->get('logger')->error($upload_error_message, [
                                'user_id' => $user_id,
                                'http_code' => $connection->getLastHttpCode(),
                                'api_response' => $media_upload,
                                'image_url' => $image_source_url
                             ]);
                            // Proceed without image if upload fails
                        }
                    } catch (\Exception $e) {
                        $this->locator->get('logger')->error('Twitter Output Exception: ' . $e->getMessage(), ['user_id' => $user_id]);
                        // Ensure temp path is null so we don't try to delete a non-existent file later
                        $temp_image_path = null;
                    } finally {
                        // Clean up the temporary file if it was created
                        if ($temp_image_path && file_exists($temp_image_path)) {
                            @unlink($temp_image_path); // Suppress errors if deletion fails
                            $this->locator->get('logger')->debug('Temporary image file cleaned up.', ['image_url' => $image_source_url]);
                        }
                    }
                }
            }
            // --- End Image Upload Logic ---

            // --- 7. Post tweet using API v2 ---
            $this->locator->get('logger')->info('Preparing to post tweet via API v2.', ['user_id' => $user_id]);
            $v2_payload = [
                'text' => $tweet_text
            ];
            if ($media_id !== null) {
                // Use the media_id obtained from the v1.1 upload endpoint
                $v2_payload['media'] = [
                    'media_ids' => [$media_id]
                ];
                $this->locator->get('logger')->info('Adding v1.1 media ID to v2 payload.', ['media_id' => $media_id, 'user_id' => $user_id]);
            } else {
                 $this->locator->get('logger')->info('No media ID to add to v2 payload.', ['user_id' => $user_id]);
            }

            // Log payload structure instead of full content
            $this->locator->get('logger')->info('Posting tweet to v2 endpoint with payload keys:', ['payload_keys' => array_keys($v2_payload), 'user_id' => $user_id]);
            // Use API v2 endpoint and payload structure
            $response = $connection->post('tweets', $v2_payload);

            // 8. Check for API errors (V2 response format differs)
            $http_code = $connection->getLastHttpCode();
            // Remove full response body from debug log; details logged on error later
            $this->locator->get('logger')->debug('Twitter API v2 response received', ['http_code' => $http_code, 'user_id' => $user_id]);

            // V2 successful creation is typically 201 Created
            if ($http_code == 201 && isset($response->data->id)) {
                // Success!
                $tweet_id = $response->data->id; // Use data->id from v2 response
                $tweet_text_response = $response->data->text ?? $tweet_text;
                 $this->locator->get('logger')->info('Tweet posted successfully (v2).', ['user_id' => $user_id, 'tweet_id' => $tweet_id, 'link' => "https://twitter.com/anyuser/status/".$tweet_id]);

                 // Construct the tweet URL using the ID
                 $tweet_url = "https://twitter.com/anyuser/status/".$tweet_id;

                 // 10. Return success array
                 return [
                     'success' => true,
                     'tweet_id' => $tweet_id,
                     'tweet_url' => $tweet_url,
                     'message' => sprintf(__( 'Successfully posted tweet: %s', 'data-machine' ), $tweet_id),
                     'raw_response' => $response // Include raw response for potential debugging/data use
                 ];
            } else {
                // Handle API errors (V2 format primarily)
                $error_message = 'Twitter API Error: Failed to post tweet.'; // Default message
                $error_code = 'twitter_post_failed_v2'; // Default code
                $api_errors = [];

                // Check V2 error format first (title, detail, type)
                if (isset($response->title) && isset($response->detail)) { 
                    $error_message = "Twitter API v2 Error: {$response->title} - {$response->detail}";
                    $error_code = $response->type ?? 'twitter_v2_error';
                     // Log specific V2 error details
                     $this->locator->get('logger')->warning('Received structured V2 API error.', [
                         'title' => $response->title,
                         'detail' => $response->detail,
                         'type' => $response->type ?? 'N/A',
                         'status' => $response->status ?? $http_code, // Use status if available
                         'user_id' => $user_id
                     ]);
                } 
                // Check V1.1 style errors as fallback (some v2 errors might still use this structure)
                elseif (isset($response->errors) && is_array($response->errors) && !empty($response->errors)) {
                    $api_errors = $response->errors;
                    $first_error = reset($api_errors); 
                    $error_message .= ' Reason: ' . ($first_error->message ?? 'Unknown') . ' (Code: ' . ($first_error->code ?? 'N/A') . ')';
                    if (isset($first_error->code)) {
                        $error_code = 'twitter_api_error_' . $first_error->code;
                    }
                    $this->locator->get('logger')->warning('Received V1.1-style API error structure.', [
                         'errors' => $api_errors,
                         'user_id' => $user_id
                     ]);                    
                }
                // Fallback to HTTP code if no structured error
                elseif ($http_code !== 201) { // Check against 201 for v2 success
                     $error_message .= ' HTTP Status: ' . $http_code;
                     $error_code = 'twitter_http_error_' . $http_code;
                     $this->locator->get('logger')->warning('API error detected via HTTP status code only.', [
                         'http_code' => $http_code,
                         'user_id' => $user_id
                     ]);
                }

                 // Log the final determined error
                 $this->locator->get('logger')->error($error_message, [
                    'final_error_code' => $error_code,
                    'user_id' => $user_id,
                    'http_code' => $http_code,
                    'api_response' => $response,
                    'tweet_content_sent' => $tweet_text // Log what we tried to send
                 ]);

                 return new WP_Error($error_code, $error_message, ['api_response' => $response]);
            }
        } catch (\Exception $e) {
             $this->locator->get('logger')->error('Twitter Output Exception: ' . $e->getMessage(), ['user_id' => $user_id]);
             return new WP_Error('twitter_post_exception', __('An unexpected error occurred while trying to post the tweet.', 'data-machine'), ['exception' => $e->getMessage()]);
        }
	}

    /**
	 * Defines the settings fields specific to the Twitter output handler.
	 *
     * @param array $current_config Current configuration values for this handler (optional).
	 * @return array An array defining the settings fields.
	 */
	public function get_settings_fields(array $current_config = []): array {
        // TODO: Define settings fields as per plan
        // - Character Limit (number, default 280)
        // - Include Source Link (checkbox, default true)

        return [
            'twitter_char_limit' => [
                'label' => __('Character Limit', 'data-machine'),
                'type' => 'number',
                'default' => 280,
                'description' => __('Maximum characters per tweet. Twitter automatically shortens links. Leave blank for default (280).', 'data-machine'),
                'attributes' => ['min' => 10, 'max' => 280] // Twitter's limit is 280
            ],
            'twitter_include_source' => [
                'label' => __('Include Source Link', 'data-machine'),
                'type' => 'checkbox',
                'default' => true,
                'description' => __('Append the original source link to the tweet if available in the input metadata?', 'data-machine')
            ]
        ];
	}

    /**
	 * Returns the user-friendly label for this output handler.
	 *
	 * @return string The label.
	 */
	public static function get_label(): string {
		return __( 'Twitter', 'data-machine' );
	}

    /**
     * Sanitizes the settings specific to this output handler.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings array.
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        
        // Sanitize Character Limit
        $sanitized['twitter_char_limit'] = isset($raw_settings['twitter_char_limit']) ? absint($raw_settings['twitter_char_limit']) : 280;
        // Apply basic validation (min 10, max 280)
        if ($sanitized['twitter_char_limit'] > 280 || $sanitized['twitter_char_limit'] < 10) { 
            $sanitized['twitter_char_limit'] = 280; // Default to 280 if out of bounds
        }

        // Sanitize Include Source Link (checkbox)
        $sanitized['twitter_include_source'] = !empty($raw_settings['twitter_include_source']); // true if checked (value is usually 'on' or 1), false otherwise

        return $sanitized;
    }
} 