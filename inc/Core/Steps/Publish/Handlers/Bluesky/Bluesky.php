<?php
/**
 * Bluesky publisher with AT Protocol authentication
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Bluesky;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bluesky {

    private $auth;

    public function __construct() {
        $all_auth = apply_filters('datamachine_auth_providers', []);
        $this->auth = $all_auth['bluesky'] ?? null;

        if ($this->auth === null) {
            do_action('datamachine_log', 'error', 'Bluesky Handler: Authentication service not available', [
                'missing_service' => 'bluesky',
                'available_providers' => array_keys($all_auth)
            ]);
        }
    }

    private function get_auth() {
        return $this->auth;
    }

    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        do_action('datamachine_log', 'debug', 'Bluesky Tool: Handling tool call', [
            'parameters' => $parameters,
            'parameter_keys' => array_keys($parameters),
            'has_handler_config' => !empty($tool_def['handler_config']),
            'handler_config_keys' => array_keys($tool_def['handler_config'] ?? [])
        ]);

        if (empty($parameters['content'])) {
            $error_msg = 'Bluesky tool call missing required content parameter';
            do_action('datamachine_log', 'error', $error_msg, [
                'provided_parameters' => array_keys($parameters),
                'required_parameters' => ['content']
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'bluesky_publish'
            ];
        }

        $handler_config = $parameters['handler_config'] ?? [];
        $bluesky_config = $handler_config['bluesky'] ?? $handler_config;
        
        do_action('datamachine_log', 'debug', 'Bluesky Tool: Using handler configuration', [
            'include_images' => $bluesky_config['include_images'] ?? true,
            'link_handling' => $bluesky_config['link_handling'] ?? 'append'
        ]);

        $job_id = $parameters['job_id'] ?? null;
        $engine_data = apply_filters('datamachine_engine_data', [], $job_id);

        $title = $parameters['title'] ?? '';
        $content = $parameters['content'] ?? '';
        $source_url = $engine_data['source_url'] ?? null;
        $image_url = $engine_data['image_url'] ?? null;
        
        $include_images = $bluesky_config['include_images'] ?? true;
        $link_handling = $bluesky_config['link_handling'] ?? 'append';

        $session = $this->auth->get_session();
        if (is_wp_error($session)) {
            $error_msg = 'Bluesky authentication failed: ' . $session->get_error_message();
            do_action('datamachine_log', 'error', $error_msg, [
                'error_code' => $session->get_error_code()
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'bluesky_publish'
            ];
        }

        $access_token = $session['accessJwt'] ?? null;
        $did = $session['did'] ?? null;
        $pds_url = $session['pds_url'] ?? null;

        if (empty($access_token) || empty($did) || empty($pds_url)) {
            return [
                'success' => false,
                'error' => 'Bluesky session data incomplete',
                'tool_name' => 'bluesky_publish'
            ];
        }

        $post_text = $title ? $title . ": " . $content : $content;
        $ellipsis = '…';
        $ellipsis_len = mb_strlen($ellipsis, 'UTF-8');
        $link = ($link_handling === 'append' && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL)) ? "\n\n" . $source_url : '';
        $link_length = $link ? mb_strlen($link, 'UTF-8') : 0; // Bluesky counts full URL length
        $available_chars = 300 - $link_length;
        
        if ($available_chars < $ellipsis_len) {
            $post_text = mb_substr($link, 0, 300);
        } else {
            if (mb_strlen($post_text, 'UTF-8') > $available_chars) {
                $post_text = mb_substr($post_text, 0, $available_chars - $ellipsis_len) . $ellipsis;
            }
            $post_text .= $link;
        }
        $post_text = trim($post_text);

        if (empty($post_text)) {
            return [
                'success' => false,
                'error' => 'Formatted post content is empty',
                'tool_name' => 'bluesky_publish'
            ];
        }

        try {
            $facets = $this->detect_link_facets($post_text);

            $current_time = gmdate("Y-m-d\TH:i:s.v\Z");
            $record = [
                '$type' => 'app.bsky.feed.post',
                'text' => $post_text,
                'createdAt' => $current_time,
                'langs' => ['en'],
            ];

            if (!empty($facets)) {
                $record['facets'] = $facets;
            }

            if ($include_images && !empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                $image_alt_text = $title ?: substr($content, 0, 50);
                $uploaded_image_blob = $this->upload_bluesky_image($pds_url, $access_token, $did, $image_url, $image_alt_text);
                
                if (!is_wp_error($uploaded_image_blob) && isset($uploaded_image_blob['blob'])) {
                    $record['embed'] = [
                        '$type' => 'app.bsky.embed.images',
                        'images' => [
                            [
                                'alt' => $image_alt_text,
                                'image' => $uploaded_image_blob['blob']
                            ]
                        ]
                    ];
                }
            }

            $post_result = $this->create_bluesky_post($pds_url, $access_token, $did, $record);

            if (is_wp_error($post_result)) {
                $error_msg = 'Bluesky API error: ' . $post_result->get_error_message();
                do_action('datamachine_log', 'error', $error_msg, [
                    'error_code' => $post_result->get_error_code()
                ]);

                return [
                    'success' => false,
                    'error' => $error_msg,
                    'tool_name' => 'bluesky_publish'
                ];
            }

            $post_uri = $post_result['uri'] ?? '';
            $post_url = $this->build_post_url($post_uri, $session['handle'] ?? '');
            
            if (is_wp_error($post_url)) {
                $post_url = 'https://bsky.app/';
            }
            
            do_action('datamachine_log', 'debug', 'Bluesky Tool: Post created successfully', [
                'post_uri' => $post_uri,
                'post_url' => $post_url
            ]);

            return [
                'success' => true,
                'data' => [
                    'post_uri' => $post_uri,
                    'post_url' => $post_url,
                    'content' => $post_text
                ],
                'tool_name' => 'bluesky_publish'
            ];
        } catch (\Exception $e) {
            do_action('datamachine_log', 'error', 'Bluesky Tool: Exception during posting', [
                'exception' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tool_name' => 'bluesky_publish'
            ];
        }
    }


    /**
     * Returns the user-friendly label for this publish handler.
     *
     * @return string The label.
     */
    public static function get_label(): string {
        return __('Post to Bluesky', 'data-machine');
    }

    /**
     * Sanitizes the settings specific to the Bluesky publish handler.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        $sanitized['include_source'] = ($raw_settings['include_source'] ?? false) == '1';
        $sanitized['enable_images'] = ($raw_settings['enable_images'] ?? false) == '1';
        return $sanitized;
    }

    /**
     * Detect link facets in post text for proper Bluesky formatting.
     *
     * @param string $text The post text to analyze.
     * @return array Array of facet objects.
     */
    private function detect_link_facets(string $text): array {
        $facets = [];
        
        // Simple URL regex pattern
        $url_pattern = '/https?:\/\/[^\s]+/i';
        
        if (preg_match_all($url_pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $url = $match[0];
                $start = $match[1];
                $end = $start + mb_strlen($url, 'UTF-8');
                
                $facets[] = [
                    'index' => [
                        'byteStart' => $start,
                        'byteEnd' => $end
                    ],
                    'features' => [
                        [
                            '$type' => 'app.bsky.richtext.facet#link',
                            'uri' => $url
                        ]
                    ]
                ];
            }
        }
        
        return $facets;
    }

    /**
     * Upload image to Bluesky blob storage.
     *
     * @param string $pds_url PDS URL
     * @param string $access_token Access token
     * @param string $did User DID
     * @param string $image_url Image URL to upload
     * @param string $alt_text Alt text for image
     * @return array|WP_Error Upload result or error
     */
    private function upload_bluesky_image(string $pds_url, string $access_token, string $did, string $image_url, string $alt_text) {
        
        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        $temp_file_path = download_url($image_url, 30);
        if (is_wp_error($temp_file_path)) {
            do_action('datamachine_log', 'error', 'Failed to download image for Bluesky upload.', ['url' => $image_url]);
            return $temp_file_path;
        }

        $file_size = @filesize($temp_file_path);
        if ($file_size === false || $file_size > 1000000) {
            wp_delete_file($temp_file_path);
            return new \WP_Error('bluesky_image_too_large', __('Image exceeds Bluesky size limit.', 'data-machine'));
        }

        $mime_type = mime_content_type($temp_file_path);
        if (!$mime_type || strpos($mime_type, 'image/') !== 0) {
            wp_delete_file($temp_file_path);
            return new \WP_Error('bluesky_invalid_image_type', __('Invalid image type.', 'data-machine'));
        }

        $image_content = file_get_contents($temp_file_path);
        if ($image_content === false) {
            wp_delete_file($temp_file_path);
            return new \WP_Error('bluesky_image_read_failed', __('Could not read image file.', 'data-machine'));
        }

        $upload_url = rtrim($pds_url, '/') . '/xrpc/com.atproto.repo.uploadBlob';
        $result = apply_filters('datamachine_request', null, 'POST', $upload_url, [
            'headers' => [
                'Content-Type' => $mime_type,
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'body' => $image_content,
        ], 'Bluesky API');

        wp_delete_file($temp_file_path);
        unset($image_content);

        if (!$result['success']) {
            return new \WP_Error('bluesky_upload_request_failed', $result['error']);
        }

        $response_code = $result['status_code'];
        $response_body = $result['data'];

        if ($response_code !== 200) {
            return new \WP_Error('bluesky_upload_failed', __('Image upload failed.', 'data-machine'));
        }

        $upload_result = json_decode($response_body, true);
        if (empty($upload_result['blob'])) {
            return new \WP_Error('bluesky_upload_decode_error', __('Missing blob data in response.', 'data-machine'));
        }

        return $upload_result;
    }

    /**
     * Create a post record on Bluesky.
     *
     * @param string $pds_url PDS URL
     * @param string $access_token Access token
     * @param string $repo_did Repository DID
     * @param array $record Post record data
     * @return array|WP_Error Post result or error
     */
    private function create_bluesky_post(string $pds_url, string $access_token, string $repo_did, array $record) {
        $url = rtrim($pds_url, '/') . '/xrpc/com.atproto.repo.createRecord';
        
        $body = wp_json_encode([
            'repo' => $repo_did,
            'collection' => 'app.bsky.feed.post',
            'record' => $record
        ]);

        $result = apply_filters('datamachine_request', null, 'POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'body' => $body,
        ], 'Bluesky API');

        if (!$result['success']) {
            return new \WP_Error('bluesky_post_request_failed', $result['error']);
        }

        $response_code = $result['status_code'];
        $response_body = $result['data'];

        if ($response_code !== 200) {
            return new \WP_Error('bluesky_post_failed', __('Failed to create Bluesky post.', 'data-machine'));
        }

        $result = json_decode($response_body, true);
        return $result ?: new \WP_Error('bluesky_post_decode_error', __('Could not decode post response.', 'data-machine'));
    }

    /**
     * Build user-friendly post URL from AT Protocol URI.
     *
     * @param string $uri AT Protocol URI
     * @param string $handle User handle
     * @return string|WP_Error User-friendly URL or error if URL construction fails
     */
    private function build_post_url(string $uri, string $handle) {
        // Extract post ID from AT URI (format: at://did:plc:xxx/app.bsky.feed.post/postid)
        if (preg_match('/\/app\.bsky\.feed\.post\/(.+)$/', $uri, $matches)) {
            $post_id = $matches[1];
            return "https://bsky.app/profile/{$handle}/post/{$post_id}";
        }
        
        do_action('datamachine_log', 'error', 'Failed to extract post ID from AT Protocol URI.', [
            'uri' => $uri,
            'handle' => $handle
        ]);
        return new \WP_Error('bluesky_url_construction_failed', __('Failed to construct post URL from AT Protocol URI.', 'data-machine'));
    }
}