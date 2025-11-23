<?php
/**
 * WordPress Update handler for post modification.
 *
 * @package DataMachine\Core\Steps\Update\Handlers\WordPress
 */

namespace DataMachine\Core\Steps\Update\Handlers\WordPress;

use DataMachine\Core\EngineData;
use DataMachine\Core\Steps\Update\Handlers\UpdateHandler;
use DataMachine\Core\Steps\HandlerRegistrationTrait;
use DataMachine\Core\WordPress\WordPressSharedTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WordPress extends UpdateHandler {
    use HandlerRegistrationTrait;
    use WordPressSharedTrait;

    public function __construct() {
        // Initialize shared helpers
        $this->initWordPressHelpers();

        // Register handler via standardized trait
        self::registerHandler(
            'wordpress_update',
            'update',
            self::class,
            'WordPress Update',
            'Update existing WordPress posts and pages',
            false,
            null,
            null,
            [self::class, 'registerTools']
        );
    }

    // Handler registration is managed by HandlerRegistrationTrait

    public static function registerTools($tools, $handler_slug, $handler_config) {
        if ($handler_slug === 'wordpress_update') {
            $tools['wordpress_update'] = [
                'class' => self::class,
                'method' => 'handle_tool_call',
                'handler' => 'wordpress_update',
                'description' => 'Update an existing WordPress post. Requires source_url from previous fetch step.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => [
                            'type' => 'string',
                            'description' => 'The new content to update the post with'
                        ],
                        'job_id' => [
                            'type' => 'string',
                            'required' => true,
                            'description' => 'Job ID for tracking workflow execution'
                        ]
                    ],
                    'required' => ['content', 'job_id']
                ]
            ];
        }
        return $tools;
    }

    protected function executeUpdate(array $parameters, array $handler_config): array {
        $job_id = $parameters['job_id'];
        $engine = $parameters['engine'] ?? null;
        if (!$engine instanceof EngineData) {
            $engine = new EngineData($parameters['engine_data'] ?? [], $job_id);
        }
        $source_url = $engine->getSourceUrl();

        do_action('datamachine_log', 'debug', 'WordPress Update Tool: Handling tool call', [
            'parameters' => $parameters,
            'parameter_keys' => array_keys($parameters),
            'has_handler_config' => !empty($handler_config),
            'handler_config_keys' => array_keys($handler_config ?? []),
            'source_url_from_engine' => $source_url,
            'engine_data_keys' => array_keys($engine->all())
        ]);

        if (empty($source_url)) {
            $error_msg = "source_url parameter is required for WordPress Update handler";
            do_action('datamachine_log', 'error', $error_msg, [
                'available_parameters' => array_keys($parameters)
            ]);

            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'wordpress_update'
            ];
        }

        $post_id = url_to_postid($source_url);
        if (!$post_id) {
            $error_msg = "Could not extract valid WordPress post ID from URL: {$source_url}";
            do_action('datamachine_log', 'error', $error_msg, [
                'source_url' => $source_url,
                'extracted_post_id' => $post_id
            ]);

            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'wordpress_update'
            ];
        }
        
        $existing_post = get_post($post_id);
        if (!$existing_post) {
            $error_msg = "WordPress post with ID {$post_id} does not exist";
            do_action('datamachine_log', 'error', $error_msg, [
                'post_id' => $post_id
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'wordpress_update'
            ];
        }

        // $handler_config is provided by UpdateHandler::handle_tool_call
        
        do_action('datamachine_log', 'debug', 'WordPress Update Tool: Using handler configuration', [
            'post_id' => $post_id,
            'existing_post_title' => $existing_post->post_title,
            'existing_post_status' => $existing_post->post_status,
            'has_title_update' => !empty($parameters['title']),
            'has_content_update' => !empty($parameters['content']),
            'has_surgical_updates' => !empty($parameters['updates']),
            'has_block_updates' => !empty($parameters['block_updates'])
        ]);
        
        $post_data = [
            'ID' => $post_id
        ];
        $all_changes = [];
        $original_content = $existing_post->post_content;

        if (!empty($parameters['updates'])) {
            $result = $this->applySurgicalUpdates($original_content, $parameters['updates']);
            $post_data['post_content'] = $this->sanitizeBlockContent($result['content']);
            $all_changes['content_updates'] = $result['changes'];
        }

        if (!empty($parameters['block_updates'])) {
            $working_content = $post_data['post_content'] ?? $original_content;
            $result = $this->applyBlockUpdates($working_content, $parameters['block_updates']);
            $post_data['post_content'] = $this->sanitizeBlockContent($result['content']);
            $all_changes['block_updates'] = $result['changes'];
        }

        if (!empty($parameters['title'])) {
            $post_data['post_title'] = sanitize_text_field(wp_unslash($parameters['title']));
            $all_changes['title_update'] = true;
        }

        $has_updates = count($post_data) > 1;
        
        if (!$has_updates) {
            do_action('datamachine_log', 'info', 'WordPress Update Tool: No updates to apply', [
                'post_id' => $post_id,
                'reason' => 'No allowed parameters provided or all updates disabled'
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'updated_id' => $post_id,
                    'post_url' => get_permalink($post_id),
                    'modifications' => [],
                    'message' => 'No updates applied - post unchanged'
                ],
                'tool_name' => 'wordpress_update'
            ];
        }

        do_action('datamachine_log', 'debug', 'WordPress Update Tool: Final post data for wp_update_post', [
            'post_id' => $post_id,
            'updating_title' => isset($post_data['post_title']),
            'updating_content' => isset($post_data['post_content']),
            'title_length' => isset($post_data['post_title']) ? strlen($post_data['post_title']) : 0,
            'content_length' => isset($post_data['post_content']) ? strlen($post_data['post_content']) : 0
        ]);

        $result = wp_update_post($post_data);

        if (is_wp_error($result)) {
            $error_msg = 'WordPress post update failed: ' . $result->get_error_message();
            do_action('datamachine_log', 'error', $error_msg, [
                'post_data' => $post_data,
                'wp_error' => $result->get_error_data()
            ]);

            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'wordpress_update'
            ];
        }

        if ($result === 0) {
            $error_msg = 'WordPress post update failed: wp_update_post returned 0';
            do_action('datamachine_log', 'error', $error_msg, [
                'post_data' => $post_data
            ]);

            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'wordpress_update'
            ];
        }

        $taxonomy_results = $this->applyTaxonomies($post_id, $parameters, $handler_config, $engine);

        do_action('datamachine_log', 'debug', 'WordPress Update Tool: Post updated successfully', [
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'taxonomy_results' => $taxonomy_results,
            'updated_fields' => array_keys($post_data)
        ]);

        return [
            'success' => true,
            'data' => [
                'updated_id' => $post_id,
                'post_url' => get_permalink($post_id),
                'taxonomy_results' => $taxonomy_results,
                'changes_applied' => $all_changes,
                'modifications' => array_diff_key($post_data, ['ID' => ''])
            ],
            'tool_name' => 'wordpress_update'
        ];
    }

    public static function get_label(): string {
        return __('WordPress Update', 'datamachine');
    }

    // Block and surgical update helpers are provided by WordPressSharedTrait
}
