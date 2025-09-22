<?php
/**
 * Modular Facebook publish handler.
 *
 * Posts content to a specified Facebook Page using the self-contained
 * FacebookAuth class for authentication. This modular approach separates
 * concerns between main handler logic and authentication functionality.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Publish\Handlers\Facebook
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Facebook;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Facebook {

    const FACEBOOK_API_VERSION = 'v22.0';

    /**
     * @var FacebookAuth Authentication handler instance
     */
    private $auth;

    public function __construct() {
        $all_auth = apply_filters('dm_auth_providers', []);
        $this->auth = $all_auth['facebook'] ?? null;
        
        if ($this->auth === null) {
            do_action('dm_log', 'error', 'Facebook Handler: Authentication service not available', [
                'missing_service' => 'facebook',
                'available_providers' => array_keys($all_auth)
            ]);
        }
    }

    private function get_auth() {
        return $this->auth;
    }

    /**
     * Publish content to Facebook page.
     *
     * @param array $parameters Tool parameters including content
     * @param array $tool_def Tool definition with handler_config
     * @return array Publication result
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {

        if (empty($parameters['content'])) {
            $error_msg = 'Facebook tool call missing required content parameter';
            do_action('dm_log', 'error', $error_msg, [
                'provided_parameters' => array_keys($parameters),
                'required_parameters' => ['content']
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'facebook_publish'
            ];
        }

        $handler_config = $parameters['handler_config'] ?? [];
        
        // Extract Facebook-specific configuration (it's nested under 'facebook' key)
        $facebook_config = $handler_config['facebook'] ?? $handler_config;
        

        $job_id = $parameters['job_id'] ?? null;
        $engine_data = apply_filters('dm_engine_data', [], $job_id);

        // Extract parameters from flat structure
        $title = $parameters['title'] ?? '';
        $content = $parameters['content'] ?? '';
        $source_url = $engine_data['source_url'] ?? null;
        $image_url = $engine_data['image_url'] ?? null;
        
        // Get config from Facebook-specific settings
        $include_images = $facebook_config['include_images'] ?? false;
        $link_handling = $facebook_config['link_handling'] ?? 'append'; // 'none', 'append', or 'comment'
        
        // Debug logging to verify parameter flow
        do_action('dm_log', 'debug', 'Facebook Handler: Parameter extraction complete', [
            'source_url' => $source_url,
            'image_url' => $image_url,
            'include_images' => $include_images,
            'image_url_empty' => empty($image_url),
            'image_url_valid' => $image_url ? filter_var($image_url, FILTER_VALIDATE_URL) : false,
            'upload_condition' => $include_images && !empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL),
            'facebook_config_keys' => array_keys($facebook_config)
        ]);

        // Get authenticated credentials
        $page_id = $this->auth->get_page_id();
        $page_access_token = $this->auth->get_page_access_token();

        // Validate auto-discovered page ID
        if (empty($page_id)) {
            return [
                'success' => false,
                'error' => 'Facebook page not found. Please re-authenticate your Facebook account.',
                'tool_name' => 'facebook_publish'
            ];
        }

        if (empty($page_access_token)) {
            return [
                'success' => false,
                'error' => 'Facebook authentication failed - no access token',
                'tool_name' => 'facebook_publish'
            ];
        }

        try {
            // Format post content
            $post_text = $title ? $title . "\n\n" . $content : $content;
            
            // Handle source URL based on consolidated link_handling setting
            if ($link_handling === 'append' && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL)) {
                $post_text .= "\n\n" . $source_url;
            }

            // Prepare Facebook API request
            $post_data = [
                'message' => $post_text,
                'access_token' => $page_access_token
            ];

            // Handle image upload if provided and enabled
            // Handle image upload if provided and enabled
            if ($include_images && !empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                $image_result = $this->upload_image_to_facebook($image_url, $page_access_token, $page_id);
                if ($image_result && isset($image_result['id'])) {
                    // Use the correct parameter name for Facebook API
                    $post_data['attached_media'] = json_encode([['media_fbid' => $image_result['id']]]);
                }
            }

            // Make API request to Facebook
            $api_url = "https://graph.facebook.com/" . self::FACEBOOK_API_VERSION . "/{$page_id}/feed";
            $response = wp_remote_post($api_url, [
                'body' => $post_data,
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $error_msg = 'Facebook API request failed: ' . $response->get_error_message();
                do_action('dm_log', 'error', $error_msg);
                
                return [
                    'success' => false,
                    'error' => $error_msg,
                    'tool_name' => 'facebook_publish'
                ];
            }

            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            if (isset($response_data['id'])) {
                $post_id = $response_data['id'];
                $post_url = "https://www.facebook.com/{$page_id}/posts/{$post_id}";
                
                do_action('dm_log', 'debug', 'Facebook Tool: Post created successfully', [
                    'post_id' => $post_id,
                    'post_url' => $post_url
                ]);

                // Handle URL as comment if configured
                $comment_result = null;
                if ($link_handling === 'comment' && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL)) {
                    // Check if we have comment permissions before attempting to post
                    if ($this->auth && $this->auth->has_comment_permission()) {
                        do_action('dm_log', 'debug', 'Facebook Tool: Attempting to post link as comment', [
                            'post_id' => $post_id,
                            'source_url' => $source_url,
                            'link_handling' => $link_handling
                        ]);
                        $comment_result = $this->post_comment($post_id, $source_url, $page_access_token);
                    } else {
                        $comment_result = [
                            'success' => false,
                            'error' => 'Facebook comment skipped: Missing pages_manage_engagement permission. Please re-authenticate your Facebook account to enable comment functionality.',
                            'requires_reauth' => true
                        ];
                        
                        do_action('dm_log', 'error', 'Facebook Tool: Comment skipped due to missing permissions', [
                            'post_id' => $post_id,
                            'source_url' => $source_url,
                            'link_handling' => $link_handling,
                            'required_permission' => 'pages_manage_engagement',
                            'requires_reauth' => true
                        ]);
                    }
                } else {
                    do_action('dm_log', 'debug', 'Facebook Tool: Comment conditions not met', [
                        'link_handling' => $link_handling,
                        'has_source_url' => !empty($source_url),
                        'source_url_valid' => !empty($source_url) ? filter_var($source_url, FILTER_VALIDATE_URL) : false,
                        'source_url' => $source_url ?? 'null'
                    ]);
                }

                $result_data = [
                    'post_id' => $post_id,
                    'post_url' => $post_url,
                    'content' => $post_text
                ];

                // Add comment information if a comment was posted
                if ($comment_result && $comment_result['success']) {
                    $result_data['comment_id'] = $comment_result['comment_id'];
                    $result_data['comment_url'] = $comment_result['comment_url'];
                } elseif ($comment_result && !$comment_result['success']) {
                    // Comment failed but main post succeeded - log error but don't fail the whole operation
                    do_action('dm_log', 'error', 'Facebook Tool: Main post created but comment failed', [
                        'post_id' => $post_id,
                        'post_url' => $post_url,
                        'comment_error' => $comment_result['error'],
                        'link_handling' => 'comment',
                        'source_url' => $source_url
                    ]);
                }

                return [
                    'success' => true,
                    'data' => $result_data,
                    'tool_name' => 'facebook_publish'
                ];
            } else {
                $error_msg = 'Facebook API error: ' . ($response_data['error']['message'] ?? 'Unknown error');
                do_action('dm_log', 'error', $error_msg, [
                    'response_data' => $response_data
                ]);

                return [
                    'success' => false,
                    'error' => $error_msg,
                    'tool_name' => 'facebook_publish'
                ];
            }
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Facebook Tool: Exception during posting', [
                'exception' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tool_name' => 'facebook_publish'
            ];
        }
    }

    /**
     * Post a comment on a Facebook post.
     *
     * @param string $post_id Facebook post ID to comment on.
     * @param string $source_url URL to post in the comment.
     * @param string $access_token Facebook access token.
     * @return array Result of comment posting operation.
     */
    private function post_comment(string $post_id, string $source_url, string $access_token): array {
        do_action('dm_log', 'debug', 'Facebook Tool: Posting URL as comment', [
            'post_id' => $post_id,
            'source_url' => $source_url
        ]);

        try {
            // Post comment using Facebook Graph API
            $api_url = "https://graph.facebook.com/" . self::FACEBOOK_API_VERSION . "/{$post_id}/comments";
            $comment_data = [
                'message' => $source_url,
                'access_token' => $access_token
            ];

            $response = wp_remote_post($api_url, [
                'body' => $comment_data,
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $error_msg = 'Facebook comment API request failed: ' . $response->get_error_message();
                do_action('dm_log', 'warning', $error_msg, [
                    'post_id' => $post_id
                ]);
                
                return [
                    'success' => false,
                    'error' => $error_msg
                ];
            }

            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            if (isset($response_data['id'])) {
                $comment_id = $response_data['id'];
                $comment_url = "https://www.facebook.com/{$post_id}/?comment_id={$comment_id}";
                
                do_action('dm_log', 'debug', 'Facebook Tool: Comment posted successfully', [
                    'comment_id' => $comment_id,
                    'comment_url' => $comment_url,
                    'post_id' => $post_id
                ]);

                return [
                    'success' => true,
                    'comment_id' => $comment_id,
                    'comment_url' => $comment_url
                ];
            } else {
                $error_msg = 'Facebook comment API error: ' . ($response_data['error']['message'] ?? 'Unknown error');
                $error_code = $response_data['error']['code'] ?? null;
                
                // Check if this is a permissions error
                if ($error_code === 200 && strpos($error_msg, 'sufficient permissions') !== false) {
                    $error_msg = 'Facebook comment failed: Missing pages_manage_engagement permission. Please re-authenticate your Facebook account to enable comment functionality.';
                    
                    do_action('dm_log', 'warning', 'Facebook Tool: Comment failed due to missing permissions', [
                        'error_code' => $error_code,
                        'error_message' => $response_data['error']['message'] ?? 'No message',
                        'post_id' => $post_id,
                        'requires_reauth' => true
                    ]);
                } else {
                    do_action('dm_log', 'warning', 'Facebook Tool: Comment posting failed', [
                        'response_data' => $response_data,
                        'post_id' => $post_id,
                        'error_code' => $error_code
                    ]);
                }

                return [
                    'success' => false,
                    'error' => $error_msg,
                    'requires_reauth' => $error_code === 200
                ];
            }
        } catch (\Exception $e) {
            do_action('dm_log', 'warning', 'Facebook Tool: Exception during comment posting', [
                'exception' => $e->getMessage(),
                'post_id' => $post_id
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload image to Facebook and return photo object.
     *
     * @param string $image_url Image URL to upload.
     * @param string $access_token Facebook access token.
     * @param string $page_id Facebook page ID.
     * @return array|null Photo object or null on failure.
     */
    private function upload_image_to_facebook(string $image_url, string $access_token, string $page_id): ?array {
        do_action('dm_log', 'debug', 'Attempting to upload image to Facebook.', ['image_url' => $image_url]);
        
        if (!function_exists('download_url')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $temp_image_path = download_url($image_url);
        if (is_wp_error($temp_image_path)) {
            do_action('dm_log', 'warning', 'Failed to download image for Facebook upload.', [
                'url' => $image_url, 
                'error' => $temp_image_path->get_error_message()
            ]);
            return null;
        }

        try {
            // Try Facebook's URL-based upload first (simpler and often more reliable)
            $api_url = "https://graph.facebook.com/" . self::FACEBOOK_API_VERSION . "/{$page_id}/photos";
            $photo_data = [
                'url' => $image_url,
                'published' => 'false', // Upload but don't publish yet
                'access_token' => $access_token
            ];

            $response = wp_remote_post($api_url, [
                'body' => $photo_data,
                'timeout' => 60
            ]);

            if (is_wp_error($response)) {
                do_action('dm_log', 'error', 'Facebook image upload failed: ' . $response->get_error_message());
                return null;
            }

            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            if (isset($response_data['id'])) {
                do_action('dm_log', 'debug', 'Successfully uploaded image to Facebook.', ['photo_id' => $response_data['id']]);
                return $response_data;
            } else {
                do_action('dm_log', 'error', 'Facebook image upload failed.', ['response' => $response_data]);
                return null;
            }
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Facebook image upload exception: ' . $e->getMessage());
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
        return __('Facebook', 'data-machine');
    }

    /**
     * Sanitizes the settings specific to the Facebook publish handler.
     * No defaults allowed - all settings must be explicitly provided.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        $sanitized['include_source'] = (bool) ($raw_settings['include_source'] ?? false);
        $sanitized['enable_images'] = (bool) ($raw_settings['enable_images'] ?? false);
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
                do_action('dm_log', 'warning', 'Facebook: Skipping problematic image URL pattern', [
                    'url' => $image_url, 
                    'pattern' => $pattern
                ]);
                return false;
            }
        }

        // Test accessibility with HEAD request
        $response = wp_remote_head($image_url, [
            'user-agent' => 'Mozilla/5.0 (compatible; DataMachine/1.0; +https://github.com/chubes/data-machine)'
        ]);

        if (is_wp_error($response)) {
            do_action('dm_log', 'warning', 'Facebook: Image URL not accessible', [
                'url' => $image_url, 
                'error' => $response->get_error_message()
            ]);
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        // Check for successful response and image content type
        if ($http_code >= 200 && $http_code < 300 && strpos($content_type, 'image/') === 0) {
            return true;
        }

        do_action('dm_log', 'warning', 'Facebook: Image URL validation failed', [
            'url' => $image_url, 
            'http_code' => $http_code, 
            'content_type' => $content_type
        ]);
        return false;
    }
}
