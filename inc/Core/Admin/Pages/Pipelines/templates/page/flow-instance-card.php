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

// Footer calculations moved to footer template

$pipeline_steps = $pipeline_steps ?? [];

$flow = $flow ?? [];

$flow_config = apply_filters('dm_get_flow_config', [], $flow_id);

?>
<div class="dm-flow-instance-card" data-flow-id="<?php echo esc_attr($flow_id); ?>">
    <div class="dm-flow-header">
        <div class="dm-flow-reorder-controls">
            <span class="dm-reorder-arrow-up" 
                  data-flow-id="<?php echo esc_attr($flow_id); ?>"
                  data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>"
                  title="<?php esc_attr_e('Move up', 'data-machine'); ?>"
                  aria-label="<?php echo esc_attr(sprintf('Move %s up', $flow_name)); ?>">
                <span class="dashicons dashicons-arrow-up-alt2"></span>
            </span>
            <span class="dm-reorder-arrow-down"
                  data-flow-id="<?php echo esc_attr($flow_id); ?>"  
                  data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>"
                  title="<?php esc_attr_e('Move down', 'data-machine'); ?>"
                  aria-label="<?php echo esc_attr(sprintf('Move %s down', $flow_name)); ?>">
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </span>
        </div>
        <div class="dm-flow-title-section">
            <input type="text" class="dm-flow-title-input" 
                   value="<?php echo esc_attr($flow_name); ?>" 
                   placeholder="<?php esc_attr_e('Enter flow name...', 'data-machine'); ?>" />
            <button type="button" class="button button-primary dm-run-now-btn" 
                    data-flow-id="<?php echo esc_attr($flow_id); ?>"
                    aria-label="<?php echo esc_attr(sprintf('Run: %s', $flow_name)); ?>">
                <?php echo esc_html__('Run', 'data-machine'); ?>
            </button>
        </div>
        <div class="dm-flow-actions">
            <button type="button" class="button button-small dm-modal-open"
                    data-template="flow-schedule"
                    data-context='<?php echo esc_attr(wp_json_encode(['flow_id' => $flow_id])); ?>'
                    aria-label="<?php echo esc_attr(sprintf('Schedule: %s', $flow_name)); ?>">
                <?php echo esc_html__('Schedule', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-small dm-duplicate-flow-btn"
                    data-flow-id="<?php echo esc_attr($flow_id); ?>"
                    aria-label="<?php echo esc_attr(sprintf('Duplicate: %s', $flow_name)); ?>"
                    title="<?php esc_attr_e('Duplicate this flow with all its configurations', 'data-machine'); ?>">
                <?php echo esc_html__('Duplicate', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-small button-delete dm-modal-open"
                    data-template="confirm-delete"
                    data-context='<?php echo esc_attr(wp_json_encode(['delete_type' => 'flow', 'flow_id' => $flow_id, 'flow_name' => $flow_name, 'pipeline_id' => $pipeline_id])); ?>'
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
                        echo wp_kses(apply_filters('dm_render_template', '', 'page/flow-step-card', [
                            'step' => $step,
                            'flow_config' => $flow_config,
                            'flow_id' => $flow_id,
                            'pipeline_id' => $pipeline_id,
                            'is_first_step' => ($flow_step_count === 0)
                        ]), dm_allowed_html());
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
    
    <?php
    // Include footer template with flow data
    include __DIR__ . '/flow-instance-footer.php';
    ?>
</div>