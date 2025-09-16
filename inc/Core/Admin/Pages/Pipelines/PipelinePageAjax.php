<?php
/**
 * Pipeline Page AJAX Handler
 *
 * Handles pipeline and flow management AJAX operations (business logic).
 * Manages data persistence, business rules, and core pipeline operations.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines;

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
        add_action('wp_ajax_dm_create_pipeline', [$instance, 'handle_create_pipeline']);
        add_action('wp_ajax_dm_create_pipeline_from_template', [$instance, 'handle_create_pipeline_from_template']);
        add_action('wp_ajax_dm_add_step', [$instance, 'handle_add_step']);
        add_action('wp_ajax_dm_add_flow', [$instance, 'handle_add_flow']);
        add_action('wp_ajax_dm_duplicate_flow', [$instance, 'handle_duplicate_flow']);
        add_action('wp_ajax_dm_delete_pipeline', [$instance, 'handle_delete_pipeline']);
        add_action('wp_ajax_dm_delete_step', [$instance, 'handle_delete_step']);
        add_action('wp_ajax_dm_delete_flow', [$instance, 'handle_delete_flow']);
        add_action('wp_ajax_dm_run_flow_now', [$instance, 'handle_run_flow_now']);
        add_action('wp_ajax_dm_save_flow_schedule', [$instance, 'handle_save_flow_schedule']);
        add_action('wp_ajax_dm_export_pipelines', [$instance, 'handle_export_pipelines']);
        add_action('wp_ajax_dm_import_pipelines', [$instance, 'handle_import_pipelines']);
        add_action('wp_ajax_dm_reorder_steps', [$instance, 'handle_reorder_steps']);
        add_action('wp_ajax_dm_reorder_flows', [$instance, 'handle_reorder_flows']);
        add_action('wp_ajax_dm_move_flow', [$instance, 'handle_move_flow']);
        add_action('wp_ajax_dm_refresh_pipeline_status', [$instance, 'handle_refresh_pipeline_status']);
        add_action('wp_ajax_dm_save_pipeline_title', [$instance, 'handle_save_pipeline_title']);
        add_action('wp_ajax_dm_save_flow_title', [$instance, 'handle_save_flow_title']);
        add_action('wp_ajax_dm_save_user_message', [$instance, 'handle_save_user_message']);
        add_action('wp_ajax_dm_save_system_prompt', [$instance, 'handle_save_system_prompt']);
        add_action('wp_ajax_dm_switch_pipeline_selection', [$instance, 'handle_switch_pipeline_selection']);
        add_action('wp_ajax_dm_save_pipeline_preference', [$instance, 'handle_save_pipeline_preference']);
    }

    /**
     * Handle pipeline page AJAX requests (business logic)
     */


    /**
     * Add step to pipeline - delegated to central dm_create action
     */
    public function handle_add_step()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        // Delegate to dm_create_step filter
        // Filter handles AJAX response directly via wp_send_json when in AJAX context
        $step_id = apply_filters('dm_create_step', false, $_POST);
        
        // If we reach here, filter didn't send JSON response (shouldn't happen in AJAX)
        if (!$step_id) {
            wp_send_json_error(['message' => __('Failed to add step', 'data-machine')]);
        }
    }


    /**
     * Delete step from pipeline - delegated to central dm_delete action
     */
    public function handle_delete_step()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $pipeline_step_id = sanitize_text_field(wp_unslash($_POST['pipeline_step_id'] ?? ''));
        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        
        // Delegate to central deletion system
        do_action('dm_delete', 'step', $pipeline_step_id, ['pipeline_id' => $pipeline_id]);
    }

    /**
     * Delete pipeline - delegated to central dm_delete action
     */
    public function handle_delete_pipeline()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        
        // Delegate to central deletion system
        do_action('dm_delete', 'pipeline', $pipeline_id);
    }

    /**
     * Create a new pipeline in the database - delegated to central dm_create filter
     */
    public function handle_create_pipeline()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        // Delegate to dm_create_pipeline filter
        // Filter handles AJAX response directly via wp_send_json when in AJAX context
        $pipeline_id = apply_filters('dm_create_pipeline', false, $_POST);
        
        // If we reach here, filter didn't send JSON response (shouldn't happen in AJAX)
        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Failed to create pipeline', 'data-machine')]);
        }
    }

    /**
     * Add flow to pipeline - delegated to central dm_create filter
     */
    public function handle_add_flow()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        // Delegate to dm_create_flow filter
        // Filter handles AJAX response directly via wp_send_json when in AJAX context
        $flow_id = apply_filters('dm_create_flow', false, $_POST);

        // If we reach here, filter didn't send JSON response (shouldn't happen in AJAX)
        if (!$flow_id) {
            wp_send_json_error(['message' => __('Failed to add flow', 'data-machine')]);
        }
    }

    /**
     * Duplicate flow - delegated to central dm_duplicate_flow filter
     */
    public function handle_duplicate_flow()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $source_flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));

        if (!$source_flow_id) {
            wp_send_json_error(['message' => __('Flow ID is required', 'data-machine')]);
        }

        // Delegate to dm_duplicate_flow filter
        // Filter handles AJAX response directly via wp_send_json when in AJAX context
        $new_flow_id = apply_filters('dm_duplicate_flow', false, $source_flow_id);

        // If we reach here, filter didn't send JSON response (shouldn't happen in AJAX)
        if (!$new_flow_id) {
            wp_send_json_error(['message' => __('Failed to duplicate flow', 'data-machine')]);
        }
    }

    /**
     * Delete flow - delegated to central dm_delete action
     */
    public function handle_delete_flow()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
        
        // Delegate to central deletion system
        do_action('dm_delete', 'flow', $flow_id);
    }

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
        $flow = $db_flows->get_flow($flow_id);
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

        // Update database
        $result = $db_flows->update_flow($flow_id, [
            'scheduling_config' => wp_json_encode($scheduling_config)
        ]);

        if (!$result) {
            wp_send_json_error(['message' => __('Failed to save schedule configuration', 'data-machine')]);
        }

        // Handle Action Scheduler scheduling via central action hook
        do_action('dm_update_flow_schedule', $flow_id, $schedule_interval, $old_interval);

        // Auto-save pipeline after flow schedule change
        $pipeline_id = (int)$flow['pipeline_id'];
        if ($pipeline_id > 0) {
            do_action('dm_auto_save', $pipeline_id);
        }

        // Clear pipeline cache before response
        if ($pipeline_id > 0) {
            do_action('dm_clear_cache', $pipeline_id);
        }

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
     * Layer 3: Add step to pipeline and sync to all existing flows
     * 
     * Adds step to pipeline configuration and creates corresponding flow steps.
     * 
     * @param int $pipeline_id Pipeline ID to add step to
     * @param string $step_type Type of step to add
     * @return array|false Step data on success, false on failure
     */
    private function add_step_to_pipeline($pipeline_id, $step_type) {
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_pipelines || !$db_flows) {
            return false;
        }

        // Get current pipeline steps to determine next execution order
        $current_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        
        // Calculate the next execution_order by finding the highest existing value
        $max_execution_order = -1;
        foreach ($current_steps as $step) {
            $execution_order = $step['execution_order'] ?? 0;
            if ($execution_order > $max_execution_order) {
                $max_execution_order = $execution_order;
            }
        }
        $next_execution_order = $max_execution_order + 1;

        // Get step config for label
        $all_steps = apply_filters('dm_steps', []);
        $step_config = $all_steps[$step_type] ?? [];

        // Create new step data
        $new_step = [
            'step_type' => $step_type,
            'execution_order' => $next_execution_order,
            'pipeline_step_id' => wp_generate_uuid4(), // Generate unique pipeline step ID for stable file isolation
            'label' => $step_config['label'] ?? ucfirst(str_replace('_', ' ', $step_type))
        ];

        // Add step to pipeline using associative array with pipeline_step_id as key
        $pipeline_config = [];
        foreach ($current_steps as $step) {
            $pipeline_config[$step['pipeline_step_id']] = $step;
        }
        $pipeline_config[$new_step['pipeline_step_id']] = $new_step;
        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_config' => json_encode($pipeline_config)
        ]);

        if (!$success) {
            return false;
        }

        // Sync new step to all existing flows
        $flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
        foreach ($flows as $flow) {
            $flow_id = $flow['flow_id'];
            $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
            
            // Add new step to this flow
            $new_flow_steps = $this->add_flow_steps($flow_id, [$new_step]);
            $flow_config = array_merge($flow_config, $new_flow_steps);
            
            // Update flow
            $flow_update_success = $db_flows->update_flow($flow_id, [
                'flow_config' => json_encode($flow_config)
            ]);
            
            if (!$flow_update_success) {
                do_action('dm_log', 'error', "Failed to sync new step to flow {$flow_id}");
            }
        }

        return $new_step;
    }

    /**
     * Bottom layer: Create flow steps for given pipeline steps
     * 
     * Single source of truth for flow step creation logic.
     * 
     * @param int $flow_id Flow ID to add steps to
     * @param array $pipeline_steps Array of pipeline step data
     * @return array Updated flow_config array
     */
    private function add_flow_steps($flow_id, $pipeline_steps) {
        $flow_config = [];
        
        foreach ($pipeline_steps as $step) {
            $pipeline_step_id = $step['pipeline_step_id'] ?? null;
            $step_type = $step['step_type'] ?? '';
            
            if ($pipeline_step_id && $step_type) {
                $flow_step_id = apply_filters('dm_generate_flow_step_id', '', $pipeline_step_id, $flow_id);
                $flow_config[$flow_step_id] = [
                    'flow_step_id' => $flow_step_id,
                    'step_type' => $step_type,
                    'pipeline_step_id' => $pipeline_step_id,
                    'flow_id' => $flow_id,
                    'handler' => null
                ];
            }
        }
        
        return $flow_config;
    }

    /**
     * Get pipeline ID from flow ID
     * 
     * @param int $flow_id Flow ID
     * @return int Pipeline ID
     */
    private function get_pipeline_id_from_flow($flow_id) {
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            return 0;
        }
        
        $flow = $db_flows->get_flow($flow_id);
        return $flow ? (int)($flow['pipeline_id'] ?? 0) : 0;
    }

    /**
     * Export selected pipelines
     */
    public function handle_export_pipelines() {
        // Security checks
        if (!check_ajax_referer('dm_ajax_actions', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $pipeline_ids = json_decode(sanitize_textarea_field(wp_unslash($_POST['pipeline_ids'] ?? '[]')), true);
        
        if (empty($pipeline_ids)) {
            wp_send_json_error(['message' => __('No pipelines selected', 'data-machine')]);
        }
        
        // Trigger export action
        do_action('dm_export', 'pipelines', $pipeline_ids);
        
        // Get result via filter
        $csv_content = apply_filters('dm_export_result', null);
        
        if ($csv_content) {
            wp_send_json_success(['csv' => $csv_content]);
        } else {
            wp_send_json_error(['message' => __('Export failed', 'data-machine')]);
        }
    }

    /**
     * Import pipelines from CSV
     */
    public function handle_import_pipelines() {
        // Security checks
        if (!check_ajax_referer('dm_ajax_actions', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $csv_content = sanitize_textarea_field(wp_unslash($_POST['csv_content'] ?? ''));
        
        if (empty($csv_content)) {
            wp_send_json_error(['message' => __('No CSV content provided', 'data-machine')]);
        }
        
        // Trigger import action
        do_action('dm_import', 'pipelines', $csv_content);
        
        // Get result via filter
        $result = apply_filters('dm_import_result', null);
        
        if ($result && isset($result['imported'])) {
            wp_send_json_success([
                /* translators: %d: Number of imported pipelines */
                'message' => sprintf(__('Successfully imported %d pipelines', 'data-machine'), count($result['imported'])),
                'pipeline_ids' => $result['imported']
            ]);
        } else {
            wp_send_json_error(['message' => __('Import failed', 'data-machine')]);
        }
    }

    /**
     * Reorder pipeline steps - update execution_order values
     */
    public function handle_reorder_steps()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $pipeline_id = (int) sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        $step_order = json_decode(sanitize_textarea_field(wp_unslash($_POST['step_order'] ?? '[]')), true);
        
        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Pipeline ID required', 'data-machine')]);
        }
        
        if (empty($step_order)) {
            wp_send_json_error(['message' => __('Step order data required', 'data-machine')]);
        }
        
        // Get database service
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }
        
        // Get current pipeline steps
        $current_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        
        if (empty($current_steps)) {
            wp_send_json_error(['message' => __('No pipeline steps found', 'data-machine')]);
        }
        
        // Validate that all step IDs in the order exist in the pipeline
        foreach ($step_order as $pipeline_step_id => $new_execution_order) {
            if (!isset($current_steps[$pipeline_step_id])) {
                wp_send_json_error(['message' => __('Invalid step ID in reorder data', 'data-machine')]);
            }
        }
        
        // Update execution_order values
        $updated_steps = $current_steps;
        foreach ($step_order as $pipeline_step_id => $new_execution_order) {
            $updated_steps[$pipeline_step_id]['execution_order'] = (int) $new_execution_order;
        }
        
        // Save updated pipeline configuration
        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_config' => wp_json_encode($updated_steps)
        ]);
        
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to save step order', 'data-machine')]);
        }
        
        // Trigger auto-save
        do_action('dm_auto_save', $pipeline_id);

        // Clear pipeline cache before response
        do_action('dm_clear_cache', $pipeline_id);

        wp_send_json_success([
            'message' => __('Step order updated successfully', 'data-machine'),
            'pipeline_id' => $pipeline_id,
            'step_count' => count($step_order)
        ]);
    }

    /**
     * Reorder flows within a pipeline - update display_order values
     */
    public function handle_reorder_flows()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $pipeline_id = (int) sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        $flow_order = json_decode(sanitize_textarea_field(wp_unslash($_POST['flow_order'] ?? '[]')), true);
        
        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Pipeline ID required', 'data-machine')]);
        }
        
        if (empty($flow_order)) {
            wp_send_json_error(['message' => __('Flow order data required', 'data-machine')]);
        }
        
        // Get database service
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }
        
        // Prepare flow order data for database update
        $flow_orders = [];
        foreach ($flow_order as $flow_data) {
            if (!isset($flow_data['flow_id']) || !isset($flow_data['display_order'])) {
                wp_send_json_error(['message' => __('Invalid flow order data format', 'data-machine')]);
            }
            $flow_orders[(int)$flow_data['flow_id']] = (int)$flow_data['display_order'];
        }
        
        // Update flow display orders
        $success = $db_flows->update_flow_display_orders($pipeline_id, $flow_orders);
        
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to save flow order', 'data-machine')]);
        }
        
        wp_send_json_success([
            'message' => __('Flow order updated successfully', 'data-machine'),
            'pipeline_id' => $pipeline_id,
            'flow_count' => count($flow_orders)
        ]);
    }

    /**
     * Move a flow up or down in display order
     */
    public function handle_move_flow()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $flow_id = (int) sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
        $pipeline_id = (int) sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        $direction = sanitize_text_field(wp_unslash($_POST['direction'] ?? ''));
        
        if (!$flow_id || !$pipeline_id) {
            wp_send_json_error(['message' => __('Flow ID and Pipeline ID required', 'data-machine')]);
        }
        
        if (!in_array($direction, ['up', 'down'])) {
            wp_send_json_error(['message' => __('Invalid direction. Must be "up" or "down"', 'data-machine')]);
        }
        
        // Get database service
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }
        
        // Move the flow
        $success = ($direction === 'up') 
            ? $db_flows->move_flow_up($flow_id)
            : $db_flows->move_flow_down($flow_id);
        
        if (!$success) {
            $message = ($direction === 'up') 
                ? __('Cannot move flow up. It may already be at the top or an error occurred.', 'data-machine')
                : __('Cannot move flow down. It may already be at the bottom or an error occurred.', 'data-machine');
            
            wp_send_json_error(['message' => $message]);
        }
        
        wp_send_json_success([
            'message' => __('Flow moved successfully', 'data-machine'),
            'flow_id' => $flow_id,
            'direction' => $direction
        ]);
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
     * Save pipeline title via auto-save functionality
     */
    public function handle_save_pipeline_title()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $pipeline_id = (int) sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        $pipeline_title = sanitize_text_field(wp_unslash($_POST['pipeline_title'] ?? ''));
        
        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Pipeline ID required', 'data-machine')]);
        }
        
        if (empty($pipeline_title)) {
            wp_send_json_error(['message' => __('Pipeline title cannot be empty', 'data-machine')]);
        }
        
        // Get database service
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }
        
        // Update pipeline title
        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_name' => $pipeline_title
        ]);
        
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to save pipeline title', 'data-machine')]);
        }
        
        // Trigger auto-save for complete pipeline persistence
        do_action('dm_auto_save', $pipeline_id);

        // Clear pipeline cache before response
        do_action('dm_clear_cache', $pipeline_id);

        wp_send_json_success([
            'message' => __('Pipeline title saved successfully', 'data-machine'),
            'pipeline_id' => $pipeline_id,
            'pipeline_title' => $pipeline_title
        ]);
    }

    /**
     * Save flow title via auto-save functionality
     */
    public function handle_save_flow_title()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $flow_id = (int) sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
        $flow_title = sanitize_text_field(wp_unslash($_POST['flow_title'] ?? ''));
        
        if (!$flow_id) {
            wp_send_json_error(['message' => __('Flow ID required', 'data-machine')]);
        }
        
        if (empty($flow_title)) {
            wp_send_json_error(['message' => __('Flow title cannot be empty', 'data-machine')]);
        }
        
        // Get database service
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }
        
        // Get existing flow to extract pipeline_id for auto-save
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Flow not found', 'data-machine')]);
        }
        
        // Update flow title
        $success = $db_flows->update_flow($flow_id, [
            'flow_name' => $flow_title
        ]);
        
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to save flow title', 'data-machine')]);
        }
        
        // Trigger auto-save for complete pipeline persistence
        $pipeline_id = (int) $flow['pipeline_id'];
        if ($pipeline_id > 0) {
            do_action('dm_auto_save', $pipeline_id);
        }

        // Clear pipeline cache before response
        if ($pipeline_id > 0) {
            do_action('dm_clear_cache', $pipeline_id);
        }

        wp_send_json_success([
            'message' => __('Flow title saved successfully', 'data-machine'),
            'flow_id' => $flow_id,
            'flow_title' => $flow_title,
            'pipeline_id' => $pipeline_id
        ]);
    }


    /**
     * Handle user message auto-save for AI flow steps
     */
    public function handle_save_user_message() {
        // Security checks
        check_ajax_referer('dm_ajax_actions', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $flow_step_id = sanitize_text_field(wp_unslash($_POST['flow_step_id'] ?? ''));
        $user_message = sanitize_textarea_field(wp_unslash($_POST['user_message'] ?? ''));
        
        if (!$flow_step_id) {
            wp_send_json_error(['message' => __('Flow step ID required', 'data-machine')]);
        }
        
        // Use centralized flow user message update action with validation
        $success = apply_filters('dm_update_flow_user_message_result', false, $flow_step_id, $user_message);

        // If no filter handled it, fall back to action (for backwards compatibility)
        if (!$success) {
            do_action('dm_update_flow_user_message', $flow_step_id, $user_message);
            $success = true; // Assume success since action doesn't return values
        }

        if ($success) {
            wp_send_json_success([
                'message' => __('User message saved successfully', 'data-machine'),
                'flow_step_id' => $flow_step_id
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save user message', 'data-machine')]);
        }
    }

    /**
     * Handle system prompt auto-save for AI pipeline steps
     */
    public function handle_save_system_prompt() {
        // Security checks
        check_ajax_referer('dm_ajax_actions', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $pipeline_step_id = sanitize_text_field(wp_unslash($_POST['pipeline_step_id'] ?? ''));
        $system_prompt = sanitize_textarea_field(wp_unslash($_POST['system_prompt'] ?? ''));
        
        if (!$pipeline_step_id) {
            wp_send_json_error(['message' => __('Pipeline step ID required', 'data-machine')]);
        }
        
        // Use centralized system prompt update action with validation
        $success = apply_filters('dm_update_system_prompt_result', false, $pipeline_step_id, $system_prompt);

        // If no filter handled it, fall back to action (for backwards compatibility)
        if (!$success) {
            do_action('dm_update_system_prompt', $pipeline_step_id, $system_prompt);
            $success = true; // Assume success since action doesn't return values
        }

        if ($success) {
            wp_send_json_success([
                'message' => __('System prompt saved successfully', 'data-machine'),
                'pipeline_step_id' => $pipeline_step_id
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save system prompt', 'data-machine')]);
        }
    }

    /**
     * Handle pipeline selection switch for dropdown functionality
     */
    public function handle_switch_pipeline_selection() {
        // Security checks
        check_ajax_referer('dm_ajax_actions', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $selected_pipeline_id = sanitize_text_field(wp_unslash($_POST['selected_pipeline_id'] ?? ''));
        
        if (!$selected_pipeline_id) {
            wp_send_json_error(['message' => __('Pipeline ID required', 'data-machine')]);
        }
        
        // Get all pipelines to validate selection
        $all_pipelines = apply_filters('dm_get_pipelines', []);
        $selected_pipeline = null;
        
        foreach ($all_pipelines as $pipeline) {
            if ($pipeline['pipeline_id'] === $selected_pipeline_id) {
                $selected_pipeline = $pipeline;
                break;
            }
        }
        
        if (!$selected_pipeline) {
            wp_send_json_error(['message' => __('Invalid pipeline ID', 'data-machine')]);
        }
        
        // Get flows for the selected pipeline
        $existing_flows = apply_filters('dm_get_pipeline_flows', [], $selected_pipeline_id);
        
        
        wp_send_json_success([
            'message' => __('Pipeline switched successfully', 'data-machine'),
            'pipeline_id' => $selected_pipeline_id,
            'pipeline_data' => $selected_pipeline,
            'existing_flows' => $existing_flows
        ]);
    }

    /**
     * Handle saving user's pipeline selection preference
     */
    public function handle_save_pipeline_preference() {
        // Security checks
        check_ajax_referer('dm_ajax_actions', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        $selected_pipeline_id = sanitize_text_field(wp_unslash($_POST['selected_pipeline_id'] ?? ''));
        
        if (!$selected_pipeline_id) {
            wp_send_json_error(['message' => __('Pipeline ID required', 'data-machine')]);
        }
        
        // Validate pipeline exists
        $all_pipelines = apply_filters('dm_get_pipelines', []);
        $pipeline_exists = false;
        
        foreach ($all_pipelines as $pipeline) {
            if ($pipeline['pipeline_id'] === $selected_pipeline_id) {
                $pipeline_exists = true;
                break;
            }
        }
        
        if (!$pipeline_exists) {
            wp_send_json_error(['message' => __('Invalid pipeline ID', 'data-machine')]);
        }
        
        // Save user preference
        $updated = update_user_meta(get_current_user_id(), 'dm_selected_pipeline_id', $selected_pipeline_id);
        
        if ($updated === false) {
            wp_send_json_error(['message' => __('Failed to save preference', 'data-machine')]);
        }
        
        wp_send_json_success([
            'message' => __('Pipeline preference saved', 'data-machine'),
            'pipeline_id' => $selected_pipeline_id
        ]);
    }

    /**
     * Create pipeline from template - delegated to central dm_create_pipeline_from_template filter
     */
    public function handle_create_pipeline_from_template()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $template_id = sanitize_text_field(wp_unslash($_POST['template_id'] ?? ''));
        $pipeline_name = sanitize_text_field(wp_unslash($_POST['pipeline_name'] ?? ''));

        if (empty($template_id)) {
            wp_send_json_error(['message' => __('Template ID is required', 'data-machine')]);
        }

        // Prepare options
        $options = [];
        if (!empty($pipeline_name)) {
            $options['pipeline_name'] = $pipeline_name;
        }

        // Delegate to dm_create_pipeline_from_template filter
        $pipeline_id = apply_filters('dm_create_pipeline_from_template', false, $template_id, $options);

        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Failed to create pipeline from template', 'data-machine')]);
        }

        // Engine handles AJAX response when wp_doing_ajax() is true
        // This code is only reached for non-AJAX contexts (if any)
        return;
    }
}