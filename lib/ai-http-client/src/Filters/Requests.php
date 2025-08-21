<?php
/**
 * AI HTTP Client - Request Processing Filters
 * 
 * Complete AI request processing system via WordPress filter system.
 * Handles HTTP communication, AI request processing, provider management,
 * and response formatting in a unified pipeline.
 *
 * @package AIHttpClient\Filters
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Register AI request processing filters
 * 
 * Enables complete AI request pipeline from HTTP to provider response
 * 
 * @since 1.2.0
 */
function ai_http_client_register_provider_filters() {
    
    // Note: Providers now self-register in their individual files
    // This eliminates central coordination and enables true modular architecture
    
    
    // Universal file-to-base64 conversion filter for AI providers
    // Usage: $base64_data_url = apply_filters('ai_file_to_base64', '', $file_path, $options);
    // Returns: "data:image/jpeg;base64,/9j/4AAQ..." format or empty string on failure
    add_filter('ai_file_to_base64', function($default, $file_path, $options = []) {
        // Validate file path
        if (empty($file_path) || !is_string($file_path)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
            return '';
        }
        
        // Check if file exists and is readable
        if (!file_exists($file_path) || !is_readable($file_path)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
            return '';
        }
        
        // Get file size and check limits (default 10MB max)
        $max_size = $options['max_size'] ?? (10 * 1024 * 1024);
        $file_size = filesize($file_path);
        if ($file_size > $max_size) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
            return '';
        }
        
        // Get MIME type
        $mime_type = mime_content_type($file_path);
        if (!$mime_type) {
            // Fallback to WordPress MIME type detection
            $file_info = wp_check_filetype($file_path);
            $mime_type = $file_info['type'] ?? 'application/octet-stream';
        }
        
        // Validate supported MIME types (images by default)
        $supported_types = $options['supported_types'] ?? [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'
        ];
        
        if (!in_array($mime_type, $supported_types)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
            return '';
        }
        
        // Read and encode file content
        $file_content = file_get_contents($file_path);
        if ($file_content === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
            return '';
        }
        
        // Create base64 data URL
        $base64_content = base64_encode($file_content);
        $data_url = "data:{$mime_type};base64,{$base64_content}";
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }
        
        return $data_url;
    }, 10, 3);
    
    // Internal HTTP request handling for AI API calls
    // Usage: $result = apply_filters('ai_http', [], 'POST', $url, $args, 'Provider Context', false, $callback);
    // For streaming: $result = apply_filters('ai_http', [], 'POST', $url, $args, 'Provider Context', true, $callback);
    add_filter('ai_http', function($default, $method, $url, $args, $context, $streaming = false, $callback = null) {
        // Input validation
        $valid_methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $method = strtoupper($method);
        if (!in_array($method, $valid_methods)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
            }
            return ['success' => false, 'error' => 'Invalid HTTP method'];
        }

        // Default args with AI HTTP Client user agent and timeout
        $args = wp_parse_args($args, [
            'user-agent' => sprintf('AI-HTTP-Client/%s (+WordPress)', 
                defined('AI_HTTP_CLIENT_VERSION') ? AI_HTTP_CLIENT_VERSION : '1.0'),
            'timeout' => 60  // 60-second timeout for AI operations
        ]);

        // Set method for non-GET requests
        if ($method !== 'GET') {
            $args['method'] = $method;
        }


        // Handle streaming requests with cURL
        if ($streaming) {
            // Streaming requires cURL as WordPress wp_remote_* functions don't support it
            $headers = $args['headers'] ?? [];
            $body = $args['body'] ?? '';
            
            // Format headers for cURL
            $formatted_headers = [];
            foreach ($headers as $key => $value) {
                $formatted_headers[] = $key . ': ' . $value;
            }
            
            // Add stream=true to the request if it's JSON
            if (isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/json' && !empty($body)) {
                $decoded_body = json_decode($body, true);
                if (is_array($decoded_body)) {
                    $decoded_body['stream'] = true;
                    $body = json_encode($decoded_body);
                }
            }
            
            $response_body = '';
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => ($method !== 'GET'),
                CURLOPT_POSTFIELDS => ($method !== 'GET') ? $body : null,
                CURLOPT_HTTPHEADER => $formatted_headers,
                CURLOPT_TIMEOUT => 60,  // 60-second timeout for streaming
                CURLOPT_WRITEFUNCTION => function($ch, $data) use ($callback, &$response_body) {
                    $response_body .= $data; // Capture response for error logging
                    if ($callback && is_callable($callback)) {
                        call_user_func($callback, $data);
                    } else {
                        echo esc_html($data);
                        flush();
                    }
                    return strlen($data);
                },
                CURLOPT_RETURNTRANSFER => false
            ]);

            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // Handle streaming errors
            if (!empty($error)) {
                // Fire hook for cURL errors
                do_action('ai_api_error', [
                    'message' => "Connection error to {$context}: {$error}",
                    'provider' => $context,
                    'error_type' => 'curl_error',
                    'details' => $error
                ]);
            } elseif ($http_code >= 400) {
                // Fire hook for HTTP error responses
                do_action('ai_api_error', [
                    'message' => "HTTP {$http_code} error from {$context}",
                    'provider' => $context,
                    'http_code' => $http_code,
                    'error_type' => 'http_error'
                ]);
            }

            if ($result === false || !empty($error)) {
                return ['success' => false, 'error' => "Streaming request failed: {$error}"];
            }

            if ($http_code < 200 || $http_code >= 300) {
                return ['success' => false, 'error' => "HTTP {$http_code} response from {$context}"];
            }

            return [
                'success' => true,
                'data' => '', // Streaming outputs directly, no data returned
                'status_code' => $http_code,
                'headers' => [],
                'error' => ''
            ];
        }

        // Make the request using appropriate WordPress function
        $response = ($method === 'GET') ? wp_remote_get($url, $args) : wp_remote_request($url, $args);

        // Handle WordPress HTTP errors (network issues, timeouts, etc.)
        if (is_wp_error($response)) {
            $error_message = "Failed to connect to {$context}: " . $response->get_error_message();
            
            // Fire hook for connection errors
            do_action('ai_api_error', [
                'message' => $error_message,
                'provider' => $context,
                'error_type' => 'connection_error',
                'wp_error' => $response->get_error_message()
            ]);
            
            return ['success' => false, 'error' => $error_message];
        }

        // Extract response details
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);

        // Fire hook for non-200 responses with raw provider response
        if ($status_code >= 400) {
            do_action('ai_api_error', [
                'message' => "HTTP {$status_code} error from {$context}",
                'provider' => $context,
                'http_code' => $status_code,
                'response_body' => $body,
                'error_type' => 'api_error'
            ]);
        }

        // For AI APIs, most operations expect 200, but some may expect 201, 202, etc.
        // Let the calling code determine if the status is acceptable
        $success = ($status_code >= 200 && $status_code < 300);
        
        return [
            'success' => $success,
            'data' => $body,
            'status_code' => $status_code,
            'headers' => $headers,
            'error' => $success ? '' : "HTTP {$status_code} response from {$context}"
        ];
    }, 10, 7);
    
    
    
    
    // Public AI Request filter - high-level plugin interface
    // Usage: $response = apply_filters('ai_request', $request);
    // Usage: $response = apply_filters('ai_request', $request, $provider_name);  
    // Usage: $response = apply_filters('ai_request', $request, null, $streaming_callback);
    // Usage: $response = apply_filters('ai_request', $request, $provider_name, $streaming_callback, $tools);
    add_filter('ai_request', function($request, $provider_name = null, $streaming_callback = null, $tools = null) {
        
        
        // Validate request format
        if (!is_array($request)) {
            return ai_http_create_error_response('Request must be an array');
        }
        
        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return ai_http_create_error_response('Request must include messages array');
        }
        
        if (empty($request['messages'])) {
            return ai_http_create_error_response('Messages array cannot be empty');
        }
        
        // Handle tools parameter - merge with request tools
        if ($tools && is_array($tools)) {
            if (!isset($request['tools'])) {
                $request['tools'] = [];
            }
            // Merge tools (parameter tools take precedence)
            $request['tools'] = array_merge($request['tools'], $tools);
        }
        
        try {
            // Provider name is now required - library no longer auto-discovers providers
            if (!$provider_name) {
                return ai_http_create_error_response('Provider name must be specified for AI requests');
            }
            
            // Build provider config from shared API keys
            $shared_api_keys = apply_filters('ai_provider_api_keys', null);
            $api_key = $shared_api_keys[$provider_name] ?? '';
            
            if (empty($api_key)) {
                return ai_http_create_error_response("No API key configured for provider '{$provider_name}'");
            }
            
            $provider_config = ['api_key' => $api_key];
            
            // Get provider instance
            $provider = ai_http_create_provider($provider_name, $provider_config);
            if (!$provider) {
                return ai_http_create_error_response("Failed to create provider instance for '{$provider_name}'");
            }
            
            // Handle streaming vs standard requests - clean interface
            if ($streaming_callback) {
                // Streaming request - provider handles all format conversion internally
                $standard_response = $provider->streaming_request($request, $streaming_callback);
            } else {
                // Standard request - provider handles all format conversion internally
                $standard_response = $provider->request($request);
            }
            
            return $standard_response;
            
        } catch (Exception $e) {
            return ai_http_create_error_response($e->getMessage(), $provider_name);
        }
    }, 10, 5);
}

/**
 * Create standardized error response
 *
 * @param string $error_message Error message
 * @param string $provider_name Provider name
 * @return array Standardized error response
 */
function ai_http_create_error_response($error_message, $provider_name = 'unknown') {
    return array(
        'success' => false,
        'data' => null,
        'error' => $error_message,
        'provider' => $provider_name,
        'raw_response' => null
    );
}

// Note: Normalizer initialization removed - providers now self-contained

/**
 * Create provider instance
 *
 * @param string $provider_name Provider name
 * @param array|null $provider_config Optional provider configuration override
 * @return object|false Provider instance or false on failure
 */
function ai_http_create_provider($provider_name, $provider_config = null) {
    // Use filter-based provider discovery
    $all_providers = apply_filters('ai_providers', []);
    $provider_info = $all_providers[strtolower($provider_name)] ?? null;
    if (!$provider_info) {
        return false;
    }
    // Get provider configuration if not provided
    if ($provider_config === null) {
        // Build minimal config from shared API keys
        $shared_api_keys = apply_filters('ai_provider_api_keys', null);
        $api_key = $shared_api_keys[$provider_name] ?? '';
        $provider_config = $api_key ? ['api_key' => $api_key] : [];
    }
    
    // Get provider class and create instance
    $provider_class = $provider_info['class'];
    $provider = new $provider_class($provider_config);

    // Set up Files API callback for file uploads in self-contained providers
    if (method_exists($provider, 'set_files_api_callback')) {
        $provider->set_files_api_callback(function($file_path, $purpose = 'user_data', $provider_name = 'openai') {
            return ai_http_upload_file_to_provider($file_path, $purpose, $provider_name);
        });
    }

    return $provider;
}

/**
 * Upload file to provider's Files API
 *
 * @param string $file_path Path to file to upload
 * @param string $purpose Purpose for upload
 * @param string $provider_name Provider to upload to
 * @return string File ID from provider's Files API
 * @throws Exception If upload fails
 */
function ai_http_upload_file_to_provider($file_path, $purpose = 'user_data', $provider_name = 'openai') {
    $provider = ai_http_create_provider($provider_name);
    
    if (!$provider) {
        throw new Exception("{$provider_name} provider not available for Files API upload");
    }
    
    return $provider->upload_file($file_path, $purpose);
}

// Initialize provider filters and AJAX actions on WordPress init
add_action('init', 'ai_http_client_register_provider_filters');