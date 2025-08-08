<?php
/**
 * Modular Bluesky output handler.
 *
 * Posts content to a specified Bluesky account using the self-contained
 * BlueskyAuth class for authentication. This modular approach separates
 * concerns between main handler logic and authentication functionality.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/output/bluesky
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Output\Bluesky;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Bluesky {

    /**
     * @var BlueskyAuth Authentication handler instance
     */
    private $auth;

    /**
     * Constructor - direct auth initialization for security
     */
    public function __construct() {
        // Use filter-based auth access following pure discovery architectural standards
        $all_auth = apply_filters('dm_get_auth_providers', []);
        $this->auth = $all_auth['bluesky'] ?? null;
        
        if ($this->auth === null) {
            throw new \RuntimeException('Bluesky authentication service not available. Required service missing from dm_get_auth_providers filter.');
        }
    }

    /**
     * Get Bluesky auth handler - internal implementation.
     * 
     * @return BlueskyAuth
     */
    private function get_auth() {
        return $this->auth;
    }

    /**
     * Handles posting the AI output to Bluesky.
     *
     * @param object $data_packet Universal DataPacket JSON object with all content and metadata.
     * @return array Result array on success or failure.
     */
    public function handle_publish($data_packet): array {
        // Access structured content directly from DataPacket (no parsing needed)
        $title = $data_packet->content->title ?? '';
        $content = $data_packet->content->body ?? '';
        
        // Get output config from DataPacket (set by OutputStep)
        $output_config = $data_packet->output_config ?? [];
        $flow_job_config = [
            'output_config' => $output_config
        ];
        
        // Extract metadata from DataPacket
        $input_metadata = [
            'source_url' => $data_packet->metadata->source_url ?? null,
            'image_source_url' => !empty($data_packet->attachments->images) ? $data_packet->attachments->images[0]->url : null
        ];
        
        // Get logger service via filter
        $logger = apply_filters('dm_get_logger', null);
        $logger && $logger->debug('Starting Bluesky output handling.');
        
        // 1. Get config - require explicit configuration
        $output_config = $flow_job_config['output_config']['bluesky'] ?? [];
        
        if (!isset($output_config['bluesky_include_source'])) {
            $logger && $logger->error('Bluesky publish configuration missing required bluesky_include_source setting.');
            return [
                'success' => false,
                'error' => __('Bluesky configuration incomplete: bluesky_include_source setting required.', 'data-machine')
            ];
        }
        
        if (!isset($output_config['bluesky_enable_images'])) {
            $logger && $logger->error('Bluesky publish configuration missing required bluesky_enable_images setting.');
            return [
                'success' => false,
                'error' => __('Bluesky configuration incomplete: bluesky_enable_images setting required.', 'data-machine')
            ];
        }
        
        $include_source = $output_config['bluesky_include_source'];
        $enable_images = $output_config['bluesky_enable_images'];

        // 2. Get authenticated session using internal BlueskyAuth
        $session = $this->auth->get_session();

        // 3. Handle authentication errors
        if (is_wp_error($session)) {
             $logger && $logger->error('Bluesky Output Error: Failed to get authenticated session.', [
                'error_code' => $session->get_error_code(),
                'error_message' => $session->get_error_message(),
             ]);
             return [
                 'success' => false,
                 'error' => $session->get_error_message()
             ];
        }

        $access_token = $session['accessJwt'] ?? null;
        $did = $session['did'] ?? null;
        $pds_url = $session['pds_url'] ?? null;

        if (empty($access_token) || empty($did) || empty($pds_url)) {
            $logger && $logger->error('Bluesky session data incomplete after authentication.');
            return [
                'success' => false,
                'error' => __('Bluesky authentication succeeded but returned incomplete session data (missing accessJwt, did, or pds_url).', 'data-machine')
            ];
        }

        // Validate content from DataPacket
        if (empty($title) && empty($content)) {
            $logger && $logger->warning('Bluesky Output: DataPacket content is empty.');
            return [
                'success' => false,
                'error' => __('Cannot post empty content to Bluesky.', 'data-machine')
            ];
        }

        // 5. Format post content
        $post_text = $title ? $title . ": " . $content : $content;
        $source_url = $input_metadata['source_url'] ?? null;
        $bluesky_char_limit = 300;
        $ellipsis = '…';
        $ellipsis_len = mb_strlen($ellipsis, 'UTF-8');
        $link_prefix = "\n\n";
        $link_text = '';

        // Prepare link text if source is included and valid
        if ($include_source && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL)) {
            $link_text = $link_prefix . $source_url;
        }

        // Calculate character count (URLs count as 22 chars in Bluesky)
        $link_text_len = 0;
        if (!empty($link_text)) {
            $prefix_len = mb_strlen($link_prefix, 'UTF-8');
            $url_char_count = 22; // Bluesky counts all URLs as 22 characters
            $link_text_len = $prefix_len + $url_char_count;
        }
        $available_main_text_len = $bluesky_char_limit - $link_text_len;

        // Truncate main content if necessary
        if ($available_main_text_len >= $ellipsis_len) {
            $main_text_len = mb_strlen($post_text, 'UTF-8');
            if ($main_text_len > $available_main_text_len) {
                $post_text = mb_substr($post_text, 0, $available_main_text_len - $ellipsis_len, 'UTF-8') . $ellipsis;
            }
            $final_post_text = $post_text . $link_text;
        } else {
            // Not enough space for main text + link, hard truncate
            $final_post_text = mb_substr($link_text, 0, $bluesky_char_limit, 'UTF-8');
        }

        $final_post_text = trim($final_post_text);

        if (empty($final_post_text)) {
             $logger && $logger->error('Bluesky Output: Formatted post content is empty after processing.');
             return [
                 'success' => false,
                 'error' => __('Formatted post content is empty after processing.', 'data-machine')
             ];
        }

        // 6. Detect link facets
        $facets = $this->detect_link_facets($final_post_text);

        // 7. Handle image upload (optional)
        $embed_data = null;
        $image_source_url = $input_metadata['image_source_url'] ?? null;
        $image_alt_text = $title ?: substr($content, 0, 50); // Use title or content summary as alt text

        if ($enable_images && !empty($image_source_url) && filter_var($image_source_url, FILTER_VALIDATE_URL)) {
            $logger && $logger->debug('Attempting to upload image to Bluesky.', ['image_url' => $image_source_url]);
            
            $uploaded_image_blob = $this->upload_bluesky_image($pds_url, $access_token, $did, $image_source_url, $image_alt_text);
            
            if (!is_wp_error($uploaded_image_blob) && isset($uploaded_image_blob['blob'])) {
                $logger && $logger->debug('Bluesky image uploaded successfully.');
                $embed_data = [
                    '$type' => 'app.bsky.embed.images',
                    'images' => [
                        [
                            'alt'   => $image_alt_text,
                            'image' => $uploaded_image_blob['blob']
                        ]
                    ]
                ];
            } else {
                // Fail immediately if image upload fails when images are enabled
                $error_message = is_wp_error($uploaded_image_blob) ? $uploaded_image_blob->get_error_message() : 'Image upload failed with unknown error.';
                $logger && $logger->error('Bluesky image upload failed when images are enabled.', [
                    'error_message' => $error_message,
                    'image_url' => $image_source_url
                ]);
                return [
                    'success' => false,
                    'error' => __('Image upload failed when images are enabled: ', 'data-machine') . $error_message
                ];
            }
        }

        // 8. Create post record
        $current_time = gmdate("Y-m-d\TH:i:s.v\Z");
        $record = [
            '$type'     => 'app.bsky.feed.post',
            'text'      => $final_post_text,
            'createdAt' => $current_time,
            'langs'     => ['en'],
        ];

        // Add facets if detected
        if (!empty($facets)) {
            $record['facets'] = $facets;
        }

        // Add embed if image was processed
        if (!empty($embed_data)) {
            $record['embed'] = $embed_data;
        }

        // 9. Post to Bluesky
        try {
            $post_result = $this->create_bluesky_post($pds_url, $access_token, $did, $record);

            if (is_wp_error($post_result)) {
                $logger && $logger->error('Failed to create Bluesky post.', [
                    'error_code' => $post_result->get_error_code(),
                    'error_message' => $post_result->get_error_message()
                ]);
                return [
                    'success' => false,
                    'error' => $post_result->get_error_message()
                ];
            }

            $post_uri = $post_result['uri'] ?? '';
            $post_url = $this->build_post_url($post_uri, $session['handle'] ?? '');
            
            if (is_wp_error($post_url)) {
                $logger && $logger->error('Failed to build post URL.', [
                    'error_code' => $post_url->get_error_code(),
                    'error_message' => $post_url->get_error_message()
                ]);
                return [
                    'success' => false,
                    'error' => $post_url->get_error_message()
                ];
            }

            $logger && $logger->debug('Successfully posted to Bluesky.', ['post_uri' => $post_uri]);

            return [
                'success' => true,
                'status' => 'success',
                'output_url' => $post_url,
                'message' => __('Successfully posted to Bluesky.', 'data-machine'),
                'raw_response' => $post_result
            ];

        } catch (\Exception $e) {
            $logger && $logger->error('Bluesky Output Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Returns the user-friendly label for this output handler.
     *
     * @return string The label.
     */
    public static function get_label(): string {
        return __('Post to Bluesky', 'data-machine');
    }

    /**
     * Sanitizes the settings specific to the Bluesky output handler.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        $sanitized['bluesky_include_source'] = isset($raw_settings['bluesky_include_source']) && $raw_settings['bluesky_include_source'] == '1';
        $sanitized['bluesky_enable_images'] = isset($raw_settings['bluesky_enable_images']) && $raw_settings['bluesky_enable_images'] == '1';
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
        $logger = apply_filters('dm_get_logger', null);
        
        // Download image temporarily
        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        $temp_file_path = download_url($image_url, 30);
        if (is_wp_error($temp_file_path)) {
            $logger && $logger->error('Failed to download image for Bluesky upload.', ['url' => $image_url]);
            return $temp_file_path;
        }

        // Check file size (1MB limit)
        $file_size = @filesize($temp_file_path);
        if ($file_size === false || $file_size > 1000000) {
            unlink($temp_file_path);
            return new \WP_Error('bluesky_image_too_large', __('Image exceeds Bluesky size limit.', 'data-machine'));
        }

        // Get mime type and content
        $mime_type = mime_content_type($temp_file_path);
        if (!$mime_type || strpos($mime_type, 'image/') !== 0) {
            unlink($temp_file_path);
            return new \WP_Error('bluesky_invalid_image_type', __('Invalid image type.', 'data-machine'));
        }

        $image_content = file_get_contents($temp_file_path);
        if ($image_content === false) {
            unlink($temp_file_path);
            return new \WP_Error('bluesky_image_read_failed', __('Could not read image file.', 'data-machine'));
        }

        // Upload to Bluesky
        $upload_url = rtrim($pds_url, '/') . '/xrpc/com.atproto.repo.uploadBlob';
        $response = wp_remote_post($upload_url, [
            'headers' => [
                'Content-Type' => $mime_type,
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'body' => $image_content,
            'timeout' => 60
        ]);

        unlink($temp_file_path);
        unset($image_content);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

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

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'body' => $body,
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

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
        
        $logger = apply_filters('dm_get_logger', null);
        $logger && $logger->error('Failed to extract post ID from AT Protocol URI.', [
            'uri' => $uri,
            'handle' => $handle
        ]);
        return new \WP_Error('bluesky_url_construction_failed', __('Failed to construct post URL from AT Protocol URI.', 'data-machine'));
    }
}