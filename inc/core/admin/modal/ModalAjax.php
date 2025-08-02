<?php
/**
 * Universal Modal AJAX Handler
 *
 * Handles all modal content requests through a single, universal AJAX endpoint.
 * Routes to the dm_get_modal filter system for component-specific content generation.
 *
 * This enables the universal modal architecture where any component can register
 * modal content via the dm_get_modal filter without needing custom AJAX handlers.
 *
 * @package DataMachine\Core\Admin\Modal
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Modal;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Universal Modal AJAX Handler Class
 *
 * Provides a single AJAX endpoint for all modal content requests across
 * the entire Data Machine admin interface. Components register modal content
 * via the dm_get_modal filter system.
 *
 * @since 1.0.0
 */
class ModalAjax
{
    /**
     * Constructor - Register AJAX actions
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        add_action('wp_ajax_dm_get_modal_content', [$this, 'handle_get_modal_content']);
    }

    /**
     * Handle modal content AJAX requests
     *
     * Routes to the dm_get_modal filter system for component-specific content.
     * Maintains WordPress security standards with nonce verification and
     * capability checks.
     *
     * @since 1.0.0
     */
    public function handle_get_modal_content()
    {
        // WordPress security verification
        if (!check_ajax_referer('dm_get_modal_content', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security verification failed', 'data-machine')
            ]);
        }

        // Capability check
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions', 'data-machine')
            ]);
        }

        // Extract and sanitize template parameter
        $template = sanitize_text_field(wp_unslash($_POST['template'] ?? ''));

        if (empty($template)) {
            wp_send_json_error([
                'message' => __('Template parameter is required', 'data-machine')
            ]);
        }

        // Route to dm_get_modal filter system
        // Components access context via $_POST['context'] during filter execution
        $content = apply_filters('dm_get_modal', null, $template);

        if ($content) {
            wp_send_json_success([
                'content' => $content,
                'template' => $template
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: modal template name */
                    __('Modal content not found for template: %s', 'data-machine'),
                    $template
                )
            ]);
        }
    }
}