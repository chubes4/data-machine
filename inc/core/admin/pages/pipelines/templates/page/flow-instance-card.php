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
$created_at = is_object($flow) ? $flow->created_at : $flow['created_at'];

// Get scheduling info (already decoded by database service)
$scheduling_config = is_object($flow) ? $flow->scheduling_config : $flow['scheduling_config'];
$schedule_interval = $scheduling_config['interval'] ?? 'manual';

// Get pipeline steps for this flow (passed from parent template)
$pipeline_steps = $pipeline_steps ?? [];
$flow_config = is_object($flow) ? $flow->flow_config : ($flow['flow_config'] ?? []);

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
            <button type="button" class="button button-small button-primary dm-run-flow-btn" 
                    data-flow-id="<?php echo esc_attr($flow_id); ?>">
                <?php esc_html_e('Run Now', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-small button-delete dm-modal-open" 
                    data-template="confirm-delete"
                    data-context='{"delete_type":"flow","flow_id":"<?php echo esc_attr($flow_id); ?>","flow_name":"<?php echo esc_attr($flow_name); ?>"}'>
                <?php esc_html_e('Delete', 'data-machine'); ?>
            </button>
        </div>
    </div>
    
    <div class="dm-flow-steps-section">
        <div class="dm-flow-steps">
            <?php if (!empty($pipeline_steps)): ?>
                <?php foreach ($pipeline_steps as $index => $step): ?>
                    <?php 
                    // Render populated flow step using flow-step-card template
                    echo apply_filters('dm_render_template', '', 'page/flow-step-card', [
                        'step' => $step,
                        'flow_config' => $flow_config,
                        'flow_id' => $flow_id,
                        'is_first_step' => ($index === 0)
                    ]);
                    ?>
                <?php endforeach; ?>
                
                <!-- Always add empty flow step at the end (identical to pipeline pattern) -->
                <?php 
                echo apply_filters('dm_render_template', '', 'page/flow-step-card', [
                    'step' => [
                        'is_empty' => true,
                        'step_type' => '',
                        'position' => ''
                    ],
                    'flow_config' => [],
                    'flow_id' => $flow_id,
                    'is_first_step' => false // Empty step at end always gets arrow
                ]);
                ?>
            <?php else: ?>
                <!-- When no pipeline steps exist, show empty flow step (not placeholder) -->
                <?php 
                echo apply_filters('dm_render_template', '', 'page/flow-step-card', [
                    'step' => [
                        'is_empty' => true,
                        'step_type' => '',
                        'position' => ''
                    ],
                    'flow_config' => [],
                    'flow_id' => $flow_id,
                    'is_first_step' => true // No arrow when it's the only step
                ]);
                ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="dm-flow-meta">
        <small><?php echo esc_html(sprintf(__('Created %s', 'data-machine'), date('M j, Y', strtotime($created_at)))); ?></small>
    </div>
</div>