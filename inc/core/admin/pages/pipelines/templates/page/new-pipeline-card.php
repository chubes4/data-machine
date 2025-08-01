<?php
/**
 * New Pipeline Card Template
 *
 * Pure rendering template for new pipeline creation form.
 * Used both on empty pages and when "Add New Pipeline" button is clicked.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

?>
<div class="dm-pipeline-card dm-new-pipeline" data-pipeline-id="new">
    <!-- Pipeline Header with Editable Title -->
    <div class="dm-pipeline-header">
        <div class="dm-pipeline-title-section">
            <?php echo '<input type="text" class="dm-pipeline-title-input" placeholder="' . esc_attr__('Enter pipeline name...', 'data-machine') . '" />'; ?>
            <div class="dm-pipeline-meta">
                <span class="dm-step-count"><?php esc_html_e('0 steps', 'data-machine'); ?></span>
                <span class="dm-flow-count"><?php esc_html_e('0 flows', 'data-machine'); ?></span>
            </div>
        </div>
        <div class="dm-pipeline-actions">
            <button type="button" class="button button-primary dm-save-pipeline-btn" disabled>
                <?php esc_html_e('Save Pipeline', 'data-machine'); ?>
            </button>
        </div>
    </div>
    
    <!-- Pipeline Steps Section (Template Level) -->
    <div class="dm-pipeline-steps-section">
        <div class="dm-section-header">
            <h4><?php esc_html_e('Pipeline Steps', 'data-machine'); ?></h4>
            <p class="dm-section-description"><?php esc_html_e('Define the step sequence for this pipeline', 'data-machine'); ?></p>
        </div>
        <div class="dm-pipeline-steps">
            <div class="dm-step-card dm-placeholder-step">
                <div class="dm-placeholder-step-content">
                    <button type="button" class="button button-primary dm-modal-trigger"
                            data-template="step-selection"
                            data-context='{"context":"pipeline_builder"}'>
                        <?php esc_html_e('Add Step', 'data-machine'); ?>
                    </button>
                    <p class="dm-placeholder-description"><?php esc_html_e('Choose a step type to begin building your pipeline', 'data-machine'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Associated Flows -->
    <div class="dm-pipeline-flows">
        <div class="dm-flows-header">
            <h4><?php esc_html_e('Flow Instances', 'data-machine'); ?></h4>
            <p class="dm-section-description"><?php esc_html_e('Each flow is a configured instance of the pipeline above', 'data-machine'); ?></p>
        </div>
        <div class="dm-flows-list">
            <div class="dm-flow-instance-card dm-placeholder-flow" data-flow-id="new">
                <div class="dm-flow-header">
                    <div class="dm-flow-title-section">
                        <?php echo '<input type="text" class="dm-flow-title-input" placeholder="' . esc_attr__('Enter flow name...', 'data-machine') . '" />'; ?>
                        <div class="dm-flow-status">
                            <span class="dm-schedule-status dm-status-inactive">
                                <?php esc_html_e('Inactive', 'data-machine'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="dm-flow-actions">
                        <!-- Flow saving managed at pipeline level -->
                    </div>
                </div>
                
                <!-- Flow Steps (mirrors all pipeline steps) -->
                <div class="dm-flow-steps-section">
                    <div class="dm-flow-steps">
                        <div class="dm-step-card dm-flow-step dm-placeholder-flow-step">
                            <div class="dm-placeholder-step-content">
                                <p class="dm-placeholder-description"><?php esc_html_e('This will mirror the pipeline steps with handler configuration', 'data-machine'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="dm-flow-meta">
                    <small class="dm-placeholder-text"><?php esc_html_e('Add steps to the pipeline above to configure handlers for this flow', 'data-machine'); ?></small>
                </div>
            </div>
        </div>
    </div>
</div>