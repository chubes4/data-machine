<?php
/**
 * WordPress publish handler with modular post creation components.
 *
 * @package DataMachine\Core\Steps\Publish\Handlers\WordPress
 */

namespace DataMachine\Core\Steps\Publish\Handlers\WordPress;

use DataMachine\Core\Steps\Publish\Handlers\PublishHandler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WordPress extends PublishHandler {

    private $featured_image_handler;
    private $source_url_handler;
    private $taxonomy_handler;

    public function __construct() {
        parent::__construct('wordpress');
        $this->featured_image_handler = apply_filters('datamachine_get_featured_image_handler', null);
        $this->source_url_handler = apply_filters('datamachine_get_source_url_handler', null);
        $this->taxonomy_handler = apply_filters('datamachine_get_taxonomy_handler', null);
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

        $job_id = $parameters['job_id'] ?? null;
        $engine_data = $this->getEngineData($job_id);

        $taxonomies = get_taxonomies(['public' => true], 'names');
        $excluded = apply_filters('datamachine_wordpress_system_taxonomies', []);
        $taxonomy_settings = [];
        foreach ($taxonomies as $taxonomy) {
            if (!in_array($taxonomy, $excluded)) {
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
    $content = $this->source_url_handler->processSourceUrl($content, $engine_data, $handler_config);
    $content = wp_filter_post_kses($content);
        
        $post_data = [
            'post_title' => sanitize_text_field(wp_unslash($parameters['title'])),
            'post_content' => $content,
            'post_status' => $this->get_effective_post_status($handler_config),
            'post_type' => $handler_config['post_type'],
            'post_author' => $this->get_effective_post_author($handler_config)
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

        $taxonomy_results = $this->taxonomy_handler->processTaxonomies($post_id, $parameters, $handler_config);
        $featured_image_result = $this->featured_image_handler->processImage($post_id, $engine_data, $handler_config);

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
     * @return string Localized handler label
     */
    public static function get_label(): string {
        return __('WordPress', 'datamachine');
    }

    /**
     * Get the effective post status using configuration hierarchy.
     *
     * System defaults override handler-specific settings.
     *
     * @param array $handler_config Handler configuration array
     * @return string Post status (publish, draft, etc.)
     */
    private function get_effective_post_status(array $handler_config): string {
        $all_settings = get_option('datamachine_settings', []);
        $wp_settings = $all_settings['wordpress_settings'] ?? [];
        $default_post_status = $wp_settings['default_post_status'] ?? '';

        if (!empty($default_post_status)) {
            return $default_post_status;
        }
        return $handler_config['post_status'] ?? 'draft';
    }

    /**
     * Get the effective post author using configuration hierarchy.
     *
     * System defaults override handler-specific settings.
     *
     * @param array $handler_config Handler configuration array
     * @return int WordPress user ID for post author
     */
    private function get_effective_post_author(array $handler_config): int {
        $all_settings = get_option('datamachine_settings', []);
        $wp_settings = $all_settings['wordpress_settings'] ?? [];
        $default_author_id = $wp_settings['default_author_id'] ?? 0;

        if (!empty($default_author_id)) {
            return $default_author_id;
        }
        return $handler_config['post_author'] ?? get_current_user_id();
    }

}
