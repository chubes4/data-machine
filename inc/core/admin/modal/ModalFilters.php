<?php
/**
 * Modal System Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as the Modal System's pure infrastructure - it provides ZERO
 * hardcoded component knowledge. Individual components register their own modal
 * capabilities in their own *Filters.php files following true modular architecture.
 * 
 * The modal system is a Universal HTML Popup Component that serves any component's
 * self-generated content via the dm_get_modals filter.
 * 
 * @package DataMachine
 * @subpackage Core\Admin\Modal
 * @since 0.1.0
 */

namespace DataMachine\Core\Admin\Modal;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Modal System infrastructure filters
 * 
 * Pure infrastructure implementation with ZERO component knowledge.
 * Components register their own modal capabilities in their own *Filters.php files.
 * 
 * This achieves true "plugins within plugins" architecture where the modal system
 * provides only the infrastructure and components provide their own modal content.
 * 
 * @since 0.1.0
 */
function dm_register_modal_system_filters() {
    
    // Note: Modal assets are now included directly in page-specific asset configurations
    // This eliminates the unused dm_get_page_assets filter and simplifies the asset loading system
    
    // Include modal template dynamically when universal modal system is loaded
    add_action('admin_footer', function() {
        // Check if universal modal JavaScript is enqueued
        if (wp_script_is('dm-core-modal', 'enqueued')) {
            // Include template file (defines function) and call render function
            require_once __DIR__ . '/ModalTemplate.php';
            dm_render_modal_template();
        }
    });
    
    // Instantiate universal modal AJAX handler
    new ModalAjax();
    
    // Confirm-delete modal now uses clean two-layer architecture via ModalAjax.php
    // Architectural violation removed - no competing modal handlers
    
    // Pure infrastructure - NO component-specific logic
    // Individual components register their own modal content generators
    // in their own *Filters.php files using the dm_get_modals collection filter
    
    // Components register modals using the collection-based pattern:
    // See PipelinesFilters.php and WordPressFilters.php for examples
}

// Auto-register when file loads - achieving complete self-containment
dm_register_modal_system_filters();