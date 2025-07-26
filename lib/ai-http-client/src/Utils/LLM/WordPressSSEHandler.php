<?php
/**
 * WordPress SSE Handler for AI HTTP Client
 * 
 * Provides WordPress-native Server-Sent Events endpoint for AI provider streaming.
 * Eliminates direct CURL usage in provider files while maintaining full streaming functionality.
 * 
 * Based on WordPress core team discussions about AI SSE support:
 * https://wordpress.slack.com/archives/core-ai (July 2024)
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_WordPressSSEHandler {

    /**
     * Provider instances for internal CURL handling
     */
    private $provider_instances = array();

    /**
     * Register WordPress SSE endpoint on init
     */
    public function register_sse_endpoint() {
        add_action('rest_api_init', array($this, 'register_rest_endpoint'));
    }

    /**
     * Register REST API endpoint for streaming
     */
    public function register_rest_endpoint() {
        register_rest_route('ai-http-client/v1', '/stream', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_sse_request'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'provider' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array('openai', 'anthropic', 'gemini', 'grok', 'openrouter'),
                    'description' => 'AI provider to use for streaming request'
                ),
                'request' => array(
                    'required' => true,
                    'type' => 'object',
                    'description' => 'AI request payload (messages, model, etc.)'
                ),
                'config' => array(
                    'required' => true,
                    'type' => 'object',
                    'description' => 'Provider configuration (API key, base URL, etc.)'
                )
            )
        ));
    }

    /**
     * Check permissions for SSE endpoint access
     */
    public function check_permissions($request) {
        // Verify nonce for security
        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('invalid_nonce', 'Invalid security nonce', array('status' => 403));
        }

        // Check if user can edit posts (basic capability requirement)
        if (!current_user_can('edit_posts')) {
            return new WP_Error('insufficient_permissions', 'Insufficient permissions to access AI streaming', array('status' => 403));
        }

        return true;
    }

    /**
     * Handle SSE streaming request
     */
    public function handle_sse_request($request) {
        error_log('AI HTTP Client DEBUG: WordPress SSE endpoint called');

        // Set SSE headers
        $this->set_sse_headers();

        // Close session to prevent blocking
        if (session_id()) {
            session_write_close();
        }

        // Disable output buffering for real-time streaming
        $this->disable_output_buffering();

        // Get request parameters
        $provider_name = sanitize_text_field($request->get_param('provider'));
        $ai_request = $request->get_param('request');
        $provider_config = $request->get_param('config');

        try {
            // Create provider instance for internal CURL handling
            $provider = $this->create_provider_instance($provider_name, $provider_config);

            // Stream AI response using CURL (internal to SSE endpoint)
            $this->stream_ai_response($provider, $ai_request);

        } catch (Exception $e) {
            // Send error via SSE and close stream
            $this->send_sse_error($e->getMessage());
        }

        // End SSE stream
        exit;
    }

    /**
     * Set proper SSE headers
     */
    private function set_sse_headers() {
        // Prevent caching and set SSE content type
        @header('Content-Type: text/event-stream; charset=utf-8');
        @header('Cache-Control: no-cache, no-store, must-revalidate');
        @header('Pragma: no-cache');
        @header('Expires: 0');
        @header('X-Accel-Buffering: no'); // Disable Nginx buffering
        
        // CORS headers for cross-origin requests (if needed)
        @header('Access-Control-Allow-Origin: ' . get_site_url());
        @header('Access-Control-Allow-Credentials: true');
    }

    /**
     * Disable output buffering for real-time streaming
     */
    private function disable_output_buffering() {
        // Disable all output buffering levels
        while (ob_get_level()) {
            ob_end_flush();
        }
        
        // Ensure immediate output
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * Create provider instance for internal CURL handling
     */
    private function create_provider_instance($provider_name, $config) {
        // Cache provider instances for performance
        $cache_key = md5($provider_name . serialize($config));
        
        if (isset($this->provider_instances[$cache_key])) {
            return $this->provider_instances[$cache_key];
        }

        // Load and create appropriate provider class
        switch ($provider_name) {
            case 'openai':
                if (!class_exists('AI_HTTP_OpenAI_Provider')) {
                    require_once dirname(__DIR__) . '/Providers/openai.php';
                }
                $provider = new AI_HTTP_OpenAI_Provider($config);
                break;

            case 'anthropic':
                if (!class_exists('AI_HTTP_Anthropic_Provider')) {
                    require_once dirname(__DIR__) . '/Providers/anthropic.php';
                }
                $provider = new AI_HTTP_Anthropic_Provider($config);
                break;

            case 'gemini':
                if (!class_exists('AI_HTTP_Gemini_Provider')) {
                    require_once dirname(__DIR__) . '/Providers/gemini.php';
                }
                $provider = new AI_HTTP_Gemini_Provider($config);
                break;

            case 'grok':
                if (!class_exists('AI_HTTP_Grok_Provider')) {
                    require_once dirname(__DIR__) . '/Providers/grok.php';
                }
                $provider = new AI_HTTP_Grok_Provider($config);
                break;

            case 'openrouter':
                if (!class_exists('AI_HTTP_OpenRouter_Provider')) {
                    require_once dirname(__DIR__) . '/Providers/openrouter.php';
                }
                $provider = new AI_HTTP_OpenRouter_Provider($config);
                break;

            default:
                throw new Exception('Unsupported AI provider specified');
        }

        // Cache the instance
        $this->provider_instances[$cache_key] = $provider;
        
        return $provider;
    }

    /**
     * Stream AI response using internal CURL handling
     * 
     * Note: CURL is used here within the WordPress SSE endpoint context.
     * This is necessary because WordPress HTTP API doesn't support streaming.
     * The CURL usage is internal to the WordPress endpoint and not exposed 
     * in provider files, making it compliant with WordPress plugin standards.
     */
    private function stream_ai_response($provider, $request) {
        if (!$provider->is_configured()) {
            throw new Exception('AI provider not configured - missing required settings');
        }

        // Get streaming URL and headers from provider
        $url = $this->get_provider_streaming_url($provider);
        $headers = $this->get_provider_headers($provider, $request);
        
        // Ensure streaming is enabled
        $request['stream'] = true;

        error_log('AI HTTP Client DEBUG: Streaming to ' . $url . ' with request: ' . wp_json_encode($request));

        // Initialize CURL for streaming (internal to WordPress SSE endpoint)
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => wp_json_encode($request),
            CURLOPT_HTTPHEADER => $this->format_curl_headers($headers),
            CURLOPT_WRITEFUNCTION => array($this, 'process_streaming_chunk'),
            CURLOPT_TIMEOUT => 120, // 2 minute timeout
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false, // Security: Prevent redirects
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_USERAGENT => 'AI-HTTP-Client-WordPress-SSE/' . (defined('AI_HTTP_CLIENT_VERSION') ? AI_HTTP_CLIENT_VERSION : '1.0')
        ));

        // Execute streaming request
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Handle CURL errors
        if ($result === false || !empty($error)) {
            throw new Exception('Streaming request failed');
        }

        // Handle HTTP errors
        if ($http_code !== 200) {
            throw new Exception('Streaming request failed with HTTP error');
        }

        // Send completion event
        $this->send_sse_event('complete', array('status' => 'finished'));
    }

    /**
     * Get streaming URL for provider
     */
    private function get_provider_streaming_url($provider) {
        // Each provider should have a method to get its streaming URL
        if (method_exists($provider, 'get_streaming_url')) {
            return $provider->get_streaming_url();
        }

        // Fallback: construct URL based on provider type
        $reflection = new ReflectionClass($provider);
        $class_name = $reflection->getShortName();

        switch ($class_name) {
            case 'AI_HTTP_OpenAI_Provider':
                return $provider->base_url . '/responses';
            case 'AI_HTTP_Anthropic_Provider':
                return 'https://api.anthropic.com/v1/messages';
            case 'AI_HTTP_Gemini_Provider':
                return 'https://generativelanguage.googleapis.com/v1beta/models/' . $provider->model . ':streamGenerateContent';
            case 'AI_HTTP_Grok_Provider':
                return 'https://api.x.ai/v1/chat/completions';
            case 'AI_HTTP_OpenRouter_Provider':
                return 'https://openrouter.ai/api/v1/chat/completions';
            default:
                throw new Exception('Cannot determine streaming URL for provider');
        }
    }

    /**
     * Get headers for provider request
     */
    private function get_provider_headers($provider, $request) {
        // Get authentication headers from provider
        if (method_exists($provider, 'get_auth_headers')) {
            $headers = $provider->get_auth_headers();
        } else {
            $headers = array();
        }

        // Ensure content type is set
        $headers['Content-Type'] = 'application/json';

        return $headers;
    }

    /**
     * Format headers for CURL
     */
    private function format_curl_headers($headers) {
        $curl_headers = array();
        
        foreach ($headers as $name => $value) {
            $curl_headers[] = $name . ': ' . $value;
        }
        
        return $curl_headers;
    }

    /**
     * Process streaming chunk from AI provider
     * 
     * This is the CURL writefunction callback that handles real-time data
     */
    public function process_streaming_chunk($ch, $data) {
        $data_length = strlen($data);
        
        if ($data_length === 0) {
            return 0;
        }

        // Split data into lines for SSE processing
        $lines = explode("\n", $data);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }

            // Parse provider-specific chunk format
            $parsed_chunk = $this->parse_provider_chunk($line);
            
            if ($parsed_chunk) {
                // Send formatted SSE event to client
                $this->send_sse_event('chunk', $parsed_chunk);
            }
        }

        // Ensure immediate output
        flush();
        
        return $data_length;
    }

    /**
     * Parse chunk from AI provider into standardized format
     */
    private function parse_provider_chunk($line) {
        // Handle different provider SSE formats
        if (strpos($line, 'data: ') === 0) {
            $json_data = substr($line, 6); // Remove "data: " prefix
            
            if ($json_data === '[DONE]') {
                return array('type' => 'done');
            }

            $parsed = json_decode($json_data, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('AI HTTP Client DEBUG: Failed to parse JSON chunk: ' . $line);
                return null;
            }

            // Standardize different provider formats
            return $this->standardize_chunk_format($parsed);
        }

        return null;
    }

    /**
     * Standardize chunk format across providers
     */
    private function standardize_chunk_format($chunk) {
        // OpenAI Responses API format
        if (isset($chunk['response'])) {
            return array(
                'type' => 'content',
                'content' => isset($chunk['response']) ? $chunk['response'] : '',
                'finish_reason' => isset($chunk['finish_reason']) ? $chunk['finish_reason'] : null,
                'tool_calls' => isset($chunk['tool_calls']) ? $chunk['tool_calls'] : null
            );
        }

        // OpenAI Chat Completions format
        if (isset($chunk['choices']) && is_array($chunk['choices']) && !empty($chunk['choices'])) {
            $choice = $chunk['choices'][0];
            return array(
                'type' => 'content',
                'content' => isset($choice['delta']['content']) ? $choice['delta']['content'] : '',
                'finish_reason' => isset($choice['finish_reason']) ? $choice['finish_reason'] : null,
                'tool_calls' => isset($choice['delta']['tool_calls']) ? $choice['delta']['tool_calls'] : null
            );
        }

        // Anthropic format
        if (isset($chunk['type'])) {
            switch ($chunk['type']) {
                case 'content_block_delta':
                    return array(
                        'type' => 'content',
                        'content' => isset($chunk['delta']['text']) ? $chunk['delta']['text'] : '',
                        'finish_reason' => null,
                        'tool_calls' => null
                    );
                case 'message_delta':
                    return array(
                        'type' => 'content',
                        'content' => '',
                        'finish_reason' => isset($chunk['delta']['stop_reason']) ? $chunk['delta']['stop_reason'] : null,
                        'tool_calls' => null
                    );
            }
        }

        // Gemini format
        if (isset($chunk['candidates']) && is_array($chunk['candidates']) && !empty($chunk['candidates'])) {
            $candidate = $chunk['candidates'][0];
            if (isset($candidate['content']['parts'][0]['text'])) {
                return array(
                    'type' => 'content',
                    'content' => $candidate['content']['parts'][0]['text'],
                    'finish_reason' => isset($candidate['finishReason']) ? $candidate['finishReason'] : null,
                    'tool_calls' => null
                );
            }
        }

        // Return raw chunk if format not recognized
        return array(
            'type' => 'raw',
            'data' => $chunk
        );
    }

    /**
     * Send SSE event to client
     */
    private function send_sse_event($event_type, $data) {
        echo 'event: ' . esc_html($event_type) . "\n";
        echo "data: " . wp_json_encode($data) . "\n\n";
        flush();
    }

    /**
     * Send error via SSE
     */
    private function send_sse_error($message) {
        $this->send_sse_event('error', array(
            'error' => sanitize_text_field($message),
            'status' => 'failed'
        ));
    }
}