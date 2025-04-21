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
    require_once DATA_MACHINE_PLUGIN_DIR . 'includes/interfaces/interface-data-machine-output-handler.php';
}

class Data_Machine_Output_Bluesky implements Data_Machine_Output_Handler_Interface {

    /**
     * Service Locator instance.
     * @var Data_Machine_Service_Locator
     */
    private $locator;

    /**
     * Logger instance.
     * @var Data_Machine_Logger
     */
    private $logger;

    /**
     * Constructor.
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     */
    public function __construct(Data_Machine_Service_Locator $locator) {
        $this->locator = $locator;
        $this->logger = $this->locator->get('logger');
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
        // Implementation will go here
        // $this->logger->log( 'Bluesky handle called. Implementation pending.', 'info', __METHOD__ );
        // return new WP_Error('bluesky_not_implemented', __('Bluesky output handler is not yet implemented.', 'data-machine'));

        $this->logger->info('Starting Bluesky output handling.', ['user_id' => $user_id]);

        // --- 1. Get Configuration ---
        $output_config = $module_job_config['output_config']['bluesky'] ?? [];
        // $handle = $output_config['bluesky_handle'] ?? '';
        // $encrypted_password = $output_config['bluesky_password'] ?? ''; // Restore original line (password is encrypted)
        $include_source = $output_config['bluesky_include_source'] ?? true;
        $enable_images = $output_config['bluesky_enable_images'] ?? true;

        // --- Get User Meta Credentials ---
        if (null === $user_id) {
             $this->logger->error('User ID context is missing for Bluesky output.', ['method' => __METHOD__]);
             return new WP_Error('bluesky_missing_user_id', __('User context is required for Bluesky authentication.', 'data-machine'));
        }
        $handle = get_user_meta($user_id, 'dm_bluesky_username', true);
        $encrypted_password = get_user_meta($user_id, 'dm_bluesky_app_password', true);

        // --- 2. Check Required Configuration ---
        if (empty($handle) || empty($encrypted_password)) { // Restore original line
            // Update error message to refer to API Keys page
            $this->logger->error('Bluesky handle or app password is missing in user meta.', ['user_id' => $user_id]);
            return new WP_Error('bluesky_config_missing', __('Bluesky handle and app password must be configured on the API / Auth page.', 'data-machine'));
        }

        // --- 3. Decrypt Password (RESTORE ORIGINAL) ---
        $password = '';
        try {
            // $this->logger->debug('Attempting to get encryption helper for decryption.', ['user_id' => $user_id]); // No longer needed
            // $encryption_helper = $this->locator->get('encryption_helper'); // No longer needed
            // if (!$encryption_helper instanceof Data_Machine_Encryption_Helper) { // Check no longer needed
            //     $this->logger->error('Encryption helper service not available during decryption.', ['user_id' => $user_id]);
            //     throw new Exception('Encryption Helper service is not available.');
            // }
            $this->logger->debug('Attempting to decrypt password using static helper.', ['user_id' => $user_id, 'encrypted_value_type' => gettype($encrypted_password)]);
            // $password = $encryption_helper->decrypt($encrypted_password); // Old call
            $password = Data_Machine_Encryption_Helper::decrypt($encrypted_password); // Use static call
            // Decryption returns false on failure, empty string if input was empty
            if ($password === false) {
                 $this->logger->error('Bluesky password decryption failed (returned false).', ['user_id' => $user_id]);
                 throw new Exception('Failed to decrypt Bluesky password.');
            }
            if (empty($password) && !empty($encrypted_password)) {
                 // This case means decryption succeeded but resulted in empty, AND the original encrypted value wasn't empty.
                 // This implies an empty password was originally encrypted.
                 $this->logger->warning('Bluesky password decryption resulted in an empty string, but encrypted value was not empty. Ensure a valid password was saved.', ['user_id' => $user_id]);
                 throw new Exception('Decrypted password is empty.');
            }
             if (empty($password) && empty($encrypted_password)) {
                 // This means the stored value was empty, so decryption correctly returns empty.
                 // This is technically valid, but we need a password to authenticate.
                 $this->logger->error('Bluesky password configuration is empty.', ['user_id' => $user_id]);
                 throw new Exception('Bluesky password is empty in configuration.');
             }
            $this->logger->debug('Bluesky app password decrypted successfully.', ['user_id' => $user_id]);
        } catch (\Exception $e) {
            $this->logger->error('Bluesky password decryption failed: ' . $e->getMessage(), ['user_id' => $user_id, 'exception_type' => get_class($e)]);
            return new WP_Error('bluesky_decrypt_failed', __('Could not decrypt Bluesky app password. Please re-save the module settings.', 'data-machine'));
        }

        // --- 4. Authenticate & Get Session ---
        $session_data = $this->_get_bluesky_session($handle, $password);
        // IMPORTANT: Clear the decrypted password from memory as soon as it's no longer needed.
        unset($password); // Restore unset

        if (is_wp_error($session_data)) {
            $this->logger->error('Bluesky authentication failed.', ['user_id' => $user_id, 'error_code' => $session_data->get_error_code(), 'error_message' => $session_data->get_error_message()]);
            return $session_data; // Return the WP_Error from authentication
        }
        $access_token = $session_data['accessJwt'] ?? null;
        $did = $session_data['did'] ?? null; // User's Decentralized Identifier
        $pds_url = $session_data['pds_url'] ?? 'https://bsky.social'; // Default PDS if not returned

        if (empty($access_token) || empty($did)) {
             $this->logger->error('Bluesky session data is incomplete after authentication (missing token or DID).', ['user_id' => $user_id, 'session_data' => $session_data]);
             return new WP_Error('bluesky_session_incomplete', __('Bluesky authentication succeeded but returned incomplete session data.', 'data-machine'));
        }
        $this->logger->info('Bluesky authentication successful.', ['user_id' => $user_id, 'did' => $did, 'pds' => $pds_url]);

        // --- 5. Parse AI Output ---
        // Ensure the parser class is loaded
        $parser_path = DATA_MACHINE_PATH . 'includes/helpers/class-data-machine-ai-response-parser.php';
        if (!class_exists('Data_Machine_AI_Response_Parser') && file_exists($parser_path)) {
            require_once $parser_path;
        }
        if (!class_exists('Data_Machine_AI_Response_Parser')) {
             $this->logger->error('Data_Machine_AI_Response_Parser class not found.', ['path' => $parser_path]);
             return new WP_Error('bluesky_parser_missing', __('AI Response Parser is missing.', 'data-machine'));
        }

        $parser = new Data_Machine_AI_Response_Parser($ai_output_string);
        $parser->parse();
        // For Bluesky, we'll likely just use the main content. Title could be prepended if desired.
        $post_text = $parser->get_content(); 
        if (empty($post_text)) {
             $this->logger->warning('Parsed AI output for Bluesky is empty.', ['user_id' => $user_id, 'original_output' => $ai_output_string]);
             // Allow empty posts for now? Or return error?
             // Let's return an error for now, as an empty post is usually not intended.
             return new WP_Error('bluesky_empty_content', __('Parsed AI output is empty, cannot post to Bluesky.', 'data-machine'));
        }

        // --- 6. Format Post Text & Handle Facets ---
        $source_url = $input_metadata['source_url'] ?? null;
        $facets = []; // Initialize facets array
        // TODO: Implement general facet generation for links/mentions within $post_text itself later

        $bluesky_char_limit = 300; // Bluesky's grapheme limit, approximated with characters.
        $ellipsis = '…';
        $ellipsis_len = mb_strlen($ellipsis, 'UTF-8'); // Usually 1
        $text_was_truncated = false;

        // Determine if we need to append the source link and calculate its length
        $link_text = '';
        $link_text_len = 0;
        $link_prefix = "\n\n";
        $prefix_byte_length = 0;
        $url_byte_length = 0;
        $append_link = $include_source && !empty($source_url);

        if ($append_link) {
            $link_text = $link_prefix . $source_url;
            $link_text_len = mb_strlen($link_text, 'UTF-8');
            $prefix_byte_length = mb_strlen($link_prefix, '8bit');
            $url_byte_length = mb_strlen($source_url, '8bit');
        }

        // Calculate the available space for the main text
        $available_main_text_len = $bluesky_char_limit - $link_text_len;

        // Check if the link itself is too long
        if ($append_link && $available_main_text_len < $ellipsis_len) { // Need at least space for ellipsis if main text is truncated
            $this->logger->warning('Source link is too long to fit within Bluesky character limit, omitting link.', [
                'user_id' => $user_id,
                'limit' => $bluesky_char_limit,
                'link_length' => $link_text_len,
                'source_url' => $source_url
            ]);
            $append_link = false; // Don't append the link
            $link_text = '';
            $link_text_len = 0;
            $available_main_text_len = $bluesky_char_limit; // Recalculate available space without link
        }

        // Check if main text needs truncation
        $main_text_len = mb_strlen($post_text, 'UTF-8');

        if ($main_text_len > $available_main_text_len) {
            $text_was_truncated = true;
            $truncate_at = $available_main_text_len - $ellipsis_len;
            if ($truncate_at < 0) $truncate_at = 0; // Ensure non-negative index
            
            $post_text = mb_substr($post_text, 0, $truncate_at, 'UTF-8') . $ellipsis;
            
            $this->logger->warning('Bluesky main post text truncated.', [
                'user_id' => $user_id,
                'original_length' => $main_text_len,
                'truncated_length' => mb_strlen($post_text, 'UTF-8'),
                'limit_for_text' => $available_main_text_len,
                'including_link' => $append_link
            ]);

            // Any pre-existing facets in the main text are now invalid
            // If we planned to support facets within the main text, clear them here.
            // $facets = []; // For now, we only have the potential link facet, which hasn't been added yet.
        }

        // Now, append the link if required and possible
        if ($append_link) {
            // Calculate final byte positions for the facet *after* potential truncation
            $final_main_byte_length = mb_strlen($post_text, '8bit');
            
            $source_link_facet = [
                'index' => [
                    'byteStart' => $final_main_byte_length + $prefix_byte_length,
                    'byteEnd'   => $final_main_byte_length + $prefix_byte_length + $url_byte_length,
                ],
                'features' => [
                    [
                        '$type' => 'app.bsky.richtext.facet#link',
                        'uri'   => $source_url
                    ]
                ]
            ];
            $facets[] = $source_link_facet;
            $post_text .= $link_text;
            $this->logger->debug('Appended source link and generated facet.', ['facet' => $source_link_facet, 'final_text_length' => mb_strlen($post_text, 'UTF-8')]);
        }

        // Final check (should not be needed with the logic above, but as a safeguard)
        $final_char_count = mb_strlen($post_text, 'UTF-8');
        if ($final_char_count > $bluesky_char_limit) {
             $this->logger->error('Post text still exceeds limit after truncation logic.', [
                'user_id' => $user_id,
                'final_length' => $final_char_count,
                'limit' => $bluesky_char_limit,
                'text' => mb_substr($post_text, 0, 50) . '...'
             ]);
             // As a fallback, hard truncate without ellipsis or facets
             $post_text = mb_substr($post_text, 0, $bluesky_char_limit, 'UTF-8');
             $facets = []; 
        }

        // --- 7. Image Handling (Implementation Pending) ---
        $embed_object = null;
        $image_source_url = $input_metadata['image_source_url'] ?? null;
        $image_alt_text = $input_metadata['image_alt_text'] ?? '';

        if ($enable_images && !empty($image_source_url)) {
             $this->logger->info('Image found in metadata, attempting to process for Bluesky.', ['url' => $image_source_url, 'user_id' => $user_id]);
             $upload_result = $this->_upload_bluesky_image($pds_url, $access_token, $did, $image_source_url, $image_alt_text);
             
             if (is_wp_error($upload_result)) {
                 $this->logger->warning('Failed to upload image to Bluesky, proceeding without image.', ['error_code' => $upload_result->get_error_code(), 'error_message' => $upload_result->get_error_message(), 'user_id' => $user_id]);
                 // Continue without image
             } elseif (!empty($upload_result['embed'])) {
                 $this->logger->info('Image processed successfully for Bluesky embed.', ['user_id' => $user_id]);
                 $embed_object = $upload_result['embed'];
             }
        }

        // --- 8. Construct Post Record --- 
        $record = [
            // '$type' => 'app.bsky.feed.post', // Type is added within the API call wrapper
            'text' => $post_text,
            'createdAt' => gmdate('Y-m-d\TH:i:s.\0\0\0\Z'), // Correct ISO 8601 format with milliseconds
            // 'langs' => ['en'], // Optional: Consider detecting language?
        ];
        if (!empty($facets)) {
            $record['facets'] = $facets;
        }
        if (!empty($embed_object)) {
            $record['embed'] = $embed_object;
        }
        
        // --- Log the final record text being sent (DEBUG) ---
        $this->logger->debug('Bluesky record text prepared for sending.', ['user_id' => $user_id, 'did' => $did, 'text_content' => $record['text']]);

        // --- 9. Create Post via API (Implementation Pending) ---
        $this->logger->info('Sending post request to Bluesky.', ['user_id' => $user_id, 'did' => $did]);
        $create_result = $this->_create_bluesky_post($pds_url, $access_token, $did, $record);

        if (is_wp_error($create_result)) {
            $this->logger->error('Failed to create Bluesky post.', ['error_code' => $create_result->get_error_code(), 'error_message' => $create_result->get_error_message(), 'user_id' => $user_id]);
            return $create_result;
        }

        // --- 10. Handle Success Response ---
        $post_uri = $create_result['uri'] ?? ''; 
        $post_cid = $create_result['cid'] ?? ''; 
        $post_url = ''; 
        if ($post_uri) {
             $uri_parts = explode('/', $post_uri);
             $rkey = end($uri_parts);
             $profile_handle = $session_data['handle'] ?? $handle; // Use handle from session if available
             if ($rkey && $profile_handle) {
                $post_url = sprintf('https://bsky.app/profile/%s/post/%s', $profile_handle, $rkey);
             }
        }

        $success_message = sprintf(__('Successfully posted to Bluesky: %s', 'data-machine'), $post_url ?: $post_uri);
        $this->logger->info($success_message, ['uri' => $post_uri, 'cid' => $post_cid, 'url' => $post_url, 'user_id' => $user_id]);
        
        return [
            'success' => true,
            'output_id' => $post_uri, // Use the AT URI as the unique ID
            'output_url' => $post_url,
            'message' => $success_message,
            'raw_response' => $create_result // Include raw response for potential debugging/data use
        ];
    }

    /**
     * Defines the settings fields specific to the Bluesky output handler.
     *
     * @param array $current_config Current configuration values for this handler (optional).
     * @return array An array defining the settings fields.
     */
    public function get_settings_fields(array $current_config = []): array {
        return [
            'bluesky_include_source' => [
                'type' => 'checkbox',
                'label' => __('Include Source Link', 'data-machine'),
                'default' => true,
                'description' => __('Append the original source link to the Bluesky post if available in the input metadata?', 'data-machine')
            ],
            'bluesky_enable_images' => [
                'type' => 'checkbox',
                'label' => __('Enable Image Posting', 'data-machine'),
                'default' => true,
                'description' => __('Allow posting images to Bluesky if available in input metadata?', 'data-machine')
            ],
        ];
    }

    /**
     * Sanitizes the settings specific to this output handler.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings array.
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        // Sanitize Handle
        // $handle = sanitize_text_field($raw_settings['bluesky_handle'] ?? '');
        // // Basic validation: check if it looks like a handle (contains at least one dot)
        // $sanitized['bluesky_handle'] = (strpos($handle, '.') !== false) ? $handle : '';

        // Sanitize and encrypt password (only if a new password is provided)
        // $password = $raw_settings['bluesky_password'] ?? '';
        // if (!empty($password)) {
        //     try {
        //         // $encryption_helper = $this->locator->get('encryption_helper'); // Get from locator
        //         // if (!$encryption_helper instanceof Data_Machine_Encryption_Helper) {
        //         //    throw new Exception('Encryption Helper service is not available.');
        //         // }
        //         // $encrypted_password = $encryption_helper->encrypt($password); // Use instance method
        //         $encrypted_password = Data_Machine_Encryption_Helper::encrypt($password); // Use static method
        //         if ($encrypted_password === false) {
        //             // Log encryption failure, but maybe don't store anything?
        //             // Or store an empty string and log an error?
        //             // For now, let's store empty and rely on logs.
        //              $this->logger->error('Bluesky password encryption failed.');
        //              $sanitized['bluesky_password'] = ''; 
        //         } else {
        //              $sanitized['bluesky_password'] = $encrypted_password;
        //         }
        //     } catch (\Exception $e) {
        //         $this->logger->error('Bluesky password encryption failed: ' . $e->getMessage());
        //         $sanitized['bluesky_password'] = ''; // Store empty on error
        //     }
        // } else {
        //     // If password field is empty, keep the existing encrypted password (don't overwrite with empty)
        //     // This requires fetching the current config which isn't ideal here.
        //     // Let's assume the main save logic handles merging and doesn't pass empty password
        //     // if it wasn't submitted. If it *was* submitted empty, we need a way to delete?
        //     // For now, we only set the value if it's not empty.
        //     // If the key exists in $raw_settings but is empty, it implies user cleared it?
        //     // This logic needs careful handling during the save process itself.
        //     // For now, if it's empty, we won't include it in the sanitized array,
        //     // assuming the main save logic will handle merging.
        // }

        // Sanitize include source
        $sanitized['bluesky_include_source'] = !empty($raw_settings['bluesky_include_source']);

        // Sanitize Checkboxes
        $sanitized['bluesky_enable_images'] = !empty($raw_settings['bluesky_enable_images']);

        return $sanitized;
    }

    /**
     * Returns the user-friendly label for this output handler.
     *
     * @return string The label.
     */
    public static function get_label(): string {
        return __( 'Bluesky', 'data-machine' );
    }

    // --- Private Helper Methods (Placeholders) ---

    /**
     * Authenticates with Bluesky and retrieves session data.
     *
     * @param string $handle User handle or email.
     * @param string $password User app password (decrypted).
     * @return array|WP_Error Session data array on success, WP_Error on failure.
     */
    private function _get_bluesky_session(string $handle, string $password): array|WP_Error {
        // TODO: Implement API call to com.atproto.server.createSession
        // $this->logger->warning('Bluesky session retrieval (_get_bluesky_session) not implemented yet.', __METHOD__);
        // For testing, return a placeholder error or mock data
        // return new WP_Error('bluesky_auth_not_implemented', __('Bluesky authentication logic is not implemented yet.', 'data-machine'));
        
        $api_url = 'https://bsky.social/xrpc/com.atproto.server.createSession'; // Use the main PDS for initial login
        $this->logger->debug('Attempting Bluesky login.', ['handle' => $handle, 'api_url' => $api_url]);

        $body = json_encode([
            'identifier' => $handle,
            'password'   => $password,
        ]);

        if (false === $body) {
             $this->logger->error('Failed to JSON encode Bluesky login request body.', ['handle' => $handle]);
             return new WP_Error('bluesky_json_encode_failed', __('Failed to prepare login request data.', 'data-machine'));
        }

        $response = wp_remote_post($api_url, [
            'method'      => 'POST',
            'headers'     => ['Content-Type' => 'application/json'],
            'body'        => $body,
            'timeout'     => 30, // Increased timeout for API call
            'sslverify'   => true, // Keep SSL verification enabled
        ]);

        // Check for WordPress HTTP API errors
        if (is_wp_error($response)) {
            $this->logger->error('Bluesky login HTTP request failed.', ['handle' => $handle, 'error_code' => $response->get_error_code(), 'error_message' => $response->get_error_message()]);
            return new WP_Error('bluesky_http_error', __('Could not connect to Bluesky API: ', 'data-machine') . $response->get_error_message());
        }

        // Check HTTP status code
        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $this->logger->debug('Bluesky login API response received.', ['handle' => $handle, 'http_code' => $http_code]);

        if ($http_code >= 400) {
            $error_data = json_decode($response_body, true);
            $api_error_message = $error_data['message'] ?? 'Unknown API error';
            $api_error_type = $error_data['error'] ?? 'UnknownErrorType';
            $this->logger->error('Bluesky API login error.', [
                'handle' => $handle,
                'http_code' => $http_code,
                'api_error' => $api_error_type,
                'api_message' => $api_error_message,
                'response_body' => $response_body // Log full body on error
            ]);
            return new WP_Error('bluesky_api_error_' . $api_error_type, sprintf(__('Bluesky API Error (%d): %s', 'data-machine'), $http_code, $api_error_message));
        }

        // Decode successful response
        $session_data = json_decode($response_body, true);
        if (null === $session_data || !isset($session_data['accessJwt']) || !isset($session_data['did'])) {
            $this->logger->error('Bluesky login response JSON decoding failed or missing required fields.', [
                'handle' => $handle,
                'http_code' => $http_code,
                'response_body' => $response_body
            ]);
            return new WP_Error('bluesky_decode_error', __('Failed to understand the response from Bluesky after successful login.', 'data-machine'));
        }

        // Add default PDS URL if not provided in the response (some PDS might not return it)
        if (!isset($session_data['pdsUrl']) && !isset($session_data['pds_url'])) { // Check both camelCase and snake_case
            $session_data['pds_url'] = 'https://bsky.social'; 
            $this->logger->info('PDS URL not found in session response, defaulting to bsky.social', ['handle' => $handle]);
        } elseif (isset($session_data['pdsUrl'])) {
             // Normalize to snake_case if camelCase is present
             $session_data['pds_url'] = $session_data['pdsUrl'];
             unset($session_data['pdsUrl']);
        }

        $this->logger->info('Bluesky session obtained successfully.', ['handle' => $handle, 'did' => $session_data['did']]);
        return $session_data; // Return the full session data array
    }

    /**
     * Uploads an image to Bluesky PDS and returns embed data.
     *
     * @param string $pds_url The user's PDS URL.
     * @param string $access_token The access token.
     * @param string $repo_did The user's repository DID.
     * @param string $image_url The URL of the image to upload.
     * @param string $alt_text Alt text for the image.
     * @return array|WP_Error Embed structure on success, WP_Error on failure.
     */
    private function _upload_bluesky_image(string $pds_url, string $access_token, string $repo_did, string $image_url, string $alt_text): array|WP_Error {
        // TODO: Implement image download, API call to com.atproto.repo.uploadBlob
        // $this->logger->warning('Bluesky image upload (_upload_bluesky_image) not implemented yet.', __METHOD__);
        // return new WP_Error('bluesky_image_upload_not_implemented', __('Bluesky image upload logic is not implemented yet.', 'data-machine'));
        
        $temp_image_path = null; // Initialize temp path
        
        try {
            $this->logger->info('Attempting to download image for Bluesky upload.', ['url' => $image_url]);

            // --- 1. Download Image --- 
            // Ensure download_url() is available (might not be in cron contexts)
            if (!function_exists('download_url')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            // Set a reasonable timeout (e.g., 30 seconds)
            $temp_image_path = download_url($image_url, 30);
            
            if (is_wp_error($temp_image_path)) {
                $this->logger->error('Failed to download image.', ['url' => $image_url, 'error' => $temp_image_path->get_error_message()]);
                return new WP_Error('bluesky_image_download_failed', __('Could not download image: ', 'data-machine') . $temp_image_path->get_error_message());
            }
            $this->logger->debug('Image downloaded successfully to temporary path.', ['url' => $image_url, 'path' => $temp_image_path]);

            // --- 2. Get Image Info & Content ---
            $mime_type = mime_content_type($temp_image_path);
            if (!$mime_type || !str_starts_with($mime_type, 'image/')) {
                 // Fallback if mime_content_type fails or returns unexpected type
                 if (function_exists('wp_get_image_mime')) { 
                     $mime_type = wp_get_image_mime($temp_image_path);
                 }
                 // Final check
                 if (!$mime_type || !str_starts_with($mime_type, 'image/')) { 
                    $this->logger->error('Failed to determine valid image MIME type for downloaded file.', ['path' => $temp_image_path, 'detected_mime' => $mime_type]);
                    return new WP_Error('bluesky_invalid_mime_type', __('Downloaded file is not a valid image type.', 'data-machine'));
                 }
            }
            $this->logger->debug('Determined image MIME type.', ['path' => $temp_image_path, 'mime' => $mime_type]);

            $image_content = file_get_contents($temp_image_path);
            if (false === $image_content) {
                $this->logger->error('Failed to read downloaded image content.', ['path' => $temp_image_path]);
                return new WP_Error('bluesky_image_read_failed', __('Could not read downloaded image file.', 'data-machine'));
            }
            
            // --- 3. Upload Blob --- 
            $api_url = rtrim($pds_url, '/') . '/xrpc/com.atproto.repo.uploadBlob';
            $this->logger->debug('Attempting to upload image blob to Bluesky.', ['did' => $repo_did, 'api_url' => $api_url, 'mime' => $mime_type]);
            
            $response = wp_remote_post($api_url, [
                'method'  => 'POST',
                'headers' => [
                    'Content-Type'  => $mime_type,
                    'Authorization' => 'Bearer ' . $access_token,
                ],
                'body'      => $image_content,
                'timeout'   => 60, // Longer timeout for potentially large uploads
                'sslverify' => true,
            ]);
            // Clear image content from memory after use
            unset($image_content); 

            // --- 4. Handle Upload Response --- 
            if (is_wp_error($response)) {
                $this->logger->error('Bluesky upload blob HTTP request failed.', ['did' => $repo_did, 'error_code' => $response->get_error_code(), 'error_message' => $response->get_error_message()]);
                return new WP_Error('bluesky_upload_http_error', __('Could not send image upload request to Bluesky API: ', 'data-machine') . $response->get_error_message());
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $this->logger->debug('Bluesky upload blob API response received.', ['did' => $repo_did, 'http_code' => $http_code]);

            if ($http_code !== 200) { // uploadBlob expects 200 OK
                $error_data = json_decode($response_body, true);
                $api_error_message = $error_data['message'] ?? 'Unknown API error during image upload';
                $api_error_type = $error_data['error'] ?? 'UnknownUploadErrorType';
                $this->logger->error('Bluesky API upload blob error.', [
                    'did' => $repo_did,
                    'http_code' => $http_code,
                    'api_error' => $api_error_type,
                    'api_message' => $api_error_message,
                    'response_body' => $response_body
                ]);
                return new WP_Error('bluesky_upload_api_error_' . $api_error_type, sprintf(__('Bluesky API Error Uploading Image (%d): %s', 'data-machine'), $http_code, $api_error_message));
            }
            
            // Decode successful response
            $blob_data = json_decode($response_body, true);
            if (null === $blob_data || !isset($blob_data['blob']) || !isset($blob_data['blob']['$type']) || $blob_data['blob']['$type'] !== 'blob' || !isset($blob_data['blob']['ref']['$link'])) {
                $this->logger->error('Bluesky upload blob response JSON decoding failed or missing required blob fields.', [
                    'did' => $repo_did,
                    'http_code' => $http_code,
                    'response_body' => $response_body
                ]);
                return new WP_Error('bluesky_upload_decode_error', __('Failed to understand the response from Bluesky after successful image upload.', 'data-machine'));
            }

            $this->logger->info('Bluesky image blob uploaded successfully.', ['did' => $repo_did, 'cid' => $blob_data['blob']['ref']['$link']]);

            // --- 5. Construct Embed Object --- 
            // Truncate alt text to 1000 bytes (Bluesky limit)
            $safe_alt_text = mb_strcut($alt_text, 0, 1000, 'UTF-8');

            $embed_object = [
                '$type' => 'app.bsky.embed.images',
                'images' => [
                    [
                        'image' => $blob_data['blob'], // Use the whole blob object from response
                        'alt' => $safe_alt_text
                    ]
                ]
            ];

            return ['embed' => $embed_object];

        } catch (\Exception $e) {
             $this->logger->error('Exception during Bluesky image processing: ' . $e->getMessage(), ['exception' => $e]);
             return new WP_Error('bluesky_image_exception', __('An unexpected error occurred while processing the image for Bluesky.', 'data-machine'));
        } finally {
             // --- 6. Cleanup --- 
             if ($temp_image_path && file_exists($temp_image_path)) {
                 if (@unlink($temp_image_path)) {
                    $this->logger->debug('Temporary image file deleted successfully.', ['path' => $temp_image_path]);
                 } else {
                    $this->logger->warning('Failed to delete temporary image file.', ['path' => $temp_image_path]);
                 }
             }
        }
    }

    /**
     * Creates a post record on Bluesky.
     *
     * @param string $pds_url The user's PDS URL.
     * @param string $access_token The access token.
     * @param string $repo_did The user's repository DID.
     * @param array $record The post record data.
     * @return array|WP_Error API response array on success, WP_Error on failure.
     */
    private function _create_bluesky_post(string $pds_url, string $access_token, string $repo_did, array $record): array|WP_Error {
        // TODO: Implement API call to com.atproto.repo.createRecord
        // $this->logger->warning('Bluesky post creation (_create_bluesky_post) not implemented yet.', __METHOD__);
        // return new WP_Error('bluesky_create_post_not_implemented', __('Bluesky post creation logic is not implemented yet.', 'data-machine'));

        $api_url = rtrim($pds_url, '/') . '/xrpc/com.atproto.repo.createRecord';
        $this->logger->debug('Attempting to create Bluesky post record.', ['did' => $repo_did, 'api_url' => $api_url]);

        // Construct the full request body for createRecord
        $request_body = [
            'repo' => $repo_did,               // User's repository DID
            'collection' => 'app.bsky.feed.post', // NSID for post records
            'record' => $record                 // The actual post record data
        ];

        $body = json_encode($request_body);

        if (false === $body) {
             $this->logger->error('Failed to JSON encode Bluesky createRecord request body.', ['did' => $repo_did]);
             return new WP_Error('bluesky_create_json_encode_failed', __('Failed to prepare post creation request data.', 'data-machine'));
        }

        $response = wp_remote_post($api_url, [
            'method'  => 'POST',
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'body'      => $body,
            'timeout'   => 45, // Slightly longer timeout for creation
            'sslverify' => true,
        ]);

        // Check for WordPress HTTP API errors
        if (is_wp_error($response)) {
            $this->logger->error('Bluesky create post HTTP request failed.', ['did' => $repo_did, 'error_code' => $response->get_error_code(), 'error_message' => $response->get_error_message()]);
            return new WP_Error('bluesky_create_http_error', __('Could not send post creation request to Bluesky API: ', 'data-machine') . $response->get_error_message());
        }

        // Check HTTP status code (Expecting 200 OK for createRecord)
        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $this->logger->debug('Bluesky create post API response received.', ['did' => $repo_did, 'http_code' => $http_code]);

        if ($http_code !== 200) { // createRecord returns 200 on success
            $error_data = json_decode($response_body, true);
            $api_error_message = $error_data['message'] ?? 'Unknown API error during post creation';
            $api_error_type = $error_data['error'] ?? 'UnknownCreateErrorType';
            $this->logger->error('Bluesky API create post error.', [
                'did' => $repo_did,
                'http_code' => $http_code,
                'api_error' => $api_error_type,
                'api_message' => $api_error_message,
                'response_body' => $response_body 
            ]);
            // Include record data in WP_Error context for debugging?
            return new WP_Error('bluesky_create_api_error_' . $api_error_type, sprintf(__('Bluesky API Error Creating Post (%d): %s', 'data-machine'), $http_code, $api_error_message), ['request_record' => $record]);
        }

        // Decode successful response
        $result_data = json_decode($response_body, true);
        if (null === $result_data || !isset($result_data['uri']) || !isset($result_data['cid'])) {
            $this->logger->error('Bluesky create post response JSON decoding failed or missing required fields.', [
                'did' => $repo_did,
                'http_code' => $http_code,
                'response_body' => $response_body
            ]);
            return new WP_Error('bluesky_create_decode_error', __('Failed to understand the response from Bluesky after successful post creation.', 'data-machine'));
        }

        $this->logger->info('Bluesky post created successfully.', ['did' => $repo_did, 'uri' => $result_data['uri']]);
        return $result_data; // Return the success data ('uri', 'cid')
    }

    // Add _make_bluesky_api_request helper later
} 