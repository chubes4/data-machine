<?php
/**
 * AI HTTP Client - Anthropic Provider
 * 
 * Single Responsibility: Pure Anthropic API communication only
 * No normalization logic - just sends/receives raw data
 * This is a "dumb" API client that the unified normalizers use
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Self-register Anthropic provider with complete configuration
 * Self-contained provider architecture - no external normalizers needed
 */
add_filter('ai_providers', function($providers) {
    $providers['anthropic'] = [
        'class' => 'AI_HTTP_Anthropic_Provider', 
        'type' => 'llm',
        'name' => 'Anthropic'
    ];
    return $providers;
});

class AI_HTTP_Anthropic_Provider {

    private $api_key;
    private $base_url;

    /**
     * Constructor
     *
     * @param array $config Provider configuration
     */
    public function __construct($config = []) {
        $this->api_key = $config['api_key'] ?? '';
        
        if (isset($config['base_url']) && !empty($config['base_url'])) {
            $this->base_url = rtrim($config['base_url'], '/');
        } else {
            $this->base_url = 'https://api.anthropic.com/v1';
        }
    }

    /**
     * Check if provider is configured
     *
     * @return bool True if configured
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Get authentication headers for Anthropic API
     *
     * @return array Headers array
     */
    private function get_auth_headers() {
        return array(
            'x-api-key' => $this->api_key,
            'anthropic-version' => '2023-06-01'
        );
    }

    /**
     * Send request to Anthropic API
     * Handles all format conversion internally - receives and returns standard format
     *
     * @param array $standard_request Standard request format
     * @return array Standard response format
     * @throws Exception If request fails
     */
    public function request($standard_request) {
        if (!$this->is_configured()) {
            throw new Exception('Anthropic provider not configured - missing API key');
        }

        // Convert standard format to Anthropic format internally
        $provider_request = $this->format_request($standard_request);
        
        $url = $this->base_url . '/messages';
        
        // Use centralized ai_http filter
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($provider_request)
        ], 'Anthropic');
        
        if (!$result['success']) {
            throw new Exception('Anthropic API request failed: ' . $result['error']);
        }
        
        $raw_response = json_decode($result['data'], true);
        
        // Convert Anthropic format to standard format
        return $this->format_response($raw_response);
    }

    /**
     * Send streaming request to Anthropic API
     * Handles all format conversion internally - receives and returns standard format
     *
     * @param array $standard_request Standard request format
     * @param callable $callback Optional callback for each chunk
     * @return array Standard response format
     * @throws Exception If request fails
     */
    public function streaming_request($standard_request, $callback = null) {
        if (!$this->is_configured()) {
            throw new Exception('Anthropic provider not configured - missing API key');
        }

        // Convert standard format to Anthropic format internally
        $provider_request = $this->format_request($standard_request);
        
        $url = $this->base_url . '/messages';
        
        // Use centralized ai_http filter with streaming=true
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($provider_request)
        ], 'Anthropic Streaming', true, $callback);
        
        if (!$result['success']) {
            throw new Exception('Anthropic streaming request failed: ' . $result['error']);
        }

        // Return standardized streaming response
        return [
            'success' => true,
            'data' => [
                'content' => '',
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'model' => $standard_request['model'] ?? '',
                'finish_reason' => 'stop',
                'tool_calls' => null
            ],
            'error' => null,
            'provider' => 'anthropic'
        ];
    }

    /**
     * Get available models from Anthropic API
     * Note: Anthropic doesn't have a models endpoint, so return empty array
     *
     * @return array Empty array (Anthropic doesn't have a models endpoint)
     */
    public function get_raw_models() {
        if (!$this->is_configured()) {
            return [];
        }

        // Anthropic doesn't have a models endpoint
        // Model names are hardcoded: claude-3-5-sonnet-20241022, claude-3-haiku-20240307, etc.
        return [];
    }

    /**
     * Upload file to Anthropic API
     * 
     * @param string $file_path Path to file to upload
     * @param string $purpose Purpose for upload (default: 'user_data')
     * @return string File ID from Anthropic
     * @throws Exception If upload fails
     */
    public function upload_file($file_path, $purpose = 'user_data') {
        if (!$this->is_configured()) {
            throw new Exception('Anthropic provider not configured');
        }

        if (!file_exists($file_path)) {
            throw new Exception("File not found: {$file_path}");
        }

        // Anthropic file upload endpoint
        $url = $this->base_url . '/files';
        
        // Prepare multipart form data
        $boundary = wp_generate_uuid4();
        $headers = array_merge($this->get_auth_headers(), [
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary
        ]);

        // Build multipart body
        $body = '';
        
        // Purpose field
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"purpose\"\r\n\r\n";
        $body .= $purpose . "\r\n";
        
        // File field
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file_path) . "\"\r\n";
        $body .= "Content-Type: " . mime_content_type($file_path) . "\r\n\r\n";
        $body .= file_get_contents($file_path) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        // Send request using centralized ai_http filter
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => $body
        ], 'Anthropic File Upload');

        if (!$result['success']) {
            throw new Exception('Anthropic file upload failed: ' . $result['error']);
        }

        $response_body = $result['data'];

        $data = json_decode($response_body, true);
        if (!isset($data['id'])) {
            throw new Exception('Anthropic file upload response missing file ID');
        }

        return $data['id'];
    }

    /**
     * Delete file from Anthropic API
     * 
     * @param string $file_id Anthropic file ID to delete
     * @return bool Success status
     * @throws Exception If delete fails
     */
    public function delete_file($file_id) {
        if (!$this->is_configured()) {
            throw new Exception('Anthropic provider not configured');
        }

        $url = $this->base_url . "/files/{$file_id}";
        
        // Send request using centralized ai_http filter
        $result = apply_filters('ai_http', [], 'DELETE', $url, [
            'headers' => $this->get_auth_headers()
        ], 'Anthropic File Delete');

        if (!$result['success']) {
            throw new Exception('Anthropic file delete failed: ' . $result['error']);
        }

        return $result['status_code'] === 200;
    }

    /**
     * Get normalized models for UI components
     * 
     * @return array Key-value array of model_id => display_name
     * @throws Exception If API call fails
     */
    public function get_normalized_models() {
        $raw_models = $this->get_raw_models();
        return $this->normalize_models_response($raw_models);
    }
    
    /**
     * Normalize Anthropic models API response
     * 
     * @param array $raw_models Raw API response
     * @return array Normalized models array
     */
    private function normalize_models_response($raw_models) {
        $models = [];
        
        // Anthropic returns: { "data": [{"id": "claude-3-5-sonnet-20241022", "display_name": "Claude 3.5 Sonnet", ...}, ...] }
        $data = $raw_models['data'] ?? $raw_models;
        if (is_array($data)) {
            foreach ($data as $model) {
                if (isset($model['id'])) {
                    $display_name = $model['display_name'] ?? $model['id'];
                    $models[$model['id']] = $display_name;
                }
            }
        }
        
        return $models;
    }
    
    /**
     * Format unified request to Anthropic API format
     *
     * @param array $unified_request Standard request format
     * @return array Anthropic-formatted request
     * @throws Exception If validation fails
     */
    private function format_request($unified_request) {
        $this->validate_unified_request($unified_request);
        
        $request = $this->sanitize_common_fields($unified_request);
        
        // Process optional parameters (only if explicitly provided)
        // Temperature parameter (OPTIONAL - only if explicitly provided)
        if (isset($request['temperature']) && !empty($request['temperature'])) {
            $request['temperature'] = max(0, min(1, floatval($request['temperature'])));
        }

        // Max tokens parameter (OPTIONAL - only if explicitly provided)
        if (isset($request['max_tokens']) && !empty($request['max_tokens'])) {
            $request['max_tokens'] = max(1, intval($request['max_tokens']));
        }

        // Handle tools (OPTIONAL - only if explicitly provided)
        if (isset($request['tools']) && is_array($request['tools'])) {
            $request['tools'] = $this->normalize_anthropic_tools($request['tools']);
        }

        // Handle tool_choice (OPTIONAL - only if explicitly provided)
        if (isset($request['tool_choice']) && !empty($request['tool_choice'])) {
            // Anthropic supports "auto", "any", or specific tool selection
            if ($request['tool_choice'] === 'required') {
                $request['tool_choice'] = 'any'; // Convert standard "required" to Anthropic "any"
            }
        }

        // Handle system message extraction for Anthropic
        if (isset($request['messages'])) {
            $request = $this->extract_anthropic_system_message($request);
        }

        return $request;
    }
    
    /**
     * Format Anthropic response to unified standard format
     *
     * @param array $anthropic_response Raw Anthropic response
     * @return array Standard response format
     */
    private function format_response($anthropic_response) {
        $content = '';
        $tool_calls = [];

        // Extract content
        if (isset($anthropic_response['content']) && is_array($anthropic_response['content'])) {
            foreach ($anthropic_response['content'] as $content_block) {
                if (isset($content_block['type'])) {
                    switch ($content_block['type']) {
                        case 'text':
                            $content .= $content_block['text'] ?? '';
                            break;
                        case 'tool_use':
                            // Convert Anthropic tool_use to standard format
                            $tool_calls[] = array(
                                'name' => $content_block['name'] ?? '',
                                'parameters' => $content_block['input'] ?? []
                            );
                            break;
                    }
                }
            }
        }

        // Extract usage
        $usage = array(
            'prompt_tokens' => $anthropic_response['usage']['input_tokens'] ?? 0,
            'completion_tokens' => $anthropic_response['usage']['output_tokens'] ?? 0,
            'total_tokens' => 0
        );
        $usage['total_tokens'] = $usage['prompt_tokens'] + $usage['completion_tokens'];

        return array(
            'success' => true,
            'data' => array(
                'content' => $content,
                'usage' => $usage,
                'model' => $anthropic_response['model'] ?? '',
                'finish_reason' => $anthropic_response['stop_reason'] ?? 'unknown',
                'tool_calls' => !empty($tool_calls) ? $tool_calls : null
            ),
            'error' => null,
            'provider' => 'anthropic',
            'raw_response' => $anthropic_response
        );
    }
    
    /**
     * Validate unified request format
     *
     * @param array $request Request to validate
     * @throws Exception If invalid
     */
    private function validate_unified_request($request) {
        if (!is_array($request)) {
            throw new Exception('Request must be an array');
        }

        if (!isset($request['messages']) || !is_array($request['messages'])) {
            throw new Exception('Request must include messages array');
        }

        if (empty($request['messages'])) {
            throw new Exception('Messages array cannot be empty');
        }
    }
    
    /**
     * Sanitize common fields
     *
     * @param array $request Request to sanitize
     * @return array Sanitized request
     */
    private function sanitize_common_fields($request) {
        // Sanitize messages
        if (isset($request['messages'])) {
            foreach ($request['messages'] as &$message) {
                if (isset($message['role'])) {
                    $message['role'] = sanitize_text_field($message['role']);
                }
                if (isset($message['content']) && is_string($message['content'])) {
                    $message['content'] = sanitize_textarea_field($message['content']);
                }
            }
        }

        // Sanitize other common fields
        if (isset($request['model'])) {
            $request['model'] = sanitize_text_field($request['model']);
        }

        return $request;
    }
    
    /**
     * Extract system message for Anthropic
     *
     * @param array $request Request with messages
     * @return array Request with system extracted
     */
    private function extract_anthropic_system_message($request) {
        $messages = $request['messages'];
        $system_content = '';
        $filtered_messages = array();

        foreach ($messages as $message) {
            if (isset($message['role']) && $message['role'] === 'system') {
                $system_content .= $message['content'] . "\n";
            } else {
                $filtered_messages[] = $message;
            }
        }

        $request['messages'] = $filtered_messages;
        
        if (!empty(trim($system_content))) {
            $request['system'] = trim($system_content);
        }

        return $request;
    }

    /**
     * Convert standard tools format to Anthropic tools format
     *
     * @param array $standard_tools Standard tools array
     * @return array Anthropic-formatted tools
     */
    private function normalize_anthropic_tools($standard_tools) {
        $anthropic_tools = array();
        
        foreach ($standard_tools as $tool) {
            if (isset($tool['name'], $tool['description'])) {
                $anthropic_tool = array(
                    'name' => $tool['name'],
                    'description' => $tool['description']
                );
                
                // Convert parameters to Anthropic input_schema format
                if (isset($tool['parameters']) && is_array($tool['parameters'])) {
                    $properties = array();
                    $required = array();
                    
                    foreach ($tool['parameters'] as $param_name => $param_config) {
                        $properties[$param_name] = array();
                        
                        if (isset($param_config['type'])) {
                            $properties[$param_name]['type'] = $param_config['type'];
                        }
                        if (isset($param_config['description'])) {
                            $properties[$param_name]['description'] = $param_config['description'];
                        }
                        if (isset($param_config['required']) && $param_config['required']) {
                            $required[] = $param_name;
                        }
                    }
                    
                    $anthropic_tool['input_schema'] = array(
                        'type' => 'object',
                        'properties' => $properties
                    );
                    
                    if (!empty($required)) {
                        $anthropic_tool['input_schema']['required'] = $required;
                    }
                }
                
                $anthropic_tools[] = $anthropic_tool;
            }
        }
        
        return $anthropic_tools;
    }

}