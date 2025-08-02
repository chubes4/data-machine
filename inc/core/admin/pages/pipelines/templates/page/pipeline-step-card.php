<?php
/**
 * Pipeline Step Card Template
 *
 * Pure rendering template for pipeline step cards (template level).
 * Used in both AJAX responses and initial page loads.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Extract variables for template use
$step_type = $step['step_type'] ?? 'unknown';
$step_config = $step['step_config'] ?? [];
$label = $step_config['label'] ?? ucfirst(str_replace('_', ' ', $step_type));

// Step configuration discovery (parallel to handler discovery pattern)
$step_config_info = apply_filters('dm_get_step_config', null, $step_type, ['context' => 'pipeline']);
$has_step_config = !empty($step_config_info);

?>
<div class="dm-step-card dm-pipeline-step" data-step-type="<?php echo esc_attr($step_type); ?>">
    <div class="dm-step-header">
        <div class="dm-step-title"><?php echo esc_html($label); ?></div>
        <div class="dm-step-actions">
            <button type="button" class="button button-small button-link-delete dm-modal-trigger" 
                    data-template="delete-step"
                    data-context='{"step_type":"<?php echo esc_attr($step_type); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
                <?php esc_html_e('Delete', 'data-machine'); ?>
            </button>
            <?php if ($has_step_config): ?>
                <button type="button" class="button button-small dm-modal-trigger" 
                        data-template="configure-step"
                        data-context='{"step_type":"<?php echo esc_attr($step_type); ?>","modal_type":"<?php echo esc_attr($step_config_info['modal_type'] ?? ''); ?>","config_type":"<?php echo esc_attr($step_config_info['config_type'] ?? ''); ?>"}'>
                    <?php echo esc_html($step_config_info['button_text'] ?? __('Configure', 'data-machine')); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="dm-step-body">
        <div class="dm-step-type-badge dm-step-<?php echo esc_attr($step_type); ?>">
            <?php echo esc_html(ucfirst($step_type)); ?>
        </div>
    </div>
</div>