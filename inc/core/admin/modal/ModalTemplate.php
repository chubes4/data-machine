<?php
/**
 * Universal Modal Template
 * 
 * Provides the base HTML structure for the universal modal system used
 * throughout the Data Machine admin interface. This template renders the
 * core modal framework that all modal content is inserted into.
 * 
 * Modal content is dynamically loaded via AJAX through the dm_get_modal
 * filter system, maintaining the plugin's filter-based architecture.
 * 
 * @package DataMachine
 * @subpackage Core\Admin\Modal
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

/**
 * Render the universal modal template
 * Called only when modal is actually needed in admin_footer
 */
function dm_render_modal_template() {
    ?>
    <div id="dm-modal" class="dm-modal" role="dialog" aria-modal="true" aria-hidden="true" tabindex="-1">
        <div class="dm-modal-overlay"></div>
        <div class="dm-modal-container">
            <div class="dm-modal-header">
                <h2 class="dm-modal-title"></h2>
                <button type="button" class="dm-modal-close" aria-label="<?php esc_attr_e('Close', 'data-machine'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="dm-modal-body"></div>
        </div>
    </div>
    <?php
}