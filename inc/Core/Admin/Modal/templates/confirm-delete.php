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

if (!defined('WPINC')) {
    die;
}

$delete_type = $data['delete_type'] ?? 'step';

if ($delete_type === 'pipeline') {
    $pipeline_id = $data['pipeline_id'] ?? 0;
    $pipeline_name = $data['pipeline_name'] ?? __('Unknown Pipeline', 'datamachine');
} elseif ($delete_type === 'flow') {
    $flow_id = $data['flow_id'] ?? 0;
    $flow_name = $data['flow_name'] ?? __('Unknown Flow', 'datamachine');
} else {
    $pipeline_step_id = $data['pipeline_step_id'] ?? '';
    $pipeline_id = $data['pipeline_id'] ?? 0;
    $step_type = $data['step_type'] ?? 'unknown';
    
    $step_label = $data['step_label'] ?? ucfirst(str_replace('_', ' ', $step_type));
}

$all_databases = apply_filters('datamachine_db', []);
$db_flows = $all_databases['flows'] ?? null;
$db_jobs = $all_databases['jobs'] ?? null;

$affected_flows = [];
$affected_jobs = [];

if ($delete_type === 'pipeline' && isset($pipeline_id)) {
    $affected_flows = apply_filters('datamachine_get_pipeline_flows', [], $pipeline_id);
    
    if ($db_jobs && method_exists($db_jobs, 'get_jobs_for_pipeline')) {
        $jobs_for_pipeline = $db_jobs->get_jobs_for_pipeline($pipeline_id);
        $affected_jobs = is_array($jobs_for_pipeline) ? $jobs_for_pipeline : [];
    }
} elseif ($delete_type === 'step' && isset($pipeline_id)) {
    // Step deletion affects all flows using this pipeline (same as pipeline impact)
    $affected_flows = apply_filters('datamachine_get_pipeline_flows', [], $pipeline_id);
}
// Flow deletion doesn't have sub-dependencies, so affected_flows stays empty

?>
<div class="datamachine-delete-warning-modal">
    <div class="datamachine-warning-header">
        <div class="datamachine-warning-icon">
            <span class="dashicons dashicons-warning"></span>
        </div>
        <div class="datamachine-warning-title">
            <?php if ($delete_type === 'pipeline'): ?>
                <?php /* translators: %s: Pipeline name */ ?>
                <h3><?php echo esc_html(sprintf(__('Delete Pipeline "%s"?', 'datamachine'), $pipeline_name)); ?></h3>
            <?php elseif ($delete_type === 'flow'): ?>
                <?php /* translators: %s: Flow name */ ?>
                <h3><?php echo esc_html(sprintf(__('Delete Flow "%s"?', 'datamachine'), $flow_name)); ?></h3>
            <?php else: ?>
                <?php /* translators: %s: Step type label */ ?>
                <h3><?php echo esc_html(sprintf(__('Delete "%s" Step?', 'datamachine'), $step_label)); ?></h3>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="datamachine-warning-content">
        <p class="datamachine-warning-description">
            <?php if ($delete_type === 'pipeline'): ?>
                <?php esc_html_e('This action will permanently delete the entire pipeline template and all associated flows.', 'datamachine'); ?>
            <?php elseif ($delete_type === 'flow'): ?>
                <?php esc_html_e('This action will permanently delete this flow instance and all its configuration settings.', 'datamachine'); ?>
            <?php else: ?>
                <?php esc_html_e('This action will permanently remove this step from the pipeline template.', 'datamachine'); ?>
            <?php endif; ?>
        </p>
        
        <?php if (!empty($affected_flows)): ?>
            <div class="datamachine-affected-flows">
                <?php if ($delete_type === 'pipeline'): ?>
                    <h4><?php esc_html_e('The following flows will be permanently deleted:', 'datamachine'); ?></h4>
                <?php elseif ($delete_type === 'flow'): ?>
                    <!-- Flows don't have sub-flows, so this section would be empty for flow deletion -->
                <?php else: ?>
                    <h4><?php esc_html_e('This will also affect the following flows:', 'datamachine'); ?></h4>
                <?php endif; ?>
                <ul class="datamachine-flows-list">
                    <?php foreach ($affected_flows as $flow): ?>
                        <li class="datamachine-flow-item">
                            <span class="datamachine-flow-name"><?php echo esc_html($flow['name'] ?? __('Unnamed Flow', 'datamachine')); ?></span>
                            <span class="datamachine-flow-info">
                                <?php /* translators: %s: Formatted creation date */ ?>
                                (<?php echo esc_html(sprintf(__('Created %s', 'datamachine'), wp_date('M j, Y', strtotime($flow['created_at'] ?? 'now')))); ?>)
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="datamachine-cascade-warning">
                    <?php if ($delete_type === 'pipeline'): ?>
                        <strong><?php esc_html_e('All flows and their configurations will be permanently deleted. Associated job records will be preserved as historical data.', 'datamachine'); ?></strong>
                    <?php elseif ($delete_type === 'flow'): ?>
                        <strong><?php esc_html_e('The flow configuration will be permanently deleted. Associated job records will be preserved as historical data.', 'datamachine'); ?></strong>
                    <?php else: ?>
                        <strong><?php esc_html_e('All step instances and their configurations will be removed from these flows.', 'datamachine'); ?></strong>
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="datamachine-no-flows">
                <?php if ($delete_type === 'pipeline'): ?>
                    <p class="datamachine-info"><?php esc_html_e('No flows are associated with this pipeline.', 'datamachine'); ?></p>
                <?php elseif ($delete_type === 'flow'): ?>
                    <!-- Flows don't have dependencies, so no message needed -->
                <?php else: ?>
                    <p class="datamachine-info"><?php esc_html_e('No flows are currently using this step.', 'datamachine'); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($delete_type === 'pipeline' && !empty($affected_jobs)): ?>
            <div class="datamachine-affected-jobs">
                <h4><?php esc_html_e('Associated job history (will be preserved):', 'datamachine'); ?></h4>
                <p class="datamachine-job-count">
                    <?php /* translators: %d: Number of job records */ ?>
                    <?php echo esc_html(sprintf(_n(
                        '%d job record will be preserved as historical data',
                        '%d job records will be preserved as historical data',
                        count($affected_jobs),
                        'datamachine'
                    ), count($affected_jobs))); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="datamachine-warning-notice">
            <p><strong><?php esc_html_e('This action cannot be undone.', 'datamachine'); ?></strong></p>
        </div>
    </div>
    
    <div class="datamachine-modal-actions">
        <button type="button" class="button button-primary button-large datamachine-modal-close" 
                data-template="delete-action"
                data-context='<?php echo esc_attr(wp_json_encode(['delete_type' => $delete_type, 'pipeline_step_id' => $pipeline_step_id ?? '', 'pipeline_id' => $pipeline_id, 'flow_id' => $flow_id ?? ''])); ?>'>
            <?php if ($delete_type === 'pipeline'): ?>
                <?php esc_html_e('Delete Pipeline', 'datamachine'); ?>
            <?php elseif ($delete_type === 'flow'): ?>
                <?php esc_html_e('Delete Flow', 'datamachine'); ?>
            <?php else: ?>
                <?php esc_html_e('Delete Step', 'datamachine'); ?>
            <?php endif; ?>
        </button>
        <button type="button" class="button button-secondary button-large datamachine-modal-close">
            <?php esc_html_e('Cancel', 'datamachine'); ?>
        </button>
    </div>
</div>