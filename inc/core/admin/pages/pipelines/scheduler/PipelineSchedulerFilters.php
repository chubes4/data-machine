<?php
/**
 * Pipeline Scheduler Component Filter Registration
 *
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * Simple filter registration for the pipeline scheduler component.
 * Uses parameter-based dm_get_scheduler filter following existing patterns.
 * 
 * @package DataMachine\Core\Admin\Pages\Pipelines\Scheduler
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Scheduler;

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

/**
 * Register all Pipeline Scheduler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Admin components can access scheduler via parameter-based filter discovery.
 * 
 * @since 1.0.0
 */
function dm_register_pipeline_scheduler_filters() {
    
    // Main scheduler service - parameter-based like all other services
    add_filter('dm_get_scheduler', function($scheduler, $type = null) {
        if ($type === 'intervals') {
            // Return Action Scheduler intervals from Constants
            return \DataMachine\Engine\Constants::get_scheduler_intervals();
        }
        
        if ($type === null) {
            // Return scheduler service instance
            return new PipelineScheduler();
        }
        
        return $scheduler;
    }, 10, 2);
    
    // Flow scheduling modal content
    add_filter('dm_get_modal', function($content, $template) {
        if ($template === 'flow-schedule') {
            // Return early if content already provided by another component
            if ($content !== null) {
                return $content;
            }
            
            $context = $_POST['context'] ?? [];
            $flow_id = $context['flow_id'] ?? 0;
            
            if (!$flow_id) {
                return '<div class="dm-modal-error"><p>' . esc_html__('Flow ID is required', 'data-machine') . '</p></div>';
            }
            
            // Render flow scheduling modal
            return render_flow_schedule_modal($flow_id, $context);
        }
        
        return $content;
    }, 10, 2);
    
    // Register flow execution hooks dynamically
    add_action('init', function() {
        // Register master hook for flow execution
        add_action('dm_execute_flow', function($flow_id) {
            $scheduler = apply_filters('dm_get_scheduler', null);
            if ($scheduler) {
                $scheduler->execute_flow($flow_id);
            }
        });
    });
}

/**
 * Render flow scheduling modal content
 *
 * @param int $flow_id Flow ID
 * @param array $context Modal context
 * @return string Modal HTML content
 */
function render_flow_schedule_modal(int $flow_id, array $context): string
{
    // Get flow data
    $all_databases = apply_filters('dm_get_database_services', []);
    $flows_db = $all_databases['flows'] ?? null;
    if (!$flows_db) {
        return '<div class="dm-modal-error"><p>' . esc_html__('Database service unavailable', 'data-machine') . '</p></div>';
    }
    
    $flow = $flows_db->get_flow($flow_id);
    if (!$flow) {
        return '<div class="dm-modal-error"><p>' . esc_html__('Flow not found', 'data-machine') . '</p></div>';
    }
    
    // Parse current scheduling config
    $scheduling_config = is_array($flow['scheduling_config'] ?? null) 
        ? $flow['scheduling_config'] 
        : json_decode($flow['scheduling_config'] ?? '{}', true);
    $current_interval = $scheduling_config['interval'] ?? 'manual';
    $last_run_at = $scheduling_config['last_run_at'] ?? null;
    
    // Get available intervals
    $intervals = apply_filters('dm_get_scheduler', null, 'intervals');
    
    // Get scheduler service for next run time
    $scheduler = apply_filters('dm_get_scheduler', null);
    $next_run_time = $scheduler ? $scheduler->get_next_run_time($flow_id) : null;
    
    ob_start();
    ?>
    <div class="dm-flow-schedule-container">
        <div class="dm-flow-schedule-header">
            <h3><?php echo esc_html(sprintf(__('Schedule Configuration: %s', 'data-machine'), $flow['flow_name'] ?? 'Flow')); ?></h3>
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
                <button type="button" class="button button-secondary dm-cancel-schedule">
                    <?php esc_html_e('Cancel', 'data-machine'); ?>
                </button>
                <button type="button" class="button button-secondary dm-run-now-btn" data-flow-id="<?php echo esc_attr($flow_id); ?>">
                    <?php esc_html_e('Run Now', 'data-machine'); ?>
                </button>
                <button type="button" class="button button-primary dm-modal-close" 
                        data-template="save-schedule-action"
                        data-context='{"flow_id":"<?php echo esc_attr($flow_id); ?>"}'>
                    <?php esc_html_e('Save Schedule', 'data-machine'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Auto-register when file loads - achieving complete self-containment
dm_register_pipeline_scheduler_filters();