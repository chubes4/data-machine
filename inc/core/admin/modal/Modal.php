<?php
/**
 * Universal Modal System - Pure HTML Component
 *
 * Modal system transformed into a pure HTML component that accepts
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
        // AJAX handler moved to ModalAjax.php for universal template-based interface
        // Template-based parameter matching provides complete flexibility
    }

}

// Auto-instantiation removed - Modal functionality moved to ModalAjax.php
// Universal modal system now handled by ModalFilters.php registration