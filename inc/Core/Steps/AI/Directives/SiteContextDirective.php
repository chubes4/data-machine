<?php
/**
 * Site Context Directive
 * 
 * Injects WordPress site context information for AI models.
 * Provides comprehensive site metadata including posts, taxonomies, users, and configuration.
 * 
 * Priority: 50 (executes LAST, appears LAST in AI message order)
 *
 * @package DataMachine\Core\Steps\AI\Directives
 */

namespace DataMachine\Core\Steps\AI\Directives;

use DataMachine\Core\Steps\AI\SiteContext;

defined('ABSPATH') || exit;

class SiteContextDirective {
    
    /**
     * Inject WordPress site context into AI request
     * 
     * @param array $request AI request array
     * @param string $provider_name Provider identifier
     * @param mixed $streaming_callback Streaming callback
     * @param array $tools Available tools array
     * @param string|null $pipeline_step_id Pipeline step ID
     * @return array Modified AI request
     */
    public static function inject($request, $provider_name, $streaming_callback, $tools, $pipeline_step_id = null): array {
        if (!self::is_site_context_enabled()) {
            return $request;
        }

        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        $context_message = self::generate_site_context();
        
        if (empty($context_message)) {
            do_action('dm_log', 'warning', 'Site Context Directive: Empty context generated');
            return $request;
        }

        array_push($request['messages'], [
            'role' => 'system',
            'content' => $context_message
        ]);

        do_action('dm_log', 'debug', 'Site Context Directive: Injected site context', [
            'context_length' => strlen($context_message),
            'provider' => $provider_name,
            'total_messages' => count($request['messages'])
        ]);

        return $request;
    }
    
    /**
     * Check if site context injection is enabled
     * 
     * @return bool Whether site context is enabled (default: true)
     */
    public static function is_site_context_enabled(): bool {
        $settings = dm_get_data_machine_settings();
        
        // Default enabled
        return $settings['site_context_enabled'] ?? true;
    }

    /**
     * Generate WordPress site context for AI models
     * 
     * @return string Formatted site context as structured JSON
     */
    public static function generate_site_context(): string {
        require_once __DIR__ . '/../SiteContext.php';
        
        $context_data = SiteContext::get_context();
        
        // Structured JSON with explanation
        $context_message = "WORDPRESS SITE CONTEXT:\n\n";
        $context_message .= "The following structured data provides comprehensive information about this WordPress site:\n\n";
        $context_message .= json_encode($context_data, JSON_PRETTY_PRINT);
        
        return $context_message;
    }
}

// Self-register with WordPress filter system (Priority 50 = executes last, appears last)
add_filter('ai_request', [SiteContextDirective::class, 'inject'], 50, 5);