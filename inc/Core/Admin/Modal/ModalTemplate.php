<?php
/**
 * Universal Modal Template
 * 
 * Provides the base HTML structure for the universal modal system used
 * throughout the Data Machine admin interface. This template renders the
 * core modal framework that all modal content is inserted into.
 * 
 * Modal content is dynamically loaded via AJAX through the datamachine_get_modal
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
function datamachine_render_modal_template() {
    ?>
    <div id="datamachine-modal" class="datamachine-modal" role="dialog" aria-modal="true" aria-hidden="true" tabindex="-1">
        <div class="datamachine-modal-overlay"></div>
        <div class="datamachine-modal-container">
            <div class="datamachine-modal-header">
                <h2 class="datamachine-modal-title"></h2>
                <button type="button" class="datamachine-modal-close" aria-label="<?php esc_attr_e('Close', 'data-machine'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="datamachine-modal-body"></div>
        </div>
    </div>
    <?php
}