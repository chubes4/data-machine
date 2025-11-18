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

use DataMachine\Core\Steps\Publish\Handlers\PublishHandler;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Threads extends PublishHandler {

    /**
     * @var ThreadsAuth Authentication handler instance
     */
    private $auth;

    public function __construct() {
        parent::__construct('threads');
        // Use filter-based auth access following pure discovery architectural standards
        $all_auth = apply_filters('datamachine_auth_providers', []);
        $this->auth = $all_auth['threads'] ?? null;

        if ($this->auth === null) {
            $this->log('error', 'Threads Handler: Authentication service not available', [
                'missing_service' => 'threads',
                'available_providers' => array_keys($all_auth)
            ]);
            // Handler will return error in executePublish() when auth is null
        }
    }

    protected function executePublish(array $parameters, array $handler_config): array {
        $this->log('debug', 'Threads Tool: Handling tool call', [
            'parameters' => $parameters,
            'parameter_keys' => array_keys($parameters),
            'has_handler_config' => !empty($handler_config),
            'handler_config_keys' => array_keys($handler_config)
        ]);

        if (empty($parameters['content'])) {
            return $this->errorResponse(
                'Threads tool call missing required content parameter',
                [
                    'provided_parameters' => array_keys($parameters),
                    'required_parameters' => ['content']
                ]
            );
        }

        $threads_config = $handler_config['threads'] ?? $handler_config;

        $this->log('debug', 'Threads Tool: Using handler configuration', [
            'include_images' => $threads_config['include_images'] ?? true,
            'link_handling' => $threads_config['link_handling'] ?? 'append'
        ]);

        $job_id = $parameters['job_id'] ?? null;
        $engine_data = $this->getEngineData($job_id);

        $title = $parameters['title'] ?? '';
        $content = $parameters['content'] ?? '';
        $source_url = $engine_data['source_url'] ?? null;
        $image_file_path = $engine_data['image_file_path'] ?? null;
        
        $include_images = $threads_config['include_images'] ?? true;
        $link_handling = $threads_config['link_handling'] ?? 'append';

        $access_token = $this->auth->get_access_token();
        if (empty($access_token)) {
            return $this->errorResponse('Threads authentication failed - no access token');
        }

        // Get page ID for posting
        $page_id = $this->auth->get_page_id();
        if (empty($page_id)) {
            return $this->errorResponse('Threads page ID not available');
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
            return $this->errorResponse('Formatted post content is empty');
        }

        try {
            // Prepare media container data
            $container_data = [
                'media_type' => 'TEXT',
                'text' => $post_text
            ];

            // Handle image if provided and enabled
            if ($include_images && !empty($image_file_path)) {
                $validation = $this->validateImage($image_file_path);

                if (!$validation['valid']) {
                    return $this->errorResponse(
                        implode(', ', $validation['errors']),
                        ['image_file_path' => $image_file_path, 'errors' => $validation['errors']]
                    );
                }

                // Convert repository file path to public URL
                $upload_dir = wp_upload_dir();
                $image_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $image_file_path);

                if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                    return $this->errorResponse(
                        'Could not generate image URL from repository file',
                        ['image_file_path' => $image_file_path, 'generated_url' => $image_url]
                    );
                }

                $container_data['media_type'] = 'IMAGE';
                $container_data['image_url'] = $image_url;
            }

            // Step 1: Create media container
            $container_response = $this->create_media_container($page_id, $container_data, $access_token);
            if (!$container_response['success']) {
                return $this->errorResponse('Threads API error: Failed to create media container - ' . $container_response['error']);
            }

            $creation_id = $container_response['creation_id'];

            // Step 2: Publish the media container
            $publish_response = $this->publish_media_container($page_id, $creation_id, $access_token);
            if (!$publish_response['success']) {
                return $this->errorResponse('Threads API error: Failed to publish - ' . $publish_response['error']);
            }

            $media_id = $publish_response['media_id'];
            $post_url = "https://www.threads.net/t/{$media_id}";

            $this->log('debug', 'Threads Tool: Post created successfully', [
                'media_id' => $media_id,
                'post_url' => $post_url
            ]);

            return $this->successResponse([
                'media_id' => $media_id,
                'post_url' => $post_url,
                'content' => $post_text
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Step 1 of Threads 2-step publishing: creates media container (TEXT or IMAGE type).
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

        $result = apply_filters('datamachine_request', null, 'POST', $endpoint, $args, 'Threads API');
        
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
     * Step 2 of Threads 2-step publishing: publishes created media container.
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

        $result = apply_filters('datamachine_request', null, 'POST', $endpoint, $args, 'Threads API');
        
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


    public function sanitize_settings(array $raw_settings): array {
        // No specific settings to sanitize for Threads
        return [];
    }

    public static function get_label(): string {
        return __('Threads', 'datamachine');
    }
}
