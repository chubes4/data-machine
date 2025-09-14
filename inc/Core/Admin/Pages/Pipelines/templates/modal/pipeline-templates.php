<?php
/**
 * Pipeline Templates Modal Template
 *
 * Template selection interface for guided pipeline creation.
 * Shows 4 core templates plus custom pipeline option.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

$templates = apply_filters('dm_pipeline_templates', []);
?>
<div class="dm-template-selection-container">
    <div class="dm-template-selection-header">
        <h3><?php esc_html_e('Choose a Pipeline Template', 'data-machine'); ?></h3>
        <p><?php esc_html_e('Select a pre-configured workflow or create a custom pipeline from scratch.', 'data-machine'); ?></p>
    </div>

    <div class="dm-template-cards">
        <?php foreach ($templates as $template_id => $template): ?>
            <div class="dm-template-card dm-modal-close"
                 data-template="select-pipeline-template"
                 data-context='<?php echo esc_attr(wp_json_encode(['template_id' => $template_id])); ?>'
                 role="button"
                 tabindex="0">
                <div class="dm-template-card-header">
                    <h4 class="dm-template-card-title"><?php echo esc_html($template['name']); ?></h4>
                </div>
                <div class="dm-template-card-body">
                    <p class="dm-template-card-description"><?php echo esc_html($template['description']); ?></p>
                    <div class="dm-template-steps-preview">
                        <?php if (!empty($template['steps'])): ?>
                            <div class="dm-steps-flow">
                                <?php foreach ($template['steps'] as $index => $step): ?>
                                    <span class="dm-step-badge"><?php echo esc_html(ucfirst($step['type'])); ?></span>
                                    <?php if ($index < count($template['steps']) - 1): ?>
                                        <span class="dm-step-arrow">→</span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Custom Pipeline Option -->
        <div class="dm-template-card dm-custom-pipeline-card dm-modal-close"
             data-template="create-custom-pipeline"
             role="button"
             tabindex="0">
            <div class="dm-template-card-header">
                <h4 class="dm-template-card-title"><?php esc_html_e('Custom Pipeline', 'data-machine'); ?></h4>
            </div>
            <div class="dm-template-card-body">
                <p class="dm-template-card-description"><?php esc_html_e('Start with an empty pipeline and add your own steps manually.', 'data-machine'); ?></p>
                <div class="dm-template-steps-preview">
                    <div class="dm-steps-flow">
                        <span class="dm-step-badge dm-step-placeholder"><?php esc_html_e('Your Steps', 'data-machine'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>