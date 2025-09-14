<?php
/**
 * Web Fetch AI Tool - Retrieves and processes web page content with 50K character limit
 */
namespace DataMachine\Core\Steps\AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WebFetch {

    public function __construct() {
        add_filter('dm_tool_success_message', [$this, 'format_success_message'], 10, 4);
    }

    /**
     * Handle web fetch tool call from AI agents.
     *
     * @param array $parameters Parameters from AI tool call
     * @param array $tool_def Tool definition (unused)
     * @return array Success/error response with fetched content
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {

        $url = $parameters['url'] ?? '';
        if (empty($url)) {
            return [
                'success' => false,
                'error' => 'URL parameter is required',
                'tool_name' => 'web_fetch'
            ];
        }

        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'])) {
            return [
                'success' => false,
                'error' => 'Invalid URL format. Must be a valid HTTP or HTTPS URL',
                'tool_name' => 'web_fetch'
            ];
        }

        do_action('dm_log', 'debug', 'Web Fetch: Starting content retrieval', [
            'url' => $url,
            'url_host' => parse_url($url, PHP_URL_HOST)
        ]);

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Data Machine WordPress Plugin/1.0 (WordPress/' . get_bloginfo('version') . ')'
            ]
        ]);

        if (is_wp_error($response)) {
            do_action('dm_log', 'error', 'Web Fetch: HTTP request failed', [
                'url' => $url,
                'error' => $response->get_error_message()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to fetch URL: ' . $response->get_error_message(),
                'tool_name' => 'web_fetch'
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            do_action('dm_log', 'error', 'Web Fetch: HTTP error response', [
                'url' => $url,
                'status_code' => $status_code
            ]);

            return [
                'success' => false,
                'error' => "HTTP error {$status_code} when fetching URL",
                'tool_name' => 'web_fetch'
            ];
        }

        $html_content = wp_remote_retrieve_body($response);
        if (empty($html_content)) {
            return [
                'success' => false,
                'error' => 'No content received from URL',
                'tool_name' => 'web_fetch'
            ];
        }

        $extracted_content = $this->extract_readable_content($html_content);

        $max_length = 50000;
        $content_truncated = false;
        if (strlen($extracted_content['content']) > $max_length) {
            $extracted_content['content'] = substr($extracted_content['content'], 0, $max_length) . '... [Content truncated]';
            $content_truncated = true;
        }

        do_action('dm_log', 'debug', 'Web Fetch: Content retrieved successfully', [
            'url' => $url,
            'title_length' => strlen($extracted_content['title']),
            'content_length' => strlen($extracted_content['content']),
            'content_truncated' => $content_truncated
        ]);

        return [
            'success' => true,
            'data' => [
                'url' => $url,
                'title' => $extracted_content['title'],
                'content' => $extracted_content['content'],
                'content_length' => strlen($extracted_content['content']),
                'content_truncated' => $content_truncated,
                'fetch_timestamp' => gmdate('Y-m-d H:i:s')
            ],
            'tool_name' => 'web_fetch'
        ];
    }

    /**
     * Extract readable content from HTML.
     *
     * @param string $html_content Raw HTML content
     * @return array Array with 'title' and 'content' keys
     */
    private function extract_readable_content(string $html_content): array {

        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html_content, $title_matches)) {
            $title = html_entity_decode(strip_tags($title_matches[1]), ENT_QUOTES, 'UTF-8');
            $title = trim($title);
        }

        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html_content);
        $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);

        $unwanted_elements = ['nav', 'header', 'footer', 'aside', 'menu'];
        foreach ($unwanted_elements as $element) {
            $content = preg_replace("/<{$element}[^>]*>.*?<\/{$element}>/is", '', $content);
        }

        $block_elements = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'br'];
        foreach ($block_elements as $element) {
            $content = preg_replace("/<\/{$element}>/i", "</{$element}>\n", $content);
        }

        $content = strip_tags($content);

        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

        $content = preg_replace('/\n\s*\n/', "\n\n", $content); // Multiple newlines to double newlines
        $content = preg_replace('/[ \t]+/', ' ', $content); // Multiple spaces/tabs to single space
        $content = trim($content);

        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        return [
            'title' => $title,
            'content' => $content
        ];
    }

    /**
     * Format success message for web fetch results.
     *
     * @param string $message Default message
     * @param string $tool_name Tool name
     * @param array $tool_result Tool execution result
     * @param array $tool_parameters Tool parameters
     * @return string Formatted success message
     */
    public function format_success_message($message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name !== 'web_fetch') {
            return $message;
        }

        $data = $tool_result['data'] ?? [];
        $url = $tool_parameters['url'] ?? 'the URL';
        $title = $data['title'] ?? '';
        $content_length = $data['content_length'] ?? 0;

        if ($content_length === 0 || empty($data['content'])) {
            return "FETCH COMPLETE: No readable content found at \"{$url}\".";
        }

        $title_text = !empty($title) ? "\nPage Title: {$title}" : '';
        return "FETCH COMPLETE: Retrieved content from \"{$url}\".{$title_text}\nContent Length: {$content_length} characters";
    }
}