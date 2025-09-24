<?php
/**
 * Pipeline Page AJAX Handler
 *
 * Handles pipeline operation AJAX actions (business logic).
 * Manages flow execution, scheduling, and status monitoring.
 * Handles 3 AJAX actions: execution, scheduling, and status refresh.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Ajax;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelinePageAjax
{
    /**
     * Register all pipeline page AJAX handlers.
     *
     * Self-contained registration pattern following WordPress-native approach.
     * Registers all AJAX actions this class handles directly with WordPress.
     *
     * @since NEXT_VERSION
     */
    public static function register() {
        $instance = new self();
        
        // Pipeline management AJAX actions
        add_action('wp_ajax_dm_run_flow_now', [$instance, 'handle_run_flow_now']);
        add_action('wp_ajax_dm_save_flow_schedule', [$instance, 'handle_save_flow_schedule']);
        add_action('wp_ajax_dm_refresh_pipeline_status', [$instance, 'handle_refresh_pipeline_status']);
        add_action('wp_ajax_dm_refresh_flow_footer', [$instance, 'handle_refresh_flow_footer']);
    }

    /**
     * Handle pipeline page AJAX requests (business logic)
     */


    /**
     * Save flow schedule configuration
     */
    public function handle_save_flow_schedule()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
        $schedule_interval = sanitize_text_field(wp_unslash($_POST['schedule_interval'] ?? 'manual'));

        if (empty($flow_id)) {
            wp_send_json_error(['message' => __('Flow ID is required', 'data-machine')]);
        }

        // Get database services using filter-based discovery
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        if (!$db_flows) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }

        // Get existing flow
        $flow = apply_filters('dm_get_flow', null, $flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Flow not found', 'data-machine')]);
        }

        // Parse existing scheduling config - handle both string and array types
        $scheduling_config = is_array($flow['scheduling_config']) ? 
            $flow['scheduling_config'] : 
            json_decode($flow['scheduling_config'] ?? '{}', true);
        $old_interval = $scheduling_config['interval'] ?? 'manual';

        // Update scheduling config
        $scheduling_config['interval'] = $schedule_interval;

        // Update flow using existing filter - filter will detect scheduling_config parameter
        $result = apply_filters('dm_update_flow', false, $flow_id, [
            'scheduling_config' => wp_json_encode($scheduling_config)
        ]);

        if (!$result) {
            wp_send_json_error(['message' => __('Failed to save schedule configuration', 'data-machine')]);
        }

        // Handle Action Scheduler scheduling via central action hook
        do_action('dm_update_flow_schedule', $flow_id, $schedule_interval, $old_interval);

        wp_send_json_success([
            /* translators: %s: Schedule interval (e.g., 'active', 'paused') */
            'message' => sprintf(__('Schedule saved successfully. Flow is now %s.', 'data-machine'), $schedule_interval),
            'flow_id' => $flow_id,
            'schedule_interval' => $schedule_interval
        ]);
    }

    /**
     * Run flow immediately - delegated to central action
     */
    public function handle_run_flow_now()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
        
        if (!$flow_id) {
            wp_send_json_error(['message' => __('Flow ID is required', 'data-machine')]);
        }
        
        // Use the existing dm_run_flow_now action that handles job creation
        // This action returns true on success, false on failure
        $result = false;
        
        // Capture the return value using output buffering to avoid action hook limitations
        ob_start();
        $result = apply_filters('dm_run_flow_now_result', false, $flow_id);
        ob_end_clean();
        
        // If no filter handled it, trigger the action and assume success
        if (!$result) {
            do_action('dm_run_flow_now', $flow_id);
            $result = true; // Assume success since action doesn't return values
        }
        
        // Send JSON response based on result
        if ($result) {
            wp_send_json_success([
                'message' => __('Flow execution started successfully', 'data-machine'),
                'flow_id' => $flow_id
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to start flow execution', 'data-machine'),
                'flow_id' => $flow_id
            ]);
        }
    }


    /**
     * Refresh pipeline status for real-time updates - individual step statuses
     */
    public function handle_refresh_pipeline_status()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $pipeline_id = intval($_POST['pipeline_id'] ?? 0);
        
        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Pipeline ID required', 'data-machine')]);
        }
        
        // Get all pipeline steps
        $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        
        if (empty($pipeline_steps)) {
            wp_send_json_success([
                'step_statuses' => [],
                'pipeline_id' => $pipeline_id
            ]);
            return;
        }
        
        // Get individual status for each step
        $step_statuses = [];
        foreach ($pipeline_steps as $pipeline_step_id => $step_config) {
            $step_type = $step_config['step_type'] ?? '';
            
            $step_status = apply_filters('dm_detect_status', 'green', 'pipeline_step_status', [
                'pipeline_id' => $pipeline_id,
                'pipeline_step_id' => $pipeline_step_id,
                'step_type' => $step_type
            ]);
            
            $step_statuses[$pipeline_step_id] = $step_status;
        }
        
        wp_send_json_success([
            'step_statuses' => $step_statuses,
            'pipeline_id' => $pipeline_id
        ]);
    }

    /**
     * Refresh flow footer with updated next run time
     */
    public function handle_refresh_flow_footer()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));

        if (empty($flow_id)) {
            wp_send_json_error(['message' => __('Flow ID is required', 'data-machine')]);
        }

        // Get flow data using filter-based discovery
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        if (!$db_flows) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }

        // Get flow data
        $flow = apply_filters('dm_get_flow', null, $flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Flow not found', 'data-machine')]);
        }

        // Parse scheduling config for footer template
        $scheduling_config = is_array($flow['scheduling_config']) ?
            $flow['scheduling_config'] :
            json_decode($flow['scheduling_config'] ?? '{}', true);

        // Render footer template
        ob_start();
        include __DIR__ . '/../templates/page/flow-instance-footer.php';
        $footer_html = ob_get_clean();

        wp_send_json_success([
            'footer_html' => $footer_html,
            'flow_id' => $flow_id
        ]);
    }

}