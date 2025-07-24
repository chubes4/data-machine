<?php
/**
 * HTTP service specifically designed for Data Machine handlers.
 * Centralizes common HTTP request patterns used by input and output handlers.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/handlers
 * @since      0.15.0
 */

namespace DataMachine\Handlers;

use DataMachine\Helpers\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class HttpService {

    /** @var Logger|null */
    private $logger;

    /**
     * Constructor.
     *
     * @param Logger|null $logger Optional logger instance.
     */
    public function __construct(?Logger $logger = null) {
        $this->logger = $logger;
    }

    /**
     * Make an HTTP GET request with standardized error handling.
     * Optimized for API data fetching patterns used by input handlers.
     *
     * @param string $url Request URL.
     * @param array $args Optional wp_remote_get arguments.
     * @param string $context Context for logging/error messages (e.g., 'Reddit API', 'RSS Feed').
     * @return array|WP_Error Parsed response data or WP_Error on failure.
     */
    public function get($url, $args = [], $context = 'API Request') {
        // Set default timeout if not provided
        if (!isset($args['timeout'])) {
            $args['timeout'] = 30;
        }
        $this->logger && $this->logger->info("Handler HTTP: Making GET request to {$context}.", [
            'url' => $url
        ]);

        // Make the request
        $response = wp_remote_get($url, $args);

        // Handle WordPress HTTP errors (network issues, timeouts, etc.)
        if (is_wp_error($response)) {
            $error_message = sprintf(
                __('Failed to connect to %s: %s', 'data-machine'),
                $context,
                $response->get_error_message()
            );
            
            $this->logger && $this->logger->error("Handler HTTP: Connection failed.", [
                'context' => $context,
                'url' => $url,
                'error' => $response->get_error_message()
            ]);
            
            return new WP_Error('http_connection_failed', $error_message, [
                'url' => $url,
                'context' => $context
            ]);
        }

        // Check HTTP status code
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code < 200 || $status_code >= 300) {
            $error_message = sprintf(
                __('%s returned HTTP %d', 'data-machine'),
                $context,
                $status_code
            );

            // Try to extract error details from response body
            $error_details = $this->extract_error_details($body, $context);
            if ($error_details) {
                $error_message .= ': ' . $error_details;
            }

            $this->logger && $this->logger->error("Handler HTTP: HTTP error response.", [
                'context' => $context,
                'url' => $url,
                'status_code' => $status_code,
                'body_preview' => substr($body, 0, 200)
            ]);

            return new WP_Error('http_status_error', $error_message, [
                'url' => $url,
                'context' => $context,
                'status_code' => $status_code,
                'body' => $body
            ]);
        }

        $this->logger && $this->logger->info("Handler HTTP: Successful response from {$context}.", [
            'url' => $url,
            'status_code' => $status_code,
            'content_length' => strlen($body)
        ]);

        // Return successful response data
        return [
            'body' => $body,
            'status_code' => $status_code,
            'headers' => wp_remote_retrieve_headers($response),
            'response' => $response
        ];
    }

    /**
     * Make an HTTP POST request with standardized error handling.
     * Optimized for API posting patterns used by output handlers.
     *
     * @param string $url Request URL.
     * @param array $data Data to post.
     * @param array $args Optional wp_remote_post arguments.
     * @param string $context Context for logging/error messages.
     * @return array|WP_Error Parsed response data or WP_Error on failure.
     */
    public function post($url, $data = [], $args = [], $context = 'API Post') {
        // Set default timeout if not provided
        if (!isset($args['timeout'])) {
            $args['timeout'] = 30;
        }

        // Set body data
        if (!empty($data) && !isset($args['body'])) {
            $args['body'] = $data;
        }

        $this->logger && $this->logger->info("Handler HTTP: Making POST request to {$context}.", [
            'url' => $url,
            'data_size' => is_array($data) ? count($data) : strlen($data)
        ]);

        // Make the request
        $response = wp_remote_post($url, $args);

        // Use same error handling as GET method
        if (is_wp_error($response)) {
            $error_message = sprintf(
                __('Failed to post to %s: %s', 'data-machine'),
                $context,
                $response->get_error_message()
            );
            
            $this->logger && $this->logger->error("Handler HTTP: POST connection failed.", [
                'context' => $context,
                'url' => $url,
                'error' => $response->get_error_message()
            ]);
            
            return new WP_Error('http_post_failed', $error_message, [
                'url' => $url,
                'context' => $context
            ]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // POST requests can return various success codes
        if ($status_code < 200 || $status_code >= 400) {
            $error_message = sprintf(
                __('%s POST returned HTTP %d', 'data-machine'),
                $context,
                $status_code
            );

            $error_details = $this->extract_error_details($body, $context);
            if ($error_details) {
                $error_message .= ': ' . $error_details;
            }

            $this->logger && $this->logger->error("Handler HTTP: POST error response.", [
                'context' => $context,
                'url' => $url,
                'status_code' => $status_code,
                'body_preview' => substr($body, 0, 200)
            ]);

            return new WP_Error('http_post_error', $error_message, [
                'url' => $url,
                'context' => $context,
                'status_code' => $status_code,
                'body' => $body
            ]);
        }

        $this->logger && $this->logger->info("Handler HTTP: Successful POST to {$context}.", [
            'url' => $url,
            'status_code' => $status_code
        ]);

        return [
            'body' => $body,
            'status_code' => $status_code,
            'headers' => wp_remote_retrieve_headers($response),
            'response' => $response
        ];
    }

    /**
     * Parse JSON response with error handling.
     * Common pattern across all API-based handlers.
     *
     * @param string $json_string JSON response body.
     * @param string $context Context for error messages.
     * @return array|WP_Error Parsed JSON data or WP_Error on failure.
     */
    public function parse_json($json_string, $context = 'API Response') {
        if (empty($json_string)) {
            $this->logger && $this->logger->error("Handler HTTP: Empty JSON response from {$context}.");
            return new WP_Error('empty_json_response', sprintf(
                __('Empty response from %s', 'data-machine'),
                $context
            ));
        }

        $decoded = json_decode($json_string, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $json_error = json_last_error_msg();
            $this->logger && $this->logger->error("Handler HTTP: JSON decode error from {$context}.", [
                'error' => $json_error,
                'json_preview' => substr($json_string, 0, 200)
            ]);
            
            return new WP_Error('json_decode_error', sprintf(
                __('Invalid JSON from %s: %s', 'data-machine'),
                $context,
                $json_error
            ));
        }

        if (!is_array($decoded)) {
            $this->logger && $this->logger->error("Handler HTTP: Non-array JSON response from {$context}.");
            return new WP_Error('invalid_json_structure', sprintf(
                __('Unexpected JSON structure from %s', 'data-machine'),
                $context
            ));
        }

        $this->logger && $this->logger->info("Handler HTTP: Successfully parsed JSON from {$context}.", [
            'item_count' => count($decoded)
        ]);

        return $decoded;
    }

    /**
     * Extract pagination information from HTTP headers.
     * Used by input handlers that deal with paginated APIs.
     *
     * @param array|WP_HTTP_Requests_Response_Headers $headers Response headers.
     * @return array Pagination info (next_url, has_more, etc.) or empty array.
     */
    public function extract_pagination_info($headers) {
        $pagination = [
            'next_url' => null,
            'has_more' => false
        ];

        // Handle Link header (common in REST APIs)
        if (isset($headers['link']) || isset($headers['Link'])) {
            $link_header = $headers['link'] ?? $headers['Link'];
            
            if (preg_match('/<([^>]+)>;\s*rel="next"/', $link_header, $matches)) {
                $pagination['next_url'] = $matches[1];
                $pagination['has_more'] = true;
                
                $this->logger && $this->logger->info('Handler HTTP: Found pagination link.', [
                    'next_url' => $pagination['next_url']
                ]);
            }
        }

        // Handle Reddit-style pagination (after parameter)
        if (isset($headers['x-ratelimit-remaining'])) {
            // Reddit-specific logic can be added here
        }

        return $pagination;
    }

    /**
     * Build common request headers for API calls.
     * Standardizes User-Agent and other common headers.
     *
     * @param array $additional_headers Additional headers to include.
     * @param string $context Context for User-Agent string.
     * @return array Complete headers array.
     */
    public function build_headers($additional_headers = [], $context = 'DataMachine') {
        $default_headers = [
            'User-Agent' => sprintf(
                '%s/%s (+%s)',
                $context,
                defined('DATA_MACHINE_VERSION') ? DATA_MACHINE_VERSION : '1.0',
                home_url()
            ),
            'Accept' => 'application/json',
            'Accept-Language' => 'en-US,en;q=0.9'
        ];

        return array_merge($default_headers, $additional_headers);
    }

    /**
     * Extract error details from response body.
     * Tries common error message patterns across different APIs.
     *
     * @param string $body Response body.
     * @param string $context API context.
     * @return string|null Error message or null if none found.
     */
    private function extract_error_details($body, $context) {
        if (empty($body)) {
            return null;
        }

        // Try to parse as JSON first
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            // Common error message keys across APIs
            $error_keys = ['message', 'error', 'error_description', 'detail', 'errors'];
            
            foreach ($error_keys as $key) {
                if (isset($decoded[$key]) && is_string($decoded[$key])) {
                    return $decoded[$key];
                }
            }

            // Handle nested error structures
            if (isset($decoded['error']) && is_array($decoded['error'])) {
                if (isset($decoded['error']['message'])) {
                    return $decoded['error']['message'];
                }
            }
        }

        // If not JSON, return first line of body (truncated)
        $first_line = strtok($body, "\n");
        return strlen($first_line) > 100 ? substr($first_line, 0, 97) . '...' : $first_line;
    }

}