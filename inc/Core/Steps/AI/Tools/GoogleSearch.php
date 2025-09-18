<?php
/**
 * Google Custom Search API tool for web search and fact-checking.
 *
 * Provides real-time web search capabilities for AI agents with configurable
 * result limits, site restrictions, and comprehensive error handling.
 *
 * @package DataMachine\Core\Steps\AI\Tools
 */

namespace DataMachine\Core\Steps\AI\Tools;

defined('ABSPATH') || exit;

/**
 * Google Custom Search API integration for web search results.
 */
class GoogleSearch {

    public function __construct() {
        add_filter('dm_tool_success_message', [$this, 'format_success_message'], 10, 4);
        $this->register_configuration();
    }

    /**
     * Register configuration filters for self-registration
     */
    private function register_configuration() {
        add_filter('ai_tools', [$this, 'register_tool'], 10, 1);
        add_filter('dm_tool_configured', [$this, 'check_configuration'], 10, 2);
        add_filter('dm_get_tool_config', [$this, 'get_configuration'], 10, 2);
        add_action('dm_save_tool_config', [$this, 'save_configuration'], 10, 2);
    }

    /**
     * Execute Google search with site restrictions and result limiting.
     * Requires API key and Custom Search Engine ID configuration.
     *
     * @param array $parameters Flat parameter structure containing 'query'
     * @param array $tool_def Tool definition (unused for general tools)
     * @return array Search results with success status and data array
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

        $query = sanitize_text_field($parameters['query']);
        $max_results = min(max(intval($parameters['max_results'] ?? 5), 1), 10); // Limit 1-10 results
        $site_restrict = !empty($parameters['site_restrict']) ? sanitize_text_field($parameters['site_restrict']) : '';
        
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
     * Register tool in ai_tools filter
     */
    public function register_tool($tools) {
        $tools['google_search'] = [
            'class' => __CLASS__,
            'method' => 'handle_tool_call',
            'description' => 'Search Google and return structured JSON results with titles, links, and snippets from external websites. Use for external information, current events, and fact-checking. Returns complete web search data in JSON format with title, link, snippet for each result.',
            'requires_config' => true,
            'parameters' => [
                'query' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Search query for external web information. Returns JSON with "results" array containing web search results.'
                ],
                'max_results' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Maximum number of results to return (1-10, default: 5). Limit to reduce response size.'
                ],
                'site_restrict' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Restrict search to specific domain (e.g., "wikipedia.org" for Wikipedia only)'
                ]
            ]
        ];

        return $tools;
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
     * Filter handler for dm_tool_configured
     */
    public function check_configuration($configured, $tool_id) {
        if ($tool_id !== 'google_search') {
            return $configured;
        }

        return self::is_configured();
    }

    /**
     * Filter handler for dm_get_tool_config
     */
    public function get_configuration($config, $tool_id) {
        if ($tool_id !== 'google_search') {
            return $config;
        }

        return self::get_config();
    }

    /**
     * Action handler for dm_save_tool_config
     */
    public function save_configuration($tool_id, $config_data) {
        if ($tool_id !== 'google_search') {
            return;
        }

        $api_key = sanitize_text_field($config_data['api_key'] ?? '');
        $search_engine_id = sanitize_text_field($config_data['search_engine_id'] ?? '');

        if (empty($api_key) || empty($search_engine_id)) {
            wp_send_json_error(['message' => __('API Key and Search Engine ID are required', 'data-machine')]);
            return;
        }

        $stored_config = get_option('dm_search_config', []);
        $stored_config['google_search'] = [
            'api_key' => $api_key,
            'search_engine_id' => $search_engine_id
        ];

        if (update_option('dm_search_config', $stored_config)) {
            wp_send_json_success([
                'message' => __('Google Search configuration saved successfully', 'data-machine'),
                'configured' => true
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save configuration', 'data-machine')]);
        }
    }

    /**
     * Get configuration fields for UI generation
     */
    public function get_config_fields(): array {
        return [
            'api_key' => [
                'type' => 'text',
                'label' => __('Google Search API Key', 'data-machine'),
                'placeholder' => __('Enter your Google Search API key', 'data-machine'),
                'required' => true,
                'description' => __('Get your API key from Google Cloud Console → APIs & Services → Credentials', 'data-machine')
            ],
            'search_engine_id' => [
                'type' => 'text',
                'label' => __('Custom Search Engine ID', 'data-machine'),
                'placeholder' => __('Enter your Search Engine ID', 'data-machine'),
                'required' => true,
                'description' => __('Create a Custom Search Engine and copy the Search Engine ID (cx parameter)', 'data-machine')
            ]
        ];
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

// Self-register the tool
new GoogleSearch();