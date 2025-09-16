<?php
/**
 * WordPress Post Reader - AI tool for retrieving complete WordPress post content by URL.
 *
 * Extracts full post content, metadata, and publication details for AI analysis.
 * No configuration required - works with any accessible WordPress post URL.
 *
 * @package DataMachine
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\AI\Tools;

defined('ABSPATH') || exit;

class WordPressPostReader {

    public function __construct() {
        add_filter('dm_tool_success_message', [$this, 'format_success_message'], 10, 4);
    }

    /**
     * Handle AI tool call to read WordPress post content.
     *
     * @param array $parameters Tool parameters containing 'source_url' (required), 'include_meta' (optional)
     * @param array $tool_def Tool definition (unused)
     * @return array Success response with post data or error response
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {

        if (empty($parameters['source_url'])) {
            return [
                'success' => false,
                'error' => 'WordPress Post Reader tool call missing required source_url parameter',
                'tool_name' => 'wordpress_post_reader'
            ];
        }

        $source_url = sanitize_url($parameters['source_url']);
        $include_meta = !empty($parameters['include_meta']);

        $post_id = url_to_postid($source_url);
        if (!$post_id) {
            return [
                'success' => false,
                'error' => sprintf('Could not extract valid WordPress post ID from URL: %s', $source_url),
                'tool_name' => 'wordpress_post_reader'
            ];
        }

        $post = get_post($post_id);

        if (!$post || $post->post_status === 'trash') {
            return [
                'success' => false,
                'error' => sprintf('Post at URL %s (ID: %d) not found or is trashed', $source_url, $post_id),
                'tool_name' => 'wordpress_post_reader'
            ];
        }

        $title = $post->post_title ?: '';
        $content = $post->post_content ?: '';
        $permalink = get_permalink($post_id);
        $post_type = get_post_type($post_id);
        $post_status = $post->post_status;
        $publish_date = get_the_date('Y-m-d H:i:s', $post_id);
        $author_name = get_the_author_meta('display_name', $post->post_author);

        $content_length = strlen($content);
        $content_word_count = str_word_count(wp_strip_all_tags($content));

        $featured_image_url = null;
        $featured_image_id = get_post_thumbnail_id($post_id);
        if ($featured_image_id) {
            $featured_image_url = wp_get_attachment_image_url($featured_image_id, 'full');
        }

        $response_data = [
            'post_id' => $post_id,
            'title' => $title,
            'content' => $content,
            'content_length' => $content_length,
            'content_word_count' => $content_word_count,
            'permalink' => $permalink,
            'post_type' => $post_type,
            'post_status' => $post_status,
            'publish_date' => $publish_date,
            'author' => $author_name,
            'featured_image' => $featured_image_url
        ];

        // Include public meta fields if requested (excludes private fields starting with _)
        if ($include_meta) {
            $meta_fields = get_post_meta($post_id);
            $clean_meta = [];
            foreach ($meta_fields as $key => $values) {
                if (strpos($key, '_') === 0) {
                    continue;
                }
                $clean_meta[$key] = count($values) === 1 ? $values[0] : $values;
            }
            $response_data['meta_fields'] = $clean_meta;
        } else {
            $response_data['meta_fields'] = [];
        }

        return [
            'success' => true,
            'data' => $response_data,
            'tool_name' => 'wordpress_post_reader'
        ];
    }

    /**
     * Check if tool is configured and available.
     *
     * @return bool Always true - no configuration required
     */
    public static function is_configured(): bool {
        return true;
    }

    /**
     * Format success message for WordPress post reading results.
     *
     * @param string $message Default message
     * @param string $tool_name Tool name
     * @param array $tool_result Tool execution result
     * @param array $tool_parameters Tool parameters
     * @return string Formatted success message
     */
    public function format_success_message($message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name !== 'wordpress_post_reader') {
            return $message;
        }

        $data = $tool_result['data'] ?? [];
        $source_url = $tool_parameters['source_url'] ?? 'the URL';
        $title = $data['title'] ?? '';
        $content_length = $data['content_length'] ?? 0;
        $word_count = $data['content_word_count'] ?? 0;

        if ($content_length === 0) {
            return "READ COMPLETE: WordPress post found at \"{$source_url}\" but has no content.";
        }

        $title_text = !empty($title) ? "\nPost Title: {$title}" : '';
        return "READ COMPLETE: Retrieved WordPress post from \"{$source_url}\".{$title_text}\nContent Length: {$content_length} characters ({$word_count} words)";
    }
}