<?php
/**
 * WordPress Update handler with engine data dependency.
 *
 * Updates existing WordPress posts/pages via source_url retrieved from database
 * storage by fetch handlers through dm_engine_data filter. Handles title, content,
 * and taxonomy modifications using wp_update_post().
 *
 * Engine Data Requirements:
 * - source_url: WordPress post/page URL for post ID extraction (REQUIRED)
 * - Stored by fetch handlers in database, accessed via dm_engine_data filter
 * - Used with url_to_postid() for target post identification
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Update\Handlers\WordPress
 * @since      1.0.0
 */

namespace DataMachine\Core\Steps\Update\Handlers\WordPress;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WordPress {

    public function __construct() {
    }

    /**
     * Handle AI tool call for WordPress content updating.
     * Requires source_url from engine data for post identification.
     *
     * @param array $parameters Structured parameters from AI tool call
     * @param array $tool_def Tool definition including handler configuration
     * @return array Tool execution result with success status and post details
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Access engine_data via centralized filter pattern
        $job_id = $parameters['job_id'] ?? null;
        $engine_data = apply_filters('dm_engine_data', [], $job_id);
        $source_url = $engine_data['source_url'] ?? null;

        do_action('dm_log', 'debug', 'WordPress Update Tool: Handling tool call', [
            'parameters' => $parameters,
            'parameter_keys' => array_keys($parameters),
            'has_handler_config' => !empty($tool_def['handler_config']),
            'handler_config_keys' => array_keys($tool_def['handler_config'] ?? []),
            'source_url_from_engine' => $source_url
        ]);

        // Validate source_url from engine data (required for post identification)
        if (empty($source_url)) {
            $error_msg = "source_url parameter is required for WordPress Update handler";
            do_action('dm_log', 'error', $error_msg, [
                'available_parameters' => array_keys($parameters)
            ]);

            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'wordpress_update'
            ];
        }

        // Extract post ID from WordPress URL provided by fetch handler
        $post_id = url_to_postid($source_url);
        if (!$post_id) {
            $error_msg = "Could not extract valid WordPress post ID from URL: {$source_url}";
            do_action('dm_log', 'error', $error_msg, [
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
            do_action('dm_log', 'error', $error_msg, [
                'post_id' => $post_id
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'wordpress_update'
            ];
        }

        $handler_config = $tool_def['handler_config'] ?? [];
        
        do_action('dm_log', 'debug', 'WordPress Update Tool: Using handler configuration', [
            'post_id' => $post_id,
            'existing_post_title' => $existing_post->post_title,
            'existing_post_status' => $existing_post->post_status,
            'has_title_update' => !empty($parameters['title']),
            'has_content_update' => !empty($parameters['content'])
        ]);
        
        // Prepare post data for update - only include fields that should be updated
        $post_data = [
            'ID' => $post_id
        ];

        // Update title if provided
        if (!empty($parameters['title'])) {
            $post_data['post_title'] = sanitize_text_field(wp_unslash($parameters['title']));
        }

        // Update content if provided
        if (!empty($parameters['content'])) {
            $post_data['post_content'] = $this->sanitize_block_content(wp_unslash($parameters['content']));
        }

        // Check if any updates are actually being made
        $has_updates = count($post_data) > 1; // More than just the ID
        
        if (!$has_updates) {
            do_action('dm_log', 'info', 'WordPress Update Tool: No updates to apply', [
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

        do_action('dm_log', 'debug', 'WordPress Update Tool: Final post data for wp_update_post', [
            'post_id' => $post_id,
            'updating_title' => isset($post_data['post_title']),
            'updating_content' => isset($post_data['post_content']),
            'title_length' => isset($post_data['post_title']) ? strlen($post_data['post_title']) : 0,
            'content_length' => isset($post_data['post_content']) ? strlen($post_data['post_content']) : 0
        ]);

        // Update the post
        $result = wp_update_post($post_data);

        if (is_wp_error($result)) {
            $error_msg = 'WordPress post update failed: ' . $result->get_error_message();
            do_action('dm_log', 'error', $error_msg, [
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
            do_action('dm_log', 'error', $error_msg, [
                'post_data' => $post_data
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'wordpress_update'
            ];
        }

        // Process taxonomies based on handler configuration (settings-driven)
        $taxonomy_results = $this->process_taxonomies_from_settings($post_id, $parameters, $handler_config);

        do_action('dm_log', 'debug', 'WordPress Update Tool: Post updated successfully', [
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
                'modifications' => array_diff_key($post_data, ['ID' => ''])
            ],
            'tool_name' => 'wordpress_update'
        ];
    }

    /**
     * Get the user-friendly label for this handler.
     *
     * @return string Handler label.
     */
    public static function get_label(): string {
        return __('WordPress Update', 'data-machine');
    }

    /**
     * Process taxonomies based on handler configuration settings.
     *
     * @param int $post_id Post ID.
     * @param array $parameters AI tool parameters.
     * @param array $handler_config Handler configuration from settings.
     * @return array Taxonomy processing results.
     */
    private function process_taxonomies_from_settings(int $post_id, array $parameters, array $handler_config): array {
        $taxonomy_results = [];
        
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        
        foreach ($taxonomies as $taxonomy) {
            // Skip built-in formats and other non-content taxonomies
            if (in_array($taxonomy->name, ['post_format', 'nav_menu', 'link_category'])) {
                continue;
            }
            
            $field_key = "taxonomy_{$taxonomy->name}_selection";
            $selection = $handler_config[$field_key] ?? 'skip';
            
            do_action('dm_log', 'debug', 'WordPress Update Tool: Processing taxonomy from settings', [
                'taxonomy_name' => $taxonomy->name,
                'field_key' => $field_key,
                'selection_value' => $selection,
                'selection_type' => gettype($selection)
            ]);
            
            if ($selection === 'skip') {
                // Skip - no assignment for this taxonomy
                continue;
                
            } elseif ($selection === 'ai_decides') {
                // AI Decides - use AI-provided parameter if available
                $param_name = $taxonomy->name === 'category' ? 'category' : 
                             ($taxonomy->name === 'post_tag' ? 'tags' : $taxonomy->name);
                
                if (!empty($parameters[$param_name])) {
                    $taxonomy_result = $this->assign_taxonomy($post_id, $taxonomy->name, $parameters[$param_name]);
                    $taxonomy_results[$taxonomy->name] = $taxonomy_result;
                    
                    do_action('dm_log', 'debug', 'WordPress Update Tool: Applied AI-decided taxonomy', [
                        'taxonomy_name' => $taxonomy->name,
                        'parameter_name' => $param_name,
                        'parameter_value' => $parameters[$param_name],
                        'result' => $taxonomy_result
                    ]);
                }
                
            } elseif (is_numeric($selection)) {
                // Specific term ID selected - assign that term
                $term_id = absint($selection);
                $term = get_term($term_id, $taxonomy->name);
                
                if (!is_wp_error($term) && $term) {
                    $result = wp_set_object_terms($post_id, [$term_id], $taxonomy->name);
                    
                    if (is_wp_error($result)) {
                        $taxonomy_results[$taxonomy->name] = [
                            'success' => false,
                            'error' => $result->get_error_message()
                        ];
                    } else {
                        $taxonomy_results[$taxonomy->name] = [
                            'success' => true,
                            'taxonomy' => $taxonomy->name,
                            'term_count' => 1,
                            'terms' => [$term->name]
                        ];
                        
                        do_action('dm_log', 'debug', 'WordPress Update Tool: Applied pre-selected taxonomy', [
                            'taxonomy_name' => $taxonomy->name,
                            'term_id' => $term_id,
                            'term_name' => $term->name
                        ]);
                    }
                }
            }
        }
        
        return $taxonomy_results;
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

    /**
     * Sanitize block content using WordPress native functions with block-aware processing.
     * Processes individual blocks to prevent corruption of malformed AI-generated block structures.
     *
     * @param string $content Raw content from AI
     * @return string Clean, validated block content
     */
    private function sanitize_block_content(string $content): string {
        // Parse blocks first to maintain structure integrity
        $blocks = parse_blocks($content);

        // Process each block while preserving block structure
        $filtered_blocks = array_map(function($block) {
            // Only sanitize the innerHTML content, not the block structure
            if (isset($block['innerHTML']) && $block['innerHTML'] !== '') {
                $block['innerHTML'] = wp_kses_post($block['innerHTML']);
            }
            // Recursively process inner blocks if they exist
            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $block['innerBlocks'] = array_map(function($inner) {
                    if (isset($inner['innerHTML']) && $inner['innerHTML'] !== '') {
                        $inner['innerHTML'] = wp_kses_post($inner['innerHTML']);
                    }
                    return $inner;
                }, $block['innerBlocks']);
            }
            return $block;
        }, $blocks);

        return serialize_blocks($filtered_blocks);
    }
}

