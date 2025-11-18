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

use DataMachine\Core\Steps\Publish\Handlers\PublishHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Facebook extends PublishHandler {

    const FACEBOOK_API_VERSION = 'v22.0';

    /**
     * @var FacebookAuth Authentication handler instance
     */
    private $auth;

    public function __construct() {
        parent::__construct('facebook');
        $all_auth = apply_filters('datamachine_auth_providers', []);
        $this->auth = $all_auth['facebook'] ?? null;

        if ($this->auth === null) {
            $this->log('error', 'Facebook Handler: Authentication service not available', [
                'missing_service' => 'facebook',
                'available_providers' => array_keys($all_auth)
            ]);
        }
    }

    private function get_auth() {
        return $this->auth;
    }

    protected function executePublish(array $parameters, array $handler_config): array {
        if (empty($parameters['content'])) {
            return $this->errorResponse(
                'Facebook tool call missing required content parameter',
                [
                    'provided_parameters' => array_keys($parameters),
                    'required_parameters' => ['content']
                ]
            );
        }

        // Extract Facebook-specific configuration (it's nested under 'facebook' key)
        $facebook_config = $handler_config['facebook'] ?? $handler_config;

        $job_id = $parameters['job_id'] ?? null;
        $engine_data = $this->getEngineData($job_id);

        // Extract parameters from flat structure
        $title = $parameters['title'] ?? '';
        $content = $parameters['content'] ?? '';
        $source_url = $engine_data['source_url'] ?? null;
        $image_file_path = $engine_data['image_file_path'] ?? null;
        
        // Get config from Facebook-specific settings
        $include_images = $facebook_config['include_images'] ?? false;
        $link_handling = $facebook_config['link_handling'] ?? 'append'; // 'none', 'append', or 'comment'
        
        // Debug logging to verify parameter flow
        $this->log('debug', 'Facebook Handler: Parameter extraction complete', [
            'source_url' => $source_url,
            'include_images' => $include_images,
            'facebook_config_keys' => array_keys($facebook_config)
        ]);

        // Get authenticated credentials
        $page_id = $this->auth->get_page_id();
        $page_access_token = $this->auth->get_page_access_token();

        // Validate auto-discovered page ID
        if (empty($page_id)) {
            return $this->errorResponse('Facebook page not found. Please re-authenticate your Facebook account.');
        }

        if (empty($page_access_token)) {
            return $this->errorResponse('Facebook authentication failed - no access token');
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
            if ($include_images && !empty($image_file_path)) {
                $validation = $this->validateImage($image_file_path);

                if (!$validation['valid']) {
                    return $this->errorResponse(
                        implode(', ', $validation['errors']),
                        ['file_path' => $image_file_path, 'errors' => $validation['errors']]
                    );
                }

                $image_result = $this->upload_image_file_to_facebook($image_file_path, $page_access_token, $page_id);
                if (!$image_result || !isset($image_result['id'])) {
                    return $this->errorResponse('Failed to upload image');
                }

                // Use the correct parameter name for Facebook API
                $post_data['attached_media'] = json_encode([['media_fbid' => $image_result['id']]]);
            }

            // Make API request to Facebook
            $api_url = "https://graph.facebook.com/" . self::FACEBOOK_API_VERSION . "/{$page_id}/feed";
            $response = wp_remote_post($api_url, [
                'body' => $post_data,
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                return $this->errorResponse('Facebook API request failed: ' . $response->get_error_message());
            }

            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            if (isset($response_data['id'])) {
                $post_id = $response_data['id'];
                $post_url = "https://www.facebook.com/{$page_id}/posts/{$post_id}";

                $this->log('debug', 'Facebook Tool: Post created successfully', [
                    'post_id' => $post_id,
                    'post_url' => $post_url
                ]);

                // Handle URL as comment if configured
                $comment_result = null;
                if ($link_handling === 'comment' && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL)) {
                    // Check if we have comment permissions before attempting to post
                    if ($this->auth && $this->auth->has_comment_permission()) {
                        $this->log('debug', 'Facebook Tool: Attempting to post link as comment', [
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

                        $this->log('error', 'Facebook Tool: Comment skipped due to missing permissions', [
                            'post_id' => $post_id,
                            'source_url' => $source_url,
                            'link_handling' => $link_handling,
                            'required_permission' => 'pages_manage_engagement',
                            'requires_reauth' => true
                        ]);
                    }
                } else {
                    $this->log('debug', 'Facebook Tool: Comment conditions not met', [
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
                    $this->log('error', 'Facebook Tool: Main post created but comment failed', [
                        'post_id' => $post_id,
                        'post_url' => $post_url,
                        'comment_error' => $comment_result['error'],
                        'link_handling' => 'comment',
                        'source_url' => $source_url
                    ]);
                }

                return $this->successResponse($result_data);
            } else {
                return $this->errorResponse(
                    'Facebook API error: ' . ($response_data['error']['message'] ?? 'Unknown error'),
                    ['response_data' => $response_data]
                );
            }
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Requires pages_manage_engagement permission for comment posting.
     */
    private function post_comment(string $post_id, string $source_url, string $access_token): array {
        $this->log('debug', 'Facebook Tool: Posting URL as comment', [
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
                $this->log('warning', $error_msg, ['post_id' => $post_id]);

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

                $this->log('debug', 'Facebook Tool: Comment posted successfully', [
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

                    $this->log('warning', 'Facebook Tool: Comment failed due to missing permissions', [
                        'error_code' => $error_code,
                        'error_message' => $response_data['error']['message'] ?? 'No message',
                        'post_id' => $post_id,
                        'requires_reauth' => true
                    ]);
                } else {
                    $this->log('warning', 'Facebook Tool: Comment posting failed', [
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
            $this->log('warning', 'Facebook Tool: Exception during comment posting', [
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
     * Uploads unpublished photo to Facebook, returns photo object for attachment to post.
     */


    public static function get_label(): string {
        return __('Facebook', 'datamachine');
    }

    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        $sanitized['include_source'] = (bool) ($raw_settings['include_source'] ?? false);
        $sanitized['enable_images'] = (bool) ($raw_settings['enable_images'] ?? false);
        return $sanitized;
    }

    /**
     * Validates image accessibility via HEAD request, skips problematic Reddit URLs.
     */
    private function is_image_accessible(string $image_url): bool {
        // Skip certain problematic domains/patterns
        $problematic_patterns = [
            'preview.redd.it', // Reddit preview URLs often have access restrictions
            'i.redd.it'        // Reddit image URLs may have restrictions
        ];
        
        foreach ($problematic_patterns as $pattern) {
            if (strpos($image_url, $pattern) !== false) {
                $this->log('warning', 'Facebook: Skipping problematic image URL pattern', [
                    'url' => $image_url,
                    'pattern' => $pattern
                ]);
                return false;
            }
        }

        // Test accessibility with HEAD request
        $response = wp_remote_head($image_url, [
            'user-agent' => 'Mozilla/5.0 (compatible; DataMachine/1.0; +https://github.com/chubes/datamachine)'
        ]);

        if (is_wp_error($response)) {
            $this->log('warning', 'Facebook: Image URL not accessible', [
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

        $this->log('warning', 'Facebook: Image URL validation failed', [
            'url' => $image_url,
            'http_code' => $http_code,
            'content_type' => $content_type
        ]);
        return false;
    }
}
