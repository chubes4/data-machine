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

    /** @var Data_Machine_Service_Locator */
    private $locator;

    /**
     * Constructor.
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     */
    public function __construct(Data_Machine_Service_Locator $locator) {
        $this->locator = $locator;
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
        wp_clear_scheduled_hook( $hook, $args );

        // Schedule if active and interval is valid
        // $allowed_intervals = ['every_5_minutes', 'hourly', 'qtrdaily', 'twicedaily', 'daily', 'weekly']; // OLD
        $allowed_intervals = Data_Machine_Constants::get_project_cron_intervals(); // NEW
        if ($status === 'active' && in_array($interval, $allowed_intervals)) {
            wp_schedule_event( time(), $interval, $hook, $args );
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
        wp_clear_scheduled_hook( $hook, $args );

        // Schedule if active and interval is valid AND NOT project_schedule/manual
        // $allowed_intervals = ['every_5_minutes', 'hourly', 'qtrdaily', 'twicedaily', 'daily', 'weekly']; // OLD
        $allowed_intervals = Data_Machine_Constants::get_module_cron_intervals(); // NEW - Gets all slugs
        if ($status === 'active' && in_array($interval, $allowed_intervals)) {
             wp_schedule_event( time(), $interval, $hook, $args );
        }
    }

    /**
     * Unschedule project-level cron event.
     * @param int $project_id The project ID.
     */
    public function unschedule_project(int $project_id) {
        wp_clear_scheduled_hook( 'dm_run_project_schedule_callback', ['project_id' => $project_id] );
    }

    /**
     * Unschedule module-level cron event.
     * @param int $module_id The module ID.
     */
    public function unschedule_module(int $module_id) {
        wp_clear_scheduled_hook( 'dm_run_module_schedule_callback', ['module_id' => $module_id] );
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
                 // error_log("DM Scheduler: No module schedules provided to update_schedules_for_project for project {$project_id}.");
                 // It might be valid if a project has no modules, so perhaps no log needed.
            }

            return true;

        } catch (Exception $e) {
            error_log("Data Machine Error updating WP Cron schedules via Scheduler: " . $e->getMessage());
            // Log error using logger service if available
            $logger = $this->locator->get('logger');
            if ($logger) {
                $logger->error("Error updating WP Cron schedules", [
                    'project_id' => $project_id,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }

    /**
     * Callback executed by WP Cron for project-level schedules.
     * (Contains logic moved from Data_Machine::run_scheduled_project)
     * @param int $project_id The ID of the project to run.
     */
    public function dm_run_project_schedule_callback(int $project_id) {
        $logger = $this->locator->get('logger');
        $log_prefix = "Data Machine Scheduler (Project Callback: {$project_id}): ";
        error_log($log_prefix . "Triggered.");

        // Get the Job Executor service
        $job_executor = $this->locator->get('job_executor');
        if (!$job_executor) {
             $error_message = $log_prefix . "Job Executor service not available.";
             error_log($error_message);
             if ($logger) {
                 $logger->critical($error_message, ['project_id' => $project_id]);
             }
             return; // Cannot proceed without the executor
        }

        try {
            // 1. Get DB dependencies from locator
            $db_projects = $this->locator->get('database_projects');
            $db_modules = $this->locator->get('database_modules');

            // 2. Verify project exists and is active (using DB method - get project without user check)
            $project = $db_projects->get_project($project_id);
            if (!$project) {
                error_log($log_prefix . "Project not found. Unscheduling potentially orphaned event.");
                $this->unschedule_project($project_id); // Clean up
                return;
            }

            // Check if project schedule is actually active (Cron might run once after deactivation)
            if (($project->schedule_status ?? 'paused') !== 'active' || ($project->schedule_interval ?? 'manual') === 'manual') {
                 error_log($log_prefix . sprintf("Project is inactive (Status: %s, Interval: %s). Skipping run.", $project->schedule_status ?? 'N/A', $project->schedule_interval ?? 'N/A'));
                 return;
            }

            // --- Add Last Run Time Check ---
            $project_interval_slug = $project->schedule_interval;
            $project_last_run_db = $project->last_run_at; // MySQL datetime string (GMT)

            if (!empty($project_last_run_db)) { // Only check if it has run before
                $interval_seconds = Data_Machine_Constants::get_cron_interval_seconds($project_interval_slug);
                if ($interval_seconds !== null && $interval_seconds > 0) { // Ensure interval is valid
                    $last_run_timestamp = strtotime($project_last_run_db); // Convert DB time to Unix timestamp
                    $current_timestamp = current_time('timestamp', true); // Get current GMT timestamp

                    if (($last_run_timestamp + $interval_seconds) > $current_timestamp) {
                        // Not enough time has passed since the last run
                        $time_to_wait = ($last_run_timestamp + $interval_seconds) - $current_timestamp;
                        error_log($log_prefix . sprintf("Skipping run. Last run was too recent (at %s). Need to wait %d more seconds for interval '%s' (%d seconds).", $project_last_run_db, $time_to_wait, $project_interval_slug, $interval_seconds));
                        return; // Exit the callback
                    }
                } else {
                     error_log($log_prefix . "Warning: Could not determine valid interval seconds for slug '{$project_interval_slug}' during last run check.");
                     // Decide whether to proceed or return if interval is invalid - for now, let's proceed cautiously
                }
            }
            // --- End Last Run Time Check ---

            $project_owner_user_id = $project->user_id;
            if (!$project_owner_user_id) {
                 error_log($log_prefix . "Project owner user ID not found. Cannot proceed.");
                 return;
            }

            // 3. Fetch modules for project (using DB method, needs project owner context)
            $modules = $db_modules->get_modules_for_project($project_id, $project_owner_user_id);
            if (empty($modules)) {
                error_log($log_prefix . "Project has no modules.");
                return;
            }

            $total_jobs_created = 0;
            $modules_processed_count = 0;

            // 4. Loop through modules:
            foreach ($modules as $module) {
                // a. Filter: Check if module->schedule_interval === 'project_schedule'
                if (($module->schedule_interval ?? 'manual') !== 'project_schedule') {
                    continue; // Skip modules not set to run with the project schedule
                }
                // b. Filter: Check if module->schedule_status === 'active'
                if (($module->schedule_status ?? 'paused') !== 'active') {
                    continue; // Skip paused modules
                }
                // c. Filter: Check if module->data_source_type !== 'files'
                if (($module->data_source_type ?? null) === 'files') {
                    continue; // Skip file input modules for scheduled runs
                }

                // d. If passes filters, call trigger_job_creation -> execute_job
                error_log($log_prefix . "Processing module ID: {$module->module_id} ({$module->module_name}).");
                $job_result = $job_executor->execute_job($module, $project_owner_user_id, 'cron_project');

                if (is_wp_error($job_result)) {
                    $error_msg = $log_prefix . "Error executing job for module ID {$module->module_id}: " . $job_result->get_error_message();
                    error_log($error_msg);
                    if ($logger) {
                        $logger->error($error_msg, [
                            'project_id' => $project_id,
                            'module_id' => $module->module_id,
                            'error_code' => $job_result->get_error_code()
                        ]);
                    }
                } elseif (is_int($job_result) && $job_result > 0) {
                    // Assuming execute_job returns the job ID (int > 0) on success
                    $total_jobs_created++; // Increment if job was successfully created/scheduled
                    error_log($log_prefix . "Successfully initiated job (ID: {$job_result}) for module ID: {$module->module_id}.");
                     if ($logger) {
                         $logger->info("Job initiated via project cron.", [
                             'project_id' => $project_id,
                             'module_id' => $module->module_id,
                             'job_id' => $job_result
                         ]);
                     }
                } else {
                     // Handle cases where execute_job might return 0 or other non-error, non-job-ID value if applicable
                     error_log($log_prefix . "Job execution did not return a valid Job ID or WP_Error for module ID: {$module->module_id}.");
                }

                $modules_processed_count++;
            }

            error_log($log_prefix . "Processed {$modules_processed_count} module(s) matching criteria.");

            // 5. Update project last_run_at timestamp if jobs were created
            if ($total_jobs_created > 0) {
                 $updated = $db_projects->update_project_last_run($project_id);
                 if ($updated) {
                      error_log($log_prefix . "Successfully created {$total_jobs_created} job(s) and updated project last_run timestamp.");
                 } else {
                      error_log($log_prefix . "Successfully created {$total_jobs_created} job(s) BUT FAILED to update project last_run timestamp.");
                 }
            } else {
                 error_log($log_prefix . "No jobs were created for this project run.");
                 // Optionally update last_run even if no jobs were created, to indicate the schedule *did* run?
                 // $db_projects->update_project_last_run($project_id);
            }

        } catch (Exception $e) {
             $error_message = $log_prefix . "Error during scheduled run: " . $e->getMessage();
             error_log($error_message);
             if ($logger) {
                 $logger->error($error_message, ['project_id' => $project_id]);
             }
        }
    }

    /**
     * Callback executed by WP Cron for module-level schedules.
     * (Contains logic moved from Data_Machine::run_scheduled_module)
     * @param int $module_id The ID of the module to run.
     */
    public function dm_run_module_schedule_callback(int $module_id) {
        $logger = $this->locator->get('logger');
        $log_prefix = "Data Machine Scheduler (Module Callback: {$module_id}): ";
        error_log($log_prefix . "Triggered.");

        // Get the Job Executor service
        $job_executor = $this->locator->get('job_executor');
        if (!$job_executor) {
             $error_message = $log_prefix . "Job Executor service not available.";
             error_log($error_message);
             if ($logger) {
                 $logger->critical($error_message, ['module_id' => $module_id]);
             }
             return; // Cannot proceed without the executor
        }

        try {
            // 1. Get DB dependencies from locator
            $db_modules = $this->locator->get('database_modules');
            $db_projects = $this->locator->get('database_projects'); // Needed for project owner

            // 2. Fetch module (using DB method)
            $module = $db_modules->get_module($module_id);

            // 3. Filter: Check if module exists
            if (!$module) {
                error_log($log_prefix . "Module not found. Unscheduling potentially orphaned event.");
                $this->unschedule_module($module_id); // Clean up
                return;
            }

            // 4. Filter: Check if module->schedule_status === 'active'
            if (($module->schedule_status ?? 'paused') !== 'active') {
                error_log($log_prefix . "Module status is not active. Skipping run.");
                return;
            }

            // 5. Filter: Check if module->schedule_interval is NOT 'manual' or 'project_schedule'
            $allowed_intervals = ['every_5_minutes', 'hourly', 'qtrdaily', 'twicedaily', 'daily', 'weekly']; // Same as allowed for scheduling
            $module_interval = $module->schedule_interval ?? 'manual';
            if (!in_array($module_interval, $allowed_intervals)) {
                 error_log($log_prefix . sprintf("Module interval ('%s') is not a valid individual schedule. Skipping run.", $module_interval));
                 // If the interval is invalid, maybe unschedule it?
                 // $this->unschedule_module($module_id);
                 return;
            }

            // 6. Filter: Check if module->data_source_type !== 'files'
            if (($module->data_source_type ?? null) === 'files') {
                 error_log($log_prefix . "Module is a 'files' input type. Skipping scheduled run.");
                 // Also unschedule if a file type somehow got scheduled individually
                 $this->unschedule_module($module_id);
                 return;
            }

            // --- Add Last Run Time Check ---
            $module_interval_slug = $module->schedule_interval;
            $module_last_run_db = $module->last_run_at; // MySQL datetime string (GMT)

            if (!empty($module_last_run_db)) { // Only check if it has run before
                $interval_seconds = Data_Machine_Constants::get_cron_interval_seconds($module_interval_slug);
                if ($interval_seconds !== null && $interval_seconds > 0) { // Ensure interval is valid
                    $last_run_timestamp = strtotime($module_last_run_db); // Convert DB time to Unix timestamp
                    $current_timestamp = current_time('timestamp', true); // Get current GMT timestamp

                    if (($last_run_timestamp + $interval_seconds) > $current_timestamp) {
                        // Not enough time has passed since the last run
                        $time_to_wait = ($last_run_timestamp + $interval_seconds) - $current_timestamp;
                        error_log($log_prefix . sprintf("Skipping run. Last run was too recent (at %s). Need to wait %d more seconds for interval '%s' (%d seconds).", $module_last_run_db, $time_to_wait, $module_interval_slug, $interval_seconds));
                        return; // Exit the callback
                    }
                } else {
                     error_log($log_prefix . "Warning: Could not determine valid interval seconds for slug '{$module_interval_slug}' during last run check.");
                     // Decide whether to proceed or return if interval is invalid - for now, let's proceed cautiously
                }
            }
            // --- End Last Run Time Check ---

            // 7. Get project owner user_id
            $project = $db_projects->get_project($module->project_id);
            if (!$project || !$project->user_id) {
                 error_log($log_prefix . "Could not find project or project owner for module. Cannot proceed.");
                 return;
            }
            $project_owner_user_id = $project->user_id;

            // 8. If passes filters, call $this->trigger_job_creation($module, $project_owner_user_id) -> execute_job
            error_log($log_prefix . "Processing module ({$module->module_name}).");
            $job_result = $job_executor->execute_job($module, $project_owner_user_id, 'cron_module');

            if (is_wp_error($job_result)) {
                $error_msg = $log_prefix . "Error executing job for module ID {$module->module_id}: " . $job_result->get_error_message();
                error_log($error_msg);
                if ($logger) {
                    $logger->error($error_msg, [
                        'module_id' => $module_id,
                        'project_id' => $module->project_id ?? null, // Add project_id if available
                        'error_code' => $job_result->get_error_code()
                    ]);
                }
                error_log($log_prefix . "Job creation failed for this module run."); // Adjusted log message
            } elseif (is_int($job_result) && $job_result > 0) {
                 error_log($log_prefix . "Successfully initiated job (ID: {$job_result}).");
                 if ($logger) {
                     $logger->info("Job initiated via module cron.", [
                         'module_id' => $module_id,
                         'project_id' => $module->project_id ?? null,
                         'job_id' => $job_result
                     ]);
                 }
                // Note: Module last_run is not currently tracked, project last_run is updated by project callback.
            } else {
                 // Handle cases where execute_job might return 0 or other non-error, non-job-ID value if applicable
                 error_log($log_prefix . "Job execution did not return a valid Job ID or WP_Error.");
                 error_log($log_prefix . "No jobs were created for this module run."); // Keep original log message here
            }

        } catch (Exception $e) {
            $error_message = $log_prefix . "Error during scheduled run: " . $e->getMessage();
            error_log($error_message);
            if ($logger) {
                $logger->error($error_message, ['module_id' => $module_id]);
            }
        }
    }
} 