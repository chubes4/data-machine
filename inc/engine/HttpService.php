<?php
/**
 * HTTP service specifically designed for Data Machine handlers.
 * Centralizes common HTTP request patterns used by input and output handlers.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/handlers
 * @since      0.15.0
 */

namespace DataMachine\Engine;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * HTTP service for Data Machine handlers.
 * 
 * Provides centralized HTTP request functionality with standardized error handling,
 * timeout management, and logging integration for all handler components.
 * 
 * @since 0.15.0
 */
class HttpService {

    /**
     * Constructor - parameter-less for pure filter-based architecture
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
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
        do_action('dm_log', 'debug', "Handler HTTP: Making GET request to {$context}.", [
            'url' => $url
        ]);

        // Make the request
        $response = wp_remote_get($url, $args);

        // Handle WordPress HTTP errors (network issues, timeouts, etc.)
        if (is_wp_error($response)) {
            $error_message = sprintf(
                /* translators: %1$s: context/service name, %2$s: error message */
                __('Failed to connect to %1$s: %2$s', 'data-machine'),
                $context,
                $response->get_error_message()
            );
            
            // Log raw server response for timeout and connection issues
            do_action('dm_log', 'error', "Handler HTTP: Connection failed.", [
                'context' => $context,
                'url' => $url,
                'error' => $response->get_error_message(),
                'error_code' => $response->get_error_code(),
                'raw_response' => $response->get_error_data()
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
                /* translators: %1$s: context/service name, %2$d: HTTP status code */
                __('%1$s returned HTTP %2$d', 'data-machine'),
                $context,
                $status_code
            );

            // Try to extract error details from response body
            $error_details = $this->extract_error_details($body, $context);
            if ($error_details) {
                $error_message .= ': ' . $error_details;
            }

            do_action('dm_log', 'error', "Handler HTTP: HTTP error response.", [
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

        do_action('dm_log', 'debug', "Handler HTTP: Successful response from {$context}.", [
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
        // Set body data
        if (!empty($data) && !isset($args['body'])) {
            $args['body'] = $data;
        }

        do_action('dm_log', 'debug', "Handler HTTP: Making POST request to {$context}.", [
            'url' => $url,
            'data_size' => is_array($data) ? count($data) : strlen($data)
        ]);

        // Make the request
        $response = wp_remote_post($url, $args);

        // Use same error handling as GET method
        if (is_wp_error($response)) {
            $error_message = sprintf(
                /* translators: %1$s: context/service name, %2$s: error message */
                __('Failed to post to %1$s: %2$s', 'data-machine'),
                $context,
                $response->get_error_message()
            );
            
            // Log raw server response for timeout and connection issues
            do_action('dm_log', 'error', "Handler HTTP: POST connection failed.", [
                'context' => $context,
                'url' => $url,
                'error' => $response->get_error_message(),
                'error_code' => $response->get_error_code(),
                'raw_response' => $response->get_error_data()
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
                /* translators: %1$s: context/service name, %2$d: HTTP status code */
                __('%1$s POST returned HTTP %2$d', 'data-machine'),
                $context,
                $status_code
            );

            $error_details = $this->extract_error_details($body, $context);
            if ($error_details) {
                $error_message .= ': ' . $error_details;
            }

            do_action('dm_log', 'error', "Handler HTTP: POST error response.", [
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

        do_action('dm_log', 'debug', "Handler HTTP: Successful POST to {$context}.", [
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
            do_action('dm_log', 'error', "Handler HTTP: Empty JSON response from {$context}.");
            return new WP_Error('empty_json_response', sprintf(
                /* translators: %s: context/service name */
                __('Empty response from %s', 'data-machine'),
                $context
            ));
        }

        $decoded = json_decode($json_string, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $json_error = json_last_error_msg();
            do_action('dm_log', 'error', "Handler HTTP: JSON decode error from {$context}.", [
                'error' => $json_error,
                'json_preview' => substr($json_string, 0, 200)
            ]);
            
            return new WP_Error('json_decode_error', sprintf(
                /* translators: %1$s: context/service name, %2$s: JSON error message */
                __('Invalid JSON from %1$s: %2$s', 'data-machine'),
                $context,
                $json_error
            ));
        }

        if (!is_array($decoded)) {
            do_action('dm_log', 'error', "Handler HTTP: Non-array JSON response from {$context}.");
            return new WP_Error('invalid_json_structure', sprintf(
                /* translators: %s: context/service name */
                __('Unexpected JSON structure from %s', 'data-machine'),
                $context
            ));
        }

        do_action('dm_log', 'debug', "Handler HTTP: Successfully parsed JSON from {$context}.", [
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
                
                do_action('dm_log', 'debug', 'Handler HTTP: Found pagination link.', [
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
     * Make an HTTP PUT request with standardized error handling.
     * Used for API updates and resource replacement.
     *
     * @param string $url Request URL.
     * @param array $data Data to put.
     * @param array $args Optional wp_remote_request arguments.
     * @param string $context Context for logging/error messages.
     * @return array|WP_Error Parsed response data or WP_Error on failure.
     */
    public function put($url, $data = [], $args = [], $context = 'API Put') {
        $args['method'] = 'PUT';
        
        // Set body data
        if (!empty($data) && !isset($args['body'])) {
            $args['body'] = $data;
        }

        return $this->make_request($url, $args, $context);
    }

    /**
     * Make an HTTP DELETE request with standardized error handling.
     * Used for API resource deletion.
     *
     * @param string $url Request URL.
     * @param array $args Optional wp_remote_request arguments.
     * @param string $context Context for logging/error messages.
     * @return array|WP_Error Parsed response data or WP_Error on failure.
     */
    public function delete($url, $args = [], $context = 'API Delete') {
        $args['method'] = 'DELETE';
        return $this->make_request($url, $args, $context);
    }

    /**
     * Make an HTTP PATCH request with standardized error handling.
     * Used for API partial updates.
     *
     * @param string $url Request URL.
     * @param array $data Data to patch.
     * @param array $args Optional wp_remote_request arguments.
     * @param string $context Context for logging/error messages.
     * @return array|WP_Error Parsed response data or WP_Error on failure.
     */
    public function patch($url, $data = [], $args = [], $context = 'API Patch') {
        $args['method'] = 'PATCH';
        
        // Set body data
        if (!empty($data) && !isset($args['body'])) {
            $args['body'] = $data;
        }

        return $this->make_request($url, $args, $context);
    }

    /**
     * Generic HTTP request method used by PUT, DELETE, PATCH.
     * Centralizes error handling for all HTTP methods.
     *
     * @param string $url Request URL.
     * @param array $args wp_remote_request arguments including method.
     * @param string $context Context for logging/error messages.
     * @return array|WP_Error Parsed response data or WP_Error on failure.
     */
    private function make_request($url, $args, $context) {
        $method = $args['method'] ?? 'REQUEST';
        
        do_action('dm_log', 'debug', "Handler HTTP: Making {$method} request to {$context}.", [
            'url' => $url,
            'method' => $method
        ]);

        // Make the request
        $response = wp_remote_request($url, $args);

        // Handle WordPress HTTP errors
        if (is_wp_error($response)) {
            $error_message = sprintf(
                /* translators: %1$s: HTTP method, %2$s: context/service name, %3$s: error message */
                __('Failed to %1$s to %2$s: %3$s', 'data-machine'),
                $method,
                $context,
                $response->get_error_message()
            );
            
            do_action('dm_log', 'error', "Handler HTTP: {$method} connection failed.", [
                'context' => $context,
                'url' => $url,
                'method' => $method,
                'error' => $response->get_error_message(),
                'error_code' => $response->get_error_code()
            ]);
            
            return new WP_Error('http_request_failed', $error_message, [
                'url' => $url,
                'context' => $context,
                'method' => $method
            ]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Different methods have different success codes
        $success_codes = $this->get_success_codes_for_method($method);
        if (!in_array($status_code, $success_codes)) {
            $error_message = sprintf(
                /* translators: %1$s: context/service name, %2$s: HTTP method, %3$d: HTTP status code */
                __('%1$s %2$s returned HTTP %3$d', 'data-machine'),
                $context,
                $method,
                $status_code
            );

            $error_details = $this->extract_error_details($body, $context);
            if ($error_details) {
                $error_message .= ': ' . $error_details;
            }

            do_action('dm_log', 'error', "Handler HTTP: {$method} error response.", [
                'context' => $context,
                'url' => $url,
                'method' => $method,
                'status_code' => $status_code,
                'body_preview' => substr($body, 0, 200)
            ]);

            return new WP_Error('http_request_error', $error_message, [
                'url' => $url,
                'context' => $context,
                'method' => $method,
                'status_code' => $status_code,
                'body' => $body
            ]);
        }

        do_action('dm_log', 'debug', "Handler HTTP: Successful {$method} to {$context}.", [
            'url' => $url,
            'method' => $method,
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
     * Get expected success status codes for different HTTP methods.
     *
     * @param string $method HTTP method.
     * @return array Array of success status codes.
     */
    private function get_success_codes_for_method($method) {
        switch (strtoupper($method)) {
            case 'GET':
                return [200];
            case 'POST':
                return [200, 201, 202];
            case 'PUT':
                return [200, 201, 204];
            case 'PATCH':
                return [200, 204];
            case 'DELETE':
                return [200, 202, 204];
            default:
                return [200, 201, 202, 204];
        }
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