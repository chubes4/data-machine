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
    
    // Register AI tools filter for plugin-scoped tool registration
    // Usage: $all_tools = apply_filters('ai_tools', []);
    add_filter('ai_tools', function($tools) {
        // Tools self-register in their own files following the same pattern as providers
        // This enables any plugin to register tools that other plugins can discover and use
        return $tools;
    });
    
    // Internal HTTP request handling for AI API calls
    // Usage: $result = apply_filters('ai_http', [], 'POST', $url, $args, 'Provider Context', false, $callback);
    // For streaming: $result = apply_filters('ai_http', [], 'POST', $url, $args, 'Provider Context', true, $callback);
    add_filter('ai_http', function($default, $method, $url, $args, $context, $streaming = false, $callback = null) {
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
                $step_config = $options_manager->get_step_configuration($step_id);
                
                if (!empty($step_config)) {
                    // Transform flat step config to provider-keyed structure that templates expect
                    $provider = $step_config['provider'] ?? 'openai';
                    
                    // Get API key from shared storage (step config doesn't store API keys)
                    $shared_api_keys = get_option(AI_HTTP_Options_Manager::SHARED_API_KEYS_OPTION, array());
                    
                    // Build expected structure: provider-keyed config with merged API key
                    $transformed_config = array(
                        'selected_provider' => $provider,
                        $provider => array(
                            'api_key' => isset($shared_api_keys[$provider]) ? $shared_api_keys[$provider] : '',
                            'model' => isset($step_config['model']) ? $step_config['model'] : '',
                            'temperature' => isset($step_config['temperature']) ? $step_config['temperature'] : 0.7,
                            'system_prompt' => isset($step_config['system_prompt']) ? $step_config['system_prompt'] : '',
                            'max_tokens' => isset($step_config['max_tokens']) ? $step_config['max_tokens'] : null
                        )
                    );
                    
                    return $transformed_config;
                }
                
                // Fallback to global config if no step config exists
                return $options_manager->get_all_providers();
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
    
    
    // AI Models filter with auto-discovery
    // Usage: $models = apply_filters('ai_models', $provider_name);
    add_filter('ai_models', function($provider_name = null) {
        // Auto-detect plugin context
        $plugin_context = ai_http_detect_plugin_context();
        if (empty($plugin_context)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AI HTTP Client] ai_models filter: Could not auto-detect plugin context');
            }
            return [];
        }
        
        // Default to LLM type
        $ai_type = 'llm';
        
        try {
            // Get provider name if not specified
            if (!$provider_name) {
                $options_manager = new AI_HTTP_Options_Manager($plugin_context, $ai_type);
                $provider_name = $options_manager->get_selected_provider();
                
                if (empty($provider_name)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[AI HTTP Client] ai_models filter: No provider configured');
                    }
                    return [];
                }
            }
            
            // Create provider instance directly
            $provider = ai_http_create_provider($provider_name, $plugin_context, $ai_type);
            if (!$provider) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("[AI HTTP Client] ai_models filter: Failed to create provider '{$provider_name}'");
                }
                return [];
            }
            
            // Get models directly from provider
            return $provider->get_raw_models();
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[AI HTTP Client] ai_models filter: Failed to fetch models - " . $e->getMessage());
            }
            return [];
        }
    }, 10, 1);
    
    // Public AI Request filter - high-level plugin interface
    // Usage: $response = apply_filters('ai_request', $request);
    // Usage: $response = apply_filters('ai_request', $request, $provider_name);  
    // Usage: $response = apply_filters('ai_request', $request, null, $streaming_callback);
    // Usage: $response = apply_filters('ai_request', $request, null, null, $step_id, $tools);
    add_filter('ai_request', function($request, $provider_name = null, $streaming_callback = null, $step_id = null, $tools = null) {
        // Auto-detect plugin context
        $plugin_context = ai_http_detect_plugin_context();
        if (empty($plugin_context)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AI HTTP Client] ai_request filter: Could not auto-detect plugin context');
            }
            return ai_http_create_error_response('Could not auto-detect plugin context');
        }
        
        // Default to LLM type
        $ai_type = 'llm';
        
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
        
        // Check for step_id in request or parameter for step-aware processing
        if (!$step_id && isset($request['step_id'])) {
            $step_id = $request['step_id'];
        }
        
        // Remove step_id from request before sending to provider
        if (isset($request['step_id'])) {
            unset($request['step_id']);
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
            // Get provider from step config or global options
            if (!$provider_name) {
                if ($step_id) {
                    // Step-aware: get provider from step configuration
                    $step_config = apply_filters('ai_config', $step_id);
                    $provider_name = isset($step_config['provider']) ? $step_config['provider'] : null;
                    
                    if (!$provider_name) {
                        return ai_http_create_error_response("No provider configured for step: {$step_id}");
                    }
                } else {
                    // Global: get provider from global configuration
                    $options_manager = new AI_HTTP_Options_Manager($plugin_context, $ai_type);
                    $provider_name = $options_manager->get_selected_provider();
                    
                    if (empty($provider_name)) {
                        return ai_http_create_error_response('No provider configured. Please select a provider in your plugin settings.');
                    }
                }
            }
            
            // Initialize normalizers for the selected provider
            $normalizers = ai_http_init_normalizers_for_provider($provider_name);
            if (!$normalizers) {
                return ai_http_create_error_response("Provider '{$provider_name}' not found or missing normalizers configuration");
            }
            
            // Get provider instance
            $provider = ai_http_create_provider($provider_name, $plugin_context, $ai_type);
            if (!$provider) {
                return ai_http_create_error_response("Failed to create provider instance for '{$provider_name}'");
            }
            
            // Get provider configuration (step-aware or global)
            if ($step_id) {
                // Step-aware: merge step config with provider config
                $step_config = apply_filters('ai_config', $step_id);
                $all_providers_config = apply_filters('ai_config', null);
                $provider_config = isset($all_providers_config[$provider_name]) ? $all_providers_config[$provider_name] : [];
                
                // Merge step-specific settings with request (step config takes precedence)
                $request = ai_http_merge_step_config_with_request($request, $step_config);
            } else {
                // Global: get provider config only
                $all_providers_config = apply_filters('ai_config', null);
                $provider_config = isset($all_providers_config[$provider_name]) ? $all_providers_config[$provider_name] : [];
            }
            
            // Normalize request for provider
            $provider_request = $normalizers['request']->normalize($request, $provider_name, $provider_config);
            
            // Handle streaming vs standard requests
            if ($streaming_callback && is_callable($streaming_callback)) {
                // Streaming request
                $streaming_request = $normalizers['streaming']->normalize_streaming_request($provider_request, $provider_name);
                return $provider->send_raw_streaming_request($streaming_request, function($chunk) use ($normalizers, $provider_name, $streaming_callback) {
                    $processed = $normalizers['streaming']->process_streaming_chunk($chunk, $provider_name);
                    if ($processed && isset($processed['content'])) {
                        call_user_func($streaming_callback, $processed['content']);
                    }
                });
            } else {
                // Standard request
                $raw_response = $provider->send_raw_request($provider_request);
                
                // Normalize response to standard format
                $standard_response = $normalizers['response']->normalize($raw_response, $provider_name);
                
                // Add metadata
                $standard_response['provider'] = $provider_name;
                $standard_response['success'] = true;
                
                return $standard_response;
            }
            
        } catch (Exception $e) {
            return ai_http_create_error_response($e->getMessage(), $provider_name);
        }
    }, 10, 5);
    
    /**
     * Render template with proper variable scoping
     *
     * @param string $template_name Template filename (without .php extension)
     * @param array $data Variables to make available in template
     * @return string Rendered template HTML
     */
    function ai_http_render_template($template_name, $data = []) {
        $template_path = dirname(__FILE__) . '/templates/' . $template_name . '.php';
        
        if (!file_exists($template_path)) {
            return '<div class="notice notice-error"><p>Template not found: ' . esc_html($template_name) . '</p></div>';
        }
        
        // Extract data array to local variables for template use
        extract($data, EXTR_SKIP);
        
        // Start output buffering to capture template output
        ob_start();
        include $template_path;
        return ob_get_clean();
    }
    
    // AI Component Rendering filter - single unified component system
    // Usage: echo apply_filters('ai_render_component', '');
    // Usage: echo apply_filters('ai_render_component', '', ['temperature' => true, 'system_prompt' => true]);
    add_filter('ai_render_component', function($html, $config = []) {
        // Auto-detect plugin context and AI type
        $plugin_context = ai_http_detect_plugin_context();
        if (!$plugin_context) {
            return '<div class="notice notice-error"><p>Unable to detect plugin context for AI components</p></div>';
        }
        
        $ai_type = 'llm'; // Default AI type
        
        // Get current configuration for auto-populated values
        $all_config = apply_filters('ai_config', null);
        $selected_provider = $all_config['selected_provider'] ?? 'openai';
        $provider_config = $all_config[$selected_provider] ?? [];
        
        // Generate unique ID for form elements
        $unique_id = 'ai_' . sanitize_key($plugin_context) . '_' . uniqid();
        
        // Extract step_id from config for step-aware field naming
        $step_id = $config['step_id'] ?? '';
        
        // Build form HTML with wrapper
        $html = '<div class="ai-http-client-components" data-plugin-context="' . esc_attr($plugin_context) . '">';
        
        // Render core components template (always rendered)
        $template_data = [
            'unique_id' => $unique_id,
            'plugin_context' => $plugin_context,
            'selected_provider' => $selected_provider,
            'provider_config' => $provider_config,
            'step_id' => $step_id,
            'all_config' => $all_config
        ];
        
        $html .= ai_http_render_template('core', $template_data);
        
        // Optional components (based on config)
        if (!empty($config['temperature'])) {
            $temp_config = is_array($config['temperature']) ? $config['temperature'] : [];
            $template_data['config'] = $temp_config;
            $html .= ai_http_render_template('temperature', $template_data);
        }
        
        if (!empty($config['system_prompt'])) {
            $prompt_config = is_array($config['system_prompt']) ? $config['system_prompt'] : [];
            $template_data['config'] = $prompt_config;
            $html .= ai_http_render_template('system-prompt', $template_data);
        }
        
        if (!empty($config['max_tokens'])) {
            $tokens_config = is_array($config['max_tokens']) ? $config['max_tokens'] : [];
            $template_data['config'] = $tokens_config;
            $html .= ai_http_render_template('max-tokens', $template_data);
        }
        
        // Add save button (if enabled) and nonce
        $show_save_button = $config['show_save_button'] ?? true; // Default to true for backward compatibility
        
        if ($show_save_button) {
            $html .= '<p class="submit">';
            $html .= '<button type="button" class="button button-primary ai-http-save-settings" ';
            $html .= 'data-plugin-context="' . esc_attr($plugin_context) . '">';
            $html .= __('Save AI Settings', 'ai-http-client');
            $html .= '</button>';
            $html .= '</p>';
        }
        
        $html .= '</div>';
        
        // Always add nonce for security (needed even without save button)
        $html .= wp_nonce_field('ai_http_nonce', 'ai_http_nonce_field', true, false);
        
        return $html;
    }, 10, 2);
    
    // TODO: Future provider types (upscaling, generative) can be added here
    // when their provider classes are implemented
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

/**
 * Initialize normalizers for a provider
 *
 * @param string $provider_name Provider name
 * @return array|false Normalizer instances or false on failure
 */
function ai_http_init_normalizers_for_provider($provider_name) {
    // Get all registered providers
    $all_providers = apply_filters('ai_providers', []);
    $provider_info = $all_providers[strtolower($provider_name)] ?? null;
    
    if (!$provider_info || !isset($provider_info['normalizers'])) {
        return false;
    }
    
    $normalizer_classes = $provider_info['normalizers'];
    
    // Instantiate normalizers
    $normalizers = array();
    foreach ($normalizer_classes as $type => $class) {
        $normalizers[$type] = new $class();
    }
    
    // Set up Files API callback for file uploads
    if (isset($normalizers['request']) && method_exists($normalizers['request'], 'set_files_api_callback')) {
        $normalizers['request']->set_files_api_callback(function($file_path, $purpose = 'user_data', $provider_name = 'openai') {
            return ai_http_upload_file_to_provider($file_path, $purpose, $provider_name);
        });
    }
    
    return $normalizers;
}

/**
 * Create provider instance
 *
 * @param string $provider_name Provider name
 * @param string $plugin_context Plugin context
 * @param string $ai_type AI type
 * @return object|false Provider instance or false on failure
 */
function ai_http_create_provider($provider_name, $plugin_context, $ai_type) {
    // Use filter-based provider discovery
    $all_providers = apply_filters('ai_providers', []);
    $provider_info = $all_providers[strtolower($provider_name)] ?? null;
    
    if (!$provider_info || $provider_info['type'] !== $ai_type) {
        return false;
    }
    
    // Get provider configuration
    $all_providers_config = apply_filters('ai_config', null);
    $provider_config = isset($all_providers_config[$provider_name]) ? $all_providers_config[$provider_name] : [];
    
    $provider_class = $provider_info['class'];
    return new $provider_class($provider_config);
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
    $plugin_context = ai_http_detect_plugin_context();
    $provider = ai_http_create_provider($provider_name, $plugin_context, 'llm');
    
    if (!$provider) {
        throw new Exception("{$provider_name} provider not available for Files API upload");
    }
    
    return $provider->upload_file($file_path, $purpose);
}

/**
 * Merge step configuration with request parameters
 *
 * @param array $request Base request
 * @param array $step_config Step configuration
 * @return array Enhanced request
 */
function ai_http_merge_step_config_with_request($request, $step_config) {
    // Start with the base request
    $enhanced_request = $request;
    
    // Override with step-specific settings (request params take precedence)
    if (isset($step_config['model']) && !isset($request['model'])) {
        $enhanced_request['model'] = $step_config['model'];
    }
    
    if (isset($step_config['temperature']) && !isset($request['temperature'])) {
        $enhanced_request['temperature'] = $step_config['temperature'];
    }
    
    if (isset($step_config['max_tokens']) && !isset($request['max_tokens'])) {
        $enhanced_request['max_tokens'] = $step_config['max_tokens'];
    }
    
    
    // Handle step-specific system prompt
    if (isset($step_config['system_prompt']) && !empty($step_config['system_prompt'])) {
        // Ensure messages array exists
        if (!isset($enhanced_request['messages'])) {
            $enhanced_request['messages'] = array();
        }
        
        // Check if there's already a system message
        $has_system_message = false;
        foreach ($enhanced_request['messages'] as $message) {
            if (isset($message['role']) && $message['role'] === 'system') {
                $has_system_message = true;
                break;
            }
        }
        
        // Add step system prompt if no system message exists
        if (!$has_system_message) {
            array_unshift($enhanced_request['messages'], array(
                'role' => 'system',
                'content' => $step_config['system_prompt']
            ));
        }
    }
    
    // Handle step-specific tools
    if (isset($step_config['tools_enabled']) && is_array($step_config['tools_enabled']) && !isset($request['tools'])) {
        $enhanced_request['tools'] = array();
        foreach ($step_config['tools_enabled'] as $tool_name) {
            // Convert tool names to tool definitions
            $enhanced_request['tools'][] = ai_http_convert_tool_name_to_definition($tool_name);
        }
    }
    
    return $enhanced_request;
}

/**
 * Convert tool name to tool definition
 *
 * @param string $tool_name Tool name  
 * @return array Tool definition
 */
function ai_http_convert_tool_name_to_definition($tool_name) {
    // Map common tool names to definitions
    $tool_definitions = array(
        'web_search_preview' => array(
            'type' => 'web_search_preview',
            'search_context_size' => 'low'
        ),
        'web_search' => array(
            'type' => 'web_search_preview',
            'search_context_size' => 'medium'
        )
    );
    
    return $tool_definitions[$tool_name] ?? array('type' => $tool_name);
}

/**
 * Get all registered AI tools with optional filtering
 *
 * @param string $plugin_context Optional plugin context filter
 * @param string $category Optional category filter  
 * @return array Filtered tools array
 * @since 1.2.0
 */
function ai_http_get_tools($plugin_context = null, $category = null) {
    $all_tools = apply_filters('ai_tools', []);
    
    // Auto-detect plugin context if not provided
    if ($plugin_context === null) {
        $plugin_context = ai_http_detect_plugin_context();
    }
    
    // Filter by plugin context
    if ($plugin_context) {
        $all_tools = array_filter($all_tools, function($tool) use ($plugin_context) {
            return isset($tool['plugin_context']) && $tool['plugin_context'] === $plugin_context;
        });
    }
    
    // Filter by category
    if ($category) {
        $all_tools = array_filter($all_tools, function($tool) use ($category) {
            return isset($tool['category']) && $tool['category'] === $category;
        });
    }
    
    return $all_tools;
}

/**
 * Check if a specific tool is available
 *
 * @param string $tool_name Tool name to check
 * @param string $plugin_context Optional plugin context
 * @return bool True if tool is available
 * @since 1.2.0
 */
function ai_http_has_tool($tool_name, $plugin_context = null) {
    $tools = ai_http_get_tools($plugin_context);
    return isset($tools[$tool_name]);
}

/**
 * Get tool definition by name
 *
 * @param string $tool_name Tool name
 * @param string $plugin_context Optional plugin context
 * @return array|null Tool definition or null if not found
 * @since 1.2.0  
 */
function ai_http_get_tool_definition($tool_name, $plugin_context = null) {
    $tools = ai_http_get_tools($plugin_context);
    return $tools[$tool_name] ?? null;
}

/**
 * Execute a registered tool by name
 *
 * @param string $tool_name Tool name to execute
 * @param array $parameters Tool parameters
 * @param string $plugin_context Optional plugin context
 * @return array Tool execution result
 * @since 1.2.0
 */
function ai_http_execute_tool($tool_name, $parameters = [], $plugin_context = null) {
    // Get tool definition
    $tool_def = ai_http_get_tool_definition($tool_name, $plugin_context);
    if (!$tool_def) {
        return [
            'success' => false,
            'error' => "Tool '{$tool_name}' not found",
            'tool_name' => $tool_name
        ];
    }
    
    // Validate required parameters
    if (isset($tool_def['parameters'])) {
        foreach ($tool_def['parameters'] as $param_name => $param_config) {
            if (isset($param_config['required']) && $param_config['required']) {
                if (!isset($parameters[$param_name])) {
                    return [
                        'success' => false,
                        'error' => "Required parameter '{$param_name}' missing for tool '{$tool_name}'",
                        'tool_name' => $tool_name
                    ];
                }
            }
        }
    }
    
    // Execute tool via class method
    if (isset($tool_def['class']) && class_exists($tool_def['class'])) {
        $tool_class = $tool_def['class'];
        $method = isset($tool_def['method']) ? $tool_def['method'] : 'execute';
        
        if (method_exists($tool_class, $method)) {
            try {
                $tool_instance = new $tool_class();
                $result = call_user_func([$tool_instance, $method], $parameters);
                
                return [
                    'success' => true,
                    'data' => $result,
                    'tool_name' => $tool_name
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'error' => "Tool execution failed: " . $e->getMessage(),
                    'tool_name' => $tool_name
                ];
            }
        }
    }
    
    // Execute tool via WordPress action (fallback)
    $result = [];
    do_action("ai_tool_{$tool_name}", $parameters, $result);
    
    if (!empty($result)) {
        return [
            'success' => true,
            'data' => $result,
            'tool_name' => $tool_name
        ];
    }
    
    return [
        'success' => false,
        'error' => "Tool '{$tool_name}' has no executable method or action handler",
        'tool_name' => $tool_name
    ];
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


// Initialize provider filters and AJAX actions on WordPress init
add_action('init', 'ai_http_client_register_provider_filters');

// Register AJAX actions for dynamic component interactions
// Only registers in admin context to avoid unnecessary overhead
if (is_admin()) {
    add_action('wp_ajax_ai_http_save_settings', ['AI_HTTP_Ajax_Handler', 'save_settings']);
    add_action('wp_ajax_ai_http_load_provider_settings', ['AI_HTTP_Ajax_Handler', 'load_provider_settings']);
    add_action('wp_ajax_ai_http_get_models', ['AI_HTTP_Ajax_Handler', 'get_models']);
}