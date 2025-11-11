<?php
/**
 * WordPress Post Reader - AI tool for retrieving WordPress post content by URL.
 *
 * @package DataMachine
 */

namespace DataMachine\Core\Steps\AI\Tools;

defined('ABSPATH') || exit;

class WordPressPostReader {

    public function __construct() {
        add_filter('datamachine_tool_success_message', [$this, 'format_success_message'], 10, 4);
        $this->register_configuration();
    }

    private function register_configuration() {
        add_filter('ai_tools', [$this, 'register_tool'], 10, 1);
        add_filter('datamachine_tool_configured', [$this, 'check_configuration'], 10, 2);
    }

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

    public function register_tool($tools) {
        $tools['wordpress_post_reader'] = [
            'class' => __CLASS__,
            'method' => 'handle_tool_call',
            'name' => 'WordPress Post Reader',
            'description' => 'Read full content from specific WordPress posts by URL for detailed analysis. Use after Local Search to get complete post content instead of excerpts. Perfect for content analysis before WordPress Update operations.',
            'requires_config' => false,
            'parameters' => [
                'source_url' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'WordPress post URL to retrieve content from (use URLs from Local Search results)'
                ],
                'include_meta' => [
                    'type' => 'boolean',
                    'required' => false,
                    'description' => 'Include custom fields in response (default: false)'
                ]
            ]
        ];

        return $tools;
    }

    public static function is_configured(): bool {
        return true;
    }

    public function check_configuration($configured, $tool_id) {
        if ($tool_id !== 'wordpress_post_reader') {
            return $configured;
        }

        return self::is_configured();
    }

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

        return "READ COMPLETE: Retrieved WordPress post from \"{$source_url}\". Content Length: {$content_length} characters ({$word_count} words)";
    }
}

// Self-register the tool
new WordPressPostReader();