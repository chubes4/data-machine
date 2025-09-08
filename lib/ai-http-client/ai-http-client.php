<?php
/**
 * AI HTTP Client Library
 * 
 * A professional WordPress library for unified AI provider communication.
 * Supports OpenAI, Anthropic, Google Gemini, Grok, and OpenRouter with
 * standardized request/response formats.
 *
 * @package AIHttpClient
 * @version 1.1.1
 * @author Chris Huber <https://chubes.net>
 * @link https://github.com/chubes/ai-http-client
 */

defined('ABSPATH') || exit;

// Define component constants
if (!defined('AI_HTTP_CLIENT_PATH')) {
    define('AI_HTTP_CLIENT_PATH', __DIR__);
}

if (!defined('AI_HTTP_CLIENT_URL')) {
    define('AI_HTTP_CLIENT_URL', plugin_dir_url(__FILE__));
}

/**
 * Initialize AI HTTP Client library
 * Loads all components using WordPress native loading
 */
function ai_http_client_init() {
    // No external dependencies - WordPress native loading only
    
    // Load providers and filters
    require_once AI_HTTP_CLIENT_PATH . '/src/Providers/openai.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Providers/gemini.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Providers/anthropic.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Providers/grok.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Providers/openrouter.php';
    
    require_once AI_HTTP_CLIENT_PATH . '/src/Filters/Models.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Filters/Tools.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Filters/Admin.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Filters/Requests.php';
    
    // Hook into WordPress for any setup needed
    if (function_exists('add_action')) {
        add_action('init', 'ai_http_client_wordpress_init', 1);
    }
}

function ai_http_client_wordpress_init() {
    // WordPress-specific initialization
    if (function_exists('do_action')) {
        do_action('ai_http_client_loaded');
    }
}

// Initialize the library
ai_http_client_init();