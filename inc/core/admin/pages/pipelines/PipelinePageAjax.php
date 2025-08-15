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
     * Handle pipeline page AJAX requests (business logic)
     */
    // Routing wrapper method removed - individual WordPress action hooks call methods directly


    /**
     * Add step to pipeline - delegated to central dm_create action
     */
    public function handle_add_step()
    {
        do_action('dm_create', 'step', $_POST, ['source' => 'ajax']);
    }


    /**
     * Delete step from pipeline - delegated to central dm_delete action
     */
    public function handle_delete_step()
    {
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
        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        
        // Delegate to central deletion system
        do_action('dm_delete', 'pipeline', $pipeline_id);
    }

    /**
     * Create a new pipeline in the database - delegated to central dm_create action
     */
    public function handle_create_pipeline()
    {
        do_action('dm_create', 'pipeline', $_POST, ['source' => 'ajax']);
    }

    /**
     * Add flow to pipeline - delegated to central dm_create action
     */
    public function handle_add_flow()
    {
        do_action('dm_create', 'flow', $_POST, ['source' => 'ajax']);
    }

    /**
     * Delete flow - delegated to central dm_delete action
     */
    public function handle_delete_flow()
    {
        $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
        
        // Delegate to central deletion system
        do_action('dm_delete', 'flow', $flow_id);
    }

    /**
     * Save flow schedule configuration
     */
    public function handle_save_flow_schedule()
    {
        $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
        $schedule_status = sanitize_text_field(wp_unslash($_POST['schedule_status'] ?? 'inactive'));
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

        // Parse existing scheduling config
        $scheduling_config = json_decode($flow['scheduling_config'] ?? '{}', true);
        $old_status = $scheduling_config['status'] ?? 'inactive';

        // Update scheduling config
        $scheduling_config['status'] = $schedule_status;
        $scheduling_config['interval'] = $schedule_interval;

        // Update database
        $result = $db_flows->update_flow($flow_id, [
            'scheduling_config' => wp_json_encode($scheduling_config)
        ]);

        if (!$result) {
            wp_send_json_error(['message' => __('Failed to save schedule configuration', 'data-machine')]);
        }

        // Handle Action Scheduler scheduling via central action hook
        do_action('dm_update_flow_schedule', $flow_id, $schedule_status, $schedule_interval, $old_status);

        // Auto-save pipeline after flow schedule change
        $pipeline_id = (int)$flow['pipeline_id'];
        if ($pipeline_id > 0) {
            do_action('dm_auto_save', $pipeline_id);
        }

        wp_send_json_success([
            'message' => sprintf(__('Schedule saved successfully. Flow is now %s.', 'data-machine'), $schedule_status),
            'flow_id' => $flow_id,
            'schedule_status' => $schedule_status,
            'schedule_interval' => $schedule_interval
        ]);
    }

    /**
     * Run flow immediately - delegated to central dm_create action
     */
    public function handle_run_flow_now()
    {
        // Use the proper action hook chain - let dm_run_flow_now handle pipeline_id lookup
        $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
        
        // Use the designed entry point for flow execution
        do_action('dm_run_flow_now', $flow_id);
    }


    // create_new_pipeline method removed - functionality moved to centralized Create.php

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
        $next_execution_order = count($current_steps);

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

    // add_flow_to_pipeline method removed - functionality moved to centralized Create.php

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
        
        $pipeline_ids = json_decode(wp_unslash($_POST['pipeline_ids'] ?? '[]'), true);
        
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
        
        $csv_content = wp_unslash($_POST['csv_content'] ?? '');
        
        if (empty($csv_content)) {
            wp_send_json_error(['message' => __('No CSV content provided', 'data-machine')]);
        }
        
        // Trigger import action
        do_action('dm_import', 'pipelines', $csv_content);
        
        // Get result via filter
        $result = apply_filters('dm_import_result', null);
        
        if ($result && isset($result['imported'])) {
            wp_send_json_success([
                'message' => sprintf(__('Successfully imported %d pipelines', 'data-machine'), count($result['imported'])),
                'pipeline_ids' => $result['imported']
            ]);
        } else {
            wp_send_json_error(['message' => __('Import failed', 'data-machine')]);
        }
    }

    // All deletion logic moved to central dm_delete action in DataMachineActions.php
    // This eliminates ~200 lines of duplicated deletion code and provides unified deletion patterns
}