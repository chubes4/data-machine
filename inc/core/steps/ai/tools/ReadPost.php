<?php
/**
 * Read Post AI Tool
 *
 * Provides AI models with targeted access to specific WordPress posts by ID.
 * Designed for workflow chaining: discover posts via Google Search Console or 
 * Local Search tools, then use read_post for detailed content analysis before
 * making updates via Update handlers. Differs from Fetch handlers which provide
 * bulk post retrieval as pipeline data sources.
 *
 * Example workflows:
 * 1. GSC tool finds underperforming posts → read_post analyzes content → WordPress Update optimizes
 * 2. Local Search discovers related posts → read_post reads content for context → content generation
 * 3. External audit identifies target posts → read_post provides full content → targeted improvements
 *
 * @package DataMachine\Core\Steps\AI\Tools
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\AI\Tools;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Read Post Tool Implementation
 * 
 * Enables targeted post analysis within AI workflows. Retrieves complete 
 * post content by ID for detailed analysis, typically after post discovery
 * via Google Search Console or Local Search, and before targeted updates.
 */
class ReadPost {

    /**
     * Handle tool call from AI model
     * 
     * @param array $parameters Tool call parameters from AI model
     * @param array $tool_def Tool definition (unused but required for interface)
     * @return array Standardized tool response
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        
        // Validate required parameters
        if (empty($parameters['post_id'])) {
            return [
                'success' => false,
                'error' => 'Read Post tool call missing required post_id parameter',
                'tool_name' => 'read_post'
            ];
        }

        // Extract and validate parameters
        $post_id = intval($parameters['post_id']);
        $include_meta = !empty($parameters['include_meta']);
        
        if ($post_id <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid post_id parameter - must be a positive integer',
                'tool_name' => 'read_post'
            ];
        }
        
        // Get the specific post
        $post = get_post($post_id);
        
        if (!$post || $post->post_status === 'trash') {
            return [
                'success' => false,
                'error' => sprintf('Post ID %d not found or is trashed', $post_id),
                'tool_name' => 'read_post'
            ];
        }
        
        // Extract post data
        $title = $post->post_title ?: '';
        $content = $post->post_content ?: '';
        $permalink = get_permalink($post_id);
        $post_type = get_post_type($post_id);
        $post_status = $post->post_status;
        $publish_date = get_the_date('Y-m-d H:i:s', $post_id);
        $author_name = get_the_author_meta('display_name', $post->post_author);
        
        // Get featured image if exists
        $featured_image_url = null;
        $featured_image_id = get_post_thumbnail_id($post_id);
        if ($featured_image_id) {
            $featured_image_url = wp_get_attachment_image_url($featured_image_id, 'full');
        }
        
        // Prepare response data
        $response_data = [
            'post_id' => $post_id,
            'title' => $title,
            'content' => $content,
            'permalink' => $permalink,
            'post_type' => $post_type,
            'post_status' => $post_status,
            'publish_date' => $publish_date,
            'author' => $author_name,
            'featured_image' => $featured_image_url
        ];
        
        // Include custom fields if requested
        if ($include_meta) {
            $meta_fields = get_post_meta($post_id);
            // Clean up meta fields - remove protected fields and simplify arrays
            $clean_meta = [];
            foreach ($meta_fields as $key => $values) {
                // Skip protected meta fields (starting with _)
                if (strpos($key, '_') === 0) {
                    continue;
                }
                // Simplify single-value arrays
                $clean_meta[$key] = count($values) === 1 ? $values[0] : $values;
            }
            $response_data['meta_fields'] = $clean_meta;
        } else {
            $response_data['meta_fields'] = [];
        }
        
        return [
            'success' => true,
            'data' => $response_data,
            'tool_name' => 'read_post'
        ];
    }
    
    /**
     * Check if Read Post tool is available
     * 
     * Read Post is always available since it uses WordPress core functionality
     * 
     * @return bool Always true - no configuration required
     */
    public static function is_configured(): bool {
        return true; // Always available, no configuration needed
    }
}