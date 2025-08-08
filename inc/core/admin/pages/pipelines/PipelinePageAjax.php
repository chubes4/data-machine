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
     * Add step to pipeline
     */
    public function handle_add_step()
    {
        $step_type = sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));
        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        
        if (empty($step_type)) {
            wp_send_json_error(['message' => __('Step type is required', 'data-machine')]);
        }

        if (empty($pipeline_id)) {
            wp_send_json_error(['message' => __('Pipeline ID is required', 'data-machine')]);
        }

        // Validate step type exists
        $all_steps = apply_filters('dm_get_steps', []);
        $step_config = $all_steps[$step_type] ?? null;
        if (!$step_config) {
            wp_send_json_error(['message' => __('Invalid step type', 'data-machine')]);
        }

        // Use layer 3 function to add step and sync to flows
        $new_step = $this->add_step_to_pipeline($pipeline_id, $step_type);
        if (!$new_step) {
            wp_send_json_error(['message' => __('Failed to add step to pipeline', 'data-machine')]);
        }

        // Trigger auto-save hooks
        do_action('dm_pipeline_auto_save', $pipeline_id);

        wp_send_json_success([
            'message' => sprintf(__('Step "%s" added successfully', 'data-machine'), $step_config['label']),
            'step_type' => $step_type,
            'step_config' => $step_config,
            'pipeline_id' => $pipeline_id,
            'execution_order' => $new_step['execution_order'],
            'pipeline_step_id' => $new_step['pipeline_step_id'],
            'step_data' => $new_step
        ]);
    }


    /**
     * Delete step from pipeline with cascade handling
     */
    public function handle_delete_step()
    {
        $pipeline_step_id = sanitize_text_field(wp_unslash($_POST['pipeline_step_id'] ?? ''));
        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        
        if (empty($pipeline_step_id) || $pipeline_id <= 0) {
            wp_send_json_error(['message' => __('Pipeline step ID and pipeline ID are required', 'data-machine')]);
        }

        // Get pipeline data for response
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_pipelines || !$db_flows) {
            wp_send_json_error(['message' => __('Database services unavailable', 'data-machine')]);
        }

        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Pipeline not found', 'data-machine')]);
        }

        $pipeline_name = is_object($pipeline) ? $pipeline->pipeline_name : $pipeline['pipeline_name'];
        $affected_flows = $db_flows->get_flows_for_pipeline($pipeline_id);
        $flow_count = count($affected_flows);

        // Use layer 3 to delete pipeline step and sync to all flows
        $success = $this->delete_pipeline_step($pipeline_id, $pipeline_step_id);
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to delete step from pipeline', 'data-machine')]);
        }

        // Trigger auto-save hooks
        do_action('dm_pipeline_auto_save', $pipeline_id);

        // Get remaining steps count for response
        $remaining_steps = $db_pipelines->get_pipeline_step_configuration($pipeline_id);
        
        // Log the deletion
        do_action('dm_log', 'debug', "Deleted step with ID '{$pipeline_step_id}' from pipeline '{$pipeline_name}' (ID: {$pipeline_id}). Affected {$flow_count} flows.");

        wp_send_json_success([
            'message' => sprintf(
                __('Step deleted successfully from pipeline "%s". %d flows were affected.', 'data-machine'),
                $pipeline_name,
                $flow_count
            ),
            'pipeline_id' => (int)$pipeline_id,
            'pipeline_step_id' => $pipeline_step_id,
            'affected_flows' => $flow_count,
            'remaining_steps' => count($remaining_steps)
        ]);
    }

    /**
     * Delete pipeline with flow cascade deletion
     * Deletes flows first, then the pipeline itself. Jobs are left intact as historical records.
     */
    public function handle_delete_pipeline()
    {
        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        
        if (empty($pipeline_id)) {
            wp_send_json_error(['message' => __('Pipeline ID is required', 'data-machine')]);
        }

        // Get pipeline data for response before deletion
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_pipelines || !$db_flows) {
            wp_send_json_error(['message' => __('Database services unavailable', 'data-machine')]);
        }

        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Pipeline not found', 'data-machine')]);
        }
        
        $pipeline_name = is_object($pipeline) ? $pipeline->pipeline_name : $pipeline['pipeline_name'];
        $affected_flows = $db_flows->get_flows_for_pipeline($pipeline_id);
        $flow_count = count($affected_flows);

        // Use layer 4 to delete pipeline with full cascade deletion
        $success = $this->delete_pipeline($pipeline_id);
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to delete pipeline', 'data-machine')]);
        }

        // Log the deletion
        do_action('dm_log', 'debug', "Deleted pipeline '{$pipeline_name}' (ID: {$pipeline_id}) with cascade deletion of {$flow_count} flows. Job records preserved as historical data.");

        wp_send_json_success([
            'message' => sprintf(
                __('Pipeline "%s" deleted successfully. %d flows were also deleted. Associated job records are preserved as historical data.', 'data-machine'),
                $pipeline_name,
                $flow_count
            ),
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'deleted_flows' => $flow_count
        ]);
    }

    /**
     * Create a new pipeline in the database
     */
    public function handle_create_pipeline()
    {
        // Use top layer function to create complete pipeline with draft flow
        $pipeline_id = $this->create_new_pipeline();
        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Failed to create pipeline', 'data-machine')]);
        }

        // Get the created pipeline for response
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        $db_flows = $all_databases['flows'] ?? null;

        if (!$db_pipelines || !$db_flows) {
            wp_send_json_error(['message' => __('Database services unavailable', 'data-machine')]);
        }

        // Get the created pipeline data
        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Failed to retrieve created pipeline', 'data-machine')]);
        }

        // Get pipeline name from created pipeline
        $pipeline_name = is_object($pipeline) ? $pipeline->pipeline_name : $pipeline['pipeline_name'];

        // Get existing flows (should include the newly created draft flow)
        $existing_flows = $db_flows->get_flows_for_pipeline($pipeline_id);

        wp_send_json_success([
            'message' => __('Pipeline created successfully', 'data-machine'),
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'pipeline_data' => $pipeline,
            'existing_flows' => $existing_flows
        ]);
    }

    /**
     * Add flow to pipeline
     */
    public function handle_add_flow()
    {
        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        
        if (empty($pipeline_id)) {
            wp_send_json_error(['message' => __('Pipeline ID is required', 'data-machine')]);
        }

        // Use layer 2 function to add flow to pipeline  
        $flow_id = $this->add_flow_to_pipeline($pipeline_id);
        if (!$flow_id) {
            wp_send_json_error(['message' => __('Failed to create flow', 'data-machine')]);
        }

        // Get the created flow data
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_flows = $all_databases['flows'] ?? null;
        $flow = $db_flows ? $db_flows->get_flow($flow_id) : null;
        
        if (!$flow) {
            wp_send_json_error(['message' => __('Failed to retrieve created flow', 'data-machine')]);
        }

        $flow_name = is_object($flow) ? $flow->flow_name : $flow['flow_name'];

        wp_send_json_success([
            'message' => sprintf(__('Flow "%s" created successfully', 'data-machine'), $flow_name),
            'flow_id' => $flow_id,
            'flow_name' => $flow_name,
            'pipeline_id' => $pipeline_id,
            'flow_data' => $flow
        ]);
    }

    /**
     * Delete flow from pipeline
     * Deletes the flow instance only. Associated jobs are left intact as historical records.
     */
    public function handle_delete_flow()
    {
        $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
        
        if (empty($flow_id)) {
            wp_send_json_error(['message' => __('Flow ID is required', 'data-machine')]);
        }

        // Get flow data for response before deletion
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            wp_send_json_error(['message' => __('Database services unavailable', 'data-machine')]);
        }

        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Flow not found', 'data-machine')]);
        }
        
        $flow_name = is_object($flow) ? $flow->flow_name : $flow['flow_name'];
        $pipeline_id = is_object($flow) ? $flow->pipeline_id : $flow['pipeline_id'];

        // Use layer 2 to delete flow
        $success = $this->delete_flow($flow_id);
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to delete flow', 'data-machine')]);
        }

        // Log the deletion
        do_action('dm_log', 'debug', "Deleted flow '{$flow_name}' (ID: {$flow_id}). Associated job records preserved as historical data.");

        wp_send_json_success([
            'message' => sprintf(
                __('Flow "%s" deleted successfully. Associated job records are preserved as historical data.', 'data-machine'),
                $flow_name
            ),
            'flow_id' => $flow_id,
            'flow_name' => $flow_name,
            'pipeline_id' => $pipeline_id
        ]);
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
        $all_databases = apply_filters('dm_get_database_services', []);
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

        // Handle Action Scheduler scheduling
        $scheduler = apply_filters('dm_get_scheduler', null);
        if ($scheduler) {
            if ($schedule_status === 'active' && $schedule_interval !== 'manual') {
                // Activate scheduling
                $scheduler->activate_flow($flow_id);
            } elseif ($old_status === 'active') {
                // Deactivate scheduling if it was previously active
                $scheduler->deactivate_flow($flow_id);
            }
        }

        wp_send_json_success([
            'message' => sprintf(__('Schedule saved successfully. Flow is now %s.', 'data-machine'), $schedule_status),
            'flow_id' => $flow_id,
            'schedule_status' => $schedule_status,
            'schedule_interval' => $schedule_interval
        ]);
    }

    /**
     * Run flow immediately
     */
    public function handle_run_flow_now()
    {
        $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));

        if (empty($flow_id)) {
            wp_send_json_error(['message' => __('Flow ID is required', 'data-machine')]);
        }

        // Get flow data for response message
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_flows = $all_databases['flows'] ?? null;
        $flow = ($db_flows) ? $db_flows->get_flow($flow_id) : null;
        $flow_name = $flow['flow_name'] ?? 'Unknown Flow';

        // Trigger central flow execution hook - "button press"
        do_action('dm_run_flow_now', $flow_id, 'run_now');

        // For now, assume success (hook handler logs any failures)
        wp_send_json_success([
            'message' => sprintf(__('Flow "%s" started successfully', 'data-machine'), $flow_name)
        ]);
    }

    /**
     * Handle auto-save operations for pipeline data
     * Always performs full pipeline save regardless of input
     */
    public function handle_auto_save()
    {
        // Verify nonce
        if (!check_ajax_referer('dm_pipeline_auto_save_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
        }

        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        // Get pipeline ID - only required parameter
        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        if (empty($pipeline_id)) {
            wp_send_json_error(['message' => __('Pipeline ID is required', 'data-machine')]);
        }

        // Simple debouncing to prevent excessive saves
        $debounce_key = "dm_auto_save_{$pipeline_id}";
        if (get_transient($debounce_key)) {
            set_transient($debounce_key, time(), 2); // Extend 2-second debounce window
            wp_send_json_success([
                'message' => __('Auto-save debounced', 'data-machine'),
                'debounced' => true,
                'timestamp' => time()
            ]);
            return;
        }

        // Set debounce transient
        set_transient($debounce_key, time(), 2);

        // Trigger the auto-save action hook (always full save)
        do_action('dm_pipeline_auto_save', $pipeline_id);

        // Return success response
        wp_send_json_success([
            'message' => __('Pipeline auto-saved successfully', 'data-machine'),
            'pipeline_id' => $pipeline_id,
            'timestamp' => time(),
            'debounced' => false
        ]);
    }

    /**
     * Top Layer: Complete pipeline initialization
     * 
     * Creates pipeline, adds default empty step, and initializes "Draft Flow".
     * 
     * @param string $pipeline_name Optional pipeline name
     * @return int|false Pipeline ID on success, false on failure
     */
    private function create_new_pipeline($pipeline_name = null) {
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            return false;
        }

        // Create pipeline with default name if not provided
        $pipeline_data = [
            'pipeline_name' => $pipeline_name ?: __('Draft Pipeline', 'data-machine'),
            'step_configuration' => []
        ];

        $pipeline_id = $db_pipelines->create_pipeline($pipeline_data);
        if (!$pipeline_id) {
            return false;
        }

        // Create "Draft Flow" for the new pipeline
        $draft_flow_id = $this->add_flow_to_pipeline($pipeline_id, __('Draft Flow', 'data-machine'));
        
        if (!$draft_flow_id) {
            do_action('dm_log', 'error', "Failed to create Draft Flow for pipeline {$pipeline_id}");
            // Don't fail pipeline creation if flow creation fails
        }

        return $pipeline_id;
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
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_pipelines || !$db_flows) {
            return false;
        }

        // Get current pipeline steps to determine next execution order
        $current_steps = $db_pipelines->get_pipeline_step_configuration($pipeline_id);
        $next_execution_order = count($current_steps);

        // Get step config for label
        $all_steps = apply_filters('dm_get_steps', []);
        $step_config = $all_steps[$step_type] ?? [];

        // Create new step data
        $new_step = [
            'step_type' => $step_type,
            'execution_order' => $next_execution_order,
            'pipeline_step_id' => wp_generate_uuid4(), // Generate unique pipeline step ID for stable file isolation
            'label' => $step_config['label'] ?? ucfirst(str_replace('_', ' ', $step_type))
        ];

        // Add step to pipeline
        $current_steps[] = $new_step;
        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'step_configuration' => json_encode($current_steps)
        ]);

        if (!$success) {
            return false;
        }

        // Sync new step to all existing flows
        $flows = $db_flows->get_flows_for_pipeline($pipeline_id);
        foreach ($flows as $flow) {
            $flow_id = is_object($flow) ? $flow->flow_id : $flow['flow_id'];
            $flow_config_raw = is_object($flow) ? $flow->flow_config : $flow['flow_config'];
            $flow_config = json_decode($flow_config_raw, true) ?: [];
            
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
     * Layer 2: Add flow to pipeline with all existing pipeline steps
     * 
     * Creates new flow and populates with all pipeline steps.
     * 
     * @param int $pipeline_id Pipeline ID to add flow to
     * @param string $flow_name Optional flow name (auto-generated if not provided)
     * @return int|false Flow ID on success, false on failure
     */
    private function add_flow_to_pipeline($pipeline_id, $flow_name = null) {
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_flows = $all_databases['flows'] ?? null;
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_flows || !$db_pipelines) {
            return false;
        }

        // Generate flow name if not provided
        if (!$flow_name) {
            $pipeline = $db_pipelines->get_pipeline($pipeline_id);
            $pipeline_name = $pipeline['pipeline_name'] ?? __('Pipeline', 'data-machine');
            $existing_flows = $db_flows->get_flows_for_pipeline($pipeline_id);
            $flow_number = count($existing_flows) + 1;
            $flow_name = sprintf(__('%s Flow %d', 'data-machine'), $pipeline_name, $flow_number);
        }

        // Create flow record
        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'flow_config' => json_encode([]), // Will be populated with steps
            'scheduling_config' => json_encode([
                'status' => 'inactive',
                'interval' => 'manual'
            ])
        ];

        $flow_id = $db_flows->create_flow($flow_data);
        if (!$flow_id) {
            return false;
        }

        // Get existing pipeline steps and create flow steps
        $pipeline_steps = $db_pipelines->get_pipeline_step_configuration($pipeline_id);
        if (!empty($pipeline_steps)) {
            $flow_config = $this->add_flow_steps($flow_id, $pipeline_steps);
            
            // Update flow with populated config
            $success = $db_flows->update_flow($flow_id, [
                'flow_config' => json_encode($flow_config)
            ]);
            
            if (!$success) {
                // Log error but don't fail flow creation
                do_action('dm_log', 'error', "Failed to populate flow steps for flow {$flow_id}");
            }
        }

        return $flow_id;
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
     * =================== DELETION HIERARCHY ===================
     * 4-layer deletion architecture mirroring creation hierarchy
     */

    /**
     * Layer 1 (Bottom): Delete specific flow steps from a flow
     * 
     * Single source of truth for flow step deletion logic.
     * Removes specified pipeline_step_ids from flow's flow_config.
     * 
     * @param int $flow_id Flow ID to delete steps from
     * @param array $pipeline_step_ids Array of pipeline step IDs to remove
     * @return bool Success status
     */
    private function delete_flow_steps($flow_id, $pipeline_step_ids) {
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            return false;
        }

        // Get current flow config
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            return false;
        }

        $flow_config_raw = is_object($flow) ? $flow->flow_config : $flow['flow_config'];
        $flow_config = json_decode($flow_config_raw, true) ?: [];
        
        // Remove flow steps for specified pipeline step IDs
        $deleted_count = 0;
        foreach ($flow_config as $flow_step_id => $step_data) {
            if (isset($step_data['pipeline_step_id']) && in_array($step_data['pipeline_step_id'], $pipeline_step_ids)) {
                unset($flow_config[$flow_step_id]);
                $deleted_count++;
            }
        }
        
        // Update flow with cleaned configuration
        if ($deleted_count > 0) {
            return $db_flows->update_flow($flow_id, [
                'flow_config' => json_encode($flow_config)
            ]);
        }
        
        return true; // No steps to delete
    }

    /**
     * Layer 2: Delete entire flow and all its flow steps
     * 
     * Thin wrapper around database delete_flow method.
     * 
     * @param int $flow_id Flow ID to delete
     * @return bool Success status
     */
    private function delete_flow($flow_id) {
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            return false;
        }
        
        return $db_flows->delete_flow($flow_id);
    }

    /**
     * Layer 3: Delete pipeline step and sync to all flows
     * 
     * Removes step from pipeline and calls delete_flow_steps for all flows.
     * 
     * @param int $pipeline_id Pipeline ID containing the step
     * @param string $pipeline_step_id Pipeline step ID to delete
     * @return bool Success status
     */
    private function delete_pipeline_step($pipeline_id, $pipeline_step_id) {
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_pipelines || !$db_flows) {
            return false;
        }

        // Get current pipeline steps and remove the specified step
        $current_steps = $db_pipelines->get_pipeline_step_configuration($pipeline_id);
        $updated_steps = [];
        $step_found = false;
        
        foreach ($current_steps as $step) {
            if (($step['pipeline_step_id'] ?? '') !== $pipeline_step_id) {
                $updated_steps[] = $step;
            } else {
                $step_found = true;
            }
        }
        
        if (!$step_found) {
            return false; // Step not found
        }

        // Update pipeline configuration
        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'step_configuration' => json_encode($updated_steps)
        ]);
        
        if (!$success) {
            return false;
        }

        // Sync step deletion to all flows using layer 1
        $flows = $db_flows->get_flows_for_pipeline($pipeline_id);
        foreach ($flows as $flow) {
            $flow_id = is_object($flow) ? $flow->flow_id : $flow['flow_id'];
            $this->delete_flow_steps($flow_id, [$pipeline_step_id]);
        }
        
        return true;
    }

    /**
     * Layer 4 (Top): Delete entire pipeline with cascade deletion
     * 
     * Deletes all flows (using layer 2) then deletes the pipeline.
     * 
     * @param int $pipeline_id Pipeline ID to delete
     * @return bool Success status
     */
    private function delete_pipeline($pipeline_id) {
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_pipelines || !$db_flows) {
            return false;
        }

        // Get all flows for this pipeline and delete them using layer 2
        $flows = $db_flows->get_flows_for_pipeline($pipeline_id);
        foreach ($flows as $flow) {
            $flow_id = is_object($flow) ? $flow->flow_id : $flow['flow_id'];
            $success = $this->delete_flow($flow_id);
            if (!$success) {
                return false; // Fail fast if any flow deletion fails
            }
        }

        // Finally delete the pipeline itself
        return $db_pipelines->delete_pipeline($pipeline_id);
    }
}