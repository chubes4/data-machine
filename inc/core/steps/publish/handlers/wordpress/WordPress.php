<?php
/**
 * Local WordPress publish handler.
 *
 * Handles WordPress publishing to local installation using wp_insert_post.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Publish\Handlers\WordPress
 * @since      1.0.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\WordPress;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WordPress {

    public function __construct() {
    }

    /**
     * Handle AI tool call for WordPress publishing.
     *
     * @param array $parameters Structured parameters from AI tool call.
     * @param array $tool_def Tool definition including handler configuration.
     * @return array Tool execution result.
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        do_action('dm_log', 'debug', 'WordPress Tool: Handling tool call', [
            'parameters' => $parameters,
            'parameter_keys' => array_keys($parameters),
            'has_handler_config' => !empty($tool_def['handler_config']),
            'handler_config_keys' => array_keys($tool_def['handler_config'] ?? [])
        ]);
        
        // Validate required parameters
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

        // Get handler configuration from tool definition
        $handler_config = $tool_def['handler_config'] ?? [];
        
        
        do_action('dm_log', 'debug', 'WordPress Tool: Using handler configuration', [
            'has_post_author' => isset($handler_config['post_author']),
            'post_author_config' => $handler_config['post_author'] ?? 'NOT_SET',
            'current_user_id' => get_current_user_id(),
            'has_post_status' => isset($handler_config['post_status']),
            'has_post_type' => isset($handler_config['post_type'])
        ]);
        
        // Validate required WordPress configuration - fail job if missing
        if (empty($handler_config['post_author'])) {
            $error_msg = 'WordPress publish handler missing required post_author configuration';
            do_action('dm_log', 'error', $error_msg, [
                'handler_config_keys' => array_keys($handler_config),
                'provided_post_author' => $handler_config['post_author'] ?? 'NOT_SET'
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'wordpress_publish'
            ];
        }

        if (empty($handler_config['post_status'])) {
            $error_msg = 'WordPress publish handler missing required post_status configuration';
            do_action('dm_log', 'error', $error_msg, [
                'handler_config_keys' => array_keys($handler_config),
                'provided_post_status' => $handler_config['post_status'] ?? 'NOT_SET'
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'wordpress_publish'
            ];
        }

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
        
        // Prepare post data using configuration from handler settings
        $post_data = [
            'post_title' => sanitize_text_field(wp_unslash($parameters['title'])),
            'post_content' => wp_kses_post(wp_unslash($parameters['content'])),
            'post_status' => $handler_config['post_status'],
            'post_type' => $handler_config['post_type'],
            'post_author' => $handler_config['post_author']
        ];

        do_action('dm_log', 'debug', 'WordPress Tool: Final post data for wp_insert_post', [
            'post_author' => $post_data['post_author'],
            'post_status' => $post_data['post_status'],
            'post_type' => $post_data['post_type'],
            'title_length' => strlen($post_data['post_title']),
            'content_length' => strlen($post_data['post_content'])
        ]);

        // Insert the post
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

        // Handle taxonomies if provided
        $taxonomy_results = [];
        if (!empty($parameters['category'])) {
            $category_result = $this->assign_taxonomy($post_id, 'category', $parameters['category']);
            $taxonomy_results['category'] = $category_result;
        }

        if (!empty($parameters['tags'])) {
            $tags_result = $this->assign_taxonomy($post_id, 'post_tag', $parameters['tags']);
            $taxonomy_results['tags'] = $tags_result;
        }

        // Handle other taxonomies dynamically - exclude core content parameters  
        $excluded_params = ['title', 'content', 'category', 'tags'];
        foreach ($parameters as $param_name => $param_value) {
            if (!in_array($param_name, $excluded_params) && !empty($param_value) && is_string($param_value)) {
                // Only process string values as potential taxonomy terms, not arrays/objects which are likely config
                $taxonomy_result = $this->assign_taxonomy($post_id, $param_name, $param_value);
                $taxonomy_results[$param_name] = $taxonomy_result;
            }
        }

        do_action('dm_log', 'debug', 'WordPress Tool: Post created successfully', [
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'taxonomy_results' => $taxonomy_results
        ]);

        return [
            'success' => true,
            'data' => [
                'post_id' => $post_id,
                'post_url' => get_permalink($post_id),
                'taxonomy_results' => $taxonomy_results
            ],
            'tool_name' => 'wordpress_publish'
        ];
    }

    /**
     * Prepend image to content if available in metadata.
     *
     * @param string $content Original content.
     * @param array $input_metadata Input metadata containing image information.
     * @return string Content with prepended image if available.
     */
    private function prepend_image_if_available(string $content, array $input_metadata): string {
        if (!empty($input_metadata['image_url'])) {
            $image_url = esc_url($input_metadata['image_url']);
            $content = "![Image]({$image_url})\n\n" . $content;
        }
        return $content;
    }

    /**
     * Append source information to content if available in metadata.
     *
     * @param string $content Original content.
     * @param array $input_metadata Input metadata containing source information.
     * @return string Content with appended source if available.
     */
    private function append_source_if_available(string $content, array $input_metadata): string {
        if (!empty($input_metadata['source_url'])) {
            $source_url = esc_url($input_metadata['source_url']);
            $content .= "\n\n---\n\n" . sprintf(
                /* translators: %s: source URL */
                __('Source: %s', 'data-machine'),
                "[{$source_url}]({$source_url})"
            );
        }
        return $content;
    }

    /**
     * Get the user-friendly label for this handler.
     *
     * @return string Handler label.
     */
    public static function get_label(): string {
        return __('WordPress', 'data-machine');
    }

    /**
     * Create Gutenberg blocks from structured content.
     * 
     * Converts structured content to proper Gutenberg blocks using WordPress native functions.
     * Uses serialize_blocks() to ensure proper block format and compatibility.
     *
     * @param string $content The content to convert to blocks.
     * @return string Properly formatted Gutenberg block content.
     */
    private function create_gutenberg_blocks_from_content(string $content): string {
        if (empty($content)) {
            return '';
        }

        // Sanitize HTML content using WordPress KSES
        $sanitized_html = wp_kses_post($content);

        // Check if content already contains blocks - if so, return as-is
        if (has_blocks($sanitized_html)) {
            return $sanitized_html;
        }

        // Convert HTML to WordPress block structure
        $blocks = $this->convert_html_to_blocks($sanitized_html);
        
        // Use WordPress native serialize_blocks() to convert block array to proper HTML
        return serialize_blocks($blocks);
    }

    /**
     * Convert HTML content to WordPress block structure.
     * 
     * Creates proper block arrays that WordPress can serialize correctly.
     *
     * @param string $html_content The HTML content to convert.
     * @return array Array of WordPress block structures.
     */
    private function convert_html_to_blocks(string $html_content): array {
        $blocks = [];
        
        // Split content by double line breaks to identify separate content blocks
        $paragraphs = preg_split('/\n\s*\n/', trim($html_content));
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) {
                continue;
            }

            // Check for heading tags
            if (preg_match('/^<h([1-6])[^>]*>(.*?)<\/h[1-6]>$/is', $paragraph, $matches)) {
                $level = (int) $matches[1];
                $content_text = trim($matches[2]);
                
                $blocks[] = [
                    'blockName' => 'core/heading',
                    'attrs' => [
                        'level' => $level
                    ],
                    'innerBlocks' => [],
                    'innerHTML' => sprintf('<h%d>%s</h%d>', $level, $content_text, $level),
                    'innerContent' => [sprintf('<h%d>%s</h%d>', $level, $content_text, $level)]
                ];
                
            } elseif (preg_match('/^<p[^>]*>(.*?)<\/p>$/is', $paragraph, $matches)) {
                $content_text = trim($matches[1]);
                
                $blocks[] = [
                    'blockName' => 'core/paragraph',
                    'attrs' => [],
                    'innerBlocks' => [],
                    'innerHTML' => sprintf('<p>%s</p>', $content_text),
                    'innerContent' => [sprintf('<p>%s</p>', $content_text)]
                ];
                
            } else {
                // Wrap other content in paragraph blocks
                $blocks[] = [
                    'blockName' => 'core/paragraph',
                    'attrs' => [],
                    'innerBlocks' => [],
                    'innerHTML' => sprintf('<p>%s</p>', $paragraph),
                    'innerContent' => [sprintf('<p>%s</p>', $paragraph)]
                ];
            }
        }
        
        return $blocks;
    }


    /**
     * Assign custom taxonomy to post.
     *
     * @param int $post_id Post ID.
     * @param string $taxonomy_name Taxonomy name.
     * @param mixed $taxonomy_value Taxonomy value (string or array).
     * @return array Assignment result.
     */
    private function assign_taxonomy(int $post_id, string $taxonomy_name, $taxonomy_value): array {
        // Validate taxonomy exists
        if (!taxonomy_exists($taxonomy_name)) {
            return [
                'success' => false,
                'error' => "Taxonomy '{$taxonomy_name}' does not exist"
            ];
        }
        
        $taxonomy_obj = get_taxonomy($taxonomy_name);
        $term_ids = [];
        
        // Handle array of terms or single term
        $terms = is_array($taxonomy_value) ? $taxonomy_value : [$taxonomy_value];
        
        foreach ($terms as $term_name) {
            $term_name = sanitize_text_field($term_name);
            if (empty($term_name)) continue;
            
            // Get or create term
            $term = get_term_by('name', $term_name, $taxonomy_name);
            if (!$term) {
                $term_result = wp_insert_term($term_name, $taxonomy_name);
                if (is_wp_error($term_result)) {
                    do_action('dm_log', 'warning', 'Failed to create taxonomy term', [
                        'taxonomy' => $taxonomy_name,
                        'term_name' => $term_name,
                        'error' => $term_result->get_error_message()
                    ]);
                    continue;
                }
                $term_ids[] = $term_result['term_id'];
            } else {
                $term_ids[] = $term->term_id;
            }
        }
        
        if (!empty($term_ids)) {
            $result = wp_set_object_terms($post_id, $term_ids, $taxonomy_name);
            if (is_wp_error($result)) {
                return [
                    'success' => false,
                    'error' => $result->get_error_message()
                ];
            }
        }
        
        return [
            'success' => true,
            'taxonomy' => $taxonomy_name,
            'term_count' => count($term_ids),
            'terms' => $terms
        ];
    }
}


