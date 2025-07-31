<?php
/**
 * Universal Modal System - Pure HTML Component
 *
 * Revolutionary modal system transformed into a pure HTML component that accepts
 * any content via filters. Eliminates hardcoded modal types and enables unlimited
 * extensibility through filter-based content registration.
 *
 * Components register their own modal content generators and handle their own
 * save logic within the injected content (forms, AJAX calls, etc.).
 *
 * @package DataMachine\Core\Admin\Modal
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Modal;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Pure HTML Modal Component Implementation.
 *
 * Handles only content display via filter-based architecture.
 * All content generation and save logic is handled by components themselves
 * via the dm_get_modal_content filter.
 */
class Modal
{
    /**
     * Constructor - Registers AJAX handler for modal system.
     */
    public function __construct()
    {
        add_action('wp_ajax_dm_get_modal_content', [$this, 'ajax_get_modal_content']);
    }

    /**
     * AJAX: Get modal content based on component ID.
     */
    public function ajax_get_modal_content()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_get_modal_content')) {
            wp_die(__('Security check failed.', 'data-machine'), 403);
        }

        $component_id = sanitize_text_field(wp_unslash($_POST['component_id'] ?? ''));

        if (empty($component_id)) {
            wp_send_json_error(__('Component ID required.', 'data-machine'));
        }

        // Pure component-driven modal content generation
        // Components register themselves and generate their own modal content
        $modal_content = apply_filters('dm_get_modal_content', null, $component_id);

        if ($modal_content) {
            wp_send_json_success($modal_content);
        } else {
            wp_send_json_error(__('No modal content available for this component.', 'data-machine'));
        }
    }

}

// Auto-instantiate for self-registration
new Modal();