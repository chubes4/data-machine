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
                    $step_type = $step['step_type'] ?? '';
                    $step_label = $step['label'] ?? ucfirst(str_replace('_', ' ', $step_type));
                    
                    // Dynamic handler discovery using parameter-based filter system
                    $available_handlers = apply_filters('dm_get_handlers', null, $step_type);
                    $has_handlers = !empty($available_handlers);
                    
                    // AI steps don't use traditional handlers - they use internal multi-provider client
                    $step_uses_handlers = ($step_type !== 'ai');
                    ?>
                    
                    <!-- Arrow before each step except the first -->
                    <?php if ($index > 0): ?>
                        <div class="dm-flow-step-arrow">
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="dm-step-card dm-flow-step" data-flow-id="<?php echo esc_attr($flow_id); ?>" data-step-type="<?php echo esc_attr($step_type); ?>">
                        <div class="dm-step-header">
                            <div class="dm-step-title"><?php echo esc_html($step_label); ?></div>
                            <div class="dm-step-actions">
                                <?php if ($has_handlers && $step_uses_handlers): ?>
                                    <button type="button" class="button button-small dm-modal-open" 
                                            data-template="handler-selection"
                                            data-context='{"step_type":"<?php echo esc_attr($step_type); ?>","flow_id":"<?php echo esc_attr($flow_id); ?>"}'>
                                        <?php esc_html_e('Add Handler', 'data-machine'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="dm-step-body">
                            <!-- Flow step configuration info -->
                            <div class="dm-flow-step-info">
                                <?php if ($step_uses_handlers): ?>
                                    <div class="dm-no-config">
                                        <span><?php esc_html_e('No handlers configured', 'data-machine'); ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="dm-no-config">
                                        <span><?php esc_html_e('No AI model configured', 'data-machine'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Placeholder when no pipeline steps exist -->
                <div class="dm-flow-placeholder">
                    <p class="dm-flow-placeholder-text">
                        <?php esc_html_e('Add steps to the pipeline above to configure handlers for this flow', 'data-machine'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="dm-flow-meta">
        <small><?php echo esc_html(sprintf(__('Created %s', 'data-machine'), date('M j, Y', strtotime($created_at)))); ?></small>
    </div>
</div>