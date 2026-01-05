<?php
/**
 * Assign Taxonomy Term Tool
 *
 * Assigns a taxonomy term to one or more posts. Can append to existing terms
 * or replace them.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Core\WordPress\TaxonomyHandler;

class AssignTaxonomyTerm {
    use ToolRegistrationTrait;

    public function __construct() {
        $this->registerTool('chat', 'assign_taxonomy_term', [$this, 'getToolDefinition']);
    }

    public function getToolDefinition(): array {
        return [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => 'Assign a taxonomy term to one or more posts. Can append to existing terms or replace them.',
            'parameters' => [
                'term' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Term to assign - ID, name, or slug'
                ],
                'taxonomy' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Taxonomy slug (venue, artist, category, post_tag, etc.)'
                ],
                'post_ids' => [
                    'type' => 'array',
                    'required' => true,
                    'description' => 'Array of post IDs to assign the term to'
                ],
                'append' => [
                    'type' => 'boolean',
                    'required' => false,
                    'description' => 'true = add to existing terms, false = replace existing terms (default: true)'
                ]
            ]
        ];
    }

    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $term_identifier = $parameters['term'] ?? null;
        $taxonomy = $parameters['taxonomy'] ?? null;
        $post_ids = $parameters['post_ids'] ?? null;
        $append = $parameters['append'] ?? true;

        // Validate taxonomy
        if (empty($taxonomy)) {
            return [
                'success' => false,
                'error' => 'taxonomy parameter is required',
                'tool_name' => 'assign_taxonomy_term'
            ];
        }

        $taxonomy = sanitize_key($taxonomy);

        if (!taxonomy_exists($taxonomy)) {
            return [
                'success' => false,
                'error' => "Taxonomy '{$taxonomy}' does not exist",
                'tool_name' => 'assign_taxonomy_term'
            ];
        }

        if (TaxonomyHandler::shouldSkipTaxonomy($taxonomy)) {
            return [
                'success' => false,
                'error' => "Taxonomy '{$taxonomy}' is a system taxonomy and cannot be modified",
                'tool_name' => 'assign_taxonomy_term'
            ];
        }

        // Validate term
        if (empty($term_identifier)) {
            return [
                'success' => false,
                'error' => 'term parameter is required',
                'tool_name' => 'assign_taxonomy_term'
            ];
        }

        // Validate post_ids
        if (empty($post_ids) || !is_array($post_ids)) {
            return [
                'success' => false,
                'error' => 'post_ids parameter is required and must be an array',
                'tool_name' => 'assign_taxonomy_term'
            ];
        }

        // Resolve term
        $term = $this->resolveTerm($term_identifier, $taxonomy);
        if (!$term) {
            return [
                'success' => false,
                'error' => "Term '{$term_identifier}' not found in taxonomy '{$taxonomy}'",
                'tool_name' => 'assign_taxonomy_term'
            ];
        }

        // Process each post
        $success_count = 0;
        $failed_posts = [];

        foreach ($post_ids as $post_id) {
            $post_id = (int) $post_id;

            // Verify post exists
            if (!get_post($post_id)) {
                $failed_posts[] = $post_id;
                continue;
            }

            $result = wp_set_object_terms($post_id, $term->term_id, $taxonomy, $append);

            if (is_wp_error($result)) {
                $failed_posts[] = $post_id;
            } else {
                $success_count++;
            }
        }

        // Build message
        $mode = $append ? 'appended to existing terms' : 'replaced existing terms';
        $message = "Assigned '{$term->name}' to {$success_count} post" . ($success_count !== 1 ? 's' : '') . " ({$mode}).";

        if (!empty($failed_posts)) {
            $message .= " Failed for post IDs: " . implode(', ', $failed_posts) . ".";
        }

        return [
            'success' => empty($failed_posts),
            'data' => [
                'term_id' => $term->term_id,
                'term_name' => $term->name,
                'taxonomy' => $taxonomy,
                'posts_assigned' => $success_count,
                'posts_failed' => $failed_posts,
                'append_mode' => $append,
                'message' => $message
            ],
            'tool_name' => 'assign_taxonomy_term'
        ];
    }

    /**
     * Resolve term by ID, name, or slug.
     */
    private function resolveTerm(string $identifier, string $taxonomy): ?\WP_Term {
        // Try as ID
        if (is_numeric($identifier)) {
            $term = get_term((int) $identifier, $taxonomy);
            if ($term && !is_wp_error($term)) {
                return $term;
            }
        }

        // Try by name
        $term = get_term_by('name', $identifier, $taxonomy);
        if ($term) {
            return $term;
        }

        // Try by slug
        $term = get_term_by('slug', $identifier, $taxonomy);
        if ($term) {
            return $term;
        }

        return null;
    }
}
