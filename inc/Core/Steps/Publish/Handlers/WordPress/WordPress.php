<?php
/**
 * WordPress publish handler with Gutenberg blocks and taxonomy management
 */

namespace DataMachine\Core\Steps\Publish\Handlers\WordPress;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WordPress {

    /**
     * Handle AI tool call for WordPress post creation
     */
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
        
        do_action('dm_log', 'debug', 'WordPress Tool: Using handler configuration', [
            'has_post_author' => isset($handler_config['post_author']),
            'post_author_config' => $handler_config['post_author'] ?? 'NOT_SET',
            'current_user_id' => get_current_user_id(),
            'has_post_status' => isset($handler_config['post_status']),
            'has_post_type' => isset($handler_config['post_type'])
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
        
        $include_source = $this->get_effective_include_source($handler_config);
        $enable_images = $this->get_effective_enable_images($handler_config);
        
        $content = $this->sanitize_block_content(wp_unslash($parameters['content']));

        $source_url = $parameters['source_url'] ?? null;
        $image_url = $parameters['image_url'] ?? null;
        
        if ($include_source && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL)) {
            $content .= "\n\n<!-- wp:separator --><hr class=\"wp-block-separator has-alpha-channel-opacity\"/><!-- /wp:separator -->\n\n<!-- wp:paragraph --><p>Source: <a href=\"" . esc_url($source_url) . "\">" . esc_url($source_url) . "</a></p><!-- /wp:paragraph -->";
        }
        
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

        // Process taxonomies based on handler configuration (settings-driven)
        $taxonomy_results = $this->process_taxonomies_from_settings($post_id, $parameters, $handler_config);

        // Handle featured image if enabled and available
        $featured_image_result = null;
        if ($enable_images && !empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
            $featured_image_result = $this->set_featured_image($post_id, $image_url);
        }

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

    /**
     * Get effective post status with system defaults always overriding handler config
     */
    private function get_effective_post_status(array $handler_config): string {
        $all_settings = get_option('data_machine_settings', []);
        $wp_settings = $all_settings['wordpress_settings'] ?? [];
        $default_post_status = $wp_settings['default_post_status'] ?? '';

        // System default ALWAYS overrides handler config when set
        if (!empty($default_post_status)) {
            return $default_post_status;
        }

        // Fallback to handler config
        return $handler_config['post_status'] ?? 'draft';
    }

    /**
     * Get effective post author with system defaults always overriding handler config
     */
    private function get_effective_post_author(array $handler_config): int {
        $all_settings = get_option('data_machine_settings', []);
        $wp_settings = $all_settings['wordpress_settings'] ?? [];
        $default_author_id = $wp_settings['default_author_id'] ?? 0;

        // System default ALWAYS overrides handler config when set
        if (!empty($default_author_id)) {
            return $default_author_id;
        }

        // Fallback to handler config
        return $handler_config['post_author'] ?? get_current_user_id();
    }

    /**
     * Get effective include source with system defaults always overriding handler config
     */
    private function get_effective_include_source(array $handler_config): bool {
        $all_settings = get_option('data_machine_settings', []);
        $wp_settings = $all_settings['wordpress_settings'] ?? [];

        // System default ALWAYS overrides handler config when set (isset check for boolean values)
        if (isset($wp_settings['default_include_source'])) {
            return (bool) $wp_settings['default_include_source'];
        }

        // Fallback to handler config
        return (bool) ($handler_config['include_source'] ?? false);
    }

    /**
     * Get effective enable images with system defaults always overriding handler config
     */
    private function get_effective_enable_images(array $handler_config): bool {
        $all_settings = get_option('data_machine_settings', []);
        $wp_settings = $all_settings['wordpress_settings'] ?? [];

        // System default ALWAYS overrides handler config when set (isset check for boolean values)
        if (isset($wp_settings['default_enable_images'])) {
            return (bool) $wp_settings['default_enable_images'];
        }

        // Fallback to handler config
        return (bool) ($handler_config['enable_images'] ?? true);
    }



    /**
     * Process taxonomies with AI-driven or pre-configured assignments
     */
    private function process_taxonomies_from_settings(int $post_id, array $parameters, array $handler_config): array {
        $taxonomy_results = [];
        
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        
        foreach ($taxonomies as $taxonomy) {
            if (in_array($taxonomy->name, ['post_format', 'nav_menu', 'link_category'])) {
                continue;
            }
            
            $field_key = "taxonomy_{$taxonomy->name}_selection";
            $selection = $handler_config[$field_key] ?? 'skip';
            
            do_action('dm_log', 'debug', 'WordPress Tool: Processing taxonomy from settings', [
                'taxonomy_name' => $taxonomy->name,
                'field_key' => $field_key,
                'selection_value' => $selection,
                'selection_type' => gettype($selection)
            ]);
            
            if ($selection === 'skip') {
                continue;

            } elseif ($selection === 'ai_decides') {
                $param_name = $taxonomy->name === 'category' ? 'category' : 
                             ($taxonomy->name === 'post_tag' ? 'tags' : $taxonomy->name);
                
                if (!empty($parameters[$param_name])) {
                    $taxonomy_result = $this->assign_taxonomy($post_id, $taxonomy->name, $parameters[$param_name]);
                    $taxonomy_results[$taxonomy->name] = $taxonomy_result;
                    
                    do_action('dm_log', 'debug', 'WordPress Tool: Applied AI-decided taxonomy', [
                        'taxonomy_name' => $taxonomy->name,
                        'parameter_name' => $param_name,
                        'parameter_value' => $parameters[$param_name],
                        'result' => $taxonomy_result
                    ]);
                }
                
            } elseif (is_numeric($selection)) {
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
                        
                        do_action('dm_log', 'debug', 'WordPress Tool: Applied pre-selected taxonomy', [
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
     * Assign taxonomy terms with dynamic term creation
     */
    private function assign_taxonomy(int $post_id, string $taxonomy_name, $taxonomy_value): array {
        if (!taxonomy_exists($taxonomy_name)) {
            return [
                'success' => false,
                'error' => "Taxonomy '{$taxonomy_name}' does not exist"
            ];
        }
        
        $taxonomy_obj = get_taxonomy($taxonomy_name);
        $term_ids = [];
        
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
     * Download and set featured image from URL
     */
    private function set_featured_image(int $post_id, string $image_url): ?array {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        try {
            $temp_file = download_url($image_url);
            if (is_wp_error($temp_file)) {
                do_action('dm_log', 'warning', 'WordPress Featured Image: Failed to download image', [
                    'image_url' => $image_url,
                    'error' => $temp_file->get_error_message()
                ]);
                return ['success' => false, 'error' => 'Failed to download image'];
            }

            $file_array = [
                'name' => basename($image_url),
                'tmp_name' => $temp_file
            ];

            $attachment_id = media_handle_sideload($file_array, $post_id);
            
            if (is_wp_error($attachment_id)) {
                wp_delete_file($temp_file);
                do_action('dm_log', 'warning', 'WordPress Featured Image: Failed to create attachment', [
                    'image_url' => $image_url,
                    'error' => $attachment_id->get_error_message()
                ]);
                return ['success' => false, 'error' => 'Failed to create media attachment'];
            }

            $result = set_post_thumbnail($post_id, $attachment_id);
            
            if (!$result) {
                do_action('dm_log', 'warning', 'WordPress Featured Image: Failed to set featured image', [
                    'post_id' => $post_id,
                    'attachment_id' => $attachment_id
                ]);
                return ['success' => false, 'error' => 'Failed to set featured image'];
            }

            do_action('dm_log', 'debug', 'WordPress Featured Image: Successfully set featured image', [
                'post_id' => $post_id,
                'attachment_id' => $attachment_id,
                'image_url' => $image_url
            ]);

            return [
                'success' => true,
                'attachment_id' => $attachment_id,
                'attachment_url' => wp_get_attachment_url($attachment_id)
            ];

        } catch (Exception $e) {
            do_action('dm_log', 'error', 'WordPress Featured Image: Exception occurred', [
                'image_url' => $image_url,
                'exception' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'Exception during image processing'];
        }
    }

    /**
     * Sanitize and validate Gutenberg block content
     */
    private function sanitize_block_content(string $content): string {
        $content = wp_kses_post($content);
        $blocks = parse_blocks($content);
        $content = serialize_blocks($blocks);

        return $content;
    }
}


