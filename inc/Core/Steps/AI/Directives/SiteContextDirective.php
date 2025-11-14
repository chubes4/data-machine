<?php
/**
 * Site Context Directive - Priority 50 (Lowest Priority)
 *
 * Injects WordPress site context information as the final directive in the
 * 5-tier AI directive system. Provides comprehensive site metadata including
 * posts, taxonomies, users, and configuration. Toggleable via settings.
 *
 * Priority Order in 5-Tier System:
 * 1. Priority 10 - Plugin Core Directive
 * 2. Priority 20 - Global System Prompt
 * 3. Priority 30 - Pipeline System Prompt
 * 4. Priority 40 - Tool Definitions and Workflow Context
 * 5. Priority 50 - WordPress Site Context (THIS CLASS)
 */

namespace DataMachine\Core\Steps\AI\Directives;

use DataMachine\Core\Steps\AI\SiteContext;

defined('ABSPATH') || exit;

class SiteContextDirective {
    
    /**
     * Inject WordPress site context into AI request.
     *
     * @param array $request AI request array with messages
     * @param string $provider_name AI provider name
     * @param callable $streaming_callback Streaming callback (unused)
     * @param array $tools Available tools (unused)
     * @param string|null $pipeline_step_id Pipeline step ID (unused)
     * @return array Modified request with site context added
     */
    public static function inject($request, $provider_name, $streaming_callback, $tools, $pipeline_step_id = null, array $context = []): array {
        if (!self::is_site_context_enabled()) {
            return $request;
        }

        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        $context_message = self::generate_site_context();
        
        if (empty($context_message)) {
            do_action('datamachine_log', 'warning', 'Site Context Directive: Empty context generated');
            return $request;
        }

        array_push($request['messages'], [
            'role' => 'system',
            'content' => $context_message
        ]);

        do_action('datamachine_log', 'debug', 'Site Context Directive: Injected site context', [
            'context_length' => strlen($context_message),
            'provider' => $provider_name,
            'total_messages' => count($request['messages'])
        ]);

        return $request;
    }
    
    /**
     * Check if site context injection is enabled in plugin settings.
     *
     * @return bool True if enabled, false otherwise
     */
    public static function is_site_context_enabled(): bool {
        $settings = datamachine_get_datamachine_settings();
        
        return $settings['site_context_enabled'] ?? true;
    }

    /**
     * Generate WordPress site context for AI models.
     *
     * @return string JSON-formatted site context data
     */
    public static function generate_site_context(): string {
        require_once __DIR__ . '/../SiteContext.php';
        
        $context_data = SiteContext::get_context();
        
        $context_message = "WORDPRESS SITE CONTEXT:\n\n";
        $context_message .= "The following structured data provides comprehensive information about this WordPress site:\n\n";
        $context_message .= json_encode($context_data, JSON_PRETTY_PRINT);
        
        return $context_message;
    }
}

/**
 * Allow plugins to override the site context directive class.
 * datamachine-multisite uses this to replace single-site context with multisite context.
 *
 * @param string $directive_class The directive class to use for site context
 * @return string The filtered directive class
 */
$site_context_directive = apply_filters('datamachine_site_context_directive', SiteContextDirective::class);

// Register the filtered directive (allows replacement by multisite plugin)
if ($site_context_directive) {
    add_filter('ai_request', [$site_context_directive, 'inject'], 50, 6);
}