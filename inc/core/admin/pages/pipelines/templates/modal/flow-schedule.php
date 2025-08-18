<?php
/**
 * Flow Schedule Modal Template
 *
 * Pure rendering template for flow scheduling configuration.
 * Displays schedule intervals, timing info, and save actions.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Required template variables
$flow_id = $flow_id ?? null;
$flow_name = $flow_name ?? 'Flow';
$current_interval = $current_interval ?? 'manual';
$intervals = $intervals ?? [];
$last_run_at = $last_run_at ?? null;
$next_run_time = $next_run_time ?? null;

?>
<div class="dm-flow-schedule-container">
    <div class="dm-flow-schedule-header">
        <h3><?php echo esc_html(sprintf(__('Schedule Configuration: %s', 'data-machine'), $flow_name)); ?></h3>
        <p><?php esc_html_e('Configure when this flow should run automatically', 'data-machine'); ?></p>
    </div>
    
    <div class="dm-flow-schedule-form" data-flow-id="<?php echo esc_attr($flow_id); ?>">
        <!-- Schedule Interval -->
        <div class="dm-form-field dm-schedule-interval-field">
            <label for="schedule_interval"><?php esc_html_e('Schedule Interval', 'data-machine'); ?></label>
            <select id="schedule_interval" name="schedule_interval" class="regular-text">
                <option value="manual" <?php selected($current_interval, 'manual'); ?>><?php esc_html_e('Manual Only', 'data-machine'); ?></option>
                <?php if ($intervals): ?>
                    <?php foreach ($intervals as $slug => $config): ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($current_interval, $slug); ?>>
                            <?php echo esc_html($config['label'] ?? ucfirst(str_replace('_', ' ', $slug))); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <p class="description"><?php esc_html_e('How often this flow should run automatically', 'data-machine'); ?></p>
        </div>
        
        <!-- Schedule Information -->
        <div class="dm-schedule-info">
            <h4><?php esc_html_e('Schedule Information', 'data-machine'); ?></h4>
            <div class="dm-schedule-details">
                <?php if ($last_run_at): ?>
                    <p><strong><?php esc_html_e('Last Run:', 'data-machine'); ?></strong> <?php echo esc_html(date('M j, Y g:i A', strtotime($last_run_at))); ?></p>
                <?php else: ?>
                    <p><strong><?php esc_html_e('Last Run:', 'data-machine'); ?></strong> <?php esc_html_e('Never', 'data-machine'); ?></p>
                <?php endif; ?>
                
                <?php if ($next_run_time): ?>
                    <p><strong><?php esc_html_e('Next Run:', 'data-machine'); ?></strong> <?php echo esc_html(date('M j, Y g:i A', strtotime($next_run_time))); ?></p>
                <?php else: ?>
                    <p><strong><?php esc_html_e('Next Run:', 'data-machine'); ?></strong> <?php esc_html_e('Not scheduled', 'data-machine'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="dm-schedule-actions">
            <button type="button" class="button button-secondary dm-cancel-schedule"
                    aria-label="<?php echo esc_attr(sprintf(__('Cancel: %s', 'data-machine'), $flow_name)); ?>">
                <?php esc_html_e('Cancel', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-primary dm-modal-close" 
                    data-template="save-schedule-action"
                    data-context='{"flow_id":"<?php echo esc_attr($flow_id); ?>"}'
                    aria-label="<?php echo esc_attr(sprintf(__('Save Schedule: %s', 'data-machine'), $flow_name)); ?>">
                <?php esc_html_e('Save Schedule', 'data-machine'); ?>
            </button>
        </div>
    </div>
</div>