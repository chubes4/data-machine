<?php
/**
 * Universal Modal AJAX Handler
 * 
 * Pure universal AJAX handler that uses zero hardcoding and 100% filter-based
 * parameter matching for modal content generation. This handler works with any
 * component that registers modal content via the dm_get_modal_content filter.
 * 
 * Design Principles:
 * - Template-based interface (not component_id based)
 * - Pure filter system with zero component knowledge
 * - Universal parameter passing via JSON context
 * - WordPress standards compliance
 * 
 * @package DataMachine\Core\Admin\Modal
 * @since 0.1.0
 */

namespace DataMachine\Core\Admin\Modal;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Universal Modal AJAX Handler
 * 
 * Processes all modal content requests through a pure filter-based system.
 * Components register their modal content generators via the dm_get_modal_content filter.
 * 
 * @since 0.1.0
 */
class ModalAjax {
    
    /**
     * Constructor - registers AJAX hooks
     */
    public function __construct() {
        add_action('wp_ajax_dm_get_modal_content', [$this, 'handle_ajax_request']);
    }
    
    /**
     * Handle universal modal content AJAX requests
     * 
     * Interface:
     * - template: Template identifier (e.g., "delete-step", "handler-selection")
     * - context: JSON string with component-specific parameters
     * - nonce: Security verification token
     * 
     * Uses pure filter-based content generation via dm_get_modal_content filter.
     * Components register their own modal content providers.
     * 
     * @since 0.1.0
     */
    public function handle_ajax_request() {
        // Security verification
        if (!check_ajax_referer('dm_get_modal_content', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security verification failed', 'data-machine')
            ]);
        }
        
        // Capability verification
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions', 'data-machine')
            ]);
        }
        
        // Get and sanitize template parameter
        $template = sanitize_text_field(wp_unslash($_POST['template'] ?? ''));
        
        if (empty($template)) {
            wp_send_json_error([
                'message' => __('Template parameter is required', 'data-machine')
            ]);
        }
        
        // Get and parse context parameter
        $context_raw = wp_unslash($_POST['context'] ?? '{}');
        $context = json_decode($context_raw, true);
        
        if (!is_array($context)) {
            $context = []; // Fallback to empty array if JSON parsing fails
        }
        
        // Filter-Based Content Generation
        // 
        // This represents a paradigm shift in WordPress modal architecture:
        // - Zero hardcoded modal types - unlimited extensibility
        // - Pure filter discovery pattern matches all Data Machine services
        // - Components register via consistent 2-parameter filter pattern
        // - Context access via $_POST maintains WordPress AJAX standards
        //
        // Registration Example:
        // add_filter('dm_get_modal_content', function($content, $template) {
        //     if ($template === 'my-modal') {
        //         $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
        //         return $this->render_template('modal/my-modal', $context);
        //     }
        //     return $content;
        // }, 10, 2);
        $modal_content = apply_filters('dm_get_modal_content', null, $template);
        
        if ($modal_content === null) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: template name */
                    __('Modal content not found for template: %s', 'data-machine'),
                    esc_html($template)
                )
            ]);
        }
        
        // Extract title from context or generate from template
        $title = $context['title'] ?? $this->generate_default_title($template);
        
        // Send successful response
        wp_send_json_success([
            'content' => $modal_content,
            'title' => $title,
            'template' => $template
        ]);
    }
    
    /**
     * Generate default modal title from template name
     * 
     * Converts template names like "delete-step" or "handler-selection" 
     * into user-friendly titles like "Delete Step" or "Handler Selection".
     * 
     * @param string $template Template identifier
     * @return string Generated title
     * @since 0.1.0
     */
    private function generate_default_title($template) {
        // Convert template to title: "delete-step" -> "Delete Step"
        $title = str_replace(['-', '_'], ' ', $template);
        $title = ucwords($title);
        
        return $title;
    }
}