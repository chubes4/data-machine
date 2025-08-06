<?php
/**
 * Threads output handler.
 *
 * Handles publishing content to Meta's Threads platform.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/inc/core/steps/output/handlers
 * @since      1.0.0
 */

namespace DataMachine\Core\Handlers\Output\Threads;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Threads {

    /**
     * @var ThreadsAuth Authentication handler instance
     */
    private $auth;

    /**
     * Constructor - direct auth initialization for security
     */
    public function __construct() {
        // Use filter-based auth access following pure discovery architectural standards
        $all_auth = apply_filters('dm_get_auth_providers', []);
        $this->auth = $all_auth['threads'] ?? null;
    }

    /**
     * Handles posting the AI output to Threads.
     *
     * @param object $data_packet Universal DataPacket JSON object with all content and metadata.
     * @return array Result array on success or failure.
     */
    public function handle_output($data_packet): array {
        // Access structured content directly from DataPacket (no parsing needed)
        $title = $data_packet->content->title ?? '';
        $content = $data_packet->content->body ?? '';
        
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
        
        $logger = apply_filters('dm_get_logger', null);
        $logger && $logger->debug('Threads Output: Starting Threads publication.');

        // Get output config from the job config array
        $config = $module_job_config['output_config'] ?? [];
        if (!is_array($config)) $config = [];

        // Access config from nested structure
        $threads_config = $config['threads'] ?? [];

        // Validate content from DataPacket
        if (empty($title) && empty($content)) {
            $logger && $logger->error('Threads Output: DataPacket content is empty.');
            return [
                'success' => false,
                'error' => __('Cannot post empty content to Threads.', 'data-machine')
            ];
        }
        
        // Prepare post content
        $post_content = $content;
        if (!empty($title) && strpos($content, $title) === false) {
            $post_content = $title . "\n\n" . $content;
        }

        // Apply character limit (Threads has a character limit)
        $max_length = 500; // Threads character limit
        if (strlen($post_content) > $max_length) {
            $post_content = substr($post_content, 0, $max_length - 3) . '...';
        }

        // Get authenticated access token using auth service (handles refresh automatically)
        $access_token = $this->auth->get_access_token();
        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => __('Threads account not connected or access token expired. Please reconnect your Threads account in API Keys.', 'data-machine')
            ];
        }

        // Get page ID for posting
        $page_id = $this->auth->get_page_id();
        if (empty($page_id)) {
            return [
                'success' => false,
                'error' => __('Threads page ID not available. Please reconnect your Threads account.', 'data-machine')
            ];
        }

        // Use page_id as the Threads user ID for API calls
        $user_id_threads = $page_id;

        try {
            // Prepare media container data
            $container_data = [
                'media_type' => 'TEXT',
                'text' => $post_content
            ];

            // Check if we have an image to include
            $image_url = $input_metadata['image_source_url'] ?? null;
            if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                $container_data['media_type'] = 'IMAGE';
                $container_data['image_url'] = $image_url;
            }

            // Step 1: Create media container
            $container_response = $this->create_media_container($user_id_threads, $container_data, $access_token);
            if (!$container_response['success']) {
                return [
                    'success' => false,
                    'error' => __('Failed to create Threads media container: ', 'data-machine') . $container_response['error']
                ];
            }

            $creation_id = $container_response['creation_id'];
            
            // Step 2: Publish the media container
            $publish_response = $this->publish_media_container($user_id_threads, $creation_id, $access_token);
            if (!$publish_response['success']) {
                return [
                    'success' => false,
                    'error' => __('Failed to publish to Threads: ', 'data-machine') . $publish_response['error']
                ];
            }

            $media_id = $publish_response['media_id'];
            // Build post URL using page_id since we don't have username in new architecture
            $post_url = "https://www.threads.net/t/{$media_id}";

            $logger && $logger->debug('Threads Output: Successfully published to Threads.', [
                'media_id' => $media_id
            ]);

            return [
                'success' => true,
                'status' => 'success',
                'message' => __('Successfully published to Threads!', 'data-machine'),
                'threads_media_id' => $media_id,
                'threads_url' => $post_url,
                'content_published' => $post_content
            ];

        } catch (Exception $e) {
            $logger && $logger->error('Threads Output: Exception during publication.', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => __('Threads publishing failed: ', 'data-machine') . $e->getMessage()
            ];
        }
    }


    /**
     * Create a media container for Threads.
     *
     * @param string $user_id_threads Threads user ID.
     * @param array $container_data Container data.
     * @param string $access_token Access token.
     * @return array Response array.
     */
    private function create_media_container(string $user_id_threads, array $container_data, string $access_token): array {
        $endpoint = "https://graph.threads.net/v1.0/{$user_id_threads}/threads";
        
        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($container_data),
            'timeout' => 30
        ];

        $response = wp_remote_request($endpoint, $args);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code !== 200 || empty($data['id'])) {
            $error_message = $data['error']['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => $error_message
            ];
        }

        return [
            'success' => true,
            'creation_id' => $data['id']
        ];
    }

    /**
     * Publish a media container to Threads.
     *
     * @param string $user_id_threads Threads user ID.
     * @param string $creation_id Media container creation ID.
     * @param string $access_token Access token.
     * @return array Response array.
     */
    private function publish_media_container(string $user_id_threads, string $creation_id, string $access_token): array {
        $endpoint = "https://graph.threads.net/v1.0/{$user_id_threads}/threads_publish";
        
        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'creation_id' => $creation_id
            ]),
            'timeout' => 30
        ];

        $response = wp_remote_request($endpoint, $args);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code !== 200 || empty($data['id'])) {
            $error_message = $data['error']['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'error' => $error_message
            ];
        }

        return [
            'success' => true,
            'media_id' => $data['id']
        ];
    }


    /**
     * Sanitize settings for the Threads output handler.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     */
    public function sanitize_settings(array $raw_settings): array {
        // No specific settings to sanitize for Threads
        return [];
    }

    /**
     * Get the user-friendly label for this handler.
     *
     * @return string Handler label.
     */
    public static function get_label(): string {
        return __('Threads', 'data-machine');
    }
}

