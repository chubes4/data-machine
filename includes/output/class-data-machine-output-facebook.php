<?php
/**
 * Handles the 'Facebook' output type.
 *
 * Posts content to a specified Facebook Page or User Feed using the Graph API.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/output
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Note: Interacting directly with Facebook Graph API via HTTP requests.
// Note: Requires integration with an OAuth flow for Facebook authentication.
// Note: Requires error handling.

class Data_Machine_Output_Facebook implements Data_Machine_Output_Handler_Interface {

    use Data_Machine_Base_Output_Handler;

    /** @var ?Data_Machine_Logger */
    private $logger;

    const FACEBOOK_API_VERSION = 'v22.0'; // Define API version

    /**
	 * Constructor.
	 *
     * @param Data_Machine_Logger|null $logger Optional Logger instance.
	 */
	public function __construct(?Data_Machine_Logger $logger = null) {
        $this->logger = $logger;
	}

    /**
	 * Handles posting the AI output to Facebook.
	 *
	 * @param string $ai_output_string The finalized string from the AI.
	 * @param array $module_job_config Configuration specific to this output job (e.g., target page ID).
	 * @param int|null $user_id The ID of the user whose Facebook account/token should be used.
	 * @param array $input_metadata Metadata from the original input source (e.g., original URL, timestamp).
	 * @return array|WP_Error Result array on success (e.g., ['status' => 'success', 'post_id' => '...', 'output_url' => '...']), WP_Error on failure.
	 */
	public function handle( string $ai_output_string, array $module_job_config, ?int $user_id, array $input_metadata ): array|WP_Error {
	       $this->logger?->info('Starting Facebook output handling.', ['user_id' => $user_id]);

	       // Initialize variables to ensure they are always defined
        $page_id = null;
        $page_access_token = null;

	       // 1. Get config
	       $output_config = $module_job_config['output_config']['facebook'] ?? []; // Use 'facebook' sub-key
        $target_id = trim($output_config['facebook_target_id'] ?? 'me');
        if (empty($target_id)) $target_id = 'me'; // Ensure 'me' if empty

        // TODO: Define and retrieve other relevant config options (e.g., link handling strategy)

        // 2. Ensure user_id is provided
        if (empty($user_id)) {
            $this->logger?->error('Facebook Output: User ID context is missing.');
            return new WP_Error('facebook_missing_user_id', __('Cannot post to Facebook without a specified user account.', 'data-machine'));
        }

        // 3. Get authenticated Page credentials for the user
        // Fetch these *before* parsing, ensuring they are always available
        $page_id = Data_Machine_OAuth_Facebook::get_page_id($user_id);
        $page_access_token = Data_Machine_OAuth_Facebook::get_page_access_token($user_id);

        if (empty($page_id) || empty($page_access_token)) {
            $this->logger?->error('Facebook Output: Failed to get Page ID or Page Access Token.', ['user_id' => $user_id, 'page_id_found' => !empty($page_id), 'token_found' => !empty($page_access_token)]);
            return new WP_Error('facebook_page_auth_failed', __('Failed to retrieve Facebook Page credentials. Please check authentication on the API Keys page.', 'data-machine'));
        }
        
        $this->logger?->debug('Facebook Output: Retrieved Page credentials.', ['user_id' => $user_id, 'page_id' => $page_id]);

        // 4. Parse AI output
        // Use the trait method if available, otherwise instantiate parser
        if (method_exists($this, 'parse_ai_output')) {
            // Assuming the trait method correctly parses into a similar structure
            $parsed_output = $this->parse_ai_output($ai_output_string);
        } else {
            // Fallback if trait method isn't found (should be there via Base_Output_Handler)
            $parser = new Data_Machine_AI_Response_Parser( $ai_output_string );
            $parser->parse();
            // Ensure fallback parser provides needed keys, even if null
            $parsed_output = [
                'title' => $parser->get_title(), // May be null
                'content' => $parser->get_content(), // May be empty
                // No hashtags or primary_link needed from parser anymore
            ];
        }
        $content = $parsed_output['content'] ?? '';
        // $hashtags = $parsed_output['hashtags'] ?? []; // Removed
        // $primary_link_from_ai = $parsed_output['primary_link'] ?? null; // Removed
        $source_link = $input_metadata['source_url'] ?? null; // Get source link from metadata

        if (empty($content)) {
            $this->logger?->warning('Facebook Output: Parsed AI output content is empty.', ['user_id' => $user_id]);
            return new WP_Error('facebook_empty_content', __('Cannot post empty content to Facebook.', 'data-machine'));
        }

        // 5. Determine post type and prepare API parameters
        $image_url = $input_metadata['image_source_url'] ?? null;
        $video_url = null; // TODO: Add logic for video handling if needed
        // $source_link = $input_metadata['source_url'] ?? null; // Source link is now appended to $content directly

        // Determine post type and prepare API parameters
        if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL) && $this->is_image_accessible($image_url)) {
            // --- Image Post --- 
            // TODO: Add config check to enable/disable images
            $endpoint = "/{$page_id}/photos"; // Post to page photos endpoint
            $api_params = [
                'caption' => $content, // Content (with appended link) becomes the caption
                'url' => $image_url // Post image by URL
            ];

        } elseif (!empty($video_url) && filter_var($video_url, FILTER_VALIDATE_URL)) {
            // --- Video Post (Fallback to Text) --- 
            // TODO: Add config check to enable/disable video
            // Video requires different handling (upload vs url, async processing)
            // For now, log and skip video, post as text.
            $this->logger?->warning('Facebook API: Video posting not yet fully implemented, posting as text.', ['video_url' => $video_url, 'user_id' => $user_id]);
            $endpoint = "/{$page_id}/feed"; // Fallback to page feed post
            $api_params['message'] = $content; // Ensure message uses content with appended link

        } else {
            // --- Text-Only Post --- 
            // This block handles all non-image/non-video posts.
            // The source link (if any) is already appended to $content.
            $endpoint = "/{$page_id}/feed"; // Post to page feed
            $api_params['message'] = $content; // Ensure message uses content with appended link
        }

        // Add the Page Access Token to parameters
        $api_params['access_token'] = $page_access_token;

        // 6. Post to Facebook using wp_remote_post
        try {
            $graph_api_url = 'https://graph.facebook.com/' . self::FACEBOOK_API_VERSION;
            $url = $graph_api_url . $endpoint;

            // Making Facebook API request 

            $response = wp_remote_post($url, [
                'method' => 'POST',
                'body' => $api_params,
                'timeout' => 45, // Increased timeout for potential image/video processing
            ]);


            if (is_wp_error($response)) {
                $error_code = $response->get_error_code();
                $error_message = $response->get_error_message();
                $this->logger?->error('Facebook API Error: wp_remote_post failed.', ['error_code' => $error_code, 'error_message' => $error_message, 'user_id' => $user_id]);
                return new WP_Error('facebook_wp_remote_error_' . $error_code, $error_message, $response);
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            $http_code = wp_remote_retrieve_response_code($response);

            // Check for API errors returned in the body
            if (isset($data['error'])) {
                $error_message = $data['error']['message'] ?? 'Unknown Facebook API error.';
                $error_type = $data['error']['type'] ?? 'APIError';
                $error_code_fb = $data['error']['code'] ?? 'UnknownCode';
                $this->logger?->error('Facebook API Error: Received error response.', ['http_code' => $http_code, 'fb_error' => $data['error'], 'user_id' => $user_id]);
                return new WP_Error('facebook_api_error_' . $error_code_fb, $error_message, ['api_response' => $data]);
            }

            // Check for successful HTTP status code and presence of post ID
            // Post ID format can be {target_id}_{post_identifier} or just {post_identifier} for photos/videos?
            $post_id = $data['id'] ?? ($data['post_id'] ?? null);

            if ($http_code >= 200 && $http_code < 300 && !empty($post_id)) {
                // Construct the URL to the post
                // Format: https://www.facebook.com/{post_id}
                // The ID returned usually contains the user/page ID already.
                $output_url = "https://www.facebook.com/" . $post_id;

                $this->logger?->info('Facebook post published successfully.', ['user_id' => $user_id, 'post_id' => $post_id, 'output_url' => $output_url]);

                // Attempt to post the source link as a comment
                if (!empty($source_link) && filter_var($source_link, FILTER_VALIDATE_URL)) {
                    $comment_message = "Source: " . $source_link;
                    $comment_endpoint = "/{$post_id}/comments";
                    $comment_api_params = [
                        'message' => $comment_message,
                        'access_token' => $page_access_token,
                    ];

                    
                    $comment_url = $graph_api_url . $comment_endpoint;
                    $comment_response = wp_remote_post($comment_url, [
                        'method' => 'POST',
                        'body' => $comment_api_params,
                        'timeout' => 30,
                    ]);

                    if (is_wp_error($comment_response)) {
                        $this->logger?->warning('Facebook API: Failed to post source link comment (wp_remote_error).', [
                            'post_id' => $post_id,
                            'error_code' => $comment_response->get_error_code(),
                            'error_message' => $comment_response->get_error_message(),
                            'user_id' => $user_id
                        ]);
                    } else {
                        $comment_body = wp_remote_retrieve_body($comment_response);
                        $comment_data = json_decode($comment_body, true);
                        $comment_http_code = wp_remote_retrieve_response_code($comment_response);

                        if (isset($comment_data['error'])) {
                            $this->logger?->warning('Facebook API: Failed to post source link comment (API error).', [
                                'post_id' => $post_id,
                                'http_code' => $comment_http_code,
                                'fb_error' => $comment_data['error'],
                                'user_id' => $user_id
                            ]);
                        } elseif ($comment_http_code >= 200 && $comment_http_code < 300 && isset($comment_data['id'])) {
                            $this->logger?->info('Facebook API: Successfully posted source link as comment.', ['post_id' => $post_id, 'comment_id' => $comment_data['id'], 'user_id' => $user_id]);
                        } else {
                            $this->logger?->warning('Facebook API: Failed to post source link comment (unexpected response).', [
                                'post_id' => $post_id,
                                'http_code' => $comment_http_code,
                                'response_body' => $comment_body,
                                'user_id' => $user_id
                            ]);
                        }
                    }
                }

                return [
                    'status' => 'success',
                    'post_id' => $post_id,
                    'output_url' => $output_url,
                    'message' => sprintf(__('Successfully posted to Facebook: %s', 'data-machine'), $post_id),
                    'raw_response' => $data
                ];
            } else {
                 // Handle cases where response is successful but doesn't contain expected ID
                 $this->logger?->error('Facebook API Error: Unexpected response format or missing post ID.', ['http_code' => $http_code, 'response_body' => $body, 'user_id' => $user_id]);
                return new WP_Error('facebook_post_id_missing', __('Unexpected response format or missing Post ID from Facebook.', 'data-machine'), ['api_response' => $data]);
            }

        } catch (\Exception $e) {
            $this->logger?->error('Facebook Output Exception: ' . $e->getMessage(), ['user_id' => $user_id, 'trace' => $e->getTraceAsString()]);
            return new WP_Error('facebook_exception', $e->getMessage());
        }
	}

    /**
     * Defines the settings fields for this output handler.
	 *
     * @param array $current_config Current configuration values for this handler (optional).
     * @return array An associative array defining the settings fields.
	 */
	public function get_settings_fields(array $current_config = []): array {
        // TODO: Define settings specific to Facebook (e.g., target page/group ID, default post type, image/video options)
        // Authentication is handled separately on the API Keys page.
        return [
            'facebook_target_id' => [
                'type' => 'text',
                'label' => __('Target Page/Group/User ID', 'data-machine'),
                'description' => __('Enter the Facebook Page ID, Group ID, or leave empty/use "me" to post to the authenticated user\'s feed.', 'data-machine'),
                'default' => 'me',
            ],
            'facebook_placeholder' => [
                'type' => 'description',
                'label' => __('Facebook Settings', 'data-machine'),
                'description' => __('Additional configuration options for Facebook output will be added here. Authentication is managed on the API Keys page.', 'data-machine'),
            ],
            // Example setting:
            // 'facebook_enable_images' => [
            //     'type' => 'checkbox',
            //     'label' => __('Enable Image Posting', 'data-machine'),
            //     'description' => __('Attempt to find and upload an image from the source data (if available). Posts as a photo post.', 'data-machine'),
            //     'default' => true,
            // ],
        ];
	}

    /**
	 * Returns the user-friendly label for this output handler.
	 *
	 * @return string The label.
	 */
	public static function get_label(): string {
        return __('Facebook', 'data-machine');
	}

    /**
     * Sanitizes the settings specific to the Facebook output handler.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        $sanitized['facebook_target_id'] = sanitize_text_field($raw_settings['facebook_target_id'] ?? 'me');
        // TODO: Sanitize other actual settings when defined.
        // Example:
        // $sanitized['facebook_enable_images'] = isset($raw_settings['facebook_enable_images']) && $raw_settings['facebook_enable_images'] == '1';
        return $sanitized;
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
                $this->logger?->warning('Facebook: Skipping problematic image URL pattern', ['url' => $image_url, 'pattern' => $pattern]);
                return false;
            }
        }

        // Test accessibility with HEAD request
        $response = wp_remote_head($image_url, [
            'timeout' => 10,
            'user-agent' => 'Mozilla/5.0 (compatible; DataMachine/1.0; +https://github.com/chubes/data-machine)'
        ]);

        if (is_wp_error($response)) {
            $this->logger?->warning('Facebook: Image URL not accessible', ['url' => $image_url, 'error' => $response->get_error_message()]);
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        // Check for successful response and image content type
        if ($http_code >= 200 && $http_code < 300 && strpos($content_type, 'image/') === 0) {
            return true;
        }

        $this->logger?->warning('Facebook: Image URL validation failed', [
            'url' => $image_url, 
            'http_code' => $http_code, 
            'content_type' => $content_type
        ]);
        return false;
    }
}