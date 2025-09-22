<?php
/**
 * Threads publish handler.
 *
 * Handles publishing content to Meta's Threads platform.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Publish\Handlers\Threads
 * @since      1.0.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Threads;
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
        $all_auth = apply_filters('dm_auth_providers', []);
        $this->auth = $all_auth['threads'] ?? null;
        
        if ($this->auth === null) {
            do_action('dm_log', 'error', 'Threads Handler: Authentication service not available', [
                'missing_service' => 'threads',
                'available_providers' => array_keys($all_auth)
            ]);
            // Handler will return error in handle_tool_call() when auth is null
        }
    }

    /**
     * Handle AI tool call for Threads publishing.
     *
     * @param array $parameters Structured parameters from AI tool call.
     * @param array $tool_def Tool definition including handler configuration.
     * @return array Tool execution result.
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        do_action('dm_log', 'debug', 'Threads Tool: Handling tool call', [
            'parameters' => $parameters,
            'parameter_keys' => array_keys($parameters),
            'has_handler_config' => !empty($tool_def['handler_config']),
            'handler_config_keys' => array_keys($tool_def['handler_config'] ?? [])
        ]);

        if (empty($parameters['content'])) {
            $error_msg = 'Threads tool call missing required content parameter';
            do_action('dm_log', 'error', $error_msg, [
                'provided_parameters' => array_keys($parameters),
                'required_parameters' => ['content']
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'threads_publish'
            ];
        }

        $handler_config = $parameters['handler_config'] ?? [];
        $threads_config = $handler_config['threads'] ?? $handler_config;
        
        do_action('dm_log', 'debug', 'Threads Tool: Using handler configuration', [
            'include_images' => $threads_config['include_images'] ?? true,
            'link_handling' => $threads_config['link_handling'] ?? 'append'
        ]);

        $job_id = $parameters['job_id'] ?? null;
        $engine_data = apply_filters('dm_engine_data', [], $job_id);

        $title = $parameters['title'] ?? '';
        $content = $parameters['content'] ?? '';
        $source_url = $engine_data['source_url'] ?? null;
        $image_url = $engine_data['image_url'] ?? null;
        
        $include_images = $threads_config['include_images'] ?? true;
        $link_handling = $threads_config['link_handling'] ?? 'append';

        $access_token = $this->auth->get_access_token();
        if (empty($access_token)) {
            return [
                'success' => false,
                'error' => 'Threads authentication failed - no access token',
                'tool_name' => 'threads_publish'
            ];
        }

        // Get page ID for posting
        $page_id = $this->auth->get_page_id();
        if (empty($page_id)) {
            return [
                'success' => false,
                'error' => 'Threads page ID not available',
                'tool_name' => 'threads_publish'
            ];
        }

        // Format post content (Threads' character limit is 500)
        $post_text = $title ? $title . "\n\n" . $content : $content;
        
        // Handle source URL based on consolidated link_handling setting
        $link = ($link_handling === 'append' && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL)) ? "\n\n" . $source_url : '';
        $ellipsis = '...';
        $max_length = 500 - strlen($link);
        
        if (strlen($post_text) > $max_length) {
            $post_text = substr($post_text, 0, $max_length - strlen($ellipsis)) . $ellipsis;
        }
        
        $post_text .= $link;

        if (empty($post_text)) {
            return [
                'success' => false,
                'error' => 'Formatted post content is empty',
                'tool_name' => 'threads_publish'
            ];
        }

        try {
            // Prepare media container data
            $container_data = [
                'media_type' => 'TEXT',
                'text' => $post_text
            ];

            // Handle image if provided and enabled
            if ($include_images && !empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                $container_data['media_type'] = 'IMAGE';
                $container_data['image_url'] = $image_url;
            }

            // Step 1: Create media container
            $container_response = $this->create_media_container($page_id, $container_data, $access_token);
            if (!$container_response['success']) {
                $error_msg = 'Threads API error: Failed to create media container - ' . $container_response['error'];
                do_action('dm_log', 'error', $error_msg);

                return [
                    'success' => false,
                    'error' => $error_msg,
                    'tool_name' => 'threads_publish'
                ];
            }

            $creation_id = $container_response['creation_id'];
            
            // Step 2: Publish the media container
            $publish_response = $this->publish_media_container($page_id, $creation_id, $access_token);
            if (!$publish_response['success']) {
                $error_msg = 'Threads API error: Failed to publish - ' . $publish_response['error'];
                do_action('dm_log', 'error', $error_msg);

                return [
                    'success' => false,
                    'error' => $error_msg,
                    'tool_name' => 'threads_publish'
                ];
            }

            $media_id = $publish_response['media_id'];
            $post_url = "https://www.threads.net/t/{$media_id}";
            
            do_action('dm_log', 'debug', 'Threads Tool: Post created successfully', [
                'media_id' => $media_id,
                'post_url' => $post_url
            ]);

            return [
                'success' => true,
                'data' => [
                    'media_id' => $media_id,
                    'post_url' => $post_url,
                    'content' => $post_text
                ],
                'tool_name' => 'threads_publish'
            ];
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Threads Tool: Exception during posting', [
                'exception' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tool_name' => 'threads_publish'
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
        ];

        $result = apply_filters('dm_request', null, 'POST', $endpoint, $args, 'Threads API');
        
        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error']
            ];
        }

        $response_code = $result['status_code'];
        $response_body = $result['data'];
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
        ];

        $result = apply_filters('dm_request', null, 'POST', $endpoint, $args, 'Threads API');
        
        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error']
            ];
        }

        $response_code = $result['status_code'];
        $response_body = $result['data'];
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
     * Sanitize settings for the Threads publish handler.
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
