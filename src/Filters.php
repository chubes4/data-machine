<?php
/**
 * AI HTTP Client - Filter Registration
 * 
 * Registers all AI providers via WordPress filter system to enable
 * third-party provider registration and eliminate hardcoded switch statements.
 *
 * @package AIHttpClient
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Register AI types and providers via filter system
 * 
 * Enables filter-based discovery and third-party registration
 * 
 * @since 1.2.0
 */
function ai_http_client_register_provider_filters() {
    
    // Register supported AI types
    add_filter('ai_types', function($types) {
        $types['llm'] = [
            'name' => 'Large Language Models',
            'description' => 'Text generation and completion',
            'status' => 'active'
        ];
        
        $types['upscaling'] = [
            'name' => 'Image Upscaling',
            'description' => 'Image resolution enhancement', 
            'status' => 'planned'
        ];
        
        $types['generative'] = [
            'name' => 'Image Generation',
            'description' => 'Text-to-image generation',
            'status' => 'planned'
        ];
        
        return $types;
    });
    
    // Note: LLM providers now self-register in their individual files
    // This eliminates central coordination and enables true modular architecture
    
    // Centralized HTTP request handling for AI API calls
    // Usage: $result = apply_filters('ai_request', [], 'POST', $url, $args, 'Provider Context', false, $callback);
    // For streaming: $result = apply_filters('ai_request', [], 'POST', $url, $args, 'Provider Context', true, $callback);
    add_filter('ai_request', function($default, $method, $url, $args, $context, $streaming = false, $callback = null) {
        // Input validation
        $valid_methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $method = strtoupper($method);
        if (!in_array($method, $valid_methods)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("AI HTTP Client: Invalid HTTP method '{$method}' for {$context}");
            }
            return ['success' => false, 'error' => 'Invalid HTTP method'];
        }

        // Default args with AI HTTP Client user agent
        $args = wp_parse_args($args, [
            'user-agent' => sprintf('AI-HTTP-Client/%s (+WordPress)', 
                defined('AI_HTTP_CLIENT_VERSION') ? AI_HTTP_CLIENT_VERSION : '1.0')
        ]);

        // Set method for non-GET requests
        if ($method !== 'GET') {
            $args['method'] = $method;
        }

        // Debug logging - request initiation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $stream_mode = $streaming ? 'streaming' : 'standard';
            error_log("[AI HTTP Client] {$method} {$stream_mode} request to {$context}: {$url}");
            
            // Log headers (sanitize API keys)
            if (isset($args['headers'])) {
                $sanitized_headers = $args['headers'];
                foreach ($sanitized_headers as $key => $value) {
                    if (stripos($key, 'authorization') !== false || stripos($key, 'key') !== false) {
                        if (empty($value)) {
                            $sanitized_headers[$key] = '[EMPTY]';
                        } else {
                            $sanitized_headers[$key] = '[REDACTED_LENGTH_' . strlen($value) . ']';
                        }
                    }
                }
                error_log("[AI HTTP Client] Request headers: " . json_encode($sanitized_headers));
            }
        }

        // Handle streaming requests with cURL
        if ($streaming) {
            // Streaming requires cURL as WordPress wp_remote_* functions don't support it
            $headers = isset($args['headers']) ? $args['headers'] : [];
            $body = isset($args['body']) ? $args['body'] : '';
            
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

            // Debug logging for streaming
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[AI HTTP Client] {$context} streaming response status: {$http_code}");
                if (!empty($error)) {
                    error_log("[AI HTTP Client] {$context} cURL error: {$error}");
                }
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
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[AI HTTP Client] Connection failed to {$context}: " . $response->get_error_message());
            }
            
            return ['success' => false, 'error' => $error_message];
        }

        // Extract response details
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);

        // Debug logging - response details  
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[AI HTTP Client] {$context} response status: {$status_code}");
            // Don't log full response body as it may be large, just length
            error_log("[AI HTTP Client] {$context} response body length: " . strlen($body));
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
    
    // AI configuration filter with auto-discovery
    // Usage: $config = apply_filters('ai_config', $step_id); // Step-specific config
    // Usage: $config = apply_filters('ai_config', null); // Global config
    add_filter('ai_config', function($step_id = null) {
        // Auto-detect plugin context
        $plugin_context = ai_http_detect_plugin_context();
        if (empty($plugin_context)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AI HTTP Client] ai_config filter: Could not auto-detect plugin context');
            }
            return [];
        }
        
        // Default to LLM type
        $ai_type = 'llm';
        
        try {
            $options_manager = new AI_HTTP_Options_Manager($plugin_context, $ai_type);
            
            if ($step_id) {
                // Step-specific configuration
                return $options_manager->get_step_configuration($step_id);
            } else {
                // Global configuration  
                return $options_manager->get_all_providers();
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[AI HTTP Client] ai_config filter: Exception - " . $e->getMessage());
            }
            return [];
        }
    }, 10, 1);
    
    // AI Client factory filter with auto-discovery
    // Usage: $client = apply_filters('ai_client', null);
    add_filter('ai_client', function($default = null) {
        // Auto-detect plugin context
        $plugin_context = ai_http_detect_plugin_context();
        if (empty($plugin_context)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AI HTTP Client] ai_client filter: Could not auto-detect plugin context');
            }
            return null;
        }
        
        // Default to LLM type
        $ai_type = 'llm';
        
        try {
            // Create and return configured AI HTTP Client
            return new AI_HTTP_Client([
                'plugin_context' => $plugin_context,
                'ai_type' => $ai_type
            ]);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[AI HTTP Client] ai_client filter: Failed to create client - " . $e->getMessage());
            }
            return null;
        }
    }, 10, 1);
    
    // TODO: Future provider types (upscaling, generative) can be added here
    // when their provider classes are implemented
}

/**
 * Auto-detect plugin context from call stack
 * 
 * Analyzes debug backtrace to determine which plugin is calling the AI HTTP Client
 * 
 * @return string|null Plugin context (directory name) or null if not detectable
 * @since 1.2.0
 */
function ai_http_detect_plugin_context() {
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    
    foreach ($backtrace as $frame) {
        if (!isset($frame['file'])) {
            continue;
        }
        
        $file_path = $frame['file'];
        
        // Check if file is in a plugin directory
        if (strpos($file_path, '/wp-content/plugins/') !== false) {
            // Extract plugin directory name
            $plugin_path = substr($file_path, strpos($file_path, '/wp-content/plugins/') + 20);
            $plugin_parts = explode('/', $plugin_path);
            
            if (!empty($plugin_parts[0])) {
                // Return the plugin directory name as context
                return $plugin_parts[0];
            }
        }
        
        // Check if file is in a theme directory (fallback)
        if (strpos($file_path, '/wp-content/themes/') !== false) {
            $theme_path = substr($file_path, strpos($file_path, '/wp-content/themes/') + 19);
            $theme_parts = explode('/', $theme_path);
            
            if (!empty($theme_parts[0])) {
                // Return theme directory name as context
                return 'theme-' . $theme_parts[0];
            }
        }
    }
    
    // Fallback: return null if context cannot be determined
    return null;
}

// Initialize provider filters on WordPress init
add_action('init', 'ai_http_client_register_provider_filters');