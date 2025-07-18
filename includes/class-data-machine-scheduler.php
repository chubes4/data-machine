<?php
/**
 * Manages WP Cron scheduling and execution for Data Machine projects and modules.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes
 * @since      [Next Version]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Data_Machine_Scheduler {

    /** @var ?Data_Machine_Logger */
    private $logger;

    /** @var Data_Machine_Job_Executor */
    private $job_executor;

    /** @var Data_Machine_Database_Projects */
    private $db_projects;

    /** @var Data_Machine_Database_Modules */
    private $db_modules;

    /** @var Data_Machine_Action_Scheduler */
    private $action_scheduler;

    /** @var Data_Machine_Database_Jobs */
    private $db_jobs;

    /**
     * Constructor.
     *
     * @param Data_Machine_Job_Executor $job_executor Job Executor service.
     * @param Data_Machine_Database_Projects $db_projects Projects DB service.
     * @param Data_Machine_Database_Modules $db_modules Modules DB service.
     * @param Data_Machine_Action_Scheduler $action_scheduler Action Scheduler service.
     * @param Data_Machine_Database_Jobs $db_jobs Jobs DB service.
     * @param Data_Machine_Logger|null $logger Logger service (optional).
     */
    public function __construct(
        Data_Machine_Job_Executor $job_executor,
        Data_Machine_Database_Projects $db_projects,
        Data_Machine_Database_Modules $db_modules,
        Data_Machine_Action_Scheduler $action_scheduler,
        Data_Machine_Database_Jobs $db_jobs,
        ?Data_Machine_Logger $logger = null
    ) {
        $this->job_executor = $job_executor;
        $this->db_projects = $db_projects;
        $this->db_modules = $db_modules;
        $this->action_scheduler = $action_scheduler;
        $this->db_jobs = $db_jobs;
        $this->logger = $logger;
    }

    /**
     * Initialize WordPress hooks for scheduling and execution.
     */
    public function init_hooks() {
        // Hook the callbacks for WP Cron events
        add_action( 'dm_run_project_schedule_callback', [ $this, 'dm_run_project_schedule_callback' ], 10, 1 );
        add_action( 'dm_run_module_schedule_callback', [ $this, 'dm_run_module_schedule_callback' ], 10, 1 );

        // Add custom cron schedules (moved from Data_Machine class)
        add_filter( 'cron_schedules', [ $this, 'add_custom_cron_schedules' ] );
    }

    /**
     * Adds custom cron schedules.
     * (Content moved from Data_Machine::add_custom_cron_schedules)
     * @param array $schedules Existing cron schedules.
     * @return array Modified schedules.
     */
    public function add_custom_cron_schedules( $schedules ) {
        // Use the constants to define schedules
        foreach (Data_Machine_Constants::CRON_SCHEDULES as $interval_slug => $details) {
            $schedules[$interval_slug] = array(
                // 'interval' => wp_get_schedule_interval($interval), // OLD - Incorrect function
                'interval' => $details['interval'], // Use interval in seconds from constant
                'display'  => __($details['label'], 'data-machine') // Use label from constant
            );
        }
        // Ensure standard WP intervals are present (hourly, twicedaily, daily)
        // WP Core adds these, so just ensure ours are there.
        // Remove the manual addition of 'every_5_minutes' as it's now handled by the loop.
        return $schedules;
    }

    /**
     * Clears and potentially schedules the project-level cron event.
     * @param int    $project_id The project ID.
     * @param string $interval   The desired interval ('hourly', 'daily', etc., or 'manual').
     * @param string $status     The desired status ('active' or 'paused').
     */
    public function schedule_project(int $project_id, string $interval, string $status) {
        $hook = 'dm_run_project_schedule_callback';
        $args = ['project_id' => $project_id];

        // Always clear the existing hook first
        $this->action_scheduler->cancel_scheduled_jobs( $hook, $args );

        // Schedule if active and interval is valid
        $allowed_intervals = Data_Machine_Constants::get_project_cron_intervals();
        if ($status === 'active' && in_array($interval, $allowed_intervals)) {
            // Get interval in seconds from constants
            $interval_seconds = Data_Machine_Constants::CRON_SCHEDULES[$interval]['interval'];
            $this->action_scheduler->schedule_recurring_job( time(), $interval_seconds, $hook, $args );
        }
    }

    /**
     * Clears and potentially schedules the module-level cron event.
     * @param int    $module_id The module ID.
     * @param string $interval  The desired interval ('hourly', etc., NOT 'project_schedule' or 'manual').
     * @param string $status    The desired status ('active' or 'paused').
     */
    public function schedule_module(int $module_id, string $interval, string $status) {
        $hook = 'dm_run_module_schedule_callback';
        $args = ['module_id' => $module_id];

        // Always clear the existing hook first
        $this->action_scheduler->cancel_scheduled_jobs( $hook, $args );

        // Schedule if active and interval is valid AND NOT project_schedule/manual
        $allowed_intervals = Data_Machine_Constants::get_module_cron_intervals();
        if ($status === 'active' && in_array($interval, $allowed_intervals)) {
            // Get interval in seconds from constants
            $interval_seconds = Data_Machine_Constants::CRON_SCHEDULES[$interval]['interval'];
            $this->action_scheduler->schedule_recurring_job( time(), $interval_seconds, $hook, $args );
        }
    }

    /**
     * Unschedule project-level cron event.
     * @param int $project_id The project ID.
     */
    public function unschedule_project(int $project_id) {
        $this->action_scheduler->cancel_scheduled_jobs( 'dm_run_project_schedule_callback', ['project_id' => $project_id] );
    }

    /**
     * Unschedule module-level cron event.
     * @param int $module_id The module ID.
     */
    public function unschedule_module(int $module_id) {
        $this->action_scheduler->cancel_scheduled_jobs( 'dm_run_module_schedule_callback', ['module_id' => $module_id] );
    }

    /**
     * Central method to update project and module schedules. Called by AJAX handler.
     * Handles DB updates and WP Cron updates.
     *
     * @param int    $project_id        Project ID.
     * @param string $project_interval  New project interval.
     * @param string $project_status    New project status.
     * @param array  $module_schedules  Array of [module_id => ['interval' => ..., 'status' => ...]].
     * @param int    $user_id           Current User ID for permission checks.
     * @return bool True on success, false on failure (e.g., DB error, permission denied).
     */
    public function update_schedules_for_project(int $project_id, string $project_interval, string $project_status, array $module_schedules, int $user_id): bool {
        // Note: Database updates are now handled *before* calling this method (e.g., in the AJAX handler).
        // This method is responsible *only* for updating the WP Cron events based on the provided state.
        try {
            // --- WP Cron Updates --- 

            // Update project schedule based on provided status and interval
            $this->schedule_project($project_id, $project_interval, $project_status);

            // Update individual module schedules based on the provided array
            // The $module_schedules array should already be validated and processed by the caller.
            if (!empty($module_schedules)) {
                 foreach ($module_schedules as $module_id => $schedule_data) {
                     // Ensure module_id is an integer, although it should be from the array key
                     $int_module_id = absint($module_id);
                     if ($int_module_id > 0) {
                         $this->schedule_module(
                             $int_module_id,
                             $schedule_data['interval'] ?? 'manual', // Use provided interval
                             $schedule_data['status'] ?? 'paused'      // Use provided status
                         );
                     }
                 }
            } else {
                 // If no module schedules were provided, maybe log this?
         
                 // It might be valid if a project has no modules, so perhaps no log needed.
            }

            return true;

        } catch (Exception $e) {
            // Error logging removed for production
            // Log error using injected logger service if available
            $this->logger?->error("Error updating WP Cron schedules", [
                'project_id' => $project_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Callback executed by WP Cron for project-level schedules.
     * (Contains logic moved from Data_Machine::run_scheduled_project)
     * @param int $project_id The ID of the project to run.
     */
    public function dm_run_project_schedule_callback(int $project_id) {
        $logger = $this->logger; // Use injected logger
        $log_prefix = "Data Machine Scheduler (Project Callback: {$project_id}): ";

        // Get the Job Executor service from property
        $job_executor = $this->job_executor;
        if (!$job_executor) { // Should not happen if constructor enforces type
             $error_message = $log_prefix . "Job Executor service not available (should be injected).";
             $logger?->critical($error_message, ['project_id' => $project_id]);
             return; // Cannot proceed without the executor
        }

        try {
            // 1. Get DB dependencies from properties
            $db_projects = $this->db_projects;
            $db_modules = $this->db_modules;

            // 2. Verify project exists and is active (using DB method - get project without user check)
            $project = $db_projects->get_project($project_id);
            if (!$project) {
                $this->unschedule_project($project_id); // Clean up
                return;
            }

            // Check if project schedule is actually active (Cron might run once after deactivation)
            if (($project->schedule_status ?? 'paused') !== 'active' || ($project->schedule_interval ?? 'manual') === 'manual') {
                 return;
            }

            $project_owner_user_id = $project->user_id;
            if (!$project_owner_user_id) {
                 return;
            }

            // 3. Clean up stuck jobs before processing (safety net)
            $stuck_jobs_cleaned = $this->db_jobs->cleanup_stuck_jobs(12); // 12 hour timeout
            if ($stuck_jobs_cleaned > 0) {
                $logger?->info($log_prefix . "Cleaned up {$stuck_jobs_cleaned} stuck jobs (>12 hours old).", ['project_id' => $project_id, 'cleaned_count' => $stuck_jobs_cleaned]);
            }

            // 4. Periodic maintenance: cleanup old jobs and log files (run once per day per project)
            if ($this->should_run_maintenance($project_id)) {
                $old_jobs_cleaned = $this->db_jobs->cleanup_old_jobs(30); // 30 days
                if ($old_jobs_cleaned > 0) {
                    $logger?->info($log_prefix . "Cleaned up {$old_jobs_cleaned} old completed/failed jobs (>30 days old).", ['project_id' => $project_id, 'cleaned_count' => $old_jobs_cleaned]);
                }
                
                if ($logger?->cleanup_log_files(10, 30)) { // 10MB or 30 days
                    $logger?->info($log_prefix . "Log file cleanup completed.", ['project_id' => $project_id]);
                }
                
                // Mark maintenance as done for this project today
                $this->mark_maintenance_done($project_id);
            }

            // 5. Fetch modules for project (using DB method, needs project owner context)
            $modules = $db_modules->get_modules_for_project($project_id, $project_owner_user_id);
            if (empty($modules)) {
                $logger?->info($log_prefix . "No modules found for project {$project_id}.", ['project_id' => $project_id]);
                return;
            }

            $logger?->info($log_prefix . "Found " . count($modules) . " modules for project {$project_id}. Starting filtering...", ['project_id' => $project_id, 'module_count' => count($modules)]);

            $total_jobs_created = 0;
            $modules_processed_count = 0;

            // 6. Loop through modules:
            foreach ($modules as $module) {
                // a. Filter: Check if module->schedule_interval === 'project_schedule'
                if (($module->schedule_interval ?? 'project_schedule') !== 'project_schedule') {
                    $logger?->debug($log_prefix . "Module {$module->module_id} skipped: schedule_interval is '{$module->schedule_interval}', not 'project_schedule'", ['module_id' => $module->module_id]);
                    continue; // Skip modules not set to run with the project schedule
                }
                // b. Filter: Check if module->schedule_status === 'active'
                if (($module->schedule_status ?? 'active') !== 'active') {
                    $logger?->debug($log_prefix . "Module {$module->module_id} skipped: schedule_status is '{$module->schedule_status}', not 'active'", ['module_id' => $module->module_id]);
                    continue; // Skip paused modules
                }
                // c. Filter: Check if module->data_source_type !== 'files'
                if (($module->data_source_type ?? null) === 'files') {
                    $logger?->debug($log_prefix . "Module {$module->module_id} skipped: data_source_type is 'files' (files not allowed in scheduled runs)", ['module_id' => $module->module_id]);
                    continue; // Skip file input modules for scheduled runs
                }

                // d. If passes filters, call schedule_job_from_config to create and schedule the job event
                $job_result = $job_executor->schedule_job_from_config($module, $project_owner_user_id, 'cron_project');

                if (is_wp_error($job_result)) {
                    $logger?->error($log_prefix . "Error scheduling job for module ID {$module->module_id}: " . $job_result->get_error_message(), ['module_id' => $module->module_id, 'project_id' => $project_id]);
                    // Potentially continue to next module or stop? Continuing for now.
                } elseif ($job_result > 0) {
                    $total_jobs_created++;
                    $logger?->info($log_prefix . "Successfully created job ID {$job_result} for module ID {$module->module_id}.", ['job_id' => $job_result, 'module_id' => $module->module_id]);
                } else {
                    // Job result was 0, meaning no new items to process for this module
                    $logger?->info($log_prefix . "No job created for module ID {$module->module_id} (likely no new items).", ['module_id' => $module->module_id]);
                }

                $modules_processed_count++;
            }

            // Log final summary
            $logger?->info($log_prefix . "Project schedule completed: {$modules_processed_count} modules processed, {$total_jobs_created} jobs created.", [
                'project_id' => $project_id,
                'total_modules' => count($modules),
                'modules_processed' => $modules_processed_count,
                'jobs_created' => $total_jobs_created
            ]);

            // Update project's last run time (only if at least one eligible module was processed)
            // IMPORTANT: Timestamp updates happen HERE now, not in Job Worker.
            if ($modules_processed_count > 0) {
                 $db_projects->update_project_last_run($project_id);
                 $logger?->info($log_prefix . "Project last run time updated.", ['project_id' => $project_id]);
            }

        } catch (Exception $e) {
            $logger?->error($log_prefix . "Exception: " . $e->getMessage(), [
                'project_id' => $project_id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Callback executed by WP Cron for module-level schedules.
     * (Contains logic moved from Data_Machine::run_scheduled_module)
     * @param int $module_id The ID of the module to run.
     */
    public function dm_run_module_schedule_callback(int $module_id) {
        $logger = $this->logger; // Use injected logger
        $log_prefix = "Data Machine Scheduler (Module Callback: {$module_id}): ";

        // Get the Job Executor service from property
        $job_executor = $this->job_executor;
        if (!$job_executor) { // Should not happen
             $error_message = $log_prefix . "Job Executor service not available (should be injected).";
             $logger?->critical($error_message, ['module_id' => $module_id]);
             return; // Cannot proceed
        }

        try {
            // 1. Get DB dependencies from properties
            $db_modules = $this->db_modules;
            $db_projects = $this->db_projects; // Need projects DB to get user_id

            // 2. Fetch module (using DB method)
            $module = $db_modules->get_module($module_id);

            // 3. Filter: Check if module exists
            if (!$module) {
                $this->unschedule_module($module_id); // Clean up
                return;
            }

            // 4. Filter: Check if module->schedule_status === 'active'
            if (($module->schedule_status ?? 'active') !== 'active') {
                $logger?->debug($log_prefix . "Module {$module_id} skipped: schedule_status is '{$module->schedule_status}', not 'active'", ['module_id' => $module_id]);
                return;
            }

            // 5. Filter: Check if module->schedule_interval is NOT 'manual' or 'project_schedule'
            $allowed_intervals = Data_Machine_Constants::get_module_cron_intervals(); // Same as allowed for scheduling
            $module_interval = $module->schedule_interval ?? 'project_schedule';
            if (!in_array($module_interval, $allowed_intervals)) {
                 $logger?->debug($log_prefix . "Module {$module_id} skipped: schedule_interval '{$module_interval}' not in allowed intervals", ['module_id' => $module_id, 'allowed' => $allowed_intervals]);
                 return;
            }

            // 6. Filter: Check if module->data_source_type !== 'files'
            if (($module->data_source_type ?? null) === 'files') {
                 $logger?->debug($log_prefix . "Module {$module_id} skipped: data_source_type is 'files' (files not allowed in scheduled runs)", ['module_id' => $module_id]);
                 $this->unschedule_module($module_id);
                 return;
            }

            // 7. Get project owner user_id
            $project = $db_projects->get_project($module->project_id);
            if (!$project || !$project->user_id) {
                 return;
            }
            $project_owner_user_id = $project->user_id;

            // 8. If passes filters, call schedule_job_from_config to create and schedule the job event
            $job_result = $job_executor->schedule_job_from_config($module, $project_owner_user_id, 'cron_module');

            if (is_wp_error($job_result)) {
                $logger?->error($log_prefix . "Error scheduling job: " . $job_result->get_error_message(), ['module_id' => $module_id]);
            } elseif ($job_result > 0) {
                // Update module's last run time only if a job was created
                $db_modules->update_module_last_run($module_id); // Update module last run time
                $logger?->info($log_prefix . "Successfully created job ID {$job_result}. Module last run time updated.", ['job_id' => $job_result, 'module_id' => $module_id]);
            } else {
                 // Job result was 0, meaning no new items to process
                 $logger?->info($log_prefix . "No job created (likely no new items). Module last run time NOT updated.", ['module_id' => $module_id]);
            }

        } catch (Exception $e) {
            $logger?->error($log_prefix . "Exception: " . $e->getMessage(), [
                'module_id' => $module_id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Check if maintenance should run for a project (once per day).
     * 
     * @param int $project_id Project ID
     * @return bool True if maintenance should run
     */
    private function should_run_maintenance($project_id) {
        $transient_key = "dm_maintenance_done_{$project_id}";
        return !get_transient($transient_key);
    }

    /**
     * Mark maintenance as completed for a project (valid for 24 hours).
     * 
     * @param int $project_id Project ID
     */
    private function mark_maintenance_done($project_id) {
        $transient_key = "dm_maintenance_done_{$project_id}";
        set_transient($transient_key, time(), DAY_IN_SECONDS);
    }
} 