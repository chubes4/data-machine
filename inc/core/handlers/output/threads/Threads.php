<?php
/**
 * Threads output handler.
 *
 * Handles publishing content to Meta's Threads platform.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/inc/core/handlers/output
 * @since      1.0.0
 */

namespace DataMachine\Core\Handlers\Output\Threads;

use DataMachine\Core\Steps\AI\AiResponseParser;
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
        // Use filter-based auth access following architectural standards
        $this->auth = apply_filters('dm_get_auth', null, 'threads');
    }

    /**
     * Handles posting the AI output to Threads.
     *
     * @param object $data_packet Universal DataPacket JSON object with all content and metadata.
     * @param int $user_id The ID of the user whose Threads account should be used.
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
        
        $logger = apply_filters('dm_get_logger', null);
        $logger?->info('Threads Output: Starting Threads publication.', ['user_id' => $user_id]);

        if (empty($user_id)) {
            return [
                'success' => false,
                'error' => __('User ID is required for Threads publishing.', 'data-machine')
            ];
        }

        // Get output config from the job config array
        $config = $module_job_config['output_config'] ?? [];
        if (!is_array($config)) $config = [];

        // Access config from nested structure
        $threads_config = $config['threads'] ?? [];

        // Parse AI output
        $parser = apply_filters('dm_get_ai_response_parser', null);
        if (!$parser) {
            $logger?->error('Threads Output: AI Response Parser service not available.');
            return [
                'success' => false,
                'error' => __('AI Response Parser service not available.', 'data-machine')
            ];
        }
        $parser->set_raw_output($ai_output_string);
        $parser->parse();
        
        $content = $parser->get_content();
        $title = $parser->get_title();
        
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

        // Use internal Threads auth service
        $threads_auth = $this->auth;

        // Get user's Threads account
        $threads_account = get_user_meta($user_id, 'data_machine_threads_account', true);
        if (empty($threads_account) || !is_array($threads_account) || empty($threads_account['access_token'])) {
            return [
                'success' => false,
                'error' => __('Threads account not connected. Please connect your Threads account in API Keys.', 'data-machine')
            ];
        }

        // Check if token needs refresh
        $needs_refresh = false;
        $token_expires_at = $threads_account['token_expires_at'] ?? 0;
        if (time() >= ($token_expires_at - 300)) { // Refresh if expires within 5 minutes
            $needs_refresh = true;
        }

        if ($needs_refresh) {
            $logger?->info('Threads Output: Refreshing access token.', ['user_id' => $user_id]);
            $refreshed = $threads_auth->refresh_token($user_id);
            if (!$refreshed) {
                return [
                    'success' => false,
                    'error' => __('Failed to refresh Threads access token. Please reconnect your account.', 'data-machine')
                ];
            }
            // Re-fetch updated account data
            $threads_account = get_user_meta($user_id, 'data_machine_threads_account', true);
        }

        // Decrypt access token
        $encryption_helper = apply_filters('dm_get_encryption_helper', null);
        if (!$encryption_helper) {
            return [
                'success' => false,
                'error' => __('Encryption service not available.', 'data-machine')
            ];
        }

        $access_token = $encryption_helper->decrypt($threads_account['access_token']);
        if ($access_token === false) {
            return [
                'success' => false,
                'error' => __('Failed to decrypt Threads access token.', 'data-machine')
            ];
        }

        $user_id_threads = $threads_account['user_id'] ?? '';
        if (empty($user_id_threads)) {
            return [
                'success' => false,
                'error' => __('Threads user ID not found.', 'data-machine')
            ];
        }

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
            $post_url = "https://www.threads.net/@{$threads_account['username']}/post/{$media_id}";

            $logger?->info('Threads Output: Successfully published to Threads.', [
                'user_id' => $user_id,
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
            $logger?->error('Threads Output: Exception during publication.', [
                'user_id' => $user_id,
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

// Self-register via universal parameter-based handler system
add_filter('dm_get_handlers', function($handlers, $type) {
    if ($type === 'output') {
        $handlers['threads'] = [
            'class' => \DataMachine\Core\Handlers\Output\Threads\Threads::class,
            'label' => __('Threads', 'data-machine'),
            'description' => __('Post content to Threads (Meta\'s Twitter alternative)', 'data-machine')
        ];
    }
    return $handlers;
}, 10, 2);
