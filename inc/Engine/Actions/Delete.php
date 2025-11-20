<?php
namespace DataMachine\Engine\Actions;

use WP_Error;

/**
 * Centralized deletion operations with cascade support.
 *
 * @package DataMachine\Engine\Actions
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Centralized deletion operations for REST endpoints and internal engine operations.
 */
class Delete {

    /**
     * Register deletion action hooks.
     */
    public static function register() {
        $instance = new self();
        // Granular deletion actions
        add_action('datamachine_delete_pipeline', [$instance, 'handle_pipeline_deletion'], 10, 1);
        add_action('datamachine_delete_flow', [$instance, 'handle_flow_deletion'], 10, 1);
        add_action('datamachine_delete_step', [$instance, 'handle_step_deletion'], 10, 2);
        add_action('datamachine_delete_processed_items', [$instance, 'handle_processed_items_deletion'], 10, 1);
        add_action('datamachine_delete_jobs', [$instance, 'handle_jobs_deletion'], 10, 2);
        add_action('datamachine_delete_logs', [$instance, 'handle_logs_deletion'], 10, 0);
    }

    /**
     * Handle pipeline deletion with cascade to flows
     *
     * Deletes a pipeline and all associated flows. Job records are preserved
     * as historical data. Provides detailed response with deletion statistics.
     *
     * @param int $pipeline_id Pipeline ID to delete
     * @since 1.0.0
     */
    public function handle_pipeline_deletion($pipeline_id) {
        $result = self::delete_pipeline((int) $pipeline_id);

        if (is_wp_error($result)) {
            if (wp_doing_ajax()) {
                wp_send_json_error(['message' => $result->get_error_message()], $result->get_error_data()['status'] ?? null);
            }
            return $result;
        }

        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();

        if (wp_doing_ajax()) {
            wp_send_json_success($result);
        }

        return $result;
    }

    /**
     * Handle flow deletion
     *
     * Deletes a single flow while preserving associated job records as
     * historical data. Provides detailed response with flow information.
     *
     * @param int $flow_id Flow ID to delete
     * @since 1.0.0
     */
    public function handle_flow_deletion($flow_id) {
        $result = self::delete_flow((int) $flow_id);

        if (is_wp_error($result)) {
            if (wp_doing_ajax()) {
                wp_send_json_error(['message' => $result->get_error_message()], $result->get_error_data()['status'] ?? null);
            }
            return $result;
        }

        if (wp_doing_ajax()) {
            wp_send_json_success($result);
        }

        return $result;
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
     * @since 1.0.0
     */
    public function handle_step_deletion($pipeline_step_id, $pipeline_id) {
        $result = self::delete_pipeline_step((string) $pipeline_step_id, (int) $pipeline_id);

        if (is_wp_error($result)) {
            if (wp_doing_ajax()) {
                wp_send_json_error(['message' => $result->get_error_message()], $result->get_error_data()['status'] ?? null);
            }
            return $result;
        }

        $pipeline_id = absint($pipeline_id);
        if ($pipeline_id <= 0) {
            return new WP_Error(
                'invalid_pipeline_id',
                __('Valid pipeline ID is required.', 'datamachine'),
                ['status' => 400]
            );
        }

        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();

        if (wp_doing_ajax()) {
            wp_send_json_success($result);
        }

        return $result;
    }

    /**
     * Perform pipeline deletion and return operation metadata.
     */
    public static function delete_pipeline(int $pipeline_id)
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('Insufficient permissions', 'datamachine'),
                ['status' => 403]
            );
        }

        $pipeline_id = absint($pipeline_id);
        if ($pipeline_id <= 0) {
            return new WP_Error(
                'invalid_pipeline_id',
                __('Valid pipeline ID is required.', 'datamachine'),
                ['status' => 400]
            );
        }

        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();

        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            return new WP_Error(
                'pipeline_not_found',
                __('Pipeline not found.', 'datamachine'),
                ['status' => 404]
            );
        }

        if (!isset($pipeline['pipeline_name']) || empty(trim($pipeline['pipeline_name']))) {
            do_action('datamachine_log', 'error', 'Cannot delete pipeline - missing or empty pipeline name', [
                'pipeline_id' => $pipeline_id
            ]);
            return new WP_Error(
                'data_integrity_error',
                __('Pipeline data is corrupted - missing name.', 'datamachine'),
                ['status' => 500]
            );
        }
        $pipeline_name = $pipeline['pipeline_name'];
        $affected_flows = $db_flows->get_flows_for_pipeline($pipeline_id);
        $flow_count = count($affected_flows);

        foreach ($affected_flows as $flow) {
            if (!isset($flow['flow_id']) || empty($flow['flow_id'])) {
                do_action('datamachine_log', 'error', 'Flow data missing flow_id during pipeline deletion', [
                    'pipeline_id' => $pipeline_id,
                    'flow' => $flow
                ]);
                continue;
            }
            $flow_id = (int) $flow['flow_id'];
            $success = $db_flows->delete_flow($flow_id);
            if (!$success) {
                return new WP_Error(
                    'flow_deletion_failed',
                    __('Failed to delete associated flows.', 'datamachine'),
                    ['status' => 500]
                );
            }
        }

        $cleanup = new \DataMachine\Core\FilesRepository\FileCleanup();
        $filesystem_deleted = $cleanup->delete_pipeline_directory($pipeline_id);

        if (!$filesystem_deleted) {
            do_action('datamachine_log', 'warning', 'Pipeline filesystem cleanup failed, but continuing with database deletion.', [
                'pipeline_id' => $pipeline_id
            ]);
        }

        $success = $db_pipelines->delete_pipeline($pipeline_id);
        if (!$success) {
            return new WP_Error(
                'pipeline_deletion_failed',
                __('Failed to delete pipeline.', 'datamachine'),
                ['status' => 500]
            );
        }

        do_action('datamachine_clear_pipelines_list_cache');

        return [
            'message' => sprintf(
                /* translators: %1$s: Pipeline name, %2$d: Number of flows deleted */
                __('Pipeline "%1$s" deleted successfully. %2$d flows were also deleted. All files and directories have been removed. Associated job records are preserved as historical data.', 'datamachine'),
                $pipeline_name,
                $flow_count
            ),
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'deleted_flows' => $flow_count
        ];
    }

    /**
     * Perform flow deletion and return operation metadata.
     */
    public static function delete_flow(int $flow_id)
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('Insufficient permissions', 'datamachine'),
                ['status' => 403]
            );
        }

        $flow_id = absint($flow_id);
        if ($flow_id <= 0) {
            return new WP_Error(
                'invalid_flow_id',
                __('Valid flow ID is required.', 'datamachine'),
                ['status' => 400]
            );
        }

        $db_flows = new \DataMachine\Core\Database\Flows\Flows();

        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            return new WP_Error(
                'flow_not_found',
                __('Flow not found.', 'datamachine'),
                ['status' => 404]
            );
        }

        if (!isset($flow['flow_name']) || empty(trim($flow['flow_name']))) {
            do_action('datamachine_log', 'error', 'Cannot delete flow - missing or empty flow name', [
                'flow_id' => $flow_id
            ]);
            return new WP_Error(
                'data_integrity_error',
                __('Flow data is corrupted - missing name.', 'datamachine'),
                ['status' => 500]
            );
        }
        $flow_name = $flow['flow_name'];
        if (!isset($flow['pipeline_id']) || empty($flow['pipeline_id'])) {
            return new WP_Error(
                'invalid_flow_data',
                __('Flow data is missing required pipeline_id.', 'datamachine'),
                ['status' => 400]
            );
        }
        $pipeline_id = (int) $flow['pipeline_id'];

        $success = $db_flows->delete_flow($flow_id);
        if (!$success) {
            return new WP_Error(
                'flow_deletion_failed',
                __('Failed to delete flow.', 'datamachine'),
                ['status' => 500]
            );
        }

        if ($pipeline_id > 0) {
            do_action('datamachine_clear_pipeline_cache', $pipeline_id);
        }

        return [
            'message' => sprintf(
                /* translators: %s: Flow name */
                __('Flow "%s" deleted successfully. Associated job records are preserved as historical data.', 'datamachine'),
                $flow_name
            ),
            'flow_id' => $flow_id,
            'flow_name' => $flow_name,
            'pipeline_id' => $pipeline_id
        ];
    }

    /**
     * Perform pipeline step deletion and return operation metadata.
     */
    public static function delete_pipeline_step(string $pipeline_step_id, int $pipeline_id)
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('Insufficient permissions', 'datamachine'),
                ['status' => 403]
            );
        }

        $pipeline_id = absint($pipeline_id);
        $pipeline_step_id = trim($pipeline_step_id);

        if ($pipeline_id <= 0 || $pipeline_step_id === '') {
            return new WP_Error(
                'invalid_step_parameters',
                __('Valid pipeline ID and step ID are required.', 'datamachine'),
                ['status' => 400]
            );
        }

        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();

        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            return new WP_Error(
                'pipeline_not_found',
                __('Pipeline not found.', 'datamachine'),
                ['status' => 404]
            );
        }

        if (!isset($pipeline['pipeline_name']) || empty(trim($pipeline['pipeline_name']))) {
            do_action('datamachine_log', 'error', 'Cannot delete pipeline step - pipeline missing or empty name', [
                'pipeline_id' => $pipeline_id,
                'pipeline_step_id' => $pipeline_step_id
            ]);
            return new WP_Error(
                'data_integrity_error',
                __('Pipeline data is corrupted - missing name.', 'datamachine'),
                ['status' => 500]
            );
        }
        $pipeline_name = $pipeline['pipeline_name'];
        $affected_flows = $db_flows->get_flows_for_pipeline($pipeline_id);
        $flow_count = count($affected_flows);

        $current_steps = $db_pipelines->get_pipeline_config($pipeline_id);
        $remaining_steps = [];
        $step_found = false;

        foreach ($current_steps as $step) {
            if (($step['pipeline_step_id'] ?? '') !== $pipeline_step_id) {
                $remaining_steps[] = $step;
            } else {
                $step_found = true;
            }
        }

        if (!$step_found) {
            return new WP_Error(
                'step_not_found',
                __('Step not found in pipeline.', 'datamachine'),
                ['status' => 404]
            );
        }

        $updated_steps = [];
        foreach ($remaining_steps as $index => $step) {
            $step['execution_order'] = $index;
            $updated_steps[$step['pipeline_step_id']] = $step;
        }

        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_config' => json_encode($updated_steps)
        ]);

        if (!$success) {
            return new WP_Error(
                'step_deletion_failed',
                __('Failed to delete step from pipeline.', 'datamachine'),
                ['status' => 500]
            );
        }

        foreach ($affected_flows as $flow) {
            if (!isset($flow['flow_id']) || empty($flow['flow_id'])) {
                do_action('datamachine_log', 'error', 'Flow data missing flow_id during step deletion', [
                    'pipeline_id' => $pipeline_id,
                    'flow' => $flow
                ]);
                continue;
            }
            $flow_id = (int) $flow['flow_id'];

            $flow_config = $flow['flow_config'] ?? [];

            foreach ($flow_config as $flow_step_id => $step_data) {
                if (isset($step_data['pipeline_step_id']) && $step_data['pipeline_step_id'] === $pipeline_step_id) {
                    unset($flow_config[$flow_step_id]);
                }
            }

            $db_flows->update_flow($flow_id, [
                'flow_config' => json_encode($flow_config)
            ]);
        }

        do_action('datamachine_clear_pipeline_cache', $pipeline_id);
        do_action('datamachine_auto_save', $pipeline_id);

        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $remaining_steps = $db_pipelines->get_pipeline_config($pipeline_id);

        return [
            'message' => sprintf(
                /* translators: %1$s: Pipeline name, %2$d: Number of affected flows */
                __('Step deleted successfully from pipeline "%1$s". %2$d flows were affected.', 'datamachine'),
                $pipeline_name,
                $flow_count
            ),
            'pipeline_id' => $pipeline_id,
            'pipeline_step_id' => $pipeline_step_id,
            'affected_flows' => $flow_count,
            'remaining_steps' => count($remaining_steps)
        ];
    }
    
    /**
     * Handle processed items deletion with flexible criteria
     *
     * Deletes processed items based on the provided criteria array. Supports
     * deletion by job_id, flow_id, source_type, flow_step_id, or pipeline_id.
     *
     * @param array $criteria Deletion criteria (e.g., ['job_id' => 123] or ['flow_id' => 456])
     * @since 1.0.0
     */
    public function handle_processed_items_deletion($criteria) {
        $processed_items = new \DataMachine\Core\Database\ProcessedItems\ProcessedItems();

        if (empty($criteria) || !is_array($criteria)) {
            do_action('datamachine_log', 'error', 'Invalid criteria for processed items deletion', ['criteria' => $criteria]);
            if (wp_doing_ajax()) {
                wp_send_json_error(['message' => __('Invalid deletion criteria.', 'datamachine')]);
            }
            return;
        }

        $result = $processed_items->delete_processed_items($criteria);

        if ($result === false) {
            do_action('datamachine_log', 'error', 'Processed items deletion failed', [
                'criteria' => $criteria,
                'context' => wp_doing_ajax() ? 'AJAX' : 'non-AJAX'
            ]);
        }

        if (wp_doing_ajax()) {
            if ($result !== false) {
                wp_send_json_success([
                    /* translators: %d: Number of processed items deleted */
                    'message' => sprintf(__('Deleted %d processed items.', 'datamachine'), $result),
                    'items_deleted' => $result
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to delete processed items.', 'datamachine')]);
            }
        }

        return $result;
    }
    
    /**
     * Handle jobs deletion with optional processed items cleanup
     *
     * Deletes jobs based on criteria (all or failed) and optionally
     * cleans up associated processed items for deleted jobs.
     *
     * @param string $clear_type Type of jobs to clear ('all' or 'failed')
     * @param bool $cleanup_processed Whether to cleanup associated processed items
     * @since 1.0.0
     */
    public function handle_jobs_deletion($clear_type, $cleanup_processed = false) {
        $db_jobs = new \DataMachine\Core\Database\Jobs\Jobs();

        $job_ids_to_delete = [];
        if ($cleanup_processed) {
            global $wpdb;
            $jobs_table = $wpdb->prefix . 'datamachine_jobs';

            if ($clear_type === 'failed') {
                $job_ids_to_delete = $wpdb->get_col($wpdb->prepare("SELECT job_id FROM %i WHERE status = %s", $jobs_table, 'failed')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            } else {
                $job_ids_to_delete = $wpdb->get_col($wpdb->prepare("SELECT job_id FROM %i", $jobs_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            }
        }

        $criteria = [];
        if ($clear_type === 'failed') {
            $criteria['failed'] = true;
        } else {
            $criteria['all'] = true;
        }

        $deleted_count = $db_jobs->delete_jobs($criteria);

        if ($deleted_count === false) {
            wp_send_json_error(['message' => __('Failed to delete jobs.', 'datamachine')]);
            return;
        }

        if ($cleanup_processed && !empty($job_ids_to_delete)) {
            foreach ($job_ids_to_delete as $job_id) {
                do_action('datamachine_delete_processed_items', ['job_id' => (int)$job_id]);
            }
        }

        $message_parts = [];
        /* translators: %d: Number of jobs deleted */
        $message_parts[] = sprintf(__('Deleted %d jobs', 'datamachine'), $deleted_count);

        if ($cleanup_processed && !empty($job_ids_to_delete)) {
            $message_parts[] = __('and their associated processed items', 'datamachine');
        }

        $message = implode(' ', $message_parts) . '.';

        if ($deleted_count > 0) {
            do_action('datamachine_clear_jobs_cache');
        }

        wp_send_json_success([
            'message' => $message,
            'jobs_deleted' => $deleted_count,
            'processed_items_cleaned' => $cleanup_processed ? count($job_ids_to_delete) : 0
        ]);
    }

    /**
     * Handle logs deletion
     *
     * Clears the Data Machine log file.
     *
     * @since 1.0.0
     */
      public function handle_logs_deletion() {
          $log_file = datamachine_get_log_file_path();

          // Ensure directory exists
          $log_dir = dirname($log_file);
          if (!file_exists($log_dir)) {
              wp_mkdir_p($log_dir);
          }

          $result = file_put_contents($log_file, '');
          return $result !== false;
      }

}