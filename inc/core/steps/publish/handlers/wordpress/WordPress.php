<?php
/**
 * Local WordPress publish handler.
 *
 * Handles WordPress publishing to local installation using wp_insert_post.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/inc/core/steps/publish/handlers
 * @since      1.0.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\WordPress;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WordPress {

    /**
     * Constructor.
     * Pure filter-based architecture - no dependencies.
     */
    public function __construct() {
        // No constructor dependencies - all services accessed via filters
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
                'required_parameters' => ['title', 'content']
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
            'post_author' => $handler_config['post_author'] ?? 'fallback',
            'post_status' => $handler_config['post_status'] ?? 'fallback',
            'post_type' => $handler_config['post_type'] ?? 'fallback'
        ]);
        
        // Prepare post data using configuration from handler settings
        $post_data = [
            'post_title' => sanitize_text_field(wp_unslash($parameters['title'])),
            'post_content' => wp_kses_post(wp_unslash($parameters['content'])),
            'post_status' => $handler_config['post_status'] ?? 'publish',
            'post_type' => $handler_config['post_type'] ?? 'post',
            'post_author' => $handler_config['post_author'] ?? get_current_user_id()
        ];

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
            $category_result = $this->assign_category($post_id, $parameters['category']);
            $taxonomy_results['category'] = $category_result;
        }

        if (!empty($parameters['tags'])) {
            $tags_result = $this->assign_tags($post_id, $parameters['tags']);
            $taxonomy_results['tags'] = $tags_result;
        }

        // Handle other taxonomies dynamically
        foreach ($parameters as $param_name => $param_value) {
            if (!in_array($param_name, ['title', 'content', 'category', 'tags']) && !empty($param_value)) {
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
     * Publish content to local WordPress installation.
     *
     * @param array $structured_content Structured content from data entry.
     * @param array $config Configuration array.
     * @param array $input_metadata Input metadata.
     * @return array Result array.
     */
    private function publish_local(array $structured_content, array $config, array $input_metadata): array {
        // Validate required local WordPress configuration
        if (!isset($config['post_type'])) {
            return ['success' => false, 'error' => 'Local WordPress configuration missing: post_type required'];
        }
        if (!isset($config['post_status'])) {
            return ['success' => false, 'error' => 'Local WordPress configuration missing: post_status required'];
        }
        if (!isset($config['post_author'])) {
            return ['success' => false, 'error' => 'Local WordPress configuration missing: post_author required'];
        }
        if (!isset($config['taxonomy_category_selection'])) {
            return ['success' => false, 'error' => 'Local WordPress configuration missing: taxonomy_category_selection required'];
        }
        if (!isset($config['taxonomy_post_tag_selection'])) {
            return ['success' => false, 'error' => 'Local WordPress configuration missing: taxonomy_post_tag_selection required'];
        }
        
        // Get settings from validated config
        $post_type = $config['post_type'];
        $post_status = $config['post_status'];
        $post_author = $config['post_author'];
        $category_id = $config['taxonomy_category_selection'];
        $tag_id = $config['taxonomy_post_tag_selection'];

        // Use structured content directly from data entry (no parsing needed)
        $parsed_data = [
            'title' => $structured_content['title'],
            'content' => $structured_content['body'], 
            'category' => '', // Category will be determined by AI directives or config
            'tags' => is_array($structured_content['tags']) ? $structured_content['tags'] : []
        ];
        
        // Ensure tags are trimmed strings
        $parsed_data['tags'] = array_map('trim', array_filter($parsed_data['tags']));
        $parsed_data['custom_taxonomies'] = []; // Will be populated by AI directives

        // Prepare Content: Prepend Image, Append Source
        $final_content = $this->prepend_image_if_available($parsed_data['content'], $input_metadata);
        $final_content = $this->append_source_if_available($final_content, $input_metadata);

        // Create Gutenberg blocks directly from structured content
        $block_content = $this->create_gutenberg_blocks_from_content($final_content);

        // Determine Post Date
        if (!isset($config['post_date_source'])) {
            return ['success' => false, 'error' => 'WordPress configuration missing: post_date_source required'];
        }
        $post_date_source = $config['post_date_source'];
        $post_date_gmt = null;
        $post_date = null;

        if ($post_date_source === 'source_date' && !empty($input_metadata['original_date_gmt'])) {
            $source_date_gmt_string = $input_metadata['original_date_gmt'];

            // Attempt to parse the GMT date string
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $source_date_gmt_string)) {
                $post_date_gmt = $source_date_gmt_string;
                $post_date = get_date_from_gmt($post_date_gmt);
            }
        }

        // Prepare post data
        $post_data = array(
            'post_title' => $parsed_data['title'] ?: __('Untitled Post', 'data-machine'),
            'post_content' => $block_content,
            'post_status' => $post_status,
            'post_author' => $post_author,
            'post_type' => $post_type,
        );

        // Add post date if determined from source
        if ($post_date && $post_date_gmt) {
            $post_data['post_date'] = $post_date;
            $post_data['post_date_gmt'] = $post_date_gmt;
        }

        // Insert the post
        $post_id = wp_insert_post($post_data, true);

        // Comprehensive error checking for wp_insert_post
        if (is_wp_error($post_id)) {
            $error_message = __('Failed to create local post:', 'data-machine') . ' ' . $post_id->get_error_message();
            do_action('dm_log', 'error', 'WordPress publish failed - wp_insert_post error', [
                'error_message' => $post_id->get_error_message(),
                'error_code' => $post_id->get_error_code(),
                'post_data' => [
                    'title' => $post_data['post_title'],
                    'status' => $post_data['post_status'],
                    'type' => $post_data['post_type'],
                    'author' => $post_data['post_author']
                ]
            ]);
            return [
                'success' => false,
                'error' => $error_message
            ];
        }

        // Check if post_id is valid (wp_insert_post can return 0 on failure)
        if (!$post_id || $post_id === 0) {
            do_action('dm_log', 'error', 'WordPress publish failed - wp_insert_post returned 0', [
                'post_data' => [
                    'title' => $post_data['post_title'],
                    'status' => $post_data['post_status'],
                    'type' => $post_data['post_type'],
                    'author' => $post_data['post_author']
                ]
            ]);
            return [
                'success' => false,
                'error' => __('Failed to create post - wp_insert_post returned invalid ID', 'data-machine')
            ];
        }

        // Verify post was actually created
        $created_post = get_post($post_id);
        if (!$created_post) {
            do_action('dm_log', 'error', 'WordPress publish failed - post not found after creation', [
                'post_id' => $post_id,
                'post_data' => [
                    'title' => $post_data['post_title'],
                    'status' => $post_data['post_status'],
                    'type' => $post_data['post_type'],
                    'author' => $post_data['post_author']
                ]
            ]);
            return [
                'success' => false,
                'error' => __('Post creation verification failed - post not found after insert', 'data-machine')
            ];
        }

        // Taxonomy Handling
        $assigned_category_id = null;
        $assigned_category_name = null;
        $assigned_tag_ids = [];
        $assigned_tag_names = [];
        $assigned_custom_taxonomies = [];

        // Category Assignment
        if ($category_id > 0) { // Manual selection
            $term = get_term($category_id, 'category');
            if ($term && !is_wp_error($term)) {
                wp_set_post_terms($post_id, array($category_id), 'category', false);
                $assigned_category_id = $category_id;
                $assigned_category_name = $term->name;
            }
        } elseif ($category_id === 'instruct_model' && !empty($parsed_data['category'])) { // Instruct Model
            $term = get_term_by('name', $parsed_data['category'], 'category');
            if ($term) {
                wp_set_post_terms($post_id, array($term->term_id), 'category', false);
                $assigned_category_id = $term->term_id;
                $assigned_category_name = $term->name;
            } else {
                // Create the category if it doesn't exist
                $term_info = wp_insert_term($parsed_data['category'], 'category');
                if (!is_wp_error($term_info) && isset($term_info['term_id'])) {
                    wp_set_post_terms($post_id, array($term_info['term_id']), 'category', false);
                    $assigned_category_id = $term_info['term_id'];
                    $assigned_category_name = $parsed_data['category'];
                }
            }
        }

        // Tag Assignment
        if ($tag_id > 0) { // Manual selection
            $term = get_term($tag_id, 'post_tag');
            if ($term && !is_wp_error($term)) {
                wp_set_post_terms($post_id, array($tag_id), 'post_tag', false);
                $assigned_tag_ids = array($tag_id);
                $assigned_tag_names = array($term->name);
            }
        } elseif ((is_string($tag_id) && ($tag_id === 'instruct_model')) && !empty($parsed_data['tags'])) { // Instruct Model
            $term_ids_to_assign = [];
            $term_names_to_assign = [];
            $first_tag_processed = false;
            
            foreach ($parsed_data['tags'] as $tag_name) {
                if (empty(trim($tag_name))) continue;

                // Enforce single tag for instruct_model
                if ($first_tag_processed && ($tag_id === 'instruct_model')) {
                    continue;
                }

                $term = get_term_by('name', $tag_name, 'post_tag');
                if ($term) {
                    $term_ids_to_assign[] = $term->term_id;
                    $term_names_to_assign[] = $term->name;
                } else {
                    // Create tag if it doesn't exist
                    $term_info = wp_insert_term($tag_name, 'post_tag');
                    if (!is_wp_error($term_info) && isset($term_info['term_id'])) {
                        $term_ids_to_assign[] = $term_info['term_id'];
                        $term_names_to_assign[] = $tag_name;
                    }
                }
                $first_tag_processed = true;
            }
            
            if (!empty($term_ids_to_assign)) {
                wp_set_post_terms($post_id, $term_ids_to_assign, 'post_tag', false);
                $assigned_tag_ids = $term_ids_to_assign;
                $assigned_tag_names = $term_names_to_assign;
            }
        }

        // Custom Taxonomy Assignment
        if (!empty($parsed_data['custom_taxonomies']) && is_array($parsed_data['custom_taxonomies'])) {
            foreach ($parsed_data['custom_taxonomies'] as $tax_slug => $term_names) {
                if (!taxonomy_exists($tax_slug)) {
                    continue;
                }

                // Determine if this custom taxonomy is set to 'instruct_model'
                $tax_mode = 'manual';
                if (isset($config["rest_" . $tax_slug])) {
                    $mode_check = $config["rest_" . $tax_slug];
                    if (is_string($mode_check) && ($mode_check === 'instruct_model')) {
                        $tax_mode = $mode_check;
                    }
                }

                $term_ids_to_assign = [];
                $term_names_assigned = [];
                $first_term_processed = false;

                foreach ($term_names as $term_name) {
                    if (empty(trim($term_name))) continue;

                    // Enforce single term for instruct_model
                    if ($first_term_processed && ($tax_mode === 'instruct_model')) {
                        continue;
                    }

                    $term = get_term_by('name', $term_name, $tax_slug);

                    if ($term) {
                        $term_ids_to_assign[] = $term->term_id;
                        $term_names_assigned[] = $term->name;
                    } else {
                        // Term does not exist - create it
                        $term_info = wp_insert_term($term_name, $tax_slug);
                        if (!is_wp_error($term_info) && isset($term_info['term_id'])) {
                            $term_ids_to_assign[] = $term_info['term_id'];
                            $term_names_assigned[] = $term_name;
                        }
                    }
                    $first_term_processed = true;
                }

                // Assign the collected/created terms for this taxonomy
                if (!empty($term_ids_to_assign)) {
                    wp_set_post_terms($post_id, $term_ids_to_assign, $tax_slug, true);
                    $assigned_custom_taxonomies[$tax_slug] = $term_names_assigned;
                }
            }
        }

        // Success
        return array(
            'success' => true,
            'status' => 'success',
            'message' => __('Post published locally successfully!', 'data-machine'),
            'local_post_id' => $post_id,
            'local_edit_link' => get_edit_post_link($post_id, 'raw'),
            'local_view_link' => get_permalink($post_id),
            'post_title' => $parsed_data['title'],
            'final_output' => $parsed_data['content'],
            'assigned_category_id' => $assigned_category_id,
            'assigned_category_name' => $assigned_category_name,
            'assigned_tag_ids' => $assigned_tag_ids,
            'assigned_tag_names' => $assigned_tag_names,
            'assigned_custom_taxonomies' => $assigned_custom_taxonomies,
        );
    }









    /**
     * Prepend image to content if available in metadata.
     *
     * @param string $content Original content.
     * @param array $input_metadata Input metadata containing image information.
     * @return string Content with prepended image if available.
     */
    private function prepend_image_if_available(string $content, array $input_metadata): string {
        if (!empty($input_metadata['image_source_url'])) {
            $image_url = esc_url($input_metadata['image_source_url']);
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
     * Assign category to post.
     *
     * @param int $post_id Post ID.
     * @param string $category_name Category name.
     * @return array Assignment result.
     */
    private function assign_category(int $post_id, string $category_name): array {
        $category_name = sanitize_text_field($category_name);
        
        // Get or create category
        $category = get_term_by('name', $category_name, 'category');
        if (!$category) {
            $category_result = wp_insert_term($category_name, 'category');
            if (is_wp_error($category_result)) {
                return [
                    'success' => false,
                    'error' => $category_result->get_error_message()
                ];
            }
            $category_id = $category_result['term_id'];
        } else {
            $category_id = $category->term_id;
        }
        
        // Assign category to post
        $result = wp_set_object_terms($post_id, [$category_id], 'category');
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => $result->get_error_message()
            ];
        }
        
        return [
            'success' => true,
            'category_id' => $category_id,
            'category_name' => $category_name
        ];
    }

    /**
     * Assign tags to post.
     *
     * @param int $post_id Post ID.
     * @param array $tag_names Array of tag names.
     * @return array Assignment result.
     */
    private function assign_tags(int $post_id, array $tag_names): array {
        $sanitized_tags = array_map('sanitize_text_field', $tag_names);
        $tag_ids = [];
        
        foreach ($sanitized_tags as $tag_name) {
            if (empty($tag_name)) continue;
            
            // Get or create tag
            $tag = get_term_by('name', $tag_name, 'post_tag');
            if (!$tag) {
                $tag_result = wp_insert_term($tag_name, 'post_tag');
                if (is_wp_error($tag_result)) {
                    do_action('dm_log', 'warning', 'Failed to create tag', [
                        'tag_name' => $tag_name,
                        'error' => $tag_result->get_error_message()
                    ]);
                    continue;
                }
                $tag_ids[] = $tag_result['term_id'];
            } else {
                $tag_ids[] = $tag->term_id;
            }
        }
        
        if (!empty($tag_ids)) {
            $result = wp_set_object_terms($post_id, $tag_ids, 'post_tag');
            if (is_wp_error($result)) {
                return [
                    'success' => false,
                    'error' => $result->get_error_message()
                ];
            }
        }
        
        return [
            'success' => true,
            'tag_count' => count($tag_ids),
            'tags' => $sanitized_tags
        ];
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


