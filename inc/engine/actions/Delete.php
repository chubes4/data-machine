<?php
/**
 * Data Machine Delete Actions
 *
 * Centralized deletion operations for all entity types - pipelines, flows, steps, and logs.
 * Eliminates code duplication across deletion types through unified validation, 
 * error handling, and service discovery patterns.
 *
 * SUPPORTED DELETION TYPES:
 * - pipeline: Cascade deletion of pipeline and associated flows
 * - flow: Single flow deletion with job preservation
 * - step: Pipeline step removal with flow synchronization
 * - logs: System log file clearing
 *
 * ARCHITECTURAL BENEFITS:
 * - Consistent permission checking across all deletion operations
 * - Unified validation patterns for different entity types
 * - Filter-based service discovery for database operations
 * - Centralized logging and error handling
 *
 * @package DataMachine
 * @since NEXT_VERSION
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Data Machine Delete Actions Class
 *
 * Handles centralized deletion operations through the dm_delete action hook.
 * Provides consistent validation, permission checking, and service discovery
 * patterns for all deletion types.
 *
 * @since NEXT_VERSION
 */
class DataMachine_Delete_Actions {

    /**
     * Register delete action hooks.
     *
     * Registers the central dm_delete action hook that routes to specific
     * deletion handlers based on entity type.
     *
     * @since NEXT_VERSION
     */
    public function register_actions() {
        // Central deletion action hook - eliminates code duplication across deletion types
        add_action('dm_delete', [$this, 'handle_delete'], 10, 3);
    }

    /**
     * Handle universal delete operations for all entity types.
     *
     * Central deletion handler that routes requests to specific deletion
     * handlers based on entity type. Provides consistent validation and
     * permission checking across all deletion operations.
     *
     * @param string $delete_type Type of entity to delete (pipeline|flow|step|logs)
     * @param mixed $target_id Target entity ID or identifier
     * @param array $context Additional context information
     * @since NEXT_VERSION
     */
    public function handle_delete($delete_type, $target_id, $context = []) {
        // Verify user capabilities - universal requirement for all deletions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions for delete operation.', 'data-machine')]);
            return;
        }
        
        // Validate delete type
        $valid_delete_types = ['pipeline', 'flow', 'step', 'logs'];
        if (!in_array($delete_type, $valid_delete_types)) {
            wp_send_json_error(['message' => __('Invalid deletion type.', 'data-machine')]);
            return;
        }
        
        // Validate target ID based on type
        if (in_array($delete_type, ['pipeline', 'flow']) && (!is_numeric($target_id) || (int)$target_id <= 0)) {
            wp_send_json_error(['message' => __('Valid target ID is required.', 'data-machine')]);
            return;
        }
        
        if ($delete_type === 'step' && (empty($target_id) || !is_string($target_id))) {
            wp_send_json_error(['message' => __('Valid pipeline step ID is required.', 'data-machine')]);
            return;
        }
        
        if ($delete_type === 'logs' && !empty($target_id)) {
            wp_send_json_error(['message' => __('Logs deletion does not require a target ID.', 'data-machine')]);
            return;
        }
        
        // Get database services using filter-based discovery (not needed for logs)
        if ($delete_type !== 'logs') {
            $all_databases = apply_filters('dm_db', []);
            $db_pipelines = $all_databases['pipelines'] ?? null;
            $db_flows = $all_databases['flows'] ?? null;
            
            if (!$db_pipelines || !$db_flows) {
                wp_send_json_error(['message' => __('Database services unavailable.', 'data-machine')]);
                return;
            }
        }
        
        // Route to specific deletion handler
        switch ($delete_type) {
            case 'pipeline':
                $this->handle_pipeline_deletion($target_id, $db_pipelines, $db_flows);
                break;
                
            case 'flow':
                $this->handle_flow_deletion($target_id, $db_flows);
                break;
                
            case 'step':
                $pipeline_id = (int)($context['pipeline_id'] ?? 0);
                if ($pipeline_id <= 0) {
                    wp_send_json_error(['message' => __('Pipeline ID is required for step deletion.', 'data-machine')]);
                    return;
                }
                $this->handle_step_deletion($target_id, $pipeline_id, $db_pipelines, $db_flows);
                break;
                
            case 'logs':
                $this->handle_logs_deletion();
                break;
        }
    }

    /**
     * Handle pipeline deletion with cascade to flows
     * 
     * Deletes a pipeline and all associated flows. Job records are preserved
     * as historical data. Provides detailed response with deletion statistics.
     * 
     * @param int $pipeline_id Pipeline ID to delete
     * @param object $db_pipelines Pipelines database service
     * @param object $db_flows Flows database service
     * @since NEXT_VERSION
     */
    private function handle_pipeline_deletion($pipeline_id, $db_pipelines, $db_flows) {
        // Get pipeline data for response before deletion
        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Pipeline not found.', 'data-machine')]);
            return;
        }
        
        $pipeline_name = is_object($pipeline) ? $pipeline->pipeline_name : $pipeline['pipeline_name'];
        $affected_flows = $db_flows->get_flows_for_pipeline($pipeline_id);
        $flow_count = count($affected_flows);

        // Delete all flows first (cascade)
        foreach ($affected_flows as $flow) {
            $flow_id = is_object($flow) ? $flow->flow_id : $flow['flow_id'];
            $success = $db_flows->delete_flow($flow_id);
            if (!$success) {
                wp_send_json_error(['message' => __('Failed to delete associated flows.', 'data-machine')]);
                return;
            }
        }

        // Finally delete the pipeline itself
        $success = $db_pipelines->delete_pipeline($pipeline_id);
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to delete pipeline.', 'data-machine')]);
            return;
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
     * Handle flow deletion
     * 
     * Deletes a single flow while preserving associated job records as
     * historical data. Provides detailed response with flow information.
     * 
     * @param int $flow_id Flow ID to delete
     * @param object $db_flows Flows database service
     * @since NEXT_VERSION
     */
    private function handle_flow_deletion($flow_id, $db_flows) {
        // Get flow data for response before deletion
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Flow not found.', 'data-machine')]);
            return;
        }
        
        $flow_name = is_object($flow) ? $flow->flow_name : $flow['flow_name'];
        $pipeline_id = is_object($flow) ? $flow->pipeline_id : $flow['pipeline_id'];

        // Delete the flow
        $success = $db_flows->delete_flow($flow_id);
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to delete flow.', 'data-machine')]);
            return;
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
     * Handle pipeline step deletion with sync to flows
     * 
     * Removes a step from pipeline configuration and synchronizes the removal
     * across all associated flows. Triggers auto-save operations and provides
     * detailed response with affected flow count.
     * 
     * @param string $pipeline_step_id Pipeline step ID to delete
     * @param int $pipeline_id Pipeline ID containing the step
     * @param object $db_pipelines Pipelines database service
     * @param object $db_flows Flows database service
     * @since NEXT_VERSION
     */
    private function handle_step_deletion($pipeline_step_id, $pipeline_id, $db_pipelines, $db_flows) {
        // Get pipeline data for response
        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Pipeline not found.', 'data-machine')]);
            return;
        }

        $pipeline_name = is_object($pipeline) ? $pipeline->pipeline_name : $pipeline['pipeline_name'];
        $affected_flows = $db_flows->get_flows_for_pipeline($pipeline_id);
        $flow_count = count($affected_flows);

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
            wp_send_json_error(['message' => __('Step not found in pipeline.', 'data-machine')]);
            return;
        }

        // Update pipeline configuration
        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'step_configuration' => json_encode($updated_steps)
        ]);
        
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to delete step from pipeline.', 'data-machine')]);
            return;
        }

        // Sync step deletion to all flows
        foreach ($affected_flows as $flow) {
            $flow_id = is_object($flow) ? $flow->flow_id : $flow['flow_id'];
            $flow_config = is_object($flow) ? $flow->flow_config : $flow['flow_config'];
            $flow_config = $flow_config ?: [];
            
            // Remove flow steps for this pipeline step ID
            foreach ($flow_config as $flow_step_id => $step_data) {
                if (isset($step_data['pipeline_step_id']) && $step_data['pipeline_step_id'] === $pipeline_step_id) {
                    unset($flow_config[$flow_step_id]);
                }
            }
            
            // Update flow with cleaned configuration
            $db_flows->update_flow($flow_id, [
                'flow_config' => json_encode($flow_config)
            ]);
        }

        // Trigger auto-save hooks
        do_action('dm_auto_save', $pipeline_id);

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
     * Handle logs deletion using Logger service
     *
     * Clears all system log files using the Logger service clear_logs method.
     * Provides success/failure response for UI feedback.
     *
     * @since NEXT_VERSION
     */
    private function handle_logs_deletion() {
        // Direct Logger instantiation - core engine component
        $logger = new \DataMachine\Engine\Logger();
        
        // Use Logger's clear_logs method for consistency
        $success = $logger->clear_logs();
        
        if ($success) {
            // Log the action
            do_action('dm_log', 'debug', 'Log files cleared successfully via central dm_delete action.');
            
            wp_send_json_success([
                'message' => __('Logs cleared successfully.', 'data-machine')
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to clear logs.', 'data-machine')]);
        }
    }
}