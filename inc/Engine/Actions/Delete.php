<?php
namespace DataMachine\Engine\Actions;


/**
 * Centralized deletion operations with cascade support via dm_delete action.
 *
 * @package DataMachine\Engine\Actions
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Centralized deletion operations via dm_delete action hook.
 */
class Delete {

    /**
     * Register deletion action hooks.
     */
    public static function register() {
        $instance = new self();
        // Central deletion routing
        add_action('dm_delete', [$instance, 'handle_delete'], 10, 3);
        
    }
    

    /**
     * Handle deletion operations with routing by entity type.
     *
     * @param string $delete_type Entity type (pipeline|flow|step)
     * @param mixed $target_id Entity ID or identifier
     * @param array $context Additional context
     */
    public function handle_delete($delete_type, $target_id, $context = []) {
        // Verify user capabilities - universal requirement for all deletions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions for delete operation.', 'data-machine')]);
            return;
        }
        
        $valid_delete_types = ['pipeline', 'flow', 'step', 'processed_items', 'jobs'];
        if (!in_array($delete_type, $valid_delete_types)) {
            wp_send_json_error(['message' => __('Invalid deletion type.', 'data-machine')]);
            return;
        }
        
        if (in_array($delete_type, ['pipeline', 'flow']) && (!is_numeric($target_id) || (int)$target_id <= 0)) {
            wp_send_json_error(['message' => __('Valid target ID is required.', 'data-machine')]);
            return;
        }
        
        if ($delete_type === 'step' && (empty($target_id) || !is_string($target_id))) {
            wp_send_json_error(['message' => __('Valid pipeline step ID is required.', 'data-machine')]);
            return;
        }
        
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        $db_flows = $all_databases['flows'] ?? null;
        $db_jobs = $all_databases['jobs'] ?? null;
        
        if (in_array($delete_type, ['pipeline', 'flow', 'step']) && (!$db_pipelines || !$db_flows)) {
            wp_send_json_error(['message' => __('Database services unavailable.', 'data-machine')]);
            return;
        }
        
        if ($delete_type === 'jobs' && !$db_jobs) {
            wp_send_json_error(['message' => __('Jobs database service unavailable.', 'data-machine')]);
            return;
        }
        
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
     * @since 1.0.0
     */
    private function handle_pipeline_deletion($pipeline_id, $db_pipelines, $db_flows) {
        $pipeline = apply_filters('dm_get_pipelines', [], $pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Pipeline not found.', 'data-machine')]);
            return;
        }
        
        $pipeline_name = $pipeline['pipeline_name'];
        $affected_flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
        $flow_count = count($affected_flows);

        foreach ($affected_flows as $flow) {
            $flow_id = $flow['flow_id'];
            $success = $db_flows->delete_flow($flow_id);
            if (!$success) {
                wp_send_json_error(['message' => __('Failed to delete associated flows.', 'data-machine')]);
                return;
            }
        }

        $success = $db_pipelines->delete_pipeline($pipeline_id);
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to delete pipeline.', 'data-machine')]);
            return;
        }

        do_action('dm_pipeline_deleted', $pipeline_id, $flow_count);

        // Clear pipelines list cache (pipeline deletion affects dropdown lists)
        do_action('dm_clear_pipelines_list_cache');

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %1$s: Pipeline name, %2$d: Number of flows deleted */
                __('Pipeline "%1$s" deleted successfully. %2$d flows were also deleted. Associated job records are preserved as historical data.', 'data-machine'),
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
     * @since 1.0.0
     */
    private function handle_flow_deletion($flow_id, $db_flows) {
        $flow = apply_filters('dm_get_flow', null, $flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Flow not found.', 'data-machine')]);
            return;
        }
        
        $flow_name = $flow['flow_name'];
        $pipeline_id = $flow['pipeline_id'];

        $success = $db_flows->delete_flow($flow_id);
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to delete flow.', 'data-machine')]);
            return;
        }


        do_action('dm_flow_deleted', $pipeline_id, $flow_id);

        do_action('dm_clear_pipeline_cache', $pipeline_id);

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %s: Flow name */
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
     * @since 1.0.0
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

        // Remove specified step from pipeline
        $current_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        $remaining_steps = [];
        $step_found = false;
        
        foreach ($current_steps as $step) {
            if (($step['pipeline_step_id'] ?? '') !== $pipeline_step_id) {
                $remaining_steps[] = $step;
            } else {
                $step_found = true;
            }
        }
        
        // Resequence execution_order values to ensure sequential ordering (0, 1, 2, etc.)
        $updated_steps = [];
        foreach ($remaining_steps as $index => $step) {
            $step['execution_order'] = $index; // Reset to sequential values
            $updated_steps[$step['pipeline_step_id']] = $step;
        }
        
        if (!$step_found) {
            wp_send_json_error(['message' => __('Step not found in pipeline.', 'data-machine')]);
            return;
        }

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
            $flow_config = $flow['flow_config'] ?? [];
            
            // Remove matching flow steps
            foreach ($flow_config as $flow_step_id => $step_data) {
                if (isset($step_data['pipeline_step_id']) && $step_data['pipeline_step_id'] === $pipeline_step_id) {
                    unset($flow_config[$flow_step_id]);
                }
            }
            
            // Update flow with cleaned configuration
            apply_filters('dm_update_flow', false, $flow_id, [
                'flow_config' => json_encode($flow_config)
            ]);
        }

        // Clear pipeline cache immediately after flow updates (before auto-save)
        do_action('dm_clear_pipeline_cache', $pipeline_id);

        // Trigger auto-save hooks for database persistence
        do_action('dm_auto_save', $pipeline_id);

        // Get remaining steps count for response
        $remaining_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %1$s: Pipeline name, %2$d: Number of affected flows */
                __('Step deleted successfully from pipeline "%1$s". %2$d flows were affected.', 'data-machine'),
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
     * @since 1.0.0
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
        
        // Log the result for errors only
        if ($result === false) {
            do_action('dm_log', 'error', 'Processed items deletion failed via dm_delete', [
                'criteria' => $criteria,
                'context' => wp_doing_ajax() ? 'AJAX' : 'non-AJAX'
            ]);
        }
        
        // Send JSON response only if in AJAX context
        if (wp_doing_ajax()) {
            if ($result !== false) {
                wp_send_json_success([
                    /* translators: %d: Number of processed items deleted */
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
     * @since 1.0.0
     */
    private function handle_jobs_deletion($clear_type, $context, $db_jobs) {
        $cleanup_processed = !empty($context['cleanup_processed']);
        
        // Get job IDs before deletion (for processed items cleanup)
        $job_ids_to_delete = [];
        if ($cleanup_processed) {
            global $wpdb;
            $jobs_table = $wpdb->prefix . 'dm_jobs';
            
            if ($clear_type === 'failed') {
                $job_ids_to_delete = $wpdb->get_col($wpdb->prepare("SELECT job_id FROM %i WHERE status = %s", $jobs_table, 'failed')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            } else {
                $job_ids_to_delete = $wpdb->get_col($wpdb->prepare("SELECT job_id FROM %i", $jobs_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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
        /* translators: %d: Number of jobs deleted */
        $message_parts[] = sprintf(__('Deleted %d jobs', 'data-machine'), $deleted_count);
        
        if ($cleanup_processed && !empty($job_ids_to_delete)) {
            $message_parts[] = __('and their associated processed items', 'data-machine');
        }
        
        $message = implode(' ', $message_parts) . '.';

        // Clear job-related caches before response
        if ($deleted_count > 0) {
            do_action('dm_clear_jobs_cache');
        }

        wp_send_json_success([
            'message' => $message,
            'jobs_deleted' => $deleted_count,
            'processed_items_cleaned' => $cleanup_processed ? count($job_ids_to_delete) : 0
        ]);
    }

}