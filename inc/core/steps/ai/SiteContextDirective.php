<?php
/**
 * Site Context Directive - WordPress Site Context Integration
 * 
 * Integrates comprehensive WordPress site context into AI requests
 * to provide models with detailed understanding of the current site.
 *
 * @package DataMachine\Core\Steps\AI
 * @author Chris Huber <https://chubes.net>
 */

namespace DataMachine\Core\Steps\AI;

defined('ABSPATH') || exit;

/**
 * Site Context Directive Manager
 * 
 * Injects WordPress site context as system message in AI requests
 * when enabled via settings, positioned optimally in message priority.
 */
class SiteContextDirective {

    /**
     * Check if site context is enabled in settings
     * 
     * @return bool Whether site context should be included
     */
    public static function is_enabled(): bool {
        $settings = dm_get_data_machine_settings();
        
        // Skip if engine mode is active
        if ($settings['engine_mode']) {
            return false;
        }
        
        // Check site context setting (default enabled)
        return $settings['site_context_enabled'] ?? true;
    }

    /**
     * Generate site context system message
     * 
     * @return string Formatted site context for AI models
     */
    public static function generate_context_message(): string {
        require_once __DIR__ . '/SiteContext.php';
        
        $context_data = SiteContext::get_context();
        return SiteContext::format_for_ai($context_data);
    }

    /**
     * Inject site context into AI request messages
     * 
     * Adds site context as system message at optimal priority position
     * between tool directives and global system prompts.
     * 
     * @param array $request AI request array
     * @param string $provider_name Provider identifier
     * @param mixed $streaming_callback Streaming callback
     * @param array $tools Available tools array
     * @return array Modified AI request with site context
     */
    public static function inject_site_context($request, $provider_name, $streaming_callback, $tools): array {
        // Skip if not enabled
        if (!self::is_enabled()) {
            return $request;
        }

        // Validate request structure
        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        // Generate site context message
        $context_message = self::generate_context_message();
        
        if (empty($context_message)) {
            do_action('dm_log', 'warning', 'Site Context Directive: Empty context generated');
            return $request;
        }

        // Add site context as system message
        array_unshift($request['messages'], [
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
}

/**
 * Register Site Context Directive Filter Hook
 * 
 * Injects site context as system message in AI requests at priority 3,
 * positioned optimally between tool directives (priority 1) and 
 * global system prompts (priority 5).
 */
add_filter('ai_request', [SiteContextDirective::class, 'inject_site_context'], 3, 4);