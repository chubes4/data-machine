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

if (!defined('WPINC')) {
    die;
}

$flow_id = $flow_id ?? null;
$flow_name = $flow_name ?? 'Flow';
$current_interval = $current_interval ?? 'manual';
$intervals = $intervals ?? [];
$last_run_at = $last_run_at ?? null;
$next_run_time = $next_run_time ?? null;

?>
<div class="datamachine-flow-schedule-container">
    <div class="datamachine-flow-schedule-header">
        <?php /* translators: %s: Flow name */ ?>
        <h3><?php echo esc_html(sprintf(__('Schedule Configuration: %s', 'datamachine'), $flow_name)); ?></h3>
        <p><?php esc_html_e('Configure when this flow should run automatically', 'datamachine'); ?></p>
    </div>
    
    <div class="datamachine-flow-schedule-form" data-flow-id="<?php echo esc_attr($flow_id); ?>">
        <!-- Schedule Interval -->
        <div class="datamachine-form-field datamachine-schedule-interval-field">
            <label for="schedule_interval"><?php esc_html_e('Schedule Interval', 'datamachine'); ?></label>
            <select id="schedule_interval" name="schedule_interval" class="regular-text">
                <option value="manual" <?php selected($current_interval, 'manual'); ?>><?php esc_html_e('Manual Only', 'datamachine'); ?></option>
                <?php if ($intervals): ?>
                    <?php foreach ($intervals as $slug => $config): ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($current_interval, $slug); ?>>
                            <?php echo esc_html($config['label'] ?? ucfirst(str_replace('_', ' ', $slug))); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <p class="description"><?php esc_html_e('How often this flow should run automatically', 'datamachine'); ?></p>
        </div>
        
        <!-- Schedule Information -->
        <div class="datamachine-schedule-info">
            <h4><?php esc_html_e('Schedule Information', 'datamachine'); ?></h4>
            <div class="datamachine-schedule-details">
                <?php if ($last_run_at): ?>
                    <p><strong><?php esc_html_e('Last Run:', 'datamachine'); ?></strong> <?php echo esc_html(wp_date('M j, Y g:i A', strtotime($last_run_at))); ?></p>
                <?php else: ?>
                    <p><strong><?php esc_html_e('Last Run:', 'datamachine'); ?></strong> <?php esc_html_e('Never', 'datamachine'); ?></p>
                <?php endif; ?>
                
                <?php if ($next_run_time): ?>
                    <p><strong><?php esc_html_e('Next Run:', 'datamachine'); ?></strong> <?php echo esc_html(wp_date('M j, Y g:i A', strtotime($next_run_time))); ?></p>
                <?php else: ?>
                    <p><strong><?php esc_html_e('Next Run:', 'datamachine'); ?></strong> <?php esc_html_e('Not scheduled', 'datamachine'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="datamachine-schedule-actions">
            <button type="button" class="button button-secondary datamachine-cancel-schedule datamachine-modal-close"
                    <?php /* translators: %s: Flow name */ ?>
                    aria-label="<?php echo esc_attr(sprintf(__('Cancel: %s', 'datamachine'), $flow_name)); ?>">
                <?php esc_html_e('Cancel', 'datamachine'); ?>
            </button>
            <button type="button" class="button button-primary datamachine-modal-close" 
                    data-template="save-schedule-action"
                    data-context='<?php echo esc_attr(wp_json_encode(['flow_id' => $flow_id])); ?>'
                    <?php /* translators: %s: Flow name */ ?>
                    aria-label="<?php echo esc_attr(sprintf(__('Save Schedule: %s', 'datamachine'), $flow_name)); ?>">
                <?php esc_html_e('Save Schedule', 'datamachine'); ?>
            </button>
        </div>
    </div>
</div>