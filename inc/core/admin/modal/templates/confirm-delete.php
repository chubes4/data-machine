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

// Extract variables with strict validation - no fallbacks
if (!isset($delete_type)) {
    throw new \InvalidArgumentException('confirm-delete template requires delete_type parameter');
}

// Context-specific validation based on deletion type
if ($delete_type === 'pipeline') {
    if (!isset($pipeline_id) || empty($pipeline_id)) {
        throw new \InvalidArgumentException('Pipeline deletion requires pipeline_id parameter');
    }
    if (!isset($pipeline_name) || empty($pipeline_name)) {
        throw new \InvalidArgumentException('Pipeline deletion requires pipeline_name parameter');
    }
} elseif ($delete_type === 'flow') {
    if (!isset($flow_id) || empty($flow_id)) {
        throw new \InvalidArgumentException('Flow deletion requires flow_id parameter');
    }
    if (!isset($flow_name) || empty($flow_name)) {
        throw new \InvalidArgumentException('Flow deletion requires flow_name parameter');
    }
} else {
    // Step deletion - default case
    if (!isset($pipeline_step_id) || empty($pipeline_step_id)) {
        throw new \InvalidArgumentException('Step deletion requires pipeline_step_id parameter');
    }
    if (!isset($pipeline_id) || empty($pipeline_id)) {
        throw new \InvalidArgumentException('Step deletion requires pipeline_id parameter');
    }
    if (!isset($step_type)) {
        throw new \InvalidArgumentException('Step deletion requires step_type parameter');  
    }
    
    // Generate step label from type
    $step_label = $step_label ?? ucfirst(str_replace('_', ' ', $step_type));
}

// Template self-discovery - fetch affected flows and jobs data via filter-based discovery
$all_databases = apply_filters('dm_db', []);
$db_flows = $all_databases['flows'] ?? null;
$db_jobs = $all_databases['jobs'] ?? null;

$affected_flows = [];
$affected_jobs = [];

// Fetch affected data based on deletion type and available database services
if ($delete_type === 'pipeline' && isset($pipeline_id)) {
    // Pipeline deletion affects all flows in that pipeline
    $affected_flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
    
    // Get job count for this pipeline if jobs service available
    if ($db_jobs && method_exists($db_jobs, 'get_jobs_for_pipeline')) {
        $jobs_for_pipeline = $db_jobs->get_jobs_for_pipeline($pipeline_id);
        $affected_jobs = is_array($jobs_for_pipeline) ? $jobs_for_pipeline : [];
    }
} elseif ($delete_type === 'step' && isset($pipeline_id)) {
    // Step deletion affects all flows using this pipeline (same as pipeline impact)
    $affected_flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
}
// Flow deletion doesn't have sub-dependencies, so affected_flows stays empty

?>
<div class="dm-delete-warning-modal">
    <div class="dm-warning-header">
        <div class="dm-warning-icon">
            <span class="dashicons dashicons-warning"></span>
        </div>
        <div class="dm-warning-title">
            <?php if ($delete_type === 'pipeline'): ?>
                <h3><?php echo esc_html(sprintf(__('Delete Pipeline "%s"?', 'data-machine'), $pipeline_name)); ?></h3>
            <?php elseif ($delete_type === 'flow'): ?>
                <h3><?php echo esc_html(sprintf(__('Delete Flow "%s"?', 'data-machine'), $flow_name)); ?></h3>
            <?php else: ?>
                <h3><?php echo esc_html(sprintf(__('Delete "%s" Step?', 'data-machine'), $step_label)); ?></h3>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="dm-warning-content">
        <p class="dm-warning-description">
            <?php if ($delete_type === 'pipeline'): ?>
                <?php esc_html_e('This action will permanently delete the entire pipeline template and all associated flows.', 'data-machine'); ?>
            <?php elseif ($delete_type === 'flow'): ?>
                <?php esc_html_e('This action will permanently delete this flow instance and all its configuration settings.', 'data-machine'); ?>
            <?php else: ?>
                <?php esc_html_e('This action will permanently remove this step from the pipeline template.', 'data-machine'); ?>
            <?php endif; ?>
        </p>
        
        <?php if (!empty($affected_flows)): ?>
            <div class="dm-affected-flows">
                <?php if ($delete_type === 'pipeline'): ?>
                    <h4><?php esc_html_e('The following flows will be permanently deleted:', 'data-machine'); ?></h4>
                <?php elseif ($delete_type === 'flow'): ?>
                    <!-- Flows don't have sub-flows, so this section would be empty for flow deletion -->
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
                        <strong><?php esc_html_e('All flows and their configurations will be permanently deleted. Associated job records will be preserved as historical data.', 'data-machine'); ?></strong>
                    <?php elseif ($delete_type === 'flow'): ?>
                        <strong><?php esc_html_e('The flow configuration will be permanently deleted. Associated job records will be preserved as historical data.', 'data-machine'); ?></strong>
                    <?php else: ?>
                        <strong><?php esc_html_e('All step instances and their configurations will be removed from these flows.', 'data-machine'); ?></strong>
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="dm-no-flows">
                <?php if ($delete_type === 'pipeline'): ?>
                    <p class="dm-info"><?php esc_html_e('No flows are associated with this pipeline.', 'data-machine'); ?></p>
                <?php elseif ($delete_type === 'flow'): ?>
                    <!-- Flows don't have dependencies, so no message needed -->
                <?php else: ?>
                    <p class="dm-info"><?php esc_html_e('No flows are currently using this step.', 'data-machine'); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($delete_type === 'pipeline' && !empty($affected_jobs)): ?>
            <div class="dm-affected-jobs">
                <h4><?php esc_html_e('Associated job history (will be preserved):', 'data-machine'); ?></h4>
                <p class="dm-job-count">
                    <?php echo esc_html(sprintf(_n(
                        '%d job record will be preserved as historical data',
                        '%d job records will be preserved as historical data',
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
        <button type="button" class="button button-primary button-large dm-modal-close" 
                data-template="delete-action"
                data-context='{"delete_type":"<?php echo esc_attr($delete_type); ?>","pipeline_step_id":"<?php echo esc_attr($pipeline_step_id ?? ''); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>","flow_id":"<?php echo esc_attr($flow_id ?? ''); ?>"}'>
            <?php if ($delete_type === 'pipeline'): ?>
                <?php esc_html_e('Delete Pipeline', 'data-machine'); ?>
            <?php elseif ($delete_type === 'flow'): ?>
                <?php esc_html_e('Delete Flow', 'data-machine'); ?>
            <?php else: ?>
                <?php esc_html_e('Delete Step', 'data-machine'); ?>
            <?php endif; ?>
        </button>
        <button type="button" class="button button-secondary button-large dm-modal-close">
            <?php esc_html_e('Cancel', 'data-machine'); ?>
        </button>
    </div>
</div>