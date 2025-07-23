<?php
/**
 * Handles the 'Threads' output type.
 *
 * Posts content to a specified Threads account using the Threads API.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/output
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Note: Requires integration with an OAuth flow for Threads authentication.
// Note: Requires error handling and potentially a library/SDK for API interaction.

class Data_Machine_Output_Threads extends Data_Machine_Base_Output_Handler {


    const THREADS_API_BASE_URL = 'https://graph.threads.net/v1.0'; // Use constant

    /** @var Data_Machine_Handler_HTTP_Service */
    private $http_service;

    /**
	 * Constructor.
	 *
     * @param Data_Machine_Handler_HTTP_Service $http_service HTTP service for API calls.
     * @param Data_Machine_Logger|null $logger Optional Logger instance.
	 */
	public function __construct(Data_Machine_Handler_HTTP_Service $http_service, ?Data_Machine_Logger $logger = null) {
        parent::__construct($logger);
        $this->http_service = $http_service;
	}

    /**
	 * Handles posting the AI output to Threads.
     * Implements the two-step process: create container, then publish.
	 *
	 * @param string $ai_output_string The finalized string from the AI.
	 * @param array $module_job_config Configuration specific to this output job.
	 * @param int|null $user_id The ID of the user whose Threads account should be used.
	 * @param array $input_metadata Metadata from the original input source (e.g., original URL, timestamp).
	 * @return array|WP_Error Result array on success (e.g., ['status' => 'success', 'threads_media_id' => '...', 'output_url' => '...']), WP_Error on failure.
	 */
	public function handle( string $ai_output_string, array $module_job_config, ?int $user_id, array $input_metadata ): array|WP_Error {
        $this->logger?->info('Starting Threads output handling.', ['user_id' => $user_id]);

        // 1. Get config
        $output_config = $module_job_config['output_config']['threads'] ?? []; // Use 'threads' sub-key
        // TODO: Define and retrieve relevant config options (e.g., post_type: text/image/video)

        // 2. Ensure user_id is provided
        if (empty($user_id)) {
            $this->logger?->error('Threads Output: User ID context is missing.');
            return new WP_Error('threads_missing_user_id', __('Cannot post to Threads without a specified user account.', 'data-machine'));
        }

        // 3. Get authenticated access token AND Threads User ID for the WP user
        $access_token = Data_Machine_OAuth_Threads::get_access_token($user_id);
        if (empty($access_token)) {
             $this->logger?->error('Threads Output: Failed to get access token or token is invalid/expired.', ['user_id' => $user_id]);
            return new WP_Error('threads_auth_failed', __('Failed to authenticate with Threads. Please check credentials on the API Keys page or re-authenticate.', 'data-machine'));
        }

        // Get associated Threads user ID from stored account details
        $account_details = get_user_meta($user_id, 'data_machine_threads_auth_account', true);
        // Use the stored Page ID as the identifier for posting, based on Graph API Explorer tests
        $posting_entity_id = $account_details['page_id'] ?? null;

        if (empty($posting_entity_id)) {
            $this->logger?->error('Threads Output: Could not find the necessary Posting Entity ID (Page ID) associated with the WP user.', ['user_id' => $user_id, 'account_details' => $account_details]);
            return new WP_Error('threads_posting_entity_id_missing', __('Could not find the Page ID needed for posting. Please try re-authenticating the Threads account.', 'data-machine'));
        }

        // 4. Parse AI output
        // Use the trait method if available
        if (method_exists($this, 'parse_ai_output')) {
            $parsed_output = $this->parse_ai_output($ai_output_string);
        } else {
            // Fallback if trait method isn't found
            $parser = new Data_Machine_AI_Response_Parser( $ai_output_string );
            $parser->parse();
            $parsed_output = [
                'title' => $parser->get_title(),
                'content' => $parser->get_content(),
                'hashtags' => $parser->get_hashtags(),
                'primary_link' => $parser->get_primary_link()
            ];
        }
        $content = $parsed_output['content'] ?? '';
        $hashtags = $parsed_output['hashtags'] ?? []; // Threads doesn't explicitly support hashtags in API, but we include in text.
        $primary_link_from_ai = $parsed_output['primary_link'] ?? null;

        if (empty($content)) {
            $this->logger?->warning('Threads Output: Parsed AI output content is empty.', ['user_id' => $user_id]);
            return new WP_Error('threads_empty_content', __('Cannot post empty content to Threads.', 'data-machine'));
        }

        // Append hashtags to content if any
        if (!empty($hashtags)) {
            $content .= "\n\n" . implode(' ', array_map(function($tag) { return '#' . trim($tag, " \t\n\r\0\x0B#"); }, $hashtags));
        }

        // 5. Determine media type and prepare container API parameters
        $media_type = 'TEXT';
        $create_params = ['text' => $content];
        $image_url = $input_metadata['image_source_url'] ?? null;
        $video_url = null; // Placeholder - Video upload is complex
        $source_link = $primary_link_from_ai ?: ($input_metadata['source_url'] ?? null);

        // TODO: Add logic for CAROUSEL posts (requires multiple container creations)

        if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
            // TODO: Add config check to enable/disable images
            $media_type = 'IMAGE';
            unset($create_params['text']); // Remove text param for image post
            $create_params['image_url'] = $image_url;
             // Add text as caption via reply_to_id after publishing?
             // Or add text back if API allows captioning image_url directly?
             // Let's try adding text back for image posts based on recent API updates allowing text with media.
             $create_params['text'] = $content;
        } elseif (!empty($video_url) && filter_var($video_url, FILTER_VALIDATE_URL)) {
            // Video requires resumable upload - complex, not implemented here.
            $this->logger?->warning('Threads API: Video posting via URL not supported, requires resumable upload. Posting as text instead.', ['video_url' => $video_url]);
            $media_type = 'TEXT'; // Fallback to TEXT
            // Add source link if available for text post
            if (!empty($source_link) && filter_var($source_link, FILTER_VALIDATE_URL)) {
                $create_params['link_attachment']['url'] = $source_link; // Check if link_attachment is valid for TEXT
            }
        } elseif ($media_type === 'TEXT' && !empty($source_link) && filter_var($source_link, FILTER_VALIDATE_URL)) {
             // For TEXT posts, use link_attachment if source link exists
             // $create_params['link_attachment']['url'] = $source_link; // Check if link_attachment is valid
             // NOTE: As of late 2024, link_attachment is NOT supported for TEXT posts. Link must be in the text itself for preview.
             // Ensure link is part of the $content if not already.
             if (strpos($content, $source_link) === false) {
                 $content .= "\n\n" . $source_link;
                 $create_params['text'] = $content; // Update text param
             }
        } else {
        }

        $create_params['media_type'] = $media_type;
        $create_params['access_token'] = $access_token; // Add token for creation

        // 6. Step 1: Create Media Container
        $container_id = null;
        try {
            // Use the Posting Entity ID (Page ID) in the API endpoint
            $create_url = self::THREADS_API_BASE_URL . "/{$posting_entity_id}/threads";

            $this->logger?->debug('Threads API: Creating container request.', ['url' => $create_url, 'params_keys' => array_keys($create_params), 'user_id' => $user_id]);

            // Use HTTP service - replaces duplicated HTTP code
            $response = $this->http_service->post($create_url, $create_params, [], 'Threads Create API');

            if (is_wp_error($response)) {
                $this->logger?->error('Threads API Error: Create container failed.', ['error' => $response->get_error_message(), 'user_id' => $user_id]);
                return $response;
            }

            $body = $response['body'];
            $http_code = $response['status_code'];

            // Parse JSON response with error handling
            $data = $this->http_service->parse_json($body, 'Threads Create API');
            if (is_wp_error($data)) {
                return $data;
            }

            if ($http_code >= 200 && $http_code < 300 && isset($data['id'])) {
                $container_id = $data['id'];
                $this->logger?->info('Threads API: Container created successfully.', ['container_id' => $container_id, 'user_id' => $user_id]);
            } else {
                $error_message = $data['error']['message'] ?? 'Failed to create Threads container.';
                $error_code_th = $data['error']['code'] ?? 'UnknownCode';
                $this->logger?->error('Threads API Error (Create Container):', ['http_code' => $http_code, 'th_error' => $data['error'] ?? $body, 'user_id' => $user_id]);
                return new WP_Error('threads_container_error_' . $error_code_th, $error_message, ['api_response' => $data]);
            }

        } catch (\Exception $e) {
            $this->logger?->error('Threads Output Exception (Create Container): ' . $e->getMessage(), ['user_id' => $user_id, 'trace' => $e->getTraceAsString()]);
            return new WP_Error('threads_exception_container', $e->getMessage());
        }

        // 7. Step 2: Publish Media Container
        if ($container_id) {
            // Publishing is async. We need to poll the container status OR use a fixed wait.
            // Let's try a simple wait first. If it fails, polling is needed.
            sleep(5); // Wait 5 seconds for processing (adjust as needed)

            try {
                // Use the Posting Entity ID (Page ID) for publishing as well
                $publish_url = self::THREADS_API_BASE_URL . "/{$posting_entity_id}/threads_publish";
                $publish_params = [
                    'creation_id' => $container_id,
                    'access_token' => $access_token
                ];

                $this->logger?->debug('Threads API: Publishing container request.', ['url' => $publish_url, 'container_id' => $container_id, 'user_id' => $user_id]);

                $response = $this->http_service->post($publish_url, $publish_params, [], 'Threads Publish API');

                if (is_wp_error($response)) {
                     $this->logger?->error('Threads API Error: Publish container failed.', ['error' => $response->get_error_message(), 'user_id' => $user_id]);
                     return $response;
                }

                $body = $response['body'];
                $http_code = $response['status_code'];

                // Parse JSON response with error handling
                $data = $this->http_service->parse_json($body, 'Threads Publish API');
                if (is_wp_error($data)) {
                    return $data;
                }

                if ($http_code >= 200 && $http_code < 300 && isset($data['id'])) {
                    $threads_media_id = $data['id'];
                    // Construct permalink (structure confirmed by docs: threads.net/t/{media_id})
                    // However, media_id from publish might be different from permalink shortcode?
                    // Let's try fetching the permalink via the media ID.
                    $permalink_data = $this->get_threads_permalink($threads_media_id, $access_token);
                    $output_url = is_wp_error($permalink_data) ? "https://www.threads.net/" : $permalink_data['permalink']; // Fallback URL

                    $this->logger?->info('Threads post published successfully.', ['user_id' => $user_id, 'threads_media_id' => $threads_media_id, 'output_url' => $output_url]);
                    return [
                        'status' => 'success',
                        'threads_media_id' => $threads_media_id,
                        'output_url' => $output_url,
                        /* translators: %s: Threads media ID */
                        'message' => sprintf(__('Successfully posted to Threads: %s', 'data-machine'), $threads_media_id),
                        'raw_response' => $data,
                        'permalink_response' => is_wp_error($permalink_data) ? $permalink_data->get_error_message() : $permalink_data
                    ];
                } else {
                    $error_message = $data['error']['message'] ?? 'Failed to publish Threads container.';
                    $error_code_th = $data['error']['code'] ?? 'UnknownCode';
                    $this->logger?->error('Threads API Error (Publish Container):', ['http_code' => $http_code, 'th_error' => $data['error'] ?? $body, 'user_id' => $user_id]);
                    // TODO: Consider deleting the container if publish fails?
                    return new WP_Error('threads_publish_error_' . $error_code_th, $error_message, ['api_response' => $data]);
                }

            } catch (\Exception $e) {
                $this->logger?->error('Threads Output Exception (Publish Container): ' . $e->getMessage(), ['user_id' => $user_id, 'trace' => $e->getTraceAsString()]);
                return new WP_Error('threads_exception_publish', $e->getMessage());
            }
        }

        // Should not reach here if container creation succeeded but publish block failed somehow
        return new WP_Error('threads_unknown_error', __('An unknown error occurred during Threads publishing after container creation.', 'data-machine'));
	}

    /**
     * Fetches the permalink for a published Threads media item.
     *
     * @param string $media_id The ID of the published Threads media.
     * @param string $access_token A valid access token.
     * @return array|WP_Error Array containing 'permalink' or WP_Error.
     */
    private function get_threads_permalink(string $media_id, string $access_token): array|WP_Error {
        $url = self::THREADS_API_BASE_URL . "/{$media_id}?fields=permalink&access_token={$access_token}";
        $this->logger?->debug('Threads API: Fetching permalink.', ['url' => $url]);

        $response = $this->http_service->get($url, [], 'Threads Permalink API');

        if (is_wp_error($response)) {
            $this->logger?->error('Threads API Error: Permalink fetch failed.', ['error' => $response->get_error_message()]);
            return $response;
        }

        $body = $response['body'];
        $http_code = $response['status_code'];

        // Parse JSON response with error handling
        $data = $this->http_service->parse_json($body, 'Threads Permalink API');
        if (is_wp_error($data)) {
            return $data;
        }

        if ($http_code !== 200 || empty($data['permalink'])) {
            $error_message = $data['error']['message'] ?? 'Failed to fetch Threads permalink.';
            $this->logger?->error('Threads API Error: Permalink fetch failed.', ['http_code' => $http_code, 'response' => $body]);
            return new WP_Error('threads_permalink_fetch_failed', $error_message, $data);
        }

        return $data; // Contains 'permalink'
    }

    /**
     * Defines the settings fields for this output handler.
	 *
     * @param array $current_config Current configuration values for this handler (optional).
     * @return array An associative array defining the settings fields.
	 */
	public function get_settings_fields(array $current_config = []): array {
        // TODO: Define settings specific to Threads (e.g., enable images/video, default post type?)
        // Authentication is handled separately on the API Keys page.
        return [
            'threads_placeholder' => [
                'type' => 'description',
                'label' => __('Threads Settings', 'data-machine'),
                'description' => __('Configuration options for Threads output will be added here. Authentication is managed on the API Keys page.', 'data-machine'),
            ],
            // Example setting:
            // 'threads_enable_images' => [
            //     'type' => 'checkbox',
            //     'label' => __('Enable Image Posting', 'data-machine'),
            //     'description' => __('Attempt to find and upload an image from the source data (if available).', 'data-machine'),
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
        return __('Threads', 'data-machine');
	}

    /**
     * Sanitizes the settings specific to the Threads output handler.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        // TODO: Sanitize actual settings when defined.
        // Example:
        // $sanitized['threads_enable_images'] = isset($raw_settings['threads_enable_images']) && $raw_settings['threads_enable_images'] == '1';
        return $sanitized;
    }
}