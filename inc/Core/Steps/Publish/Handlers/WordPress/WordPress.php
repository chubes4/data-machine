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
use DataMachine\Core\WordPress\TaxonomyHandler;
use DataMachine\Core\WordPress\WordPressSettingsResolver;
use DataMachine\Core\WordPress\WordPressPublishHelper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WordPress extends PublishHandler {
    use HandlerRegistrationTrait;

    protected $taxonomy_handler;

    public function __construct() {
        parent::__construct('wordpress');

        $this->taxonomy_handler = new TaxonomyHandler();

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
                    // Base parameters (always present)
                    $base_parameters = [
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
                    ];

                    // Dynamic taxonomy parameters based on "AI Decides" selections
                    $taxonomy_parameters = TaxonomyHandler::getTaxonomyToolParameters($handler_config);

                    // Merge base + dynamic parameters
                    $all_parameters = array_merge($base_parameters, $taxonomy_parameters);

                    $tools['wordpress_publish'] = [
                        'class' => self::class,
                        'method' => 'handle_tool_call',
                        'handler' => 'wordpress_publish',
                        'description' => 'Create WordPress posts and pages with automatic taxonomy assignment, featured image processing, and source URL attribution.',
                        'parameters' => $all_parameters,
                        'handler_config' => $handler_config
                    ];
                }
                return $tools;
            }
        );
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
    $content = WordPressPublishHelper::applySourceAttribution($content, $engine->getSourceUrl(), $handler_config);
    $content = wp_filter_post_kses($content);
        
        $post_data = [
            'post_title' => sanitize_text_field(wp_unslash($parameters['title'])),
            'post_content' => $content,
            'post_status' => WordPressSettingsResolver::getPostStatus($handler_config),
            'post_type' => $handler_config['post_type'],
            'post_author' => WordPressSettingsResolver::getPostAuthor($handler_config)
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

        $engine_data_array = $engine instanceof EngineData ? $engine->all() : [];
        $taxonomy_results = $this->taxonomy_handler->processTaxonomies($post_id, $parameters, $handler_config, $engine_data_array);
        $featured_image_result = null;
        $attachment_id = WordPressPublishHelper::attachImageToPost($post_id, $engine->getImagePath(), $handler_config);
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
}
