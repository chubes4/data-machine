<?php
/**
 * Local Search AI Tool
 *
 * Provides local WordPress site search capabilities for AI models to gather
 * context and information from the current site's published content.
 *
 * @package DataMachine\Core\Steps\AI\Tools
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\AI\Tools;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Local Search Tool Implementation
 * 
 * Enables AI models to search the current WordPress site for context gathering,
 * research enhancement, and content discovery using WordPress's built-in search.
 */
class LocalSearch {

    /**
     * Handle tool call from AI model
     * 
     * @param array $parameters Tool call parameters from AI model
     * @param array $tool_def Tool definition (unused but required for interface)
     * @return array Standardized tool response
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        
        // Validate required parameters
        if (empty($parameters['query'])) {
            return [
                'success' => false,
                'error' => 'Local Search tool call missing required query parameter',
                'tool_name' => 'local_search'
            ];
        }

        // Extract parameters with defaults
        $query = sanitize_text_field($parameters['query']);
        $max_results = min(max(intval($parameters['max_results'] ?? 10), 1), 20); // Limit 1-20 results
        $post_types = $parameters['post_types'] ?? ['post', 'page'];
        
        // Validate and sanitize post types
        if (!is_array($post_types)) {
            $post_types = ['post', 'page'];
        }
        $post_types = array_map('sanitize_text_field', $post_types);
        
        // Build WP_Query arguments for search
        $query_args = [
            's' => $query, // WordPress search parameter
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $max_results,
            'orderby' => 'relevance', // WordPress search relevance ranking
            'order' => 'DESC',
            'no_found_rows' => false, // We need total count
            'update_post_meta_cache' => false, // Performance optimization
            'update_post_term_cache' => false, // Performance optimization
        ];
        
        // Execute search query
        $wp_query = new \WP_Query($query_args);
        
        if (is_wp_error($wp_query)) {
            return [
                'success' => false,
                'error' => 'WordPress search query failed: ' . $wp_query->get_error_message(),
                'tool_name' => 'local_search'
            ];
        }
        
        // Process search results
        $results = [];
        if ($wp_query->have_posts()) {
            while ($wp_query->have_posts()) {
                $wp_query->the_post();
                
                $post = get_post();
                $permalink = get_permalink($post->ID);
                
                // Get excerpt or truncated content
                $excerpt = get_the_excerpt($post->ID);
                if (empty($excerpt)) {
                    // Generate excerpt from content if none exists
                    $content = strip_tags(get_the_content('', false, $post));
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
            
            // Reset global post data
            wp_reset_postdata();
        }
        
        // Get search statistics
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
     * Check if Local Search tool is available
     * 
     * Local search is always available since it uses WordPress core functionality
     * 
     * @return bool Always true - no configuration required
     */
    public static function is_configured(): bool {
        return true; // Always available, no configuration needed
    }
    
    /**
     * Get available post types for search
     * 
     * @return array List of public post types available for search
     */
    public static function get_searchable_post_types(): array {
        $post_types = get_post_types([
            'public' => true,
            'exclude_from_search' => false
        ], 'names');
        
        return array_values($post_types);
    }
}