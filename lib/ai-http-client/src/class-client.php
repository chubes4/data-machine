<?php
/**
 * AI HTTP Client - Main Orchestrator
 * 
 * Single Responsibility: Orchestrate AI requests using unified normalizers
 * Acts as the "round plug" interface with filter-based provider architecture
 *
 * @package AIHttpClient
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Client {

    /**
     * Unified normalizers
     */
    private $request_normalizer;
    private $response_normalizer;
    private $streaming_normalizer;
    private $tool_results_normalizer;
    
    /**
     * Client configuration
     */
    private $config = array();

    /**
     * Provider instances cache
     */
    private $providers = array();

    /**
     * Plugin context for scoped configuration
     */
    private $plugin_context;

    /**
     * AI type (llm, upscaling, generative, etc.)
     */
    private $ai_type;

    /**
     * Whether the client is properly configured
     */
    private $is_configured = false;

    /**
     * Constructor with unified normalizers and plugin context support
     *
     * @param array $config Client configuration - should include 'plugin_context' and 'ai_type'
     */
    public function __construct($config = array()) {
        // Require ai_type parameter - no defaults
        if (empty($config['ai_type'])) {
            error_log('AI HTTP Client: ai_type parameter is required. Specify "llm", "upscaling", or "generative".');
            $this->is_configured = false;
            return;
        }
        
        // Validate ai_type using filter-based discovery
        $ai_types = apply_filters('ai_types', []);
        $valid_types = array_keys($ai_types);
        if (!in_array($config['ai_type'], $valid_types)) {
            error_log('AI HTTP Client: Invalid ai_type "' . $config['ai_type'] . '". Must be one of: ' . implode(', ', $valid_types));
            $this->is_configured = false;
            return;
        }
        
        $this->ai_type = $config['ai_type'];
        
        // Graceful fallback for missing plugin context
        if (empty($config['plugin_context'])) {
            error_log('AI HTTP Client: plugin_context parameter is required for proper configuration.');
            $this->is_configured = false;
            return;
        }
        
        $this->plugin_context = sanitize_key($config['plugin_context']);
        $this->is_configured = true;
        
        
        // Store configuration - no operational defaults
        $this->config = $config;
    }

    /**
     * Initialize normalizers based on selected provider's configuration
     * Uses provider self-registration to load appropriate normalizers dynamically
     *
     * @param string $provider_name The provider to initialize normalizers for
     * @throws Exception If provider not found or missing normalizers configuration
     */
    private function init_normalizers_for_provider($provider_name) {
        // Get all registered providers
        $all_providers = apply_filters('ai_providers', []);
        $provider_info = $all_providers[strtolower($provider_name)] ?? null;
        
        if (!$provider_info) {
            throw new Exception("Provider '{$provider_name}' not found in registered providers");
        }
        
        if (!isset($provider_info['normalizers'])) {
            throw new Exception("Provider '{$provider_name}' missing required normalizers configuration");
        }
        
        $normalizers = $provider_info['normalizers'];
        
        // Instantiate normalizers from provider configuration
        $this->request_normalizer = new $normalizers['request']();
        $this->response_normalizer = new $normalizers['response']();
        $this->streaming_normalizer = new $normalizers['streaming']();
        $this->tool_results_normalizer = new $normalizers['tool_results']();
        
        // Set up Files API callback for file uploads (NO BASE64) - MULTI-PROVIDER
        if (method_exists($this->request_normalizer, 'set_files_api_callback')) {
            $this->request_normalizer->set_files_api_callback(function($file_path, $purpose = 'user_data', $provider_name = 'openai') {
                return $this->upload_file_to_provider_files_api($file_path, $purpose, $provider_name);
            });
        }
    }

    /**
     * Main pipeline: Send standardized request through unified architecture
     *
     * @param array $request Standardized "round plug" input
     * @param string $provider_name Optional specific provider to use
     * @return array Standardized "round plug" output
     */
    public function send_request($request, $provider_name = null) {
        // Return error if client is not properly configured
        if (!$this->is_configured) {
            return $this->create_error_response('AI HTTP Client is not properly configured - plugin context is required');
        }
        
        // Get provider from options if not specified
        if (!$provider_name) {
            if (class_exists('AI_HTTP_Options_Manager') && !empty($this->plugin_context)) {
                $options_manager = new AI_HTTP_Options_Manager($this->plugin_context, $this->ai_type);
                $provider_name = $options_manager->get_selected_provider();
            }
            
            if (empty($provider_name)) {
                throw new Exception('No provider configured. Please select a provider in your plugin settings.');
            }
        }
        
        try {
            // Step 1: Initialize normalizers for the selected provider
            $this->init_normalizers_for_provider($provider_name);
            
            // Step 2: Validate standard input
            $this->validate_request($request);
            
            // Step 3: Get or create provider instance
            $provider = $this->get_provider($provider_name);
            
            // Step 4: Normalize request for provider
            $provider_config = $this->get_provider_config($provider_name);
            $provider_request = $this->request_normalizer->normalize($request, $provider_name, $provider_config);
            
            // Step 5: Send raw request to provider
            $raw_response = $provider->send_raw_request($provider_request);
            
            // Step 6: Normalize response to standard format
            $standard_response = $this->response_normalizer->normalize($raw_response, $provider_name);
            
            // Step 7: Add metadata
            $standard_response['provider'] = $provider_name;
            $standard_response['success'] = true;
            
            return $standard_response;
            
        } catch (Exception $e) {
            return $this->create_error_response($e->getMessage(), $provider_name);
        }
    }

    /**
     * Send streaming request through unified architecture
     *
     * @param array $request Standardized request
     * @param string $provider_name Optional specific provider to use
     * @param callable $completion_callback Optional callback when streaming completes
     * @return string Full response from streaming request
     * @throws Exception If streaming fails
     */
    public function send_streaming_request($request, $provider_name = null, $completion_callback = null) {
        // Return early if client is not properly configured
        if (!$this->is_configured) {
            AI_HTTP_Plugin_Context_Helper::log_context_error('Streaming request failed - client not properly configured', 'AI_HTTP_Client');
            return;
        }
        
        // Get provider from options if not specified
        if (!$provider_name) {
            if (class_exists('AI_HTTP_Options_Manager') && !empty($this->plugin_context)) {
                $options_manager = new AI_HTTP_Options_Manager($this->plugin_context, $this->ai_type);
                $provider_name = $options_manager->get_selected_provider();
            }
            
            if (empty($provider_name)) {
                throw new Exception('No provider configured. Please select a provider in your plugin settings.');
            }
        }

        try {
            // Step 1: Initialize normalizers for the selected provider
            $this->init_normalizers_for_provider($provider_name);
            
            // Check if streaming is supported for this provider
            if ($this->streaming_normalizer === null) {
                throw new Exception('Streaming is not supported for specified provider');
            }
            
            // Step 2: Validate standard input
            $this->validate_request($request);
            
            // Step 3: Get or create provider instance
            $provider = $this->get_provider($provider_name);
            
            // Step 4: Normalize request for streaming
            $provider_config = $this->get_provider_config($provider_name);
            $provider_request = $this->request_normalizer->normalize($request, $provider_name, $provider_config);
            $streaming_request = $this->streaming_normalizer->normalize_streaming_request($provider_request, $provider_name);
            
            // Step 4: Send streaming request with chunk processor
            return $provider->send_raw_streaming_request($streaming_request, function($chunk) use ($provider_name) {
                $processed = $this->streaming_normalizer->process_streaming_chunk($chunk, $provider_name);
                if ($processed && isset($processed['content'])) {
                    echo esc_html($processed['content']);
                    flush();
                }
            });
            
        } catch (Exception $e) {
            throw new Exception('Streaming request failed');
        }
    }

    /**
     * Continue conversation with tool results using unified architecture
     *
     * @param string|array $context_data Provider-specific context (response_id, conversation_history, etc.)
     * @param array $tool_results Array of tool results
     * @param string $provider_name Optional specific provider to use
     * @param callable $completion_callback Optional callback for streaming
     * @return array|string Response from continuation request
     * @throws Exception If continuation fails
     */
    public function continue_with_tool_results($context_data, $tool_results, $provider_name = null, $completion_callback = null) {
        // Check if tool results are supported for this AI type
        if ($this->tool_results_normalizer === null) {
            throw new Exception('Tool results are not supported for specified ai_type');
        }
        
        // Get provider from options if not specified
        if (!$provider_name) {
            if (class_exists('AI_HTTP_Options_Manager') && !empty($this->plugin_context)) {
                $options_manager = new AI_HTTP_Options_Manager($this->plugin_context, $this->ai_type);
                $provider_name = $options_manager->get_selected_provider();
            }
            
            if (empty($provider_name)) {
                throw new Exception('No provider configured. Please select a provider in your plugin settings.');
            }
        }
        
        try {
            // Step 1: Get provider instance
            $provider = $this->get_provider($provider_name);
            
            // Step 2: Normalize tool results for continuation
            $continuation_request = $this->tool_results_normalizer->normalize_for_continuation(
                $tool_results, 
                $provider_name, 
                $context_data
            );
            
            // Step 3: Send continuation request
            if ($completion_callback) {
                // Streaming continuation
                return $provider->send_raw_streaming_request($continuation_request, $completion_callback);
            } else {
                // Non-streaming continuation
                $raw_response = $provider->send_raw_request($continuation_request);
                return $this->response_normalizer->normalize($raw_response, $provider_name);
            }
            
        } catch (Exception $e) {
            throw new Exception('Tool continuation request failed');
        }
    }


    /**
     * Get available models for provider
     *
     * @param string $provider_name Provider name
     * @return array Available models
     */
    public function get_available_models($provider_name = null) {
        // Get provider from options if not specified
        if (!$provider_name) {
            if (class_exists('AI_HTTP_Options_Manager') && !empty($this->plugin_context)) {
                $options_manager = new AI_HTTP_Options_Manager($this->plugin_context, $this->ai_type);
                $provider_name = $options_manager->get_selected_provider();
            }
            
            if (empty($provider_name)) {
                throw new Exception('No provider configured. Please select a provider in your plugin settings.');
            }
        }
        
        try {
            $provider = $this->get_provider($provider_name);
            return $provider->get_raw_models();
            
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('AI HTTP Client: Model fetch failed for ' . $provider_name . ': ' . $e->getMessage());
            }
            return array();
        }
    }

    /**
     * Get or create provider instance
     *
     * @param string $provider_name Provider name
     * @return object Provider instance
     * @throws Exception If provider not supported
     */
    private function get_provider($provider_name) {
        if (isset($this->providers[$provider_name])) {
            return $this->providers[$provider_name];
        }
        
        $provider_config = $this->get_provider_config($provider_name);
        
        // Route to appropriate provider based on ai_type
        switch ($this->ai_type) {
            case 'llm':
                $this->providers[$provider_name] = $this->create_llm_provider($provider_name, $provider_config);
                break;
                
            case 'upscaling':
                $this->providers[$provider_name] = $this->create_upscaling_provider($provider_name, $provider_config);
                break;
                
            case 'generative':
                $this->providers[$provider_name] = $this->create_generative_provider($provider_name, $provider_config);
                break;
                
            default:
                throw new Exception('Unsupported ai_type for provider creation');
        }
        
        return $this->providers[$provider_name];
    }

    /**
     * Create LLM provider instance
     *
     * @param string $provider_name Provider name
     * @param array $provider_config Provider configuration
     * @return object Provider instance
     * @throws Exception If provider not supported
     */
    private function create_llm_provider($provider_name, $provider_config) {
        // Use filter-based provider discovery
        $all_providers = apply_filters('ai_providers', []);
        $provider_info = $all_providers[strtolower($provider_name)] ?? null;
        
        if (!$provider_info || $provider_info['type'] !== 'llm') {
            throw new Exception('LLM provider not supported');
        }
        
        $provider_class = $provider_info['class'];
        return new $provider_class($provider_config);
    }

    /**
     * Create upscaling provider instance
     *
     * @param string $provider_name Provider name
     * @param array $provider_config Provider configuration
     * @return object Provider instance
     * @throws Exception If provider not supported
     */
    private function create_upscaling_provider($provider_name, $provider_config) {
        // Use filter-based provider discovery
        $all_providers = apply_filters('ai_providers', []);
        $provider_info = $all_providers[strtolower($provider_name)] ?? null;
        
        if (!$provider_info || $provider_info['type'] !== 'upscaling') {
            throw new Exception('Upscaling provider not supported');
        }
        
        $provider_class = $provider_info['class'];
        return new $provider_class($provider_config);
    }

    /**
     * Create generative provider instance
     *
     * @param string $provider_name Provider name
     * @param array $provider_config Provider configuration
     * @return object Provider instance
     * @throws Exception If provider not supported
     */
    private function create_generative_provider($provider_name, $provider_config) {
        // Use filter-based provider discovery
        $all_providers = apply_filters('ai_providers', []);
        $provider_info = $all_providers[strtolower($provider_name)] ?? null;
        
        if (!$provider_info || $provider_info['type'] !== 'generative') {
            throw new Exception('Generative provider not supported');
        }
        
        $provider_class = $provider_info['class'];
        return new $provider_class($provider_config);
    }

    /**
     * Get provider configuration
     *
     * @param string $provider_name Provider name
     * @return array Provider configuration
     */
    /**
     * Get provider configuration from plugin-scoped options
     *
     * @param string $provider_name Provider name
     * @return array Provider configuration with merged API keys
     */
    private function get_provider_config($provider_name) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller_method = $backtrace[1]['function'] ?? 'unknown';
        
        error_log("[AI_HTTP_Client Debug] ====== GET_PROVIDER_CONFIG START ======");
        error_log("[AI_HTTP_Client Debug] Called by method: {$caller_method}");
        error_log("[AI_HTTP_Client Debug] Provider: {$provider_name}");
        error_log("[AI_HTTP_Client Debug] Plugin context: {$this->plugin_context}, AI type: {$this->ai_type}");
        
        // Use ai_config filter for universal configuration access
        $all_providers_config = apply_filters('ai_config', [], $this->plugin_context, $this->ai_type);
        
        // Extract provider-specific settings from global configuration
        $config = isset($all_providers_config[$provider_name]) ? $all_providers_config[$provider_name] : [];
        
        // Debug API key status
        $api_key_status = isset($config['api_key']) && !empty($config['api_key']) ? 'SET_LENGTH_' . strlen($config['api_key']) : 'EMPTY';
        error_log("[AI_HTTP_Client Debug] FINAL CONFIG API key status: {$api_key_status}");
        error_log("[AI_HTTP_Client Debug] FINAL CONFIG keys available: " . json_encode(array_keys($config)));
        error_log("[AI_HTTP_Client Debug] ====== GET_PROVIDER_CONFIG END ======");
        
        return $config;
    }


    /**
     * Validate standard request format
     *
     * @param array $request Request to validate
     * @throws Exception If invalid
     */
    private function validate_request($request) {
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
     * Upload file to any provider's Files API (NO BASE64) - MULTI-PROVIDER SUPPORT
     *
     * @param string $file_path Path to file to upload
     * @param string $purpose Purpose for upload (default: 'user_data')
     * @param string $provider_name Provider to upload to (openai, anthropic, gemini, etc.)
     * @return string File ID from provider's Files API
     * @throws Exception If upload fails
     */
    private function upload_file_to_provider_files_api($file_path, $purpose = 'user_data', $provider_name = 'openai') {
        // Get the specified provider for Files API upload
        $provider = $this->get_provider($provider_name);
        if (!$provider) {
            throw new Exception("{$provider_name} provider not available for Files API upload");
        }

        // Use the provider's native upload_file method (all providers implement Files API)
        return $provider->upload_file($file_path, $purpose);
    }

    /**
     * Create standardized error response
     *
     * @param string $error_message Error message
     * @param string $provider_name Provider name
     * @return array Standardized error response
     */
    private function create_error_response($error_message, $provider_name = 'unknown') {
        return array(
            'success' => false,
            'data' => null,
            'error' => $error_message,
            'provider' => $provider_name,
            'raw_response' => null
        );
    }

    /**
     * Check if client is properly configured
     *
     * @return bool True if configured, false otherwise
     */
    public function is_configured() {
        return $this->is_configured;
    }


    // === STEP-AWARE REQUEST METHODS ===

    /**
     * Send a request using step-specific configuration
     * 
     * This convenience method automatically loads the step configuration
     * and merges it with the provided request parameters.
     *
     * @param string $step_id Step identifier
     * @param array $request Base request parameters
     * @return array Standardized response
     */
    public function send_step_request($step_id, $request) {
        // Return error if client is not properly configured
        if (!$this->is_configured) {
            return $this->create_error_response('AI HTTP Client is not properly configured - plugin context is required');
        }
        
        try {
            // Load step configuration
            error_log("[AI_HTTP_Client Debug] send_step_request() called for step_id: {$step_id}");
            error_log("[AI_HTTP_Client Debug] Plugin context: {$this->plugin_context}, AI type: {$this->ai_type}");
            
            $options_manager = new AI_HTTP_Options_Manager($this->plugin_context, $this->ai_type);
            $step_config = $options_manager->get_step_configuration($step_id);
            
            if (empty($step_config)) {
                error_log("[AI_HTTP_Client Debug] CRITICAL: No step configuration found for step_id: {$step_id}");
                return $this->create_error_response("No configuration found for step: {$step_id}");
            }
            
            // Merge step configuration with request
            $enhanced_request = $this->merge_step_config_with_request($request, $step_config);
            
            // Get provider from step config
            $provider = $step_config['provider'] ?? null;
            if (!$provider) {
                error_log("[AI_HTTP_Client Debug] CRITICAL: No provider in step configuration for step_id: {$step_id}");
                error_log("[AI_HTTP_Client Debug] Step config keys: " . json_encode(array_keys($step_config)));
                return $this->create_error_response("No provider configured for step: {$step_id}");
            }
            
            error_log("[AI_HTTP_Client Debug] Step configuration loaded successfully - provider: {$provider}");
            
            // Send request using step-configured provider  
            return $this->send_request($enhanced_request, $provider);
            
        } catch (Exception $e) {
            return $this->create_error_response("Step request failed: " . $e->getMessage(), $step_id);
        }
    }
    
    /**
     * Get step configuration for debugging/inspection
     *
     * @param string $step_id Step identifier
     * @return array Step configuration
     */
    public function get_step_configuration($step_id) {
        if (!$this->is_configured) {
            return array();
        }
        
        $options_manager = new AI_HTTP_Options_Manager($this->plugin_context, $this->ai_type);
        return $options_manager->get_step_configuration($step_id);
    }
    
    /**
     * Check if a step has configuration
     *
     * @param string $step_id Step identifier
     * @return bool True if step is configured
     */
    public function has_step_configuration($step_id) {
        if (!$this->is_configured) {
            return false;
        }
        
        $options_manager = new AI_HTTP_Options_Manager($this->plugin_context, $this->ai_type);
        return $options_manager->has_step_configuration($step_id);
    }
    
    /**
     * Merge step configuration with request parameters
     *
     * @param array $request Base request
     * @param array $step_config Step configuration
     * @return array Enhanced request
     */
    private function merge_step_config_with_request($request, $step_config) {
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
        
        if (isset($step_config['top_p']) && !isset($request['top_p'])) {
            $enhanced_request['top_p'] = $step_config['top_p'];
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
                $enhanced_request['tools'][] = $this->convert_tool_name_to_definition($tool_name);
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
    private function convert_tool_name_to_definition($tool_name) {
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
}