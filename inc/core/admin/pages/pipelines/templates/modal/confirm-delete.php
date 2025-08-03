<?php
/**
 * Confirm Delete Modal Template
 *
 * Universal modal content for confirming deletion of pipelines or steps.
 * Shows cascade warnings and affected flows before deletion.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates\Modal
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Extract variables for template use - supports both pipeline and step deletion
$delete_type = $delete_type ?? 'step'; // 'pipeline' or 'step'
$step_type = $step_type ?? 'unknown';
$step_position = $step_position ?? 'unknown';
$step_label = $step_label ?? ucfirst(str_replace('_', ' ', $step_type));
$pipeline_name = $pipeline_name ?? __('Unknown Pipeline', 'data-machine');
$affected_flows = $affected_flows ?? [];
$affected_jobs = $affected_jobs ?? [];
$pipeline_id = $pipeline_id ?? null;

?>
<div class="dm-delete-warning-modal">
    <div class="dm-warning-header">
        <div class="dm-warning-icon">
            <span class="dashicons dashicons-warning"></span>
        </div>
        <div class="dm-warning-title">
            <?php if ($delete_type === 'pipeline'): ?>
                <h3><?php echo esc_html(sprintf(__('Delete Pipeline "%s"?', 'data-machine'), $pipeline_name)); ?></h3>
            <?php else: ?>
                <h3><?php echo esc_html(sprintf(__('Delete "%s" Step (Position %s)?', 'data-machine'), $step_label, $step_position)); ?></h3>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="dm-warning-content">
        <p class="dm-warning-description">
            <?php if ($delete_type === 'pipeline'): ?>
                <?php esc_html_e('This action will permanently delete the entire pipeline template and all associated flows.', 'data-machine'); ?>
            <?php else: ?>
                <?php esc_html_e('This action will permanently remove this step from the pipeline template.', 'data-machine'); ?>
            <?php endif; ?>
        </p>
        
        <?php if (!empty($affected_flows)): ?>
            <div class="dm-affected-flows">
                <?php if ($delete_type === 'pipeline'): ?>
                    <h4><?php esc_html_e('The following flows will be permanently deleted:', 'data-machine'); ?></h4>
                <?php else: ?>
                    <h4><?php esc_html_e('This will also affect the following flows:', 'data-machine'); ?></h4>
                <?php endif; ?>
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
                    <?php if ($delete_type === 'pipeline'): ?>
                        <strong><?php esc_html_e('All flows, their configurations, and associated job history will be permanently deleted.', 'data-machine'); ?></strong>
                    <?php else: ?>
                        <strong><?php esc_html_e('All step instances and their configurations will be removed from these flows.', 'data-machine'); ?></strong>
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="dm-no-flows">
                <?php if ($delete_type === 'pipeline'): ?>
                    <p class="dm-info"><?php esc_html_e('No flows are associated with this pipeline.', 'data-machine'); ?></p>
                <?php else: ?>
                    <p class="dm-info"><?php esc_html_e('No flows are currently using this step.', 'data-machine'); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($delete_type === 'pipeline' && !empty($affected_jobs)): ?>
            <div class="dm-affected-jobs">
                <h4><?php esc_html_e('Associated job history:', 'data-machine'); ?></h4>
                <p class="dm-job-count">
                    <?php echo esc_html(sprintf(_n(
                        '%d job record will be deleted',
                        '%d job records will be deleted',
                        count($affected_jobs),
                        'data-machine'
                    ), count($affected_jobs))); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="dm-warning-notice">
            <p><strong><?php esc_html_e('This action cannot be undone.', 'data-machine'); ?></strong></p>
        </div>
    </div>
    
    <div class="dm-modal-actions">
        <button type="button" class="button button-primary button-large dm-confirm-delete-ajax" 
                data-delete-type="<?php echo esc_attr($delete_type); ?>"
                data-step-position="<?php echo esc_attr($step_position); ?>"
                data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">
            <?php if ($delete_type === 'pipeline'): ?>
                <?php esc_html_e('Delete Pipeline', 'data-machine'); ?>
            <?php else: ?>
                <?php esc_html_e('Delete Step', 'data-machine'); ?>
            <?php endif; ?>
        </button>
        <button type="button" class="button button-secondary button-large dm-modal-close">
            <?php esc_html_e('Cancel', 'data-machine'); ?>
        </button>
    </div>
</div>