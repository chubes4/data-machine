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

    private $taxonomy_handler;

    public function __construct() {
        $this->taxonomy_handler = apply_filters('datamachine_get_taxonomy_handler', null);
    }

    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $job_id = $parameters['job_id'] ?? null;
        $engine_data = datamachine_get_engine_data($job_id);
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

        $taxonomy_results = [];
        if ($this->taxonomy_handler) {
            $taxonomy_results = $this->taxonomy_handler->processTaxonomies($post_id, $parameters, $handler_config);
        }

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
     * Apply surgical find-and-replace updates with change tracking.
     *
     * @param string $original_content The original post content
     * @param array $updates Array of update operations with 'find' and 'replace' keys
     * @return array Array with 'content' (updated content) and 'changes' (change tracking)
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
     *
     * @param string $original_content The original post content with blocks
     * @param array $block_updates Array of block update operations with 'block_index', 'find', 'replace' keys
     * @return array Array with 'content' (updated serialized blocks) and 'changes' (change tracking)
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
     *
     * @param string $content The block content to sanitize
     * @return string Sanitized block content with safe HTML
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
