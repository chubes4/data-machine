<?php
/**
 * Delete Step Warning Modal Template
 *
 * Modal content for warning users about cascade deletion when removing pipeline steps.
 * Shows affected flows and confirms user intent before deletion.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates\Modal
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Extract variables for template use
$step_type = $step_type ?? 'unknown';
$step_label = $step_label ?? ucfirst(str_replace('_', ' ', $step_type));
$affected_flows = $affected_flows ?? [];
$pipeline_id = $pipeline_id ?? null;

?>
<div class="dm-delete-warning-modal">
    <div class="dm-warning-header">
        <div class="dm-warning-icon">
            <span class="dashicons dashicons-warning"></span>
        </div>
        <div class="dm-warning-title">
            <h3><?php echo esc_html(sprintf(__('Delete "%s" Step?', 'data-machine'), $step_label)); ?></h3>
        </div>
    </div>
    
    <div class="dm-warning-content">
        <p class="dm-warning-description">
            <?php esc_html_e('This action will permanently remove this step from the pipeline template.', 'data-machine'); ?>
        </p>
        
        <?php if (!empty($affected_flows)): ?>
            <div class="dm-affected-flows">
                <h4><?php esc_html_e('This will also affect the following flows:', 'data-machine'); ?></h4>
                <ul class="dm-flows-list">
                    <?php foreach ($affected_flows as $flow): ?>
                        <li class="dm-flow-item">
                            <span class="dm-flow-name"><?php echo esc_html($flow['name'] ?? __('Unnamed Flow', 'data-machine')); ?></span>
                            <span class="dm-flow-info">
                                (<?php echo esc_html(sprintf(__('Created %s', 'data-machine'), date('M j, Y', strtotime($flow['created_at'] ?? 'now')))); ?>)
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="dm-cascade-warning">
                    <strong><?php esc_html_e('All step instances and their configurations will be removed from these flows.', 'data-machine'); ?></strong>
                </p>
            </div>
        <?php else: ?>
            <div class="dm-no-flows">
                <p class="dm-info"><?php esc_html_e('No flows are currently using this step.', 'data-machine'); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="dm-warning-notice">
            <p><strong><?php esc_html_e('This action cannot be undone.', 'data-machine'); ?></strong></p>
        </div>
    </div>
    
    <div class="dm-modal-actions">
        <button type="button" class="button button-primary button-large dm-confirm-delete-ajax" 
                data-step-type="<?php echo esc_attr($step_type); ?>"
                data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">
            <?php esc_html_e('Delete Step', 'data-machine'); ?>
        </button>
        <button type="button" class="button button-secondary button-large dm-modal-close">
            <?php esc_html_e('Cancel', 'data-machine'); ?>
        </button>
    </div>
</div>