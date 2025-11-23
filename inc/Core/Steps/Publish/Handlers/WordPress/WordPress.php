<?php
/**
 * WordPress publish handler with modular post creation components.
 *
 * @package DataMachine\Core\Steps\Publish\Handlers\WordPress
 */

namespace DataMachine\Core\Steps\Publish\Handlers\WordPress;

use DataMachine\Core\EngineData;
use DataMachine\Core\Steps\Publish\Handlers\PublishHandler;
use DataMachine\Core\Steps\HandlerRegistrationTrait;
use DataMachine\Core\WordPress\WordPressSharedTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WordPress extends PublishHandler {
    use HandlerRegistrationTrait;
    use WordPressSharedTrait;

    // Plugin-specific handlers (featured image, source url, taxonomy) are initialized by WordPressSharedTrait

    public function __construct() {
        parent::__construct('wordpress');

        // Self-register with filters
        self::registerHandler(
            'wordpress_publish',
            'publish',
            self::class,
            'WordPress',
            'Create WordPress posts and pages',
            false,
            null,
            WordPressSettings::class,
            function($tools, $handler_slug, $handler_config) {
                if ($handler_slug === 'wordpress_publish') {
                    $tools['wordpress_publish'] = [
                        'class' => self::class,
                        'method' => 'handle_tool_call',
                        'handler' => 'wordpress_publish',
                        'description' => 'Create WordPress posts and pages with automatic taxonomy assignment, featured image processing, and source URL attribution.',
                        'parameters' => [
                            'title' => [
                                'type' => 'string',
                                'required' => true,
                                'description' => 'The title of the WordPress post or page'
                            ],
                            'content' => [
                                'type' => 'string',
                                'required' => true,
                                'description' => 'The main content of the post in HTML format'
                            ],
                            'job_id' => [
                                'type' => 'string',
                                'description' => 'Optional job ID for tracking workflow execution'
                            ]
                        ],
                        'handler_config' => $handler_config
                    ];
                }
                return $tools;
            }
        );

        // Initialize shared helpers
        $this->initWordPressHelpers();
    }

    /**
     * Execute WordPress post publishing.
     *
     * Creates a WordPress post with modular processing for taxonomies, featured images,
     * and source URL attribution. Uses configuration hierarchy where system defaults
     * override handler-specific settings.
     *
     * @param array $parameters Tool call parameters including title, content, job_id
     * @param array $handler_config Handler configuration
     * @return array Success status with post data or error information
     */
    protected function executePublish(array $parameters, array $handler_config): array {
        if (empty($parameters['title']) || empty($parameters['content'])) {
            return $this->errorResponse(
                'WordPress tool call missing required parameters',
                [
                    'provided_parameters' => array_keys($parameters),
                    'required_parameters' => ['title', 'content'],
                    'parameter_values' => [
                        'title' => $parameters['title'] ?? 'NOT_PROVIDED',
                        'content_length' => isset($parameters['content']) ? strlen($parameters['content']) : 'NOT_PROVIDED'
                    ]
                ]
            );
        }

        $engine = $parameters['engine'] ?? null;
        if (!$engine instanceof EngineData) {
            $engine = new EngineData($parameters['engine_data'] ?? [], $parameters['job_id'] ?? null);
        }

        $taxonomies = get_taxonomies(['public' => true], 'names');
        $taxonomy_settings = [];
        foreach ($taxonomies as $taxonomy) {
            if (!\DataMachine\Core\WordPress\TaxonomyHandler::shouldSkipTaxonomy($taxonomy)) {
                $field_key = "taxonomy_{$taxonomy}_selection";
                $taxonomy_settings[$taxonomy] = $handler_config[$field_key] ?? 'NOT_SET';
            }
        }

        $this->log('debug', 'WordPress Tool: Handler configuration accessed', [
            'taxonomy_settings' => $taxonomy_settings,
            'config_keys_count' => count($handler_config)
        ]);

        if (empty($handler_config['post_type'])) {
            return $this->errorResponse(
                'WordPress publish handler missing required post_type configuration',
                [
                    'handler_config_keys' => array_keys($handler_config),
                    'provided_post_type' => $handler_config['post_type'] ?? 'NOT_SET'
                ]
            );
        }
        
    $content = wp_unslash($parameters['content']);
    $content = $engine->applySourceAttribution($content, $handler_config);
    $content = wp_filter_post_kses($content);
        
        $post_data = [
            'post_title' => sanitize_text_field(wp_unslash($parameters['title'])),
            'post_content' => $content,
            'post_status' => $this->getEffectivePostStatus($handler_config),
            'post_type' => $handler_config['post_type'],
            'post_author' => $this->getEffectivePostAuthor($handler_config)
        ];

        $this->log('debug', 'WordPress Tool: Final post data for wp_insert_post', [
            'post_author' => $post_data['post_author'],
            'post_status' => $post_data['post_status'],
            'post_type' => $post_data['post_type'],
            'title_length' => strlen($post_data['post_title']),
            'content_length' => strlen($post_data['post_content'])
        ]);

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return $this->errorResponse(
                'WordPress post creation failed: ' . $post_id->get_error_message(),
                [
                    'post_data' => $post_data,
                    'wp_error' => $post_id->get_error_data()
                ]
            );
        }

        $taxonomy_results = $this->applyTaxonomies($post_id, $parameters, $handler_config, $engine);
        $featured_image_result = null;
        $attachment_id = $engine->attachImageToPost($post_id, $handler_config);
        if ($attachment_id) {
            $featured_image_result = [
                'success' => true,
                'attachment_id' => $attachment_id,
                'attachment_url' => wp_get_attachment_url($attachment_id)
            ];
        }

        // Store post metadata in engine snapshot
        $job_id = $parameters['job_id'] ?? null;
        if ($job_id) {
            datamachine_merge_engine_data((int) $job_id, [
                'post_id' => $post_id,
                'published_url' => get_permalink($post_id)
            ]);
        }

        $this->log('debug', 'WordPress Tool: Post created successfully', [
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'taxonomy_results' => $taxonomy_results,
            'featured_image_result' => $featured_image_result
        ]);

        return $this->successResponse([
            'post_id' => $post_id,
            'post_title' => $parameters['title'],
            'post_url' => get_permalink($post_id),
            'taxonomy_results' => $taxonomy_results,
            'featured_image_result' => $featured_image_result
        ]);
    }


    /**
     * Get the display label for the WordPress handler.
     *
     * @return string Handler label
     */
    public static function get_label(): string {
        return 'WordPress';
    }

    // Effective post status/author logic is provided by WordPressSharedTrait


}