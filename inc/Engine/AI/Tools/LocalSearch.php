<?php
/**
 * WordPress Local Search AI Tool - Site content discovery for AI agents
 */

namespace DataMachine\Engine\AI\Tools;

defined('ABSPATH') || exit;

/**
 * WordPress site search for AI context gathering.
 */
class LocalSearch {

    public function __construct() {
        add_filter('datamachine_tool_success_message', [$this, 'format_success_message'], 10, 4);
        $this->register_configuration();
    }

    private function register_configuration() {
        add_filter('datamachine_global_tools', [$this, 'register_tool'], 10, 1);
        add_filter('datamachine_tool_configured', [$this, 'check_configuration'], 10, 2);
    }

    public function handle_tool_call(array $parameters, array $tool_def = []): array {

        if (empty($parameters['query'])) {
            return [
                'success' => false,
                'error' => 'Local Search tool call missing required query parameter',
                'tool_name' => 'local_search'
            ];
        }

        $query = sanitize_text_field($parameters['query']);
        $max_results = 10;
        $post_types = $parameters['post_types'] ?? ['post', 'page'];

        if (!is_array($post_types)) {
            $post_types = ['post', 'page'];
        }
        $post_types = array_map('sanitize_text_field', $post_types);

        $query_args = [
            's' => $query,
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $max_results,
            'orderby' => 'relevance',
            'order' => 'DESC',
            'no_found_rows' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        $wp_query = new \WP_Query($query_args);

        if (is_wp_error($wp_query)) {
            return [
                'success' => false,
                'error' => 'WordPress search query failed: ' . $wp_query->get_error_message(),
                'tool_name' => 'local_search'
            ];
        }

        $results = [];
        if ($wp_query->have_posts()) {
            while ($wp_query->have_posts()) {
                $wp_query->the_post();

                $post = get_post();
                $permalink = get_permalink($post->ID);

                $excerpt = get_the_excerpt($post->ID);
                if (empty($excerpt)) {
                    $content = wp_strip_all_tags(get_the_content('', false, $post));
                    $excerpt = wp_trim_words($content, 25, '...');
                }

                $results[] = [
                    'title' => get_the_title($post->ID),
                    'link' => $permalink,
                    'excerpt' => $excerpt,
                    'post_type' => get_post_type($post->ID),
                    'publish_date' => get_the_date('Y-m-d H:i:s', $post->ID),
                    'author' => get_the_author_meta('display_name', $post->post_author)
                ];
            }

            wp_reset_postdata();
        }

        $total_results = $wp_query->found_posts;
        $results_count = count($results);

        return [
            'success' => true,
            'data' => [
                'query' => $query,
                'results_count' => $results_count,
                'total_available' => $total_results,
                'post_types_searched' => $post_types,
                'max_results_requested' => $max_results,
                'results' => $results
            ],
            'tool_name' => 'local_search'
        ];
    }

    /**
     * Register Local Search tool with the global tools system.
     *
     * @param array $tools Existing tools array
     * @return array Updated tools array with Local Search tool
     */
    public function register_tool($tools) {
        $tools['local_search'] = [
            'class' => __CLASS__,
            'method' => 'handle_tool_call',
            'description' => 'Search this WordPress site and return up to 10 structured JSON results with post titles, excerpts, permalinks, and metadata. Prefer this over external search tools for site-specific content discovery. Use ONCE to find existing content before creating new content. Returns complete search data in JSON format - avoid calling multiple times for the same query.',
            'requires_config' => false,
            'parameters' => [
                'query' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Search terms to find relevant posts. Returns JSON with "results" array containing title, link, excerpt, post_type, publish_date, author for each match.'
                ],
                'post_types' => [
                    'type' => 'array',
                    'required' => false,
                    'description' => 'Post types to search (default: ["post", "page"]). Available types depend on site configuration.'
                ]
            ]
        ];

        return $tools;
    }

    public static function is_configured(): bool {
        return true;
    }

    /**
     * Check if Local Search tool is properly configured.
     *
     * @param bool $configured Current configuration status
     * @param string $tool_id Tool identifier to check
     * @return bool True if configured, false otherwise
     */
    public function check_configuration($configured, $tool_id) {
        if ($tool_id !== 'local_search') {
            return $configured;
        }

        return self::is_configured();
    }

    public static function get_searchable_post_types(): array {
        $post_types = get_post_types([
            'public' => true,
            'exclude_from_search' => false
        ], 'names');

        return array_values($post_types);
    }

    public function format_success_message($message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name !== 'local_search') {
            return $message;
        }

        $data = $tool_result['data'] ?? [];
        $results = $data['results'] ?? [];
        $result_count = $data['results_count'] ?? count($results);
        $query = $tool_parameters['query'] ?? 'your query';

        if (empty($results)) {
            return "SEARCH COMPLETE: No WordPress posts/pages found matching \"{$query}\".";
        }

        return "SEARCH COMPLETE: Found {$result_count} WordPress posts matching \"{$query}\".\nSearch Results:";
    }
}

// Self-register the tool
new LocalSearch();
