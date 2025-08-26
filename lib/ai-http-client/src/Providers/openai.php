<?php
/**
 * AI HTTP Client - OpenAI Provider
 * 
 * Single Responsibility: Pure OpenAI API communication only
 * No normalization logic - just sends/receives raw data
 * This is a "dumb" API client that the unified normalizers use
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Self-register OpenAI provider with complete configuration
 * Self-contained provider architecture - no external normalizers needed
 */
add_filter('ai_providers', function($providers) {
    $providers['openai'] = [
        'class' => 'AI_HTTP_OpenAI_Provider',
        'type' => 'llm',
        'name' => 'OpenAI'
    ];
    return $providers;
});

class AI_HTTP_OpenAI_Provider {

    private $api_key;
    private $base_url;
    private $organization;
    private $files_api_callback = null;

    /**
     * Constructor
     *
     * @param array $config Provider configuration
     */
    public function __construct($config = []) {
        $this->api_key = isset($config['api_key']) ? $config['api_key'] : '';
        $this->organization = isset($config['organization']) ? $config['organization'] : '';
        
        if (isset($config['base_url']) && !empty($config['base_url'])) {
            $this->base_url = rtrim($config['base_url'], '/');
        } else {
            $this->base_url = 'https://api.openai.com/v1';
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
     * Get authentication headers for OpenAI API
     * Includes organization header if configured
     *
     * @return array Headers array
     */
    private function get_auth_headers() {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key
        );

        if (!empty($this->organization)) {
            $headers['OpenAI-Organization'] = $this->organization;
        }

        return $headers;
    }

    /**
     * Send request to OpenAI API
     * Handles all format conversion internally - receives and returns standard format
     *
     * @param array $standard_request Standard request format
     * @return array Standard response format
     * @throws Exception If request fails
     */
    public function request($standard_request) {
        if (!$this->is_configured()) {
            throw new Exception('OpenAI provider not configured - missing API key');
        }

        // Convert standard format to OpenAI format internally
        $provider_request = $this->format_request($standard_request);
        
        $url = $this->base_url . '/responses';
        
        
        // Use centralized ai_http filter
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($provider_request)
        ], 'OpenAI');
        
        if (!$result['success']) {
            throw new Exception('OpenAI API request failed: ' . $result['error']);
        }
        
        $raw_response = json_decode($result['data'], true);
        
        // Debug: Log raw OpenAI response to see what we actually get back
        if (defined('WP_DEBUG') && WP_DEBUG) {
            do_action('dm_log', 'debug', 'OpenAI Provider: Raw response from API', [
                'response_structure' => array_keys($raw_response ?? []),
                'has_output' => isset($raw_response['output']),
                'output_count' => isset($raw_response['output']) ? count($raw_response['output']) : 0,
                'output_items' => $raw_response['output'] ?? 'NOT_SET',
                'status' => $raw_response['status'] ?? 'NOT_SET',
                'error' => $raw_response['error'] ?? 'NOT_SET'
            ]);
        }
        
        // Convert OpenAI format to standard format
        return $this->format_response($raw_response);
    }

    /**
     * Send streaming request to OpenAI API
     * Handles all format conversion internally - receives and returns standard format
     *
     * @param array $standard_request Standard request format
     * @param callable $callback Optional callback for each chunk
     * @return array Standard response format
     * @throws Exception If request fails
     */
    public function streaming_request($standard_request, $callback = null) {
        if (!$this->is_configured()) {
            throw new Exception('OpenAI provider not configured - missing API key');
        }

        // Convert standard format to OpenAI format internally
        $provider_request = $this->format_request($standard_request);
        
        $url = $this->base_url . '/responses';
        
        // Debug logging in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }

        // Use centralized ai_http filter with streaming=true
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($provider_request)
        ], 'OpenAI Streaming', true, $callback);
        
        if (!$result['success']) {
            throw new Exception('OpenAI streaming request failed: ' . $result['error']);
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
            'provider' => 'openai'
        ];
    }

    /**
     * Get available models from OpenAI API
     *
     * @return array Raw models response
     * @throws Exception If request fails
     */
    public function get_raw_models() {
        if (!$this->is_configured()) {
            return array();
        }

        $url = $this->base_url . '/models';
        
        // Use centralized ai_http filter
        $result = apply_filters('ai_http', [], 'GET', $url, [
            'headers' => $this->get_auth_headers()
        ], 'OpenAI');

        if (!$result['success']) {
            throw new Exception('OpenAI API request failed: ' . $result['error']);
        }

        return json_decode($result['data'], true);
    }

    /**
     * Upload file to OpenAI Files API
     * 
     * @param string $file_path Path to file to upload
     * @param string $purpose Purpose for upload (default: 'user_data')
     * @return string File ID from OpenAI
     * @throws Exception If upload fails
     */
    public function upload_file($file_path, $purpose = 'user_data') {
        if (!$this->is_configured()) {
            throw new Exception('OpenAI provider not configured');
        }

        if (!file_exists($file_path)) {
            throw new Exception("File not found: {$file_path}");
        }

        // OpenAI file upload endpoint
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
        ], 'OpenAI File Upload');

        if (!$result['success']) {
            throw new Exception('OpenAI file upload failed: ' . $result['error']);
        }

        $response_body = $result['data'];

        $data = json_decode($response_body, true);
        if (!isset($data['id'])) {
            throw new Exception('OpenAI file upload response missing file ID');
        }

        return $data['id'];
    }

    /**
     * Delete file from OpenAI Files API
     * 
     * @param string $file_id OpenAI file ID to delete
     * @return bool Success status
     * @throws Exception If delete fails
     */
    public function delete_file($file_id) {
        if (!$this->is_configured()) {
            throw new Exception('OpenAI provider not configured');
        }

        $url = $this->base_url . "/files/{$file_id}";
        
        // Send request using centralized ai_http filter
        $result = apply_filters('ai_http', [], 'DELETE', $url, [
            'headers' => $this->get_auth_headers()
        ], 'OpenAI File Delete');

        if (!$result['success']) {
            throw new Exception('OpenAI file delete failed: ' . $result['error']);
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
     * Normalize OpenAI models API response
     * 
     * @param array $raw_models Raw API response
     * @return array Normalized models array
     */
    private function normalize_models_response($raw_models) {
        $models = [];
        
        // OpenAI returns: { "data": [{"id": "gpt-4", "object": "model", ...}, ...] }
        $data = isset($raw_models['data']) ? $raw_models['data'] : $raw_models;
        if (is_array($data)) {
            foreach ($data as $model) {
                if (isset($model['id'])) {
                    $models[$model['id']] = $model['id'];
                }
            }
        }
        
        return $models;
    }
    
    /**
     * Set Files API callback for file uploads
     *
     * @param callable $callback Function that takes (file_path, purpose, provider_name) and returns file_id
     */
    public function set_files_api_callback($callback) {
        $this->files_api_callback = $callback;
    }
    
    
    /**
     * Format unified request to OpenAI Responses API format
     *
     * @param array $unified_request Standard request format
     * @return array OpenAI-formatted request
     * @throws Exception If validation fails
     */
    private function format_request($unified_request) {
        $this->validate_unified_request($unified_request);
        
        $request = $this->sanitize_common_fields($unified_request);
        
        // Convert messages to input for Responses API
        if (isset($request['messages'])) {
            $request['input'] = $this->normalize_openai_messages($request['messages']);
            unset($request['messages']);
        }

        // Convert max_tokens to max_output_tokens for Responses API (OPTIONAL - only if explicitly provided)
        // Note: Not supported by reasoning models (o1*, o3*, o4*) - will cause API errors if sent
        if (isset($request['max_tokens']) && !empty($request['max_tokens'])) {
            $request['max_output_tokens'] = intval($request['max_tokens']);
            unset($request['max_tokens']);
        }

        // Handle tools (OPTIONAL - only if explicitly provided)
        if (isset($request['tools'])) {
            $request['tools'] = $this->normalize_openai_tools($request['tools']);
        }

        // Process temperature parameter (OPTIONAL - only if explicitly provided)
        // Note: Not supported by reasoning models (o1*, o3*, o4*) - will cause API errors if sent
        if (isset($request['temperature']) && !empty($request['temperature'])) {
            $request['temperature'] = max(0, min(1, floatval($request['temperature'])));
        }


        return $request;
    }
    
    /**
     * Format OpenAI response to unified standard format
     *
     * @param array $openai_response Raw OpenAI response
     * @return array Standard response format
     * @throws Exception If response format invalid
     */
    private function format_response($openai_response) {
        // Handle OpenAI Responses API format (primary)
        if (isset($openai_response['object']) && $openai_response['object'] === 'response') {
            return $this->normalize_openai_responses_api($openai_response);
        }
        
        // Handle streaming format
        if (isset($openai_response['content']) && !isset($openai_response['choices'])) {
            return $this->normalize_openai_streaming($openai_response);
        }
        
        throw new Exception('Invalid OpenAI response format');
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
     * Normalize OpenAI messages for Responses API format
     *
     * @param array $messages Array of messages
     * @return array OpenAI-formatted messages
     */
    private function normalize_openai_messages($messages) {
        $normalized = [];

        foreach ($messages as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                $normalized[] = $message;
                continue;
            }


            $normalized_message = array('role' => $message['role']);

            // Handle multi-modal content (images, files) or content arrays
            if (isset($message['images']) || isset($message['image_urls']) || isset($message['files']) || is_array($message['content'])) {
                $normalized_message['content'] = $this->build_openai_multimodal_content($message);
            } else {
                $normalized_message['content'] = $message['content'];
            }

            // Preserve other fields (excluding tool_calls for Responses API compatibility)
            foreach ($message as $key => $value) {
                if (!in_array($key, array('role', 'content', 'images', 'image_urls', 'files', 'tool_calls'))) {
                    $normalized_message[$key] = $value;
                }
            }

            $normalized[] = $normalized_message;
        }

        return $normalized;
    }
    
    /**
     * Build OpenAI multi-modal content with direct file upload
     *
     * @param array $message Message with multi-modal content
     * @return array OpenAI multi-modal content format
     */
    private function build_openai_multimodal_content($message) {
        $content = [];

        // Handle content array format (from AIStep)
        if (is_array($message['content'])) {
            foreach ($message['content'] as $content_item) {
                if (isset($content_item['type'])) {
                    switch ($content_item['type']) {
                        case 'text':
                            $content[] = array(
                                'type' => 'input_text',
                                'text' => $content_item['text']
                            );
                            break;
                        case 'file':
                            // FILES API INTEGRATION
                            try {
                                $file_path = $content_item['file_path'];
                                $file_id = $this->upload_file_via_files_api($file_path);
                                
                                $mime_type = $content_item['mime_type'] ?? mime_content_type($file_path);
                                
                                if (strpos($mime_type, 'image/') === 0) {
                                    $content[] = array(
                                        'type' => 'input_image',
                                        'file_id' => $file_id
                                    );
                                } else {
                                    $content[] = array(
                                        'type' => 'input_file',
                                        'file_id' => $file_id
                                    );
                                }
                            } catch (Exception $e) {
                                if (defined('WP_DEBUG') && WP_DEBUG) {
                                }
                            }
                            break;
                        default:
                            $content[] = $content_item;
                            break;
                    }
                }
            }
        } else {
            // Add text content for string format
            if (!empty($message['content'])) {
                $content[] = array(
                    'type' => 'input_text',
                    'text' => $message['content']
                );
            }
        }

        return $content;
    }
    
    /**
     * Upload file via Files API callback
     *
     * @param string $file_path Path to file to upload
     * @return string File ID from Files API
     * @throws Exception If upload fails
     */
    private function upload_file_via_files_api($file_path) {
        if (!$this->files_api_callback) {
            throw new Exception('Files API callback not set - cannot upload files');
        }

        if (!file_exists($file_path)) {
            throw new Exception("File not found: {$file_path}");
        }

        return call_user_func($this->files_api_callback, $file_path, 'user_data', 'openai');
    }
    
    /**
     * Normalize OpenAI tools
     *
     * @param array $tools Array of tools
     * @return array OpenAI-formatted tools
     */
    private function normalize_openai_tools($tools) {
        $normalized = [];

        foreach ($tools as $tool) {
            // Handle nested format (Chat Completions) - convert to flat format (Responses API)
            if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
                $normalized[] = array(
                    'name' => sanitize_text_field($tool['function']['name']),
                    'type' => 'function',
                    'description' => sanitize_textarea_field($tool['function']['description']),
                    'parameters' => $this->convert_to_openai_schema($tool['function']['parameters'] ?? array())
                );
            } 
            // Handle flat format - convert library standard to OpenAI JSON Schema
            elseif (isset($tool['name']) && isset($tool['description'])) {
                $normalized[] = array(
                    'name' => sanitize_text_field($tool['name']),
                    'type' => 'function',
                    'description' => sanitize_textarea_field($tool['description']),
                    'parameters' => $this->convert_to_openai_schema($tool['parameters'] ?? array())
                );
            }
        }

        return $normalized;
    }
    
    /**
     * Convert library standardized parameters to OpenAI JSON Schema format
     * 
     * Converts from library format:
     * ['param' => ['type' => 'string', 'required' => true, 'description' => 'desc']]
     * 
     * To OpenAI JSON Schema format:
     * ['type' => 'object', 'properties' => ['param' => ['type' => 'string', 'description' => 'desc']], 'required' => ['param']]
     *
     * @param array $library_parameters Library standardized parameters
     * @return array OpenAI JSON Schema formatted parameters
     */
    private function convert_to_openai_schema($library_parameters) {
        // Handle already-formatted JSON Schema (pass through)
        if (isset($library_parameters['type']) && $library_parameters['type'] === 'object') {
            return $library_parameters;
        }
        
        // Convert library standard format to OpenAI JSON Schema
        $properties = [];
        $required = [];
        
        foreach ($library_parameters as $param_name => $param_config) {
            if (!is_array($param_config)) {
                continue;
            }
            
            // Extract type and description
            $properties[$param_name] = [];
            if (isset($param_config['type'])) {
                $properties[$param_name]['type'] = $param_config['type'];
                
                // OpenAI requires 'items' property for array types
                if ($param_config['type'] === 'array' && !isset($param_config['items'])) {
                    $properties[$param_name]['items'] = array('type' => 'string');
                }
            }
            if (isset($param_config['description'])) {
                $properties[$param_name]['description'] = $param_config['description'];
            }
            if (isset($param_config['enum'])) {
                $properties[$param_name]['enum'] = $param_config['enum'];
            }
            if (isset($param_config['items'])) {
                $properties[$param_name]['items'] = $param_config['items'];
            }
            
            // Handle required flag
            if (isset($param_config['required']) && $param_config['required']) {
                $required[] = $param_name;
            }
        }
        
        // Return OpenAI JSON Schema format
        $schema = array(
            'type' => 'object',
            'properties' => $properties
        );
        
        // Only add required array if there are required parameters
        if (!empty($required)) {
            $schema['required'] = $required;
        }
        
        return $schema;
    }
    
    /**
     * Normalize OpenAI Responses API format
     *
     * @param array $response Raw Responses API response
     * @return array Standard format
     */
    private function normalize_openai_responses_api($response) {
        
        // Extract content and tool calls from output
        $content = '';
        $tool_calls = [];
        
        if (isset($response['output']) && is_array($response['output'])) {
            foreach ($response['output'] as $output_item) {
                // Handle message type output items
                if (isset($output_item['type']) && $output_item['type'] === 'message') {
                    if (isset($output_item['content']) && is_array($output_item['content'])) {
                        foreach ($output_item['content'] as $content_item) {
                            if (isset($content_item['type'])) {
                                switch ($content_item['type']) {
                                    case 'output_text':
                                        $content .= isset($content_item['text']) ? $content_item['text'] : '';
                                        break;
                                    case 'tool_call':
                                        // Convert OpenAI Responses API format to standard format
                                        $function_name = $content_item['name'] ?? '';
                                        $function_arguments = $content_item['arguments'] ?? array();
                                        
                                        if (!empty($function_name)) {
                                            $tool_calls[] = array(
                                                'name' => $function_name,
                                                'parameters' => $function_arguments
                                            );
                                        }
                                        break;
                                }
                            }
                        }
                    }
                }
                // Handle direct function_call type (actual OpenAI Responses API format)
                elseif (isset($output_item['type']) && $output_item['type'] === 'function_call') {
                    // Extract function name and arguments from direct function_call
                    $function_name = $output_item['name'] ?? '';
                    $function_arguments_json = $output_item['arguments'] ?? '{}';
                    
                    // Parse JSON arguments
                    $function_arguments = json_decode($function_arguments_json, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $function_arguments = [];
                    }
                    
                    if (!empty($function_name)) {
                        $tool_calls[] = array(
                            'name' => $function_name,
                            'parameters' => $function_arguments
                        );
                    }
                }
                // Handle direct content types (fallback)
                elseif (isset($output_item['type'])) {
                    switch ($output_item['type']) {
                        case 'content':
                        case 'output_text':
                            $content .= isset($output_item['text']) ? $output_item['text'] : '';
                            break;
                    }
                }
            }
        }

        // Extract usage
        $usage = array(
            'prompt_tokens' => isset($response['usage']['input_tokens']) ? $response['usage']['input_tokens'] : 0,
            'completion_tokens' => isset($response['usage']['output_tokens']) ? $response['usage']['output_tokens'] : 0,
            'total_tokens' => isset($response['usage']['total_tokens']) ? $response['usage']['total_tokens'] : 0
        );

        // Debug: Log final parsed response before returning to AIStep
        if (defined('WP_DEBUG') && WP_DEBUG) {
            do_action('dm_log', 'debug', 'OpenAI Provider: Final parsed response', [
                'content_length' => strlen($content),
                'content_preview' => substr($content, 0, 100) . '...',
                'tool_calls_count' => count($tool_calls),
                'tool_calls' => $tool_calls,
                'finish_reason' => isset($response['status']) ? $response['status'] : 'unknown'
            ]);
        }

        return array(
            'success' => true,
            'data' => array(
                'content' => $content,
                'usage' => $usage,
                'model' => isset($response['model']) ? $response['model'] : '',
                'finish_reason' => isset($response['status']) ? $response['status'] : 'unknown',
                'tool_calls' => !empty($tool_calls) ? $tool_calls : null
            ),
            'error' => null,
            'provider' => 'openai',
            'raw_response' => $response
        );
    }
    
    
    /**
     * Normalize OpenAI streaming response format
     *
     * @param array $response Streaming response
     * @return array Standard format
     */
    private function normalize_openai_streaming($response) {
        $content = isset($response['content']) ? $response['content'] : '';
        
        return array(
            'success' => true,
            'data' => array(
                'content' => $content,
                'usage' => array(
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0
                ),
                'model' => isset($response['model']) ? $response['model'] : '',
                'finish_reason' => 'stop',
                'tool_calls' => null
            ),
            'error' => null,
            'provider' => 'openai',
            'raw_response' => $response
        );
    }


}