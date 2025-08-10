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

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Extract flow data
$flow_id = is_object($flow) ? $flow->flow_id : $flow['flow_id'];
$flow_name = is_object($flow) ? $flow->flow_name : $flow['flow_name'];
$pipeline_id = is_object($flow) ? $flow->pipeline_id : $flow['pipeline_id'];

// Get scheduling info (already decoded by database service)
$scheduling_config = is_object($flow) ? $flow->scheduling_config : $flow['scheduling_config'];
$schedule_interval = $scheduling_config['interval'] ?? 'manual';

// Get last run time
$last_run = $scheduling_config['last_run_at'] ?? null;
if (!$last_run) {
    // Fallback: Get latest job for this flow
    $all_databases = apply_filters('dm_db', []);
    $jobs_db = $all_databases['jobs'] ?? null;
    if ($jobs_db) {
        $jobs = $jobs_db->get_jobs_for_flow($flow_id);
        $last_run = !empty($jobs) ? ($jobs[0]['completed_at'] ?? $jobs[0]['started_at']) : null;
    }
}

// Get next scheduled run directly from Action Scheduler
$next_run = null;
if (function_exists('as_next_scheduled_action')) {
    $next_action = as_next_scheduled_action("dm_execute_flow_{$flow_id}", ['flow_id' => $flow_id], 'data-machine');
    if ($next_action) {
        $next_run = date('Y-m-d H:i:s', $next_action);
    }
}

// Validate required data - no fallbacks
if (!isset($pipeline_steps)) {
    throw new \InvalidArgumentException('flow-instance-card template requires pipeline_steps parameter');
}
if (!is_array($pipeline_steps)) {
    throw new \InvalidArgumentException('flow-instance-card template pipeline_steps must be array');
}

// Extract flow config without fallbacks
if (!isset($flow)) {
    throw new \InvalidArgumentException('flow-instance-card template requires flow parameter');
}

if (is_object($flow)) {
    $flow_config = $flow->flow_config;
} else if (is_array($flow) && isset($flow['flow_config'])) {
    $flow_config = $flow['flow_config'];
} else {
    throw new \InvalidArgumentException('flow-instance-card template flow must have flow_config property/key');
}

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
                    data-context='{"flow_id":"<?php echo esc_attr($flow_id); ?>"}'>
                <?php esc_html_e('Manage Schedule', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-small button-primary dm-run-now-btn" 
                    data-flow-id="<?php echo esc_attr($flow_id); ?>">
                <?php esc_html_e('Run Now', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-small button-delete dm-modal-open" 
                    data-template="confirm-delete"
                    data-context='{"delete_type":"flow","flow_id":"<?php echo esc_attr($flow_id); ?>","flow_name":"<?php echo esc_attr($flow_name); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
                <?php esc_html_e('Delete', 'data-machine'); ?>
            </button>
        </div>
    </div>
    
    <div class="dm-flow-steps-section">
        <div class="dm-flow-steps">
            <?php if (!empty($pipeline_steps)): ?>
                <?php foreach ($pipeline_steps as $index => $step): ?>
                    <?php 
                    // Skip empty steps entirely - they don't belong in flow instances
                    if (!($step['is_empty'] ?? false)) {
                        echo apply_filters('dm_render_template', '', 'page/flow-step-card', [
                            'step' => $step,
                            'flow_config' => $flow_config,
                            'flow_id' => $flow_id,
                            'pipeline_id' => $pipeline_id,
                            'is_first_step' => ($index === 0)
                        ]);
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