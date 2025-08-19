<?php
/**
 * Modular Facebook publish handler.
 *
 * Posts content to a specified Facebook Page using the self-contained
 * FacebookAuth class for authentication. This modular approach separates
 * concerns between main handler logic and authentication functionality.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/publish/facebook
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Publish\Facebook;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Facebook {

    const FACEBOOK_API_VERSION = 'v22.0';

    /**
     * @var FacebookAuth Authentication handler instance
     */
    private $auth;

    /**
     * Constructor - filter-based auth access following pure discovery architectural standards
     */
    public function __construct() {
        // Use filter-based auth access following pure discovery architectural standards
        $all_auth = apply_filters('dm_auth_providers', []);
        $this->auth = $all_auth['facebook'] ?? null;
        
        if ($this->auth === null) {
            do_action('dm_log', 'error', 'Facebook Handler: Authentication service not available', [
                'missing_service' => 'facebook',
                'available_providers' => array_keys($all_auth)
            ]);
            // Handler will return error in handle_tool_call() when auth is null
        }
    }

    /**
     * Get Facebook auth handler - internal implementation.
     * 
     * @return FacebookAuth
     */
    private function get_auth() {
        return $this->auth;
    }

    /**
     * Handle AI tool call for Facebook publishing.
     *
     * @param array $parameters Structured parameters from AI tool call.
     * @param array $tool_def Tool definition including handler configuration.
     * @return array Tool execution result.
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        do_action('dm_log', 'debug', 'Facebook Tool: Handling tool call', [
            'parameters' => $parameters,
            'parameter_keys' => array_keys($parameters),
            'has_handler_config' => !empty($tool_def['handler_config']),
            'handler_config_keys' => array_keys($tool_def['handler_config'] ?? [])
        ]);

        // Validate required parameters
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

        // Get handler configuration from tool definition
        $handler_config = $tool_def['handler_config'] ?? [];
        
        do_action('dm_log', 'debug', 'Facebook Tool: Using handler configuration', [
            'facebook_target_id' => $handler_config['facebook_target_id'] ?? '',
            'include_images' => $handler_config['include_images'] ?? true,
            'include_videos' => $handler_config['include_videos'] ?? true,
            'link_handling' => $handler_config['link_handling'] ?? 'append'
        ]);

        // Extract parameters
        $title = $parameters['title'] ?? '';
        $content = $parameters['content'] ?? '';
        $source_url = $parameters['source_url'] ?? null;
        $image_url = $parameters['image_url'] ?? null;
        
        // Get config from handler settings
        $target_id = trim($handler_config['facebook_target_id'] ?? '');
        $include_images = $handler_config['include_images'] ?? true;
        $include_videos = $handler_config['include_videos'] ?? true;
        $link_handling = $handler_config['link_handling'] ?? 'append';

        // Validate target ID
        if (empty($target_id)) {
            return [
                'success' => false,
                'error' => 'Facebook target ID is required',
                'tool_name' => 'facebook_publish'
            ];
        }

        // Get authenticated credentials
        $page_id = $this->auth->get_page_id();
        $page_access_token = $this->auth->get_page_access_token();

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
            
            // Handle links based on configuration (exclude comment mode - handled after post creation)
            if (!empty($source_url) && $link_handling !== 'none' && $link_handling !== 'comment') {
                if ($link_handling === 'append') {
                    $post_text .= "\n\n" . $source_url;
                } elseif ($link_handling === 'replace') {
                    $post_text = $source_url;
                }
            }

            // Prepare Facebook API request
            $post_data = [
                'message' => $post_text,
                'access_token' => $page_access_token
            ];

            // Handle image upload if provided and enabled
            if ($include_images && !empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                $image_result = $this->upload_image_to_facebook($image_url, $page_access_token, $target_id);
                if ($image_result && isset($image_result['id'])) {
                    $post_data['object_attachment'] = $image_result['id'];
                }
            }

            // Make API request to Facebook
            $api_url = "https://graph.facebook.com/" . self::FACEBOOK_API_VERSION . "/{$target_id}/feed";
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
                $post_url = "https://www.facebook.com/{$target_id}/posts/{$post_id}";
                
                do_action('dm_log', 'debug', 'Facebook Tool: Post created successfully', [
                    'post_id' => $post_id,
                    'post_url' => $post_url
                ]);

                // Handle URL as comment if configured
                $comment_result = null;
                if ($link_handling === 'comment' && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL)) {
                    $comment_result = $this->post_comment($post_id, $source_url, $page_access_token);
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
                    // Comment failed but main post succeeded - log but don't fail the whole operation
                    do_action('dm_log', 'warning', 'Facebook Tool: Main post created but comment failed', [
                        'post_id' => $post_id,
                        'comment_error' => $comment_result['error']
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
                do_action('dm_log', 'warning', 'Facebook Tool: Comment posting failed', [
                    'response_data' => $response_data,
                    'post_id' => $post_id
                ]);

                return [
                    'success' => false,
                    'error' => $error_msg
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
     * @param string $target_id Facebook target ID.
     * @return array|null Photo object or null on failure.
     */
    private function upload_image_to_facebook(string $image_url, string $access_token, string $target_id): ?array {
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
            // Upload to Facebook
            $api_url = "https://graph.facebook.com/" . self::FACEBOOK_API_VERSION . "/{$target_id}/photos";
            $photo_data = [
                'source' => new \CURLFile($temp_image_path),
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
        
        // facebook_target_id - provide default if missing
        $sanitized['facebook_target_id'] = sanitize_text_field($raw_settings['facebook_target_id'] ?? 'me');
        
        // include_images - provide default if missing
        $sanitized['include_images'] = isset($raw_settings['include_images']) ? (bool) $raw_settings['include_images'] : false;
        
        // include_videos - provide default if missing
        $sanitized['include_videos'] = isset($raw_settings['include_videos']) ? (bool) $raw_settings['include_videos'] : false;
        
        // link_handling - provide default and validate
        $valid_link_options = ['append', 'replace', 'comment', 'none'];
        $link_handling = $raw_settings['link_handling'] ?? 'append';
        if (!in_array($link_handling, $valid_link_options)) {
            do_action('dm_log', 'error', 'Facebook Handler: Invalid link_handling parameter in sanitize method', [
                'provided_value' => $link_handling,
                'valid_options' => $valid_link_options
            ]);
            $link_handling = 'append'; // Fall back to default
        }
        $sanitized['link_handling'] = $link_handling;
        
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


