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

use DataMachine\Core\Steps\AI\AiResponseParser;

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
        // Use filter-based auth access following architectural standards
        $this->auth = apply_filters('dm_get_auth', null, 'bluesky');
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
     * @param int $user_id The ID of the user whose Bluesky account should be used.
     * @return array Result array on success or failure.
     */
    public function handle_output($data_packet, int $user_id): array {
        // Extract content from DataPacket JSON object
        $ai_output_string = $data_packet->content->body ?? $data_packet->content->title ?? '';
        
        // Get output config from DataPacket (set by OutputStep)
        $output_config = $data_packet->output_config ?? [];
        $module_job_config = [
            'output_config' => $output_config
        ];
        
        // Extract metadata from DataPacket
        $input_metadata = [
            'source_url' => $data_packet->metadata->source_url ?? null,
            'image_source_url' => !empty($data_packet->attachments->images) ? $data_packet->attachments->images[0]->url : null
        ];
        
        // Get logger service via filter
        $logger = apply_filters('dm_get_logger', null);
        $logger && $logger->info('Starting Bluesky output handling.', ['user_id' => $user_id]);
        
        // 1. Get config
        $output_config = $module_job_config['output_config']['bluesky'] ?? [];
        $include_source = $output_config['bluesky_include_source'] ?? true;
        $enable_images = $output_config['bluesky_enable_images'] ?? true;

        // 2. Ensure user_id is provided
        if (empty($user_id)) {
            $logger && $logger->error('Bluesky Output: User ID context is missing.');
            return [
                'success' => false,
                'error' => __('Cannot post to Bluesky without a specified user account.', 'data-machine')
            ];
        }

        // 3. Get authenticated session using internal BlueskyAuth
        $session = $this->auth->get_session($user_id);

        // 4. Handle authentication errors
        if (is_wp_error($session)) {
             $logger && $logger->error('Bluesky Output Error: Failed to get authenticated session.', [
                'user_id' => $user_id,
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
        $pds_url = $session['pds_url'] ?? 'https://bsky.social';

        if (empty($access_token) || empty($did)) {
            $logger && $logger->error('Bluesky session data incomplete after authentication.');
            return [
                'success' => false,
                'error' => __('Bluesky authentication succeeded but returned incomplete session data.', 'data-machine')
            ];
        }

        // 5. Parse AI output
        $parser = apply_filters('dm_get_ai_response_parser', null);
        if (!$parser) {
            $logger && $logger->error('Bluesky Output: AI Response Parser service not available.');
            return [
                'success' => false,
                'error' => __('AI Response Parser service not available.', 'data-machine')
            ];
        }
        $parser->set_raw_output($ai_output_string);
        $parser->parse();
        $title = $parser->get_title();
        $content = $parser->get_content();

        if (empty($title) && empty($content)) {
            $logger && $logger->warning('Bluesky Output: Parsed AI output is empty.', ['user_id' => $user_id]);
            return [
                'success' => false,
                'error' => __('Cannot post empty content to Bluesky.', 'data-machine')
            ];
        }

        // 6. Format post content
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
             $logger && $logger->error('Bluesky Output: Formatted post content is empty after processing.', ['user_id' => $user_id]);
             return [
                 'success' => false,
                 'error' => __('Formatted post content is empty after processing.', 'data-machine')
             ];
        }

        // 7. Detect link facets
        $facets = $this->detect_link_facets($final_post_text);

        // 8. Handle image upload (optional)
        $embed_data = null;
        $image_source_url = $input_metadata['image_source_url'] ?? null;
        $image_alt_text = $parser ? ($parser->get_title() ?: $parser->get_content_summary(50)) : '';

        if ($enable_images && !empty($image_source_url) && filter_var($image_source_url, FILTER_VALIDATE_URL)) {
            $logger && $logger->info('Attempting to upload image to Bluesky.', ['image_url' => $image_source_url, 'user_id' => $user_id]);
            
            $uploaded_image_blob = $this->upload_bluesky_image($pds_url, $access_token, $did, $image_source_url, $image_alt_text);
            
            if (!is_wp_error($uploaded_image_blob) && isset($uploaded_image_blob['blob'])) {
                $logger && $logger->info('Bluesky image uploaded successfully.', ['user_id' => $user_id]);
                $embed_data = [
                    '$type' => 'app.bsky.embed.images',
                    'images' => [
                        [
                            'alt'   => $image_alt_text,
                            'image' => $uploaded_image_blob['blob']
                        ]
                    ]
                ];
            } elseif (is_wp_error($uploaded_image_blob)) {
                $logger && $logger->warning('Bluesky image upload failed, proceeding without image.', [
                    'user_id' => $user_id,
                    'error_code' => $uploaded_image_blob->get_error_code(),
                    'error_message' => $uploaded_image_blob->get_error_message()
                ]);
            }
        }

        // 9. Create post record
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

        // 10. Post to Bluesky
        try {
            $post_result = $this->create_bluesky_post($pds_url, $access_token, $did, $record);

            if (is_wp_error($post_result)) {
                $logger && $logger->error('Failed to create Bluesky post.', [
                    'user_id' => $user_id,
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

            $logger && $logger->info('Successfully posted to Bluesky.', ['user_id' => $user_id, 'post_uri' => $post_uri]);

            return [
                'success' => true,
                'status' => 'success',
                'output_url' => $post_url,
                'message' => __('Successfully posted to Bluesky.', 'data-machine'),
                'raw_response' => $post_result
            ];

        } catch (\Exception $e) {
            $logger && $logger->error('Bluesky Output Exception: ' . $e->getMessage(), ['user_id' => $user_id]);
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
     * @return string User-friendly URL
     */
    private function build_post_url(string $uri, string $handle): string {
        // Extract post ID from AT URI (format: at://did:plc:xxx/app.bsky.feed.post/postid)
        if (preg_match('/\/app\.bsky\.feed\.post\/(.+)$/', $uri, $matches)) {
            $post_id = $matches[1];
            return "https://bsky.app/profile/{$handle}/post/{$post_id}";
        }
        
        return "https://bsky.app/profile/{$handle}"; // Fallback to profile
    }
}