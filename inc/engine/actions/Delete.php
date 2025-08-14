<?php
/**
 * Data Machine Delete Actions
 *
 * Centralized deletion operations for all entity types - pipelines, flows, and steps.
 * Eliminates code duplication across deletion types through unified validation, 
 * error handling, and service discovery patterns.
 *
 * SUPPORTED DELETION TYPES:
 * - pipeline: Cascade deletion of pipeline and associated flows
 * - flow: Single flow deletion with job preservation
 * - step: Pipeline step removal with flow synchronization
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
     * Register delete action hooks using static method.
     *
     * Registers the central dm_delete action hook that routes to specific
     * deletion handlers based on entity type.
     *
     * @since NEXT_VERSION
     */
    public static function register() {
        $instance = new self();
        // Central deletion action hook - eliminates code duplication across deletion types
        add_action('dm_delete', [$instance, 'handle_delete'], 10, 3);
    }

    /**
     * Handle universal delete operations for all entity types.
     *
     * Central deletion handler that routes requests to specific deletion
     * handlers based on entity type. Provides consistent validation and
     * permission checking across all deletion operations.
     *
     * @param string $delete_type Type of entity to delete (pipeline|flow|step)
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
        $valid_delete_types = ['pipeline', 'flow', 'step', 'processed_items', 'jobs'];
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
        
        // Get database services using filter-based discovery
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        $db_flows = $all_databases['flows'] ?? null;
        $db_jobs = $all_databases['jobs'] ?? null;
        
        // Check required services based on delete type
        if (in_array($delete_type, ['pipeline', 'flow', 'step']) && (!$db_pipelines || !$db_flows)) {
            wp_send_json_error(['message' => __('Database services unavailable.', 'data-machine')]);
            return;
        }
        
        if ($delete_type === 'jobs' && !$db_jobs) {
            wp_send_json_error(['message' => __('Jobs database service unavailable.', 'data-machine')]);
            return;
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
                
            case 'processed_items':
                $this->handle_processed_items_deletion($target_id, $context);
                break;
                
            case 'jobs':
                $this->handle_jobs_deletion($target_id, $context, $db_jobs);
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
        $pipeline = apply_filters('dm_get_pipelines', [], $pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Pipeline not found.', 'data-machine')]);
            return;
        }
        
        $pipeline_name = $pipeline['pipeline_name'];
        $affected_flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
        $flow_count = count($affected_flows);

        // Delete all flows first (cascade)
        foreach ($affected_flows as $flow) {
            $flow_id = $flow['flow_id'];
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
        
        $flow_name = $flow['flow_name'];
        $pipeline_id = $flow['pipeline_id'];

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
        $pipeline = apply_filters('dm_get_pipelines', [], $pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Pipeline not found.', 'data-machine')]);
            return;
        }

        $pipeline_name = $pipeline['pipeline_name'];
        $affected_flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
        $flow_count = count($affected_flows);

        // Get current pipeline steps and remove the specified step
        $current_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
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
            'pipeline_config' => json_encode($updated_steps)
        ]);
        
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to delete step from pipeline.', 'data-machine')]);
            return;
        }

        // Sync step deletion to all flows
        foreach ($affected_flows as $flow) {
            $flow_id = $flow['flow_id'];
            $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
            
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
        $remaining_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        
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
     * Handle processed items deletion with flexible criteria
     * 
     * Deletes processed items based on the provided criteria. Supports
     * deletion by job_id, flow_id, source_type, or flow_step_id.
     * 
     * @param mixed $target_id Target identifier (job_id, flow_id, source_type, or flow_step_id)
     * @param array $context Context containing 'delete_by' criteria
     * @since NEXT_VERSION
     */
    private function handle_processed_items_deletion($target_id, $context) {
        $all_databases = apply_filters('dm_db', []);
        $processed_items = $all_databases['processed_items'] ?? null;
        
        if (!$processed_items) {
            do_action('dm_log', 'error', 'ProcessedItems service unavailable for cleanup');
            if (wp_doing_ajax()) {
                wp_send_json_error(['message' => __('ProcessedItems service unavailable.', 'data-machine')]);
            }
            return;
        }
        
        // Build criteria from context
        $criteria = [];
        $delete_by = $context['delete_by'] ?? 'job_id';
        
        switch ($delete_by) {
            case 'job_id':
                $criteria['job_id'] = (int)$target_id;
                break;
            case 'flow_id':
                $criteria['flow_id'] = (int)$target_id;
                break;
            case 'source_type':
                $criteria['source_type'] = (string)$target_id;
                break;
            case 'flow_step_id':
                $criteria['flow_step_id'] = (string)$target_id;
                break;
            case 'pipeline_id':
                $criteria['pipeline_id'] = (int)$target_id;
                break;
            default:
                do_action('dm_log', 'error', 'Invalid delete_by criteria for processed items', [
                    'delete_by' => $delete_by,
                    'target_id' => $target_id
                ]);
                if (wp_doing_ajax()) {
                    wp_send_json_error(['message' => __('Invalid deletion criteria.', 'data-machine')]);
                }
                return;
        }
        
        $result = $processed_items->delete_processed_items($criteria);
        
        // Always log the result for debugging (both AJAX and non-AJAX contexts)
        if ($result !== false) {
            do_action('dm_log', 'debug', 'Processed items deletion successful via dm_delete', [
                'criteria' => $criteria,
                'items_deleted' => $result,
                'context' => wp_doing_ajax() ? 'AJAX' : 'non-AJAX'
            ]);
        } else {
            do_action('dm_log', 'error', 'Processed items deletion failed via dm_delete', [
                'criteria' => $criteria,
                'context' => wp_doing_ajax() ? 'AJAX' : 'non-AJAX'
            ]);
        }
        
        // Send JSON response only if in AJAX context
        if (wp_doing_ajax()) {
            if ($result !== false) {
                wp_send_json_success([
                    'message' => sprintf(__('Deleted %d processed items.', 'data-machine'), $result),
                    'items_deleted' => $result
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to delete processed items.', 'data-machine')]);
            }
        }
        
        // Return result for non-AJAX usage (like job failure cleanup)
        return $result;
    }
    
    /**
     * Handle jobs deletion with optional processed items cleanup
     * 
     * Deletes jobs based on criteria (all or failed) and optionally
     * cleans up associated processed items for deleted jobs.
     * 
     * @param string $clear_type Type of jobs to clear ('all' or 'failed')
     * @param array $context Context containing cleanup options
     * @param object $db_jobs Jobs database service
     * @since NEXT_VERSION
     */
    private function handle_jobs_deletion($clear_type, $context, $db_jobs) {
        $cleanup_processed = !empty($context['cleanup_processed']);
        
        // Get job IDs before deletion (for processed items cleanup)
        $job_ids_to_delete = [];
        if ($cleanup_processed) {
            global $wpdb;
            $jobs_table = $wpdb->prefix . 'dm_jobs';
            
            if ($clear_type === 'failed') {
                $job_ids_to_delete = $wpdb->get_col("SELECT job_id FROM {$jobs_table} WHERE status = 'failed'");
            } else {
                $job_ids_to_delete = $wpdb->get_col("SELECT job_id FROM {$jobs_table}");
            }
        }
        
        // Build deletion criteria
        $criteria = [];
        if ($clear_type === 'failed') {
            $criteria['failed'] = true;
        } else {
            $criteria['all'] = true;
        }
        
        // Delete jobs
        $deleted_count = $db_jobs->delete_jobs($criteria);
        
        if ($deleted_count === false) {
            wp_send_json_error(['message' => __('Failed to delete jobs.', 'data-machine')]);
            return;
        }
        
        // Clean up processed items if requested
        $processed_items_deleted = 0;
        if ($cleanup_processed && !empty($job_ids_to_delete)) {
            foreach ($job_ids_to_delete as $job_id) {
                do_action('dm_delete', 'processed_items', $job_id, ['delete_by' => 'job_id']);
            }
            // Note: We don't count processed items deleted as each dm_delete call is independent
        }
        
        $message_parts = [];
        $message_parts[] = sprintf(__('Deleted %d jobs', 'data-machine'), $deleted_count);
        
        if ($cleanup_processed && !empty($job_ids_to_delete)) {
            $message_parts[] = __('and their associated processed items', 'data-machine');
        }
        
        $message = implode(' ', $message_parts) . '.';
        
        do_action('dm_log', 'debug', 'Jobs deletion completed', [
            'clear_type' => $clear_type,
            'jobs_deleted' => $deleted_count,
            'cleanup_processed' => $cleanup_processed,
            'job_ids_cleaned' => $cleanup_processed ? count($job_ids_to_delete) : 0
        ]);
        
        wp_send_json_success([
            'message' => $message,
            'jobs_deleted' => $deleted_count,
            'processed_items_cleaned' => $cleanup_processed ? count($job_ids_to_delete) : 0
        ]);
    }

}