<?php
/**
 * Google Custom Search API tool for web search and fact-checking
 */

namespace DataMachine\Core\Steps\AI\Tools;

defined('ABSPATH') || exit;

/**
 * Google Custom Search API integration for web search results
 */
class GoogleSearch {

    public function __construct() {
        add_filter('dm_tool_success_message', [$this, 'format_success_message'], 10, 4);
    }

    /**
     * Execute Google search with site restrictions and result limiting
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        
        if (empty($parameters['query'])) {
            return [
                'success' => false,
                'error' => 'Google Search tool call missing required query parameter',
                'tool_name' => 'google_search'
            ];
        }

        $config = get_option('dm_search_config', []);
        $google_config = $config['google_search'] ?? [];
        
        if (empty($google_config['api_key']) || empty($google_config['search_engine_id'])) {
            return [
                'success' => false,
                'error' => 'Google Search tool not configured. Please configure API key and Search Engine ID.',
                'tool_name' => 'google_search'
            ];
        }

        // Extract parameters with defaults
        $query = sanitize_text_field($parameters['query']);
        $max_results = min(max(intval($parameters['max_results'] ?? 5), 1), 10); // Limit 1-10 results
        $site_restrict = !empty($parameters['site_restrict']) ? sanitize_text_field($parameters['site_restrict']) : '';
        
        // Build search request
        $search_url = 'https://www.googleapis.com/customsearch/v1';
        $search_params = [
            'key' => $google_config['api_key'],
            'cx' => $google_config['search_engine_id'],
            'q' => $query,
            'num' => $max_results,
            'safe' => 'active' // Enable safe search
        ];
        
        // Add site restriction if specified
        if ($site_restrict) {
            $search_params['siteSearch'] = $site_restrict;
        }
        
        $request_url = add_query_arg($search_params, $search_url);
        
        // Execute search request
        $response = wp_remote_get($request_url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to connect to Google Search API: ' . $response->get_error_message(),
                'tool_name' => 'google_search'
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return [
                'success' => false,
                'error' => 'Google Search API error (HTTP ' . $response_code . '): ' . $response_body,
                'tool_name' => 'google_search'
            ];
        }
        
        $search_data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Failed to parse Google Search API response',
                'tool_name' => 'google_search'
            ];
        }
        
        // Process search results
        $results = [];
        if (!empty($search_data['items'])) {
            foreach ($search_data['items'] as $item) {
                $results[] = [
                    'title' => $item['title'] ?? '',
                    'link' => $item['link'] ?? '',
                    'snippet' => $item['snippet'] ?? '',
                    'displayLink' => $item['displayLink'] ?? ''
                ];
            }
        }
        
        // Get search information
        $search_info = $search_data['searchInformation'] ?? [];
        $total_results = $search_info['totalResults'] ?? '0';
        $search_time = $search_info['searchTime'] ?? 0;
        
        return [
            'success' => true,
            'data' => [
                'query' => $query,
                'results_count' => count($results),
                'total_available' => $total_results,
                'search_time' => $search_time,
                'results' => $results
            ],
            'tool_name' => 'google_search'
        ];
    }
    
    /**
     * Check if Google Search tool is properly configured
     * 
     * @return bool True if configured, false otherwise
     */
    public static function is_configured(): bool {
        $config = get_option('dm_search_config', []);
        $google_config = $config['google_search'] ?? [];
        
        return !empty($google_config['api_key']) && !empty($google_config['search_engine_id']);
    }
    
    /**
     * Get current configuration
     * 
     * @return array Configuration array
     */
    public static function get_config(): array {
        $config = get_option('dm_search_config', []);
        return $config['google_search'] ?? [];
    }
    
    /**
     * Format success message for Google search results
     * 
     * @param string $message Default message
     * @param string $tool_name Tool name
     * @param array $tool_result Tool execution result
     * @param array $tool_parameters Tool parameters
     * @return string Formatted success message
     */
    public function format_success_message($message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name !== 'google_search') {
            return $message;
        }
        
        $data = $tool_result['data'] ?? [];
        $results = $data['results'] ?? $data ?? [];
        $query = $tool_parameters['query'] ?? 'your query';
        
        if (empty($results)) {
            return "SEARCH COMPLETE: No results found for \"{$query}\". Search task finished.";
        }
        
        $result_count = count($results);
        return "SEARCH COMPLETE: Found {$result_count} results for \"{$query}\".\nSearch Results:";
    }
}