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

    /**
     * Constructor.
     *
     * @param Data_Machine_Job_Executor $job_executor Job Executor service.
     * @param Data_Machine_Database_Projects $db_projects Projects DB service.
     * @param Data_Machine_Database_Modules $db_modules Modules DB service.
     * @param Data_Machine_Logger|null $logger Logger service (optional).
     */
    public function __construct(
        Data_Machine_Job_Executor $job_executor,
        Data_Machine_Database_Projects $db_projects,
        Data_Machine_Database_Modules $db_modules,
        ?Data_Machine_Logger $logger = null
    ) {
        $this->job_executor = $job_executor;
        $this->db_projects = $db_projects;
        $this->db_modules = $db_modules;
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

            // 3. Fetch modules for project (using DB method, needs project owner context)
            $modules = $db_modules->get_modules_for_project($project_id, $project_owner_user_id);
            if (empty($modules)) {
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
            if (($module->schedule_status ?? 'paused') !== 'active') {
                return;
            }

            // 5. Filter: Check if module->schedule_interval is NOT 'manual' or 'project_schedule'
            $allowed_intervals = Data_Machine_Constants::get_module_cron_intervals(); // Same as allowed for scheduling
            $module_interval = $module->schedule_interval ?? 'manual';
            if (!in_array($module_interval, $allowed_intervals)) {
                 return;
            }

            // 6. Filter: Check if module->data_source_type !== 'files'
            if (($module->data_source_type ?? null) === 'files') {
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
} 