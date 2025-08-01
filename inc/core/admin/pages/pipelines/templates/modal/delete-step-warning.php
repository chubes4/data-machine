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
        <button type="button" class="button button-primary button-large dm-confirm-delete" 
                data-step-type="<?php echo esc_attr($step_type); ?>"
                data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">
            <?php esc_html_e('Delete Step', 'data-machine'); ?>
        </button>
        <button type="button" class="button button-secondary button-large dm-modal-close">
            <?php esc_html_e('Cancel', 'data-machine'); ?>
        </button>
    </div>
</div>

<style>
.dm-delete-warning-modal {
    padding: 20px;
    max-width: 500px;
}

.dm-warning-header {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.dm-warning-icon {
    margin-right: 15px;
}

.dm-warning-icon .dashicons {
    color: #d63638;
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.dm-warning-title h3 {
    margin: 0;
    color: #d63638;
}

.dm-warning-content {
    margin-bottom: 25px;
}

.dm-warning-description {
    font-size: 14px;
    margin-bottom: 15px;
}

.dm-affected-flows {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    padding: 15px;
    margin: 15px 0;
}

.dm-affected-flows h4 {
    margin: 0 0 10px 0;
    color: #856404;
}

.dm-flows-list {
    margin: 10px 0;
    padding-left: 20px;
}

.dm-flow-item {
    margin-bottom: 5px;
}

.dm-flow-name {
    font-weight: 600;
}

.dm-flow-info {
    color: #666;
    font-size: 12px;
}

.dm-cascade-warning {
    margin-top: 15px;
    color: #856404;
    font-size: 14px;
}

.dm-no-flows {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    border-radius: 4px;
    padding: 15px;
    margin: 15px 0;
}

.dm-no-flows .dm-info {
    margin: 0;
    color: #0c5460;
}

.dm-warning-notice {
    background: #f8d7da;
    border: 1px solid #f1aeb5;
    border-radius: 4px;
    padding: 10px;
    margin-top: 15px;
}

.dm-warning-notice p {
    margin: 0;
    color: #721c24;
}

.dm-modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding-top: 15px;
    border-top: 1px solid #ddd;
}

.dm-modal-actions .button {
    min-width: 100px;
}
</style>