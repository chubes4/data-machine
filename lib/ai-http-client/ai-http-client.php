<?php
/**
 * AI HTTP Client Library
 * 
 * A professional WordPress library for unified AI provider communication.
 * Supports OpenAI, Anthropic, Google Gemini, Grok, and OpenRouter with
 * standardized request/response formats and automatic fallback handling.
 *
 * Modeled after Action Scheduler for enterprise WordPress development.
 *
 * @package AIHttpClient
 * @version 1.1.1
 * @author Chris Huber <https://chubes.net>
 * @link https://github.com/chubes/ai-http-client
 */

defined('ABSPATH') || exit;

/**
 * AI HTTP Client version and compatibility checking
 * Prevents conflicts when multiple plugins include different versions
 */
if (!defined('AI_HTTP_CLIENT_VERSION')) {
    define('AI_HTTP_CLIENT_VERSION', '1.1.1');
}

// Check if we should load this version
if (!function_exists('ai_http_client_version_check')) {
    function ai_http_client_version_check() {
        global $ai_http_client_version;
        
        if (empty($ai_http_client_version) || version_compare(AI_HTTP_CLIENT_VERSION, $ai_http_client_version, '>')) {
            $ai_http_client_version = AI_HTTP_CLIENT_VERSION;
            return true;
        }
        
        return false;
    }
}

// Only load if this is the highest version
if (!ai_http_client_version_check()) {
    return;
}

// Prevent multiple inclusions of the same version
// Pure filter architecture - no classes needed
if (defined('AI_HTTP_CLIENT_FILTERS_LOADED')) {
    return;
}
define('AI_HTTP_CLIENT_FILTERS_LOADED', true);

// Define component constants
if (!defined('AI_HTTP_CLIENT_PATH')) {
    define('AI_HTTP_CLIENT_PATH', __DIR__);
}

if (!defined('AI_HTTP_CLIENT_URL')) {
    define('AI_HTTP_CLIENT_URL', plugin_dir_url(__FILE__));
}

/**
 * Initialize AI HTTP Client library
 * Loads all modular components in correct dependency order
 * Supports both Composer autoloading and manual WordPress loading
 */
if (!function_exists('ai_http_client_init')) {
    function ai_http_client_init() {
        // Check if Composer autoloader is available
        $composer_autoload = AI_HTTP_CLIENT_PATH . '/vendor/autoload.php';
        $composer_loaded = false;
        
        if (file_exists($composer_autoload)) {
            require_once $composer_autoload;
            $composer_loaded = true;
        }
        
        // If Composer isn't available, use manual loading
        if (!$composer_loaded) {
            ai_http_client_manual_load();
        }
        
        // 5. Hook into WordPress for any setup needed
        if (function_exists('add_action')) {
            add_action('init', 'ai_http_client_wordpress_init', 1);
        }
    }
    
    /**
     * Manual loading for non-Composer environments
     * Maintains backward compatibility with existing WordPress installations
     */
    function ai_http_client_manual_load() {
        // Load in dependency order
        
        // 1. Load dependencies in order
        
        // 2. Shared utilities (moved to top-level)
        require_once AI_HTTP_CLIENT_PATH . '/src/Utils/FileUploadClient.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Utils/LLM/ToolExecutor.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Utils/LLM/WordPressSSEHandler.php';
        
        // 2.6. Unified Normalizers (NEW ARCHITECTURE)
        require_once AI_HTTP_CLIENT_PATH . '/src/Normalizers/LLM/UnifiedRequestNormalizer.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Normalizers/LLM/UnifiedResponseNormalizer.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Normalizers/LLM/UnifiedStreamingNormalizer.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Normalizers/LLM/UnifiedToolResultsNormalizer.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Normalizers/LLM/UnifiedModelFetcher.php';
        
        // 2.7. LLM Provider Classes (FILTER-BASED ARCHITECTURE)
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/LLM/openai.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/LLM/gemini.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/LLM/anthropic.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/LLM/grok.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/LLM/openrouter.php';
        
        // 2.8. Filter-based provider registration system
        require_once AI_HTTP_CLIENT_PATH . '/src/Filters.php';
        
        // 2.9. Action-based write operations system
        require_once AI_HTTP_CLIENT_PATH . '/src/Actions.php';
        
        // 3. Pure filter architecture - no client class needed
        
        // 4.5. WordPress management components
        require_once AI_HTTP_CLIENT_PATH . '/src/Utils/OptionsManager.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Utils/AjaxHandler.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Utils/LLM/PromptManager.php';
        
        // 4.6. UI Components system (shared components moved to top-level)
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/ComponentInterface.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/ComponentRegistry.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/Core/ProviderSelector.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/Core/ApiKeyInput.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/LLM/Core/ModelSelector.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/LLM/Extended/TemperatureSlider.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/LLM/Extended/SystemPromptField.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/LLM/ProviderManagerComponent.php';
    }
    
    function ai_http_client_wordpress_init() {
        // WordPress-specific initialization
        if (function_exists('do_action')) {
            do_action('ai_http_client_loaded');
        }
        
        // AJAX actions are now auto-registered via the unified filter system
        
        // Provider configuration filters are now auto-registered via the unified filter system
    }
    

}

// Initialize the library
ai_http_client_init();