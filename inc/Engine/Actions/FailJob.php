<?php
/**
 * Dedicated job failure operations.
 *
 * Single responsibility: Handle all job failure scenarios with
 * proper cleanup, logging, and state management.
 *
 * @package DataMachine\Engine\Actions
 * @since 0.2.4
 */

namespace DataMachine\Engine\Actions;

use DataMachine\Core\PluginSettings;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Dedicated job failure operations class.
 *
 * Provides centralized job failure handling with configurable cleanup
 * and comprehensive logging for all failure scenarios.
 */
class FailJob {

    /**
     * Register job failure action hooks.
     */
    public static function register() {
        $instance = new self();
        add_action('datamachine_fail_job', [$instance, 'handle_job_failure'], 10, 3);
    }

    /**
     * Handle explicit job failure with cleanup and logging.
     *
     * Always marks job as failed, cleans up processed items, and logs failure details.
     * Provides configurable file cleanup based on admin settings.
     *
     * @param int $job_id Job ID to mark as failed
     * @param string $reason Failure reason identifier
     * @param array $context_data Additional context for logging
     * @return bool True on success, false on database failure
     */
    public function handle_job_failure($job_id, $reason, $context_data = []) {
        $job_id = (int) $job_id; // Ensure job_id is int for database operations

        $db_jobs = new \DataMachine\Core\Database\Jobs\Jobs();

        // Always use complete_job method for failed status (sets completion timestamp)
        $success = $db_jobs->complete_job($job_id, 'failed');

        if (!$success) {
            do_action('datamachine_log', 'error', 'Failed to mark job as failed in database', [
                'job_id' => $job_id,
                'reason' => $reason
            ]);
            return false;
        }

        // Clean up processed items to allow retry (existing logic from handle_job_status_update)
        do_action('datamachine_delete_processed_items', ['job_id' => (int)$job_id]);

        // Conditional file cleanup based on settings
        $cleanup_files = PluginSettings::get('cleanup_job_data_on_failure', true);
        $files_cleaned = false;

        if ($cleanup_files) {
            $cleanup = new \DataMachine\Core\FilesRepository\FileCleanup();
            // Get flow_id from job to build file context
            $job = $db_jobs->get_job($job_id);
            if ($job && function_exists('datamachine_get_file_context')) {
                $context = datamachine_get_file_context($job['flow_id']);
                $deleted_count = $cleanup->cleanup_job_data_packets($job_id, $context);
                $files_cleaned = $deleted_count > 0;
            }
        }

        // Enhanced logging with failure details
        do_action('datamachine_log', 'error', 'Job marked as failed', [
            'job_id' => $job_id,
            'failure_reason' => $reason,
            'triggered_by' => 'datamachine_fail_job_action',
            'context_data' => $context_data,
            'processed_items_cleaned' => true,
            'files_cleanup_enabled' => $cleanup_files,
            'files_cleaned' => $files_cleaned
        ]);

        return true;
    }
}