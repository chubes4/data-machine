<?php
/**
 * Modal System Component Filter Registration
 *
 * "Plugins Within Plugins" Architecture Implementation
 *
 * This file provides CSS infrastructure for the modal system.
 * Individual components now pre-render their own modals in page templates
 * instead of loading content via AJAX.
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
 * Provides only CSS infrastructure.
 * Components pre-render their own modal content in page templates.
 *
 * @since 0.1.0
 */
function datamachine_register_modal_system_filters() {
    // Note: Modal CSS is included directly in page-specific asset configurations
    // Modals are now pre-rendered in page templates (Settings, Jobs)
    // No AJAX loading, no jQuery dependencies
}

// Auto-register when file loads - achieving complete self-containment
datamachine_register_modal_system_filters();
