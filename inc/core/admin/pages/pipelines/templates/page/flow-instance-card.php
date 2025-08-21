<?php
/**
 * Flow Instance Card Template
 *
 * Pure rendering template for flow instance cards.
 * Used in both AJAX responses and initial page loads.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

$flow_id = $flow['flow_id'];
$flow_name = $flow['flow_name'];
$pipeline_id = $flow['pipeline_id'];

$scheduling_config = $flow['scheduling_config'];
$schedule_interval = $scheduling_config['interval'] ?? 'manual';

$last_run = $scheduling_config['last_run_at'] ?? null;
if (!$last_run) {
    $all_databases = apply_filters('dm_db', []);
    $jobs_db = $all_databases['jobs'] ?? null;
    if ($jobs_db) {
        $jobs = $jobs_db->get_jobs_for_flow($flow_id);
        $last_run = !empty($jobs) ? ($jobs[0]['completed_at'] ?? $jobs[0]['created_at']) : null;
    }
}

$next_run = null;
if (function_exists('as_next_scheduled_action')) {
    $next_action = as_next_scheduled_action("dm_execute_flow_{$flow_id}", ['flow_id' => $flow_id], 'data-machine');
    if ($next_action) {
        $next_run = date('Y-m-d H:i:s', $next_action);
    }
}

$pipeline_steps = $pipeline_steps ?? [];

$flow = $flow ?? [];

$flow_config = apply_filters('dm_get_flow_config', [], $flow_id);

?>
<div class="dm-flow-instance-card" data-flow-id="<?php echo esc_attr($flow_id); ?>">
    <div class="dm-flow-header">
        <div class="dm-flow-title-section">
            <input type="text" class="dm-flow-title-input" 
                   value="<?php echo esc_attr($flow_name); ?>" 
                   placeholder="<?php esc_attr_e('Enter flow name...', 'data-machine'); ?>" />
        </div>
        <div class="dm-flow-actions">
            <button type="button" class="button button-small dm-modal-open" 
                    data-template="flow-schedule"
                    data-context='{"flow_id":"<?php echo esc_attr($flow_id); ?>"}'
                    aria-label="<?php echo esc_attr(sprintf('Schedule: %s', $flow_name)); ?>">
                <?php echo esc_html__('Schedule', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-small button-primary dm-run-now-btn" 
                    data-flow-id="<?php echo esc_attr($flow_id); ?>"
                    aria-label="<?php echo esc_attr(sprintf('Run Now: %s', $flow_name)); ?>">
                <?php echo esc_html__('Run Now', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-small button-delete dm-modal-open" 
                    data-template="confirm-delete"
                    data-context='{"delete_type":"flow","flow_id":"<?php echo esc_attr($flow_id); ?>","flow_name":"<?php echo esc_attr($flow_name); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'
                    aria-label="<?php echo esc_attr(sprintf('Delete: %s', $flow_name)); ?>">
                <?php echo esc_html__('Delete', 'data-machine'); ?>
            </button>
        </div>
    </div>
    
    <div class="dm-flow-steps-section">
        <div class="dm-flow-steps">
            <?php if (!empty($pipeline_steps)): ?>
                <?php 
                $flow_step_count = 0; // Track actual flow steps rendered (non-empty steps only)
                foreach ($pipeline_steps as $index => $step): ?>
                    <?php 
                        if (!($step['is_empty'] ?? false)) {
                        echo apply_filters('dm_render_template', '', 'page/flow-step-card', [
                            'step' => $step,
                            'flow_config' => $flow_config,
                            'flow_id' => $flow_id,
                            'pipeline_id' => $pipeline_id,
                            'is_first_step' => ($flow_step_count === 0)
                        ]);
                        $flow_step_count++;
                    }
                    ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="dm-no-steps">
                    <p><?php esc_html_e('No steps configured in this pipeline.', 'data-machine'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="dm-flow-meta">
        <small>
            <?php echo esc_html(sprintf(__('Last: %s | Next: %s', 'data-machine'), 
                $last_run ? date('M j, Y g:i a', strtotime($last_run)) : __('Never', 'data-machine'),
                $next_run ? date('M j, Y g:i a', strtotime($next_run)) : __('Manual', 'data-machine')
            )); ?>
        </small>
    </div>
</div>