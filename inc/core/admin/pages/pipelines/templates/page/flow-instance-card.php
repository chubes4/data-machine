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

// Get scheduling info
$scheduling_config = is_object($flow) ? json_decode($flow->scheduling_config, true) : json_decode($flow['scheduling_config'], true);
$schedule_status = $scheduling_config['status'] ?? 'inactive';
$schedule_interval = $scheduling_config['interval'] ?? 'manual';

?>
<div class="dm-flow-instance-card" data-flow-id="<?php echo esc_attr($flow_id); ?>">
    <div class="dm-flow-header">
        <div class="dm-flow-title-section">
            <h5 class="dm-flow-title"><?php echo esc_html($flow_name); ?></h5>
            <div class="dm-flow-status">
                <span class="dm-schedule-status dm-status-<?php echo esc_attr($schedule_status); ?>">
                    <?php echo esc_html(ucfirst($schedule_status)); ?>
                    <?php if ($schedule_status === 'active' && $schedule_interval !== 'manual'): ?>
                        <span class="dm-schedule-interval">(<?php echo esc_html($schedule_interval); ?>)</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <div class="dm-flow-actions">
            <button type="button" class="button button-small dm-modal-trigger" 
                    data-template="flow-schedule"
                    data-context='{"flow_id":<?php echo esc_attr($flow_id); ?>}'>
                <?php esc_html_e('Manage Schedule', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-small dm-edit-flow-btn" 
                    data-flow-id="<?php echo esc_attr($flow_id); ?>">
                <?php esc_html_e('Configure', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-small button-primary dm-run-flow-btn" 
                    data-flow-id="<?php echo esc_attr($flow_id); ?>">
                <?php esc_html_e('Run Now', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-small button-link-delete dm-delete-flow-btn" 
                    data-flow-id="<?php echo esc_attr($flow_id); ?>">
                <?php esc_html_e('Delete', 'data-machine'); ?>
            </button>
        </div>
    </div>
    
    <div class="dm-flow-steps-section">
        <div class="dm-flow-steps">
            <div class="dm-no-flow-steps">
                <p><?php esc_html_e('Configure pipeline steps above to enable handler configuration', 'data-machine'); ?></p>
            </div>
        </div>
    </div>
    
    <div class="dm-flow-meta">
        <small><?php echo esc_html(sprintf(__('Created %s', 'data-machine'), date('M j, Y', strtotime($created_at)))); ?></small>
    </div>
</div>