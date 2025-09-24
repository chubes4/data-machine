<?php
/**
 * WordPress publish handler with modular post creation components.
 *
 * @package DataMachine\Core\Steps\Publish\Handlers\WordPress
 */

namespace DataMachine\Core\Steps\Publish\Handlers\WordPress;

use DataMachine\Core\Steps\Publish\Handlers\WordPress\FeaturedImageHandler;
use DataMachine\Core\Steps\Publish\Handlers\WordPress\SourceUrlHandler;
use DataMachine\Core\Steps\Publish\Handlers\WordPress\TaxonomyHandler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WordPress {

    private $featured_image_handler;
    private $source_url_handler;
    private $taxonomy_handler;

    public function __construct() {
        $this->featured_image_handler = new FeaturedImageHandler();
        $this->source_url_handler = new SourceUrlHandler();
        $this->taxonomy_handler = new TaxonomyHandler();
    }

    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        
        if (empty($parameters['title']) || empty($parameters['content'])) {
            $error_msg = 'WordPress tool call missing required parameters';
            do_action('dm_log', 'error', $error_msg, [
                'provided_parameters' => array_keys($parameters),
                'required_parameters' => ['title', 'content'],
                'parameter_values' => [
                    'title' => $parameters['title'] ?? 'NOT_PROVIDED',
                    'content_length' => isset($parameters['content']) ? strlen($parameters['content']) : 'NOT_PROVIDED'
                ]
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'wordpress_publish'
            ];
        }

        $handler_config = $tool_def['handler_config'] ?? [];

        $job_id = $parameters['job_id'] ?? null;
        $engine_data = apply_filters('dm_engine_data', [], $job_id);

        $taxonomies = get_taxonomies(['public' => true], 'names');
        $taxonomy_settings = [];
        foreach ($taxonomies as $taxonomy) {
            if (!in_array($taxonomy, ['post_format', 'nav_menu', 'link_category'])) {
                $field_key = "taxonomy_{$taxonomy}_selection";
                $taxonomy_settings[$taxonomy] = $handler_config[$field_key] ?? 'NOT_SET';
            }
        }

        do_action('dm_log', 'debug', 'WordPress Tool: Handler configuration accessed', [
            'taxonomy_settings' => $taxonomy_settings,
            'config_keys_count' => count($handler_config)
        ]);

        if (empty($handler_config['post_type'])) {
            $error_msg = 'WordPress publish handler missing required post_type configuration';
            do_action('dm_log', 'error', $error_msg, [
                'handler_config_keys' => array_keys($handler_config),
                'provided_post_type' => $handler_config['post_type'] ?? 'NOT_SET'
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'wordpress_publish'
            ];
        }
        
    $content = wp_unslash($parameters['content']);
    $content = $this->source_url_handler->processSourceUrl($content, $engine_data, $handler_config);
    $content = $this->sanitize_block_content($content);
        
        $post_data = [
            'post_title' => sanitize_text_field(wp_unslash($parameters['title'])),
            'post_content' => $content,
            'post_status' => $this->get_effective_post_status($handler_config),
            'post_type' => $handler_config['post_type'],
            'post_author' => $this->get_effective_post_author($handler_config)
        ];

        do_action('dm_log', 'debug', 'WordPress Tool: Final post data for wp_insert_post', [
            'post_author' => $post_data['post_author'],
            'post_status' => $post_data['post_status'],
            'post_type' => $post_data['post_type'],
            'title_length' => strlen($post_data['post_title']),
            'content_length' => strlen($post_data['post_content'])
        ]);

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            $error_msg = 'WordPress post creation failed: ' . $post_id->get_error_message();
            do_action('dm_log', 'error', $error_msg, [
                'post_data' => $post_data,
                'wp_error' => $post_id->get_error_data()
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'wordpress_publish'
            ];
        }

        $taxonomy_results = $this->taxonomy_handler->processTaxonomies($post_id, $parameters, $handler_config);
        $featured_image_result = $this->featured_image_handler->processImage($post_id, $engine_data, $handler_config);

        do_action('dm_log', 'debug', 'WordPress Tool: Post created successfully', [
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'taxonomy_results' => $taxonomy_results,
            'featured_image_result' => $featured_image_result
        ]);

        return [
            'success' => true,
            'data' => [
                'post_id' => $post_id,
                'post_title' => $parameters['title'],
                'post_url' => get_permalink($post_id),
                'taxonomy_results' => $taxonomy_results,
                'featured_image_result' => $featured_image_result
            ],
            'tool_name' => 'wordpress_publish'
        ];
    }


    public static function get_label(): string {
        return __('WordPress', 'data-machine');
    }

    private function get_effective_post_status(array $handler_config): string {
        $all_settings = get_option('data_machine_settings', []);
        $wp_settings = $all_settings['wordpress_settings'] ?? [];
        $default_post_status = $wp_settings['default_post_status'] ?? '';

        if (!empty($default_post_status)) {
            return $default_post_status;
        }
        return $handler_config['post_status'] ?? 'draft';
    }

    private function get_effective_post_author(array $handler_config): int {
        $all_settings = get_option('data_machine_settings', []);
        $wp_settings = $all_settings['wordpress_settings'] ?? [];
        $default_author_id = $wp_settings['default_author_id'] ?? 0;

        if (!empty($default_author_id)) {
            return $default_author_id;
        }
        return $handler_config['post_author'] ?? get_current_user_id();
    }

    private function sanitize_block_content(string $content): string {
        $original_length = strlen($content);
        $has_wp_comments = (bool) preg_match('/<!--\s*wp:/i', $content);

        $unterminated_detected = false;
        if (preg_match('/<!--\s*wp:[^\n\r{}]+\{[^}]*$/', $content)) {
            $unterminated_detected = true;
            do_action('dm_log', 'debug', 'WordPress Publish: Detected potentially unterminated block JSON', [
                'content_preview' => substr($content, 0, 200)
            ]);
        }

        // First attempt: try parsing as-is to avoid stripping valid attributes when content is fine.
        $initial_blocks = parse_blocks($content);
        $initial_serialized = serialize_blocks($initial_blocks);
        $initial_length = strlen($initial_serialized);

        $needs_repair = $unterminated_detected || ($has_wp_comments && $initial_length < max(64, (int) ($original_length * 0.8)));

        $attributes_stripped = false;
        $appended_closers = 0;

        if ($needs_repair) {
            // 1) Normalize/repair block openers by stripping attributes/JSON to avoid parser breaks.
            $before_strip = $content;
            $content = preg_replace('/<!--\s*wp:(?!\/)\s*([a-z0-9\-\/_]+)\s+\{[^}]*}\s*-->\s*/i', '<!-- wp:$1 -->', $content);
            $content = preg_replace('/<!--\s*wp:(?!\/)\s*([a-z0-9\-\/_]+)[^>]*-->\s*/i', '<!-- wp:$1 -->', $content);
            $attributes_stripped = ($before_strip !== $content);

            // 2) Ensure opener comments terminate
            if (preg_match('/<!--\s*wp:(?!\/)\s*([a-z0-9\-\/_]+)[^>]*\z/i', $content)) {
                $content .= ' -->';
            }

            // 3) Append missing closers
            $stack = [];
            if (preg_match_all('/<!--\s*(\/?)(?:wp:)([a-z0-9\-\/_]+)[^>]*-->\s*/i', $content, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($m as $match) {
                    $is_close = $match[1][0] === '/';
                    $name = strtolower($match[2][0]);
                    if ($is_close) {
                        if (!empty($stack) && end($stack) === $name) {
                            array_pop($stack);
                        }
                    } else {
                        $stack[] = $name;
                    }
                }
            }
            if (!empty($stack)) {
                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    $content .= sprintf('<!-- /wp:%s -->', $stack[$i]);
                    $appended_closers++;
                }
            }

            // Re-parse after repairs
            $blocks = parse_blocks($content);
        } else {
            $blocks = $initial_blocks;
        }

        // Sanitize recursively: innerHTML + innerContent + nested innerBlocks.
        $sanitize_block = null;
        $sanitize_block = function($block) use (&$sanitize_block) {
            if (isset($block['innerHTML']) && $block['innerHTML'] !== '') {
                $block['innerHTML'] = wp_kses_post($block['innerHTML']);
            }
            if (isset($block['innerContent']) && is_array($block['innerContent'])) {
                foreach ($block['innerContent'] as $idx => $piece) {
                    if (is_string($piece) && $piece !== '') {
                        $block['innerContent'][$idx] = wp_kses_post($piece);
                    }
                }
            }
            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $block['innerBlocks'] = array_map($sanitize_block, $block['innerBlocks']);
            }
            return $block;
        };

        $sanitized = array_map($sanitize_block, $blocks);
        $serialized = serialize_blocks($sanitized);

        // Diagnostics
        if ($needs_repair) {
            do_action('dm_log', 'debug', 'WordPress Publish: Block content repaired/sanitized', [
                'attributes_stripped' => $attributes_stripped,
                'appended_closers' => $appended_closers,
                'unterminated_detected' => $unterminated_detected,
                'original_length' => $original_length,
                'before_repair_length' => $initial_length,
                'final_length' => strlen($serialized)
            ]);
        }

        return $serialized;
    }
}
