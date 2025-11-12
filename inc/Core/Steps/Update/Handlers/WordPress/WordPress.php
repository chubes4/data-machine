<?php
/**
 * WordPress Update handler for post modification.
 *
 * @package DataMachine\Core\Steps\Update\Handlers\WordPress
 */

namespace DataMachine\Core\Steps\Update\Handlers\WordPress;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WordPress {

    public function __construct() {
    }

    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $job_id = $parameters['job_id'] ?? null;
        $engine_data = apply_filters('datamachine_engine_data', [], $job_id);
        $source_url = $engine_data['source_url'] ?? null;

        do_action('datamachine_log', 'debug', 'WordPress Update Tool: Handling tool call', [
            'parameters' => $parameters,
            'parameter_keys' => array_keys($parameters),
            'has_handler_config' => !empty($tool_def['handler_config']),
            'handler_config_keys' => array_keys($tool_def['handler_config'] ?? []),
            'source_url_from_engine' => $source_url
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

        $handler_config = $tool_def['handler_config'] ?? [];
        
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
            $result = $this->apply_surgical_updates($original_content, $parameters['updates']);
            $post_data['post_content'] = $this->sanitize_block_content($result['content']);
            $all_changes['content_updates'] = $result['changes'];
        }

        if (!empty($parameters['block_updates'])) {
            $working_content = $post_data['post_content'] ?? $original_content;
            $result = $this->apply_block_updates($working_content, $parameters['block_updates']);
            $post_data['post_content'] = $this->sanitize_block_content($result['content']);
            $all_changes['block_updates'] = $result['changes'];
        }

        if (!empty($parameters['title'])) {
            $post_data['post_title'] = sanitize_text_field(wp_unslash($parameters['title']));
            $all_changes['title_update'] = true;
        }

        if (!empty($parameters['content']) && !isset($post_data['post_content'])) {
            $post_data['post_content'] = $this->sanitize_block_content(wp_unslash($parameters['content']));
            $all_changes['legacy_content_update'] = true;
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

        $taxonomy_results = $this->process_taxonomies_from_settings($post_id, $parameters, $handler_config);

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

    /**
     * Process taxonomies with three modes: skip, AI-decided, or pre-selected term.
     */
    private function process_taxonomies_from_settings(int $post_id, array $parameters, array $handler_config): array {
        $taxonomy_results = [];

        $taxonomies = get_taxonomies(['public' => true], 'objects');

        foreach ($taxonomies as $taxonomy) {
            $excluded = apply_filters('datamachine_wordpress_system_taxonomies', []);
            if (in_array($taxonomy->name, $excluded)) {
                continue;
            }

            $field_key = "taxonomy_{$taxonomy->name}_selection";
            $selection = $handler_config[$field_key] ?? 'skip';

            do_action('datamachine_log', 'debug', 'WordPress Update Tool: Processing taxonomy from settings', [
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

                    do_action('datamachine_log', 'debug', 'WordPress Update Tool: Applied AI-decided taxonomy', [
                        'taxonomy_name' => $taxonomy->name,
                        'parameter_name' => $param_name,
                        'parameter_value' => $parameters[$param_name],
                        'result' => $taxonomy_result
                    ]);
                }

            } elseif (is_numeric($selection)) {
                $term_id = absint($selection);
                $term_name = apply_filters('datamachine_wordpress_term_name', null, $term_id, $taxonomy->name);

                if ($term_name !== null) {
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

                        do_action('datamachine_log', 'debug', 'WordPress Update Tool: Applied pre-selected taxonomy', [
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
     * Assign taxonomy terms with dynamic term creation.
     */
    private function assign_taxonomy(int $post_id, string $taxonomy_name, $taxonomy_value): array {
        if (!taxonomy_exists($taxonomy_name)) {
            return [
                'success' => false,
                'error' => "Taxonomy '{$taxonomy_name}' does not exist"
            ];
        }

        $term_ids = [];

        $terms = is_array($taxonomy_value) ? $taxonomy_value : [$taxonomy_value];

        foreach ($terms as $term_name) {
            $term_name = sanitize_text_field($term_name);
            if (empty($term_name)) continue;

            $term = get_term_by('name', $term_name, $taxonomy_name);
            if (!$term) {
                $term_result = wp_insert_term($term_name, $taxonomy_name);
                if (is_wp_error($term_result)) {
                    do_action('datamachine_log', 'warning', 'Failed to create taxonomy term', [
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
     * Apply surgical find-and-replace updates with change tracking.
     */
    private function apply_surgical_updates(string $original_content, array $updates): array {
        $working_content = $original_content;
        $changes_made = [];

        foreach ($updates as $update) {
            if (!isset($update['find']) || !isset($update['replace'])) {
                $changes_made[] = [
                    'found' => $update['find'] ?? '',
                    'replaced_with' => $update['replace'] ?? '',
                    'success' => false,
                    'error' => 'Missing find or replace parameter'
                ];
                continue;
            }

            $find = $update['find'];
            $replace = $update['replace'];

            if (strpos($working_content, $find) !== false) {
                $working_content = str_replace($find, $replace, $working_content);
                $changes_made[] = [
                    'found' => $find,
                    'replaced_with' => $replace,
                    'success' => true
                ];

                do_action('datamachine_log', 'debug', 'WordPress Update: Surgical update applied', [
                    'find_length' => strlen($find),
                    'replace_length' => strlen($replace),
                    'change_successful' => true
                ]);
            } else {
                $changes_made[] = [
                    'found' => $find,
                    'replaced_with' => $replace,
                    'success' => false,
                    'error' => 'Target text not found in content'
                ];

                do_action('datamachine_log', 'warning', 'WordPress Update: Surgical update target not found', [
                    'find_text' => substr($find, 0, 100) . (strlen($find) > 100 ? '...' : ''),
                    'content_length' => strlen($working_content)
                ]);
            }
        }

        return [
            'content' => $working_content,
            'changes' => $changes_made
        ];
    }

    /**
     * Apply targeted updates to specific Gutenberg blocks by index.
     */
    private function apply_block_updates(string $original_content, array $block_updates): array {
        $blocks = parse_blocks($original_content);
        $changes_made = [];

        foreach ($block_updates as $update) {
            if (!isset($update['block_index']) || !isset($update['find']) || !isset($update['replace'])) {
                $changes_made[] = [
                    'block_index' => $update['block_index'] ?? 'unknown',
                    'found' => $update['find'] ?? '',
                    'replaced_with' => $update['replace'] ?? '',
                    'success' => false,
                    'error' => 'Missing required parameters (block_index, find, replace)'
                ];
                continue;
            }

            $target_index = $update['block_index'];
            $find = $update['find'];
            $replace = $update['replace'];

            if (isset($blocks[$target_index])) {
                $old_content = $blocks[$target_index]['innerHTML'] ?? '';

                if (strpos($old_content, $find) !== false) {
                    $blocks[$target_index]['innerHTML'] = str_replace($find, $replace, $old_content);
                    $changes_made[] = [
                        'block_index' => $target_index,
                        'found' => $find,
                        'replaced_with' => $replace,
                        'success' => true
                    ];

                    do_action('datamachine_log', 'debug', 'WordPress Update: Block update applied', [
                        'block_index' => $target_index,
                        'block_type' => $blocks[$target_index]['blockName'] ?? 'unknown',
                        'find_length' => strlen($find),
                        'replace_length' => strlen($replace)
                    ]);
                } else {
                    $changes_made[] = [
                        'block_index' => $target_index,
                        'found' => $find,
                        'replaced_with' => $replace,
                        'success' => false,
                        'error' => 'Target text not found in block'
                    ];

                    do_action('datamachine_log', 'warning', 'WordPress Update: Block update target not found', [
                        'block_index' => $target_index,
                        'block_type' => $blocks[$target_index]['blockName'] ?? 'unknown',
                        'find_text' => substr($find, 0, 100) . (strlen($find) > 100 ? '...' : '')
                    ]);
                }
            } else {
                $changes_made[] = [
                    'block_index' => $target_index,
                    'found' => $find,
                    'replaced_with' => $replace,
                    'success' => false,
                    'error' => 'Block index does not exist'
                ];

                do_action('datamachine_log', 'warning', 'WordPress Update: Block index out of range', [
                    'requested_index' => $target_index,
                    'total_blocks' => count($blocks)
                ]);
            }
        }

        return [
            'content' => serialize_blocks($blocks),
            'changes' => $changes_made
        ];
    }

    /**
     * Sanitize Gutenberg blocks with recursive innerHTML processing.
     */
    private function sanitize_block_content(string $content): string {
        $blocks = parse_blocks($content);

        $filtered_blocks = array_map(function($block) {
            if (isset($block['innerHTML']) && $block['innerHTML'] !== '') {
                $block['innerHTML'] = wp_kses_post($block['innerHTML']);
            }
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
