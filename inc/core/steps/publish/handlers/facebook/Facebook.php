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
            throw new \RuntimeException('Facebook authentication service not available. Required service missing from dm_auth_providers filter.');
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
     * Handles posting the AI output to Facebook.
     *
     * @param object $data Universal DataPacket JSON object with all content and metadata.
     * @return array Result array on success or failure.
     */
    public function handle_publish($data): array {
        // Access structured content directly from DataPacket (no parsing needed)
        $title = $data->content->title ?? '';
        $content = $data->content->body ?? '';
        
        // Get publish config from DataPacket (set by PublishStep)
        $publish_config = $data->publish_config ?? [];
        
        // Extract metadata from DataPacket
        $input_metadata = [
            'source_url' => $data->metadata->source_url ?? null,
            'image_source_url' => !empty($data->attachments->images) ? $data->attachments->images[0]->url : null
        ];
        
        do_action('dm_log', 'debug', 'Starting Facebook output handling.');

        // 1. Get config - publish_config is the handler_settings directly
        $target_id = trim($publish_config['facebook_target_id'] ?? '');
        $include_images = (bool) ($publish_config['include_images'] ?? true);
        $include_videos = (bool) ($publish_config['include_videos'] ?? true);
        $link_handling = $publish_config['link_handling'] ?? 'auto';
        
        if (empty($target_id)) {
            return [
                'success' => false,
                'error' => __('Facebook target_id configuration is required.', 'data-machine')
            ];
        }
        
        if (empty($target_id)) {
            return [
                'success' => false,
                'error' => __('Facebook target_id cannot be empty.', 'data-machine')
            ];
        }
        
        if (!in_array($link_handling, ['append', 'replace', 'none'])) {
            return [
                'success' => false,
                'error' => __('Invalid Facebook link_handling configuration. Must be "append", "replace", or "none".', 'data-machine')
            ];
        }

        // 2. Get authenticated Page credentials using internal FacebookAuth
        $page_id = $this->auth->get_page_id();
        $page_access_token = $this->auth->get_page_access_token();

        if (empty($page_id) || empty($page_access_token)) {
            do_action('dm_log', 'error', 'Facebook Output: Failed to get Page ID or Page Access Token.', [
                'page_id_found' => !empty($page_id), 
                'token_found' => !empty($page_access_token)
            ]);
            return [
                'success' => false,
                'error' => __('Failed to retrieve Facebook Page credentials. Please check authentication on the API Keys page.', 'data-machine')
            ];
        }
        
        do_action('dm_log', 'debug', 'Facebook Output: Retrieved Page credentials.', ['page_id' => $page_id]);

        // 4. Validate content from DataPacket
        $source_link = $input_metadata['source_url'] ?? null;
        $post_content = $title ? $title . ": " . $content : $content;

        if (empty($title) && empty($content)) {
            do_action('dm_log', 'warning', 'Facebook Output: DataPacket content is empty.');
            return [
                'success' => false,
                'error' => __('Cannot post empty content to Facebook.', 'data-machine')
            ];
        }

        // 5. Determine post type and prepare API parameters
        $image_url = $input_metadata['image_source_url'] ?? null;
        $video_url = null;
        
        // Extract video URL if video handling is enabled
        if ($include_videos && !empty($input_metadata['video_source_url'])) {
            $video_url = esc_url($input_metadata['video_source_url']);
        }

        // Note: Using dm_send_request action hook for all HTTP operations

        // Determine post type and prepare API parameters - no silent fallbacks
        if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
            if (!$this->is_image_accessible($image_url)) {
                return [
                    'success' => false,
                    'error' => sprintf(__('Facebook image URL not accessible: %s', 'data-machine'), $image_url)
                ];
            }
            
            if (!$include_images) {
                return [
                    'success' => false,
                    'error' => __('Facebook handler configured to exclude images but image URL provided in content. Enable images or remove image from content.', 'data-machine')
                ];
            }
            
            // Image Post
            $endpoint = "/{$page_id}/photos";
            $api_params = [
                'caption' => $post_content,
                'url' => $image_url
            ];
        } elseif (!empty($video_url) && filter_var($video_url, FILTER_VALIDATE_URL)) {
            if (!$include_videos) {
                return [
                    'success' => false,
                    'error' => __('Facebook handler configured to exclude videos but video URL provided in content. Enable videos or remove video from content.', 'data-machine')
                ];
            }
            
            // Video Post - append video link to content
            do_action('dm_log', 'debug', 'Facebook API: Including video link in text post.', ['video_url' => $video_url]);
            $post_content .= "\n\nVideo: " . $video_url;
            $endpoint = "/{$page_id}/feed";
            $api_params = ['message' => $post_content];
        } else {
            // Text-Only Post
            $endpoint = "/{$page_id}/feed";
            $api_params = ['message' => $post_content];
        }

        // Add the Page Access Token to parameters
        $api_params['access_token'] = $page_access_token;

        // 6. Post to Facebook using HTTP service
        try {
            $graph_api_url = 'https://graph.facebook.com/' . self::FACEBOOK_API_VERSION;
            $url = $graph_api_url . $endpoint;

            // Use dm_send_request action hook for Facebook API call
            $args = ['body' => $api_params];
            $result = null;
            do_action('dm_send_request', 'POST', $url, $args, 'Facebook API', $result);
            
            if (!$result['success']) {
                do_action('dm_log', 'error', 'Facebook API Error: HTTP request failed.', [
                    'error' => $result['error'], 
                ]);
                return [
                    'success' => false,
                    'error' => $result['error']
                ];
            }

            $body = $result['data']['body'];
            $http_code = $result['data']['status_code'];

            // Parse JSON response with error handling
            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_message = sprintf(__('Invalid JSON from Facebook API: %s', 'data-machine'), json_last_error_msg());
                return [
                    'success' => false,
                    'error' => $error_message
                ];
            }

            // Check for API errors returned in the body
            if (isset($data['error'])) {
                $error_message = $data['error']['message'] ?? 'Unknown Facebook API error.';
                $error_type = $data['error']['type'] ?? 'APIError';
                $error_code_fb = $data['error']['code'] ?? 'UnknownCode';
                do_action('dm_log', 'error', 'Facebook API Error: Received error response.', [
                    'http_code' => $http_code, 
                    'fb_error' => $data['error'], 
                ]);
                return [
                    'success' => false,
                    'error' => $error_message
                ];
            }

            // Check for successful HTTP status code and presence of post ID
            $post_id = $data['id'] ?? ($data['post_id'] ?? null);

            if ($http_code >= 200 && $http_code < 300 && !empty($post_id)) {
                // Construct the URL to the post
                $output_url = "https://www.facebook.com/" . $post_id;

                do_action('dm_log', 'debug', 'Facebook post published successfully.', [
                    'post_id' => $post_id, 
                    'output_url' => $output_url
                ]);

                // Post source link as comment - fail if configured to do so
                if (!empty($source_link) && filter_var($source_link, FILTER_VALIDATE_URL)) {
                    $comment_message = "Source: " . $source_link;
                    $comment_endpoint = "/{$post_id}/comments";
                    $comment_api_params = [
                        'message' => $comment_message,
                        'access_token' => $page_access_token,
                    ];

                    $comment_url = $graph_api_url . $comment_endpoint;
                    $comment_args = ['body' => $comment_api_params];
                    $comment_result = null;
                    do_action('dm_send_request', 'POST', $comment_url, $comment_args, 'Facebook Comments API', $comment_result);

                    if (!$comment_result['success']) {
                        do_action('dm_log', 'error', 'Facebook API: Failed to post source link comment.', [
                            'post_id' => $post_id,
                            'error' => $comment_result['error'],
                        ]);
                        return [
                            'success' => false,
                            'error' => sprintf(__('Facebook post successful but source comment failed: %s', 'data-machine'), $comment_result['error'])
                        ];
                    }
                    
                    $comment_body = $comment_result['data']['body'];
                    $comment_data = json_decode($comment_body, true);
                    $comment_http_code = $comment_result['data']['status_code'];

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        do_action('dm_log', 'error', 'Facebook API: Failed to parse comment response.', [
                            'post_id' => $post_id,
                            'error' => json_last_error_msg(),
                        ]);
                        return [
                            'success' => false,
                            'error' => sprintf(__('Facebook post successful but source comment parsing failed: %s', 'data-machine'), json_last_error_msg())
                        ];
                    }
                    
                    if (isset($comment_data['error'])) {
                        $error_message = $comment_data['error']['message'] ?? 'Unknown comment API error';
                        do_action('dm_log', 'error', 'Facebook API: Source link comment API error.', [
                            'post_id' => $post_id,
                            'http_code' => $comment_http_code,
                            'fb_error' => $comment_data['error'],
                        ]);
                        return [
                            'success' => false,
                            'error' => sprintf(__('Facebook post successful but source comment failed: %s', 'data-machine'), $error_message)
                        ];
                    }
                    
                    if ($comment_http_code < 200 || $comment_http_code >= 300 || !isset($comment_data['id'])) {
                        do_action('dm_log', 'error', 'Facebook API: Unexpected comment response.', [
                            'post_id' => $post_id,
                            'http_code' => $comment_http_code,
                            'response' => $comment_data
                        ]);
                        return [
                            'success' => false,
                            'error' => sprintf(__('Facebook post successful but source comment had unexpected response (HTTP %d)', 'data-machine'), $comment_http_code)
                        ];
                    }
                    
                    do_action('dm_log', 'debug', 'Facebook API: Successfully posted source link as comment.', [
                        'post_id' => $post_id, 
                        'comment_id' => $comment_data['id']
                    ]);
                }

                return [
                    'success' => true,
                    'status' => 'success',
                    'post_id' => $post_id,
                    'output_url' => $output_url,
                    /* translators: %s: Facebook post ID */
                    'message' => sprintf(__('Successfully posted to Facebook: %s', 'data-machine'), $post_id),
                    'raw_response' => $data
                ];
            } else {
                // Handle cases where response is successful but doesn't contain expected ID
                do_action('dm_log', 'error', 'Facebook API Error: Unexpected response format or missing post ID.', [
                    'http_code' => $http_code, 
                    'response_body' => $body, 
                ]);
                return [
                    'success' => false,
                    'error' => __('Unexpected response format or missing Post ID from Facebook.', 'data-machine')
                ];
            }

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Facebook Output Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
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
        
        // facebook_target_id is required - no defaults allowed
        if (!isset($raw_settings['facebook_target_id'])) {
            throw new Exception(esc_html__('Facebook target_id setting is required.', 'data-machine'));
        }
        $sanitized['facebook_target_id'] = sanitize_text_field($raw_settings['facebook_target_id']);
        
        // include_images is required - no defaults allowed
        if (!isset($raw_settings['include_images'])) {
            throw new Exception(esc_html__('Facebook include_images setting is required.', 'data-machine'));
        }
        $sanitized['include_images'] = (bool) $raw_settings['include_images'];
        
        // include_videos is required - no defaults allowed  
        if (!isset($raw_settings['include_videos'])) {
            throw new Exception(esc_html__('Facebook include_videos setting is required.', 'data-machine'));
        }
        $sanitized['include_videos'] = (bool) $raw_settings['include_videos'];
        
        // link_handling is required - no defaults allowed
        if (!isset($raw_settings['link_handling'])) {
            throw new Exception(esc_html__('Facebook link_handling setting is required.', 'data-machine'));
        }
        $link_handling = $raw_settings['link_handling'];
        if (!in_array($link_handling, ['append', 'replace', 'none'])) {
            throw new Exception(esc_html__('Invalid Facebook link_handling parameter. Must be "append", "replace", or "none".', 'data-machine'));
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


