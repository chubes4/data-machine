<?php

/**
 * Handles the 'Bluesky' output type.
 *
 * Posts content to a specified Bluesky account.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/output
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! interface_exists( 'Data_Machine_Output_Handler_Interface' ) ) {
    // Ensure the interface is loaded. Adjust path if necessary.
    // Assuming DATA_MACHINE_PATH is defined globally or adjust as needed.
    if (defined('DATA_MACHINE_PATH')) {
        require_once DATA_MACHINE_PATH . 'includes/interfaces/interface-data-machine-output-handler.php';
    } else {
        // Fallback or error if path constant is not available
        error_log('Data Machine Error: DATA_MACHINE_PATH not defined. Cannot load Output Handler Interface.');
        return; // Or trigger a more specific error
    }
}

use Data_Machine_Encryption_Helper;
use Data_Machine_Logger;

class Data_Machine_Output_Bluesky implements Data_Machine_Output_Handler_Interface {

    use Data_Machine_Base_Output_Handler;

    /** @var Data_Machine_Logger|null */
    private $logger;

    /** @var Data_Machine_Encryption_Helper */
    private $encryption_helper;

    /**
     * Constructor.
     *
     * @param Data_Machine_Encryption_Helper $encryption_helper Encryption service.
     * @param Data_Machine_Logger|null $logger Optional Logger instance.
     */
    public function __construct(Data_Machine_Encryption_Helper $encryption_helper, ?Data_Machine_Logger $logger = null) {
        $this->encryption_helper = $encryption_helper;
        $this->logger = $logger;
    }

    /**
     * Handles publishing the AI output to Bluesky.
     *
     * @param string $ai_output_string The finalized string from the AI.
     * @param array $module_job_config Configuration specific to this output job.
     * @param int|null $user_id The ID of the user whose Bluesky account should be used. Default null uses site settings.
     * @param array $input_metadata Metadata from the original input source.
     * @return array|WP_Error Result array on success, WP_Error on failure.
     */
    public function handle( string $ai_output_string, array $module_job_config, ?int $user_id, array $input_metadata ): array|WP_Error {
        $this->logger?->info('Starting Bluesky output handling.', ['user_id' => $user_id]);

        // --- 1. Get Configuration ---
        $output_config = $module_job_config['output_config']['bluesky'] ?? [];
        $include_source = $output_config['bluesky_include_source'] ?? true;
        $enable_images = $output_config['bluesky_enable_images'] ?? true;

        // --- Get User Meta Credentials ---
        if (null === $user_id) {
             $this->logger?->error('User ID context is missing for Bluesky output.', ['method' => __METHOD__]);
             return new WP_Error('bluesky_missing_user_id', __('User context is required for Bluesky authentication.', 'data-machine'));
        }
        $handle = get_user_meta($user_id, 'dm_bluesky_username', true);
        $encrypted_password = get_user_meta($user_id, 'dm_bluesky_app_password', true);

        // --- 2. Check Required Configuration ---
        if (empty($handle) || empty($encrypted_password)) {
            $this->logger?->error('Bluesky handle or app password is missing in user meta.', ['user_id' => $user_id]);
            return new WP_Error('bluesky_config_missing', __('Bluesky handle and app password must be configured on the API / Auth page.', 'data-machine'));
        }

        // --- 3. Decrypt Password ---
        $password = '';
        if (!$this->encryption_helper) {
            $this->logger?->error('Encryption helper service not available for Bluesky password decryption.', ['user_id' => $user_id]);
            return new WP_Error('bluesky_service_unavailable', __('Encryption service is unavailable.', 'data-machine'));
        }

        try {
            $this->logger?->debug('Attempting to decrypt password using injected helper.', ['user_id' => $user_id, 'encrypted_value_type' => gettype($encrypted_password)]);
            $password = $this->encryption_helper->decrypt($encrypted_password);

            if ($password === false) {
                 $this->logger?->error('Bluesky password decryption failed (returned false).', ['user_id' => $user_id]);
                 throw new Exception('Failed to decrypt Bluesky password.');
            }
            if (empty($password) && !empty($encrypted_password)) {
                 $this->logger?->warning('Bluesky password decryption resulted in an empty string, but encrypted value was not empty. Ensure a valid password was saved.', ['user_id' => $user_id]);
                 throw new Exception('Decrypted password is empty.');
            }
             if (empty($password) && empty($encrypted_password)) {
                 $this->logger?->error('Bluesky password configuration is empty.', ['user_id' => $user_id]);
                 throw new Exception('Bluesky password is empty in configuration.');
             }
            $this->logger?->debug('Bluesky app password decrypted successfully.', ['user_id' => $user_id]);
        } catch (\Exception $e) {
            $this->logger?->error('Bluesky password decryption failed: ' . $e->getMessage(), ['user_id' => $user_id, 'exception_type' => get_class($e)]);
            // Clear potentially sensitive password from memory in case of error
            unset($password);
            return new WP_Error('bluesky_decrypt_failed', __('Could not decrypt Bluesky app password. Please re-save the module settings.', 'data-machine'));
        }

        // --- 4. Authenticate & Get Session ---
        $session_data = $this->_get_bluesky_session($handle, $password);
        unset($password); // Clear password from memory

        if (is_wp_error($session_data)) {
            $this->logger?->error('Bluesky authentication failed.', ['user_id' => $user_id, 'error_code' => $session_data->get_error_code(), 'error_message' => $session_data->get_error_message()]);
            return $session_data;
        }
        $access_token = $session_data['accessJwt'] ?? null;
        $did = $session_data['did'] ?? null;
        $pds_url = $session_data['pds_url'] ?? 'https://bsky.social';

        if (empty($access_token) || empty($did)) {
             $this->logger?->error('Bluesky session data is incomplete after authentication (missing token or DID).', ['user_id' => $user_id, 'session_data' => $session_data]);
             return new WP_Error('bluesky_session_incomplete', __('Bluesky authentication succeeded but returned incomplete session data.', 'data-machine'));
        }
        $this->logger?->info('Bluesky authentication successful.', ['user_id' => $user_id, 'did' => $did, 'pds' => $pds_url]);

        // --- 5. Parse AI Output ---
        $parser_path = DATA_MACHINE_PATH . 'includes/helpers/class-data-machine-ai-response-parser.php';
        if (!class_exists('Data_Machine_AI_Response_Parser') && file_exists($parser_path)) {
            require_once $parser_path;
        }
        if (!class_exists('Data_Machine_AI_Response_Parser')) {
             $this->logger?->error('Data_Machine_AI_Response_Parser class not found.', ['path' => $parser_path]);
             return new WP_Error('bluesky_parser_missing', __('AI Response Parser is missing.', 'data-machine'));
        }

        $parser = new Data_Machine_AI_Response_Parser($ai_output_string);
        $parser->parse();
        $post_text = $parser->get_content(); 
        if (empty($post_text)) {
             $this->logger?->warning('Parsed AI output for Bluesky is empty.', ['user_id' => $user_id, 'original_output' => $ai_output_string]);
             return new WP_Error('bluesky_empty_content', __('Parsed AI output is empty, cannot post to Bluesky.', 'data-machine'));
        }

        // --- 6. Format Post Text & Handle Facets ---
        $source_url = $input_metadata['source_url'] ?? null;
        $facets = [];

        $bluesky_char_limit = 300;
        $ellipsis = '…';
        $ellipsis_len = mb_strlen($ellipsis, 'UTF-8');
        $link_prefix = "\n\n";
        $link = ($include_source && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL)) ? $link_prefix . $source_url : '';
        $link_length = mb_strlen($link, 'UTF-8');
        $available_main_text_len = $bluesky_char_limit - $link_length;
        if ($available_main_text_len < $ellipsis_len) {
            // If the link itself is too long, hard truncate it (rare)
            $post_text = mb_substr($link, 0, $bluesky_char_limit);
        } else {
            if (mb_strlen($post_text, 'UTF-8') > $available_main_text_len) {
                $post_text = mb_substr($post_text, 0, $available_main_text_len - $ellipsis_len) . $ellipsis;
            }
            $post_text .= $link;
        }
        $post_text = trim($post_text);

        $main_text_len = mb_strlen($post_text, 'UTF-8');

        if ($main_text_len > $available_main_text_len) {
            $this->logger?->warning('Bluesky main post text truncated.', [
                'user_id' => $user_id,
                'original_length' => $main_text_len,
                'truncated_length' => mb_strlen($post_text, 'UTF-8'),
                'limit_for_text' => $available_main_text_len,
            ]);
        }

        $final_char_count = mb_strlen($post_text, 'UTF-8');
        if ($final_char_count > $bluesky_char_limit) {
             $this->logger?->error('Post text still exceeds limit after truncation logic.', [
                'user_id' => $user_id,
                'final_length' => $final_char_count,
                'limit' => $bluesky_char_limit
             ]);
            // Hard truncate as a final measure (shouldn't happen)
             $post_text = mb_substr($post_text, 0, $bluesky_char_limit, 'UTF-8');
            $facets = []; // Invalidate facets if hard truncated
        }

        // --- 7. Handle Images (Optional) ---
        $embed_data = null;
        $uploaded_image_blob = null;
        $image_alt_text = $parser->get_title() ?: $parser->get_content_summary(50); // Use title or summary as alt text

        if ($enable_images) {
            // Check for image URL in input metadata first (e.g., from Reddit, Instagram)
            $image_url_from_input = $input_metadata['image_source_url'] ?? null;
            if ($image_url_from_input && filter_var($image_url_from_input, FILTER_VALIDATE_URL)) {
                 $this->logger?->info('Found image URL in input metadata, attempting upload.', ['url' => $image_url_from_input]);
                 $uploaded_image_blob = $this->_upload_bluesky_image($pds_url, $access_token, $did, $image_url_from_input, $image_alt_text);
            } else {
                 $this->logger?->info('No image URL found in input metadata or image handling disabled.');
            }
        }

        if (!is_wp_error($uploaded_image_blob) && isset($uploaded_image_blob['blob'])) {
             $this->logger?->info('Bluesky image uploaded successfully.', ['user_id' => $user_id, 'blob_cid' => $uploaded_image_blob['blob']['ref']['$link'] ?? 'N/A']);
            // Prepare embed data for the post
            $embed_data = [
                '$type' => 'app.bsky.embed.images',
                'images' => [
                    [
                        'alt'   => $image_alt_text,
                        'image' => $uploaded_image_blob['blob']
                    ]
                ]
            ];
        } elseif (is_wp_error($uploaded_image_blob)){
             $this->logger?->error('Bluesky image upload failed.', [
                'user_id' => $user_id,
                'error_code' => $uploaded_image_blob->get_error_code(),
                'error_message' => $uploaded_image_blob->get_error_message(),
                'image_url' => $image_url_from_input ?? 'N/A'
             ]);
             // Optionally, decide whether to fail the whole post or just post without the image.
             // For now, let's post without the image if upload fails.
             $embed_data = null;
        }

        // --- 8. Prepare Post Record ---
        $current_time = gmdate("Y-m-d\TH:i:s.v\Z"); // AT Protocol timestamp format
        $record = [
            '$type'     => 'app.bsky.feed.post',
            'text'      => $post_text,
            'createdAt' => $current_time,
            'langs'     => ['en'], // TODO: Detect language?
        ];
        if (!empty($facets)) {
            $record['facets'] = $facets;
        }
        if (!empty($embed_data)) {
            $record['embed'] = $embed_data;
        }

        // --- 9. Create Post ---
        $post_result = $this->_create_bluesky_post($pds_url, $access_token, $did, $record);

        if (is_wp_error($post_result)) {
            $this->logger?->error('Failed to create Bluesky post.', [
                'user_id' => $user_id,
                'error_code' => $post_result->get_error_code(),
                'error_message' => $post_result->get_error_message()
            ]);
            return $post_result;
        }

        // --- 10. Success --- 
        $this->logger?->info('Successfully posted to Bluesky.', ['user_id' => $user_id, 'post_uri' => $post_result['uri'] ?? 'N/A']);
        return [
            'status' => 'success',
            'message' => __('Successfully posted to Bluesky.', 'data-machine'),
            'output_url' => $this->_build_post_url($post_result['uri'] ?? null, $handle), // Construct user-friendly URL
            'raw_response' => $post_result // Include raw API response
        ];
    }

    /**
     * Defines the settings fields for this output handler.
     *
     * @param array $current_config Current configuration values for this handler (optional).
     * @return array An associative array defining the settings fields.
     */
    public function get_settings_fields(array $current_config = []): array {
        // Handle and Password are now fetched from User Meta during handle(), not stored in module config.
        // The settings here are for *behaviour*.
        return [
            'bluesky_include_source' => [
                'type' => 'checkbox',
                'label' => __('Include Source Link', 'data-machine'),
                'description' => __('Append the original source URL to the post (if available and fits within character limits).', 'data-machine'),
                'default' => true,
            ],
            'bluesky_enable_images' => [
                'type' => 'checkbox',
                'label' => __('Enable Image Posting', 'data-machine'),
                 'description' => __('Attempt to find and upload an image from the source data (if available).', 'data-machine'),
                'default' => true,
            ],
        ];
    }

    /**
     * Sanitizes the settings specific to the Bluesky output handler.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        // Handle and Password are no longer stored here.
        $sanitized['bluesky_include_source'] = isset($raw_settings['bluesky_include_source']) && $raw_settings['bluesky_include_source'] == '1';
        $sanitized['bluesky_enable_images'] = isset($raw_settings['bluesky_enable_images']) && $raw_settings['bluesky_enable_images'] == '1';
        return $sanitized;
    }

    /**
     * Returns the user-friendly label for this output handler.
     *
     * @return string The label.
     */
    public static function get_label(): string {
        return __('Post to Bluesky', 'data-machine');
    }

    // --- Private Helper Methods ---

    /**
     * Authenticates with Bluesky and gets session data.
     *
     * @param string $handle User handle (e.g., user.bsky.social).
     * @param string $password App password.
     * @return array|WP_Error Session data array on success, WP_Error on failure.
     */
    private function _get_bluesky_session(string $handle, string $password): array|WP_Error {
        $url = 'https://bsky.social/xrpc/com.atproto.server.createSession'; // PDS URL might differ, but start here
        $body = json_encode([
            'identifier' => $handle,
            'password'   => $password,
        ]);

        if (false === $body) {
            $this->logger?->error('Failed to JSON encode Bluesky session request body.', ['handle' => $handle]);
            return new WP_Error('bluesky_json_encode_error', __('Could not encode authentication request.', 'data-machine'));
        }

        $args = [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $body,
            'timeout' => 20,
        ];

        $this->logger?->info('Attempting Bluesky authentication (createSession).', ['handle' => $handle, 'url' => $url]);

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $this->logger?->error('Bluesky session request failed (wp_remote_post error).', ['handle' => $handle, 'error' => $response->get_error_message()]);
            return new WP_Error('bluesky_session_request_failed', __('Could not connect to Bluesky server for authentication.', 'data-machine') . ' ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $this->logger?->debug('Bluesky session response received.', ['handle' => $handle, 'code' => $response_code, 'body_snippet' => substr($response_body, 0, 200)]);

        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = $error_data['message'] ?? 'Authentication failed.';
            $this->logger?->error('Bluesky authentication failed (non-200 response).', ['handle' => $handle, 'code' => $response_code, 'response_message' => $error_message]);
            return new WP_Error('bluesky_auth_failed', sprintf(__( 'Bluesky authentication failed: %s (Code: %d)', 'data-machine' ), $error_message, $response_code));
        }

        $session_data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger?->error('Failed to decode Bluesky session response JSON.', ['handle' => $handle, 'json_error' => json_last_error_msg()]);
            return new WP_Error('bluesky_json_decode_error', __('Could not decode Bluesky authentication response.', 'data-machine'));
        }

        // --- Add PDS URL to Session Data ---
        // The PDS URL is needed for subsequent requests (like posting)
        // It might not always be bsky.social. It could potentially be part of the session response,
        // or derived from the handle if it's a full handle like user.example.com.
        // For simplicity now, we assume bsky.social if not explicitly provided.
        if (empty($session_data['pdsUrl'])) {
            // Basic attempt to infer PDS from handle if it looks like a domain
            if (strpos($handle, '.') !== false && !str_ends_with($handle, '.bsky.social')) {
                 $pds_domain = $handle; // Assume handle is the PDS domain if it contains dots
            } else {
                 $pds_domain = 'bsky.social'; // Default PDS
            }
            $session_data['pds_url'] = 'https://' . $pds_domain;
            $this->logger?->debug('PDS URL not in session response, using inferred/default.', ['handle' => $handle, 'pds_url' => $session_data['pds_url']]);
        } else {
            // Ensure it has https:// prefix if returned
            if (!str_starts_with($session_data['pdsUrl'], 'http')) {
                $session_data['pds_url'] = 'https://' . ltrim($session_data['pdsUrl'], '/');
            }
            $this->logger?->debug('Using PDS URL from session response.', ['handle' => $handle, 'pds_url' => $session_data['pds_url']]);
        }
        // --- End PDS URL handling ---

        return $session_data;
    }

    /**
     * Uploads an image to Bluesky's blob storage.
     *
     * @param string $pds_url The user's PDS URL (e.g., https://bsky.social).
     * @param string $access_token The access JWT.
     * @param string $repo_did The user's DID.
     * @param string $image_url The URL of the image to upload.
     * @param string $alt_text Alt text for the image.
     * @return array|WP_Error Upload result array (containing blob) on success, WP_Error on failure.
     */
    private function _upload_bluesky_image(string $pds_url, string $access_token, string $repo_did, string $image_url, string $alt_text): array|WP_Error {
        $this->logger?->info('Attempting to upload image to Bluesky.', ['image_url' => $image_url, 'did' => $repo_did]);

        // 1. Download the image temporarily
        $temp_file_path = download_url($image_url, 30); // 30 second timeout
        if (is_wp_error($temp_file_path)) {
            $this->logger?->error('Failed to download image for Bluesky upload.', ['url' => $image_url, 'error' => $temp_file_path->get_error_message()]);
            return new WP_Error('bluesky_image_download_failed', __('Could not download image from source URL.', 'data-machine') . ' ' . $temp_file_path->get_error_message());
        }
        $this->logger?->debug('Image downloaded temporarily.', ['image_url' => $image_url, 'temp_path' => $temp_file_path]);

        // 2. Get image mime type and read content
        $mime_type = mime_content_type($temp_file_path);
        if (!$mime_type || strpos($mime_type, 'image/') !== 0) {
            unlink($temp_file_path); // Clean up temp file
            $this->logger?->error('Downloaded file is not a valid image type.', ['url' => $image_url, 'mime' => $mime_type ?: 'unknown']);
            return new WP_Error('bluesky_invalid_image_type', __('Downloaded file is not a valid image type.', 'data-machine'));
        }
        $image_content = file_get_contents($temp_file_path);
        if ($image_content === false) {
            unlink($temp_file_path); // Clean up temp file
            $this->logger?->error('Failed to read downloaded image content.', ['temp_path' => $temp_file_path]);
                return new WP_Error('bluesky_image_read_failed', __('Could not read downloaded image file.', 'data-machine'));
            }
            
        // 3. Upload to Bluesky Blob Storage
        $upload_url = rtrim($pds_url, '/') . '/xrpc/com.atproto.repo.uploadBlob';
        $args = [
                'method'  => 'POST',
                'headers' => [
                    'Content-Type'  => $mime_type,
                    'Authorization' => 'Bearer ' . $access_token,
                ],
            'body'    => $image_content,
            'timeout' => 60, // Increase timeout for uploads
        ];

        $this->logger?->info('Uploading image blob to Bluesky.', ['did' => $repo_did, 'upload_url' => $upload_url, 'mime' => $mime_type]);

        $response = wp_remote_post($upload_url, $args);
        unlink($temp_file_path); // Clean up temp file immediately after request
        unset($image_content); // Free memory

            if (is_wp_error($response)) {
            $this->logger?->error('Bluesky blob upload request failed (wp_remote_post error).', ['did' => $repo_did, 'error' => $response->get_error_message()]);
            return new WP_Error('bluesky_upload_request_failed', __('Could not connect to Bluesky server for image upload.', 'data-machine') . ' ' . $response->get_error_message());
            }

        $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
        $this->logger?->debug('Bluesky blob upload response received.', ['did' => $repo_did, 'code' => $response_code, 'body_snippet' => substr($response_body, 0, 200)]);

        if ($response_code !== 200) {
                $error_data = json_decode($response_body, true);
            $error_message = $error_data['message'] ?? 'Blob upload failed.';
            $this->logger?->error('Bluesky blob upload failed (non-200 response).', ['did' => $repo_did, 'code' => $response_code, 'response_message' => $error_message]);
            return new WP_Error('bluesky_upload_failed', sprintf(__( 'Bluesky image upload failed: %s (Code: %d)', 'data-machine' ), $error_message, $response_code));
        }

        $upload_result = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($upload_result['blob'])) {
            $this->logger?->error('Failed to decode Bluesky blob upload response or missing blob data.', ['did' => $repo_did, 'json_error' => json_last_error_msg()]);
            return new WP_Error('bluesky_upload_decode_error', __('Could not decode Bluesky image upload response.', 'data-machine'));
        }

        // 4. Add Alt Text to Blob (Separate Step? No, include in post record)
        // Alt text is now added directly to the embed record when creating the post.
        // We just need to return the successful blob upload result.

        $this->logger?->info('Image blob uploaded successfully.', ['did' => $repo_did, 'blob_cid' => $upload_result['blob']['ref']['$link'] ?? 'N/A']);
        return $upload_result; // Return the full upload result containing the blob object
    }

    /**
     * Creates a post record on Bluesky.
     *
     * @param string $pds_url The user's PDS URL.
     * @param string $access_token The access JWT.
     * @param string $repo_did The user's repository DID.
     * @param array $record The post record data (text, facets, embed, etc.).
     * @return array|WP_Error Post result array on success, WP_Error on failure.
     */
    private function _create_bluesky_post(string $pds_url, string $access_token, string $repo_did, array $record): array|WP_Error {
        $url = rtrim($pds_url, '/') . '/xrpc/com.atproto.repo.createRecord';
        $body = json_encode([
            'repo'       => $repo_did,
            'collection' => 'app.bsky.feed.post',
            // 'rkey'    => '...', // Optional: Client-specified record key (usually omitted for server generation)
            'record'     => $record
        ]);

        if (false === $body) {
            $this->logger?->error('Failed to JSON encode Bluesky post record body.', ['did' => $repo_did]);
            return new WP_Error('bluesky_post_encode_error', __('Could not encode post request.', 'data-machine'));
        }

        $args = [
            'method'  => 'POST',
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'body'    => $body,
            'timeout' => 30,
        ];

        $this->logger?->info('Attempting to create Bluesky post record.', ['did' => $repo_did, 'url' => $url]);
        $this->logger?->debug('Bluesky post record data', ['record' => $record]); // Log the full record being sent

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $this->logger?->error('Bluesky create post request failed (wp_remote_post error).', ['did' => $repo_did, 'error' => $response->get_error_message()]);
            return new WP_Error('bluesky_post_request_failed', __('Could not connect to Bluesky server to create post.', 'data-machine') . ' ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $this->logger?->debug('Bluesky create post response received.', ['did' => $repo_did, 'code' => $response_code, 'body_snippet' => substr($response_body, 0, 200)]);

        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = $error_data['message'] ?? 'Post creation failed.';
            $this->logger?->error('Bluesky post creation failed (non-200 response).', ['did' => $repo_did, 'code' => $response_code, 'response_message' => $error_message]);
            return new WP_Error('bluesky_post_failed', sprintf(__( 'Bluesky post creation failed: %s (Code: %d)', 'data-machine' ), $error_message, $response_code));
        }

        $post_result = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($post_result['uri'])) {
            $this->logger?->error('Failed to decode Bluesky post response or missing URI.', ['did' => $repo_did, 'json_error' => json_last_error_msg()]);
            return new WP_Error('bluesky_post_decode_error', __('Could not decode Bluesky post creation response.', 'data-machine'));
        }

        return $post_result; // Contains uri and cid
    }

    /**
     * Builds a user-friendly URL to the Bluesky post.
     *
     * @param string|null $uri The AT URI of the post (e.g., at://did:plc:abcd.../app.bsky.feed.post/3kabc...).
     * @param string|null $handle The user's handle.
     * @return string|null The web URL or null if URI/handle is invalid.
     */
    private function _build_post_url(?string $uri, ?string $handle): ?string {
        if (empty($uri) || empty($handle)) {
            return null;
        }
        // Example URI: at://did:plc:abcdefghijklmnopqrstuvwxyz/app.bsky.feed.post/3kabcdefghijklm
        // Desired URL: https://bsky.app/profile/user.bsky.social/post/3kabcdefghijklm
        if (preg_match('/at:\/\/[^\/]+\/app\.bsky\.feed\.post\/([a-zA-Z0-9]+)$/', $uri, $matches)) {
            $rkey = $matches[1]; // The record key (post ID)
            return sprintf('https://bsky.app/profile/%s/post/%s', $handle, $rkey);
        }
        return null; // Invalid URI format
    }

} 