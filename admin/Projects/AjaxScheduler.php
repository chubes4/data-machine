<?php
/**
 * Handles AJAX requests specifically related to project and module scheduling.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      [Next Version]
 */

namespace DataMachine\Admin\Projects;

use DataMachine\Database\{Modules, Projects};
use DataMachine\Admin\Projects\Scheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
class AjaxScheduler {

    /** @var Projects */
    private $db_projects;

    /** @var Modules */
    private $db_modules;

    /** @var Data_Machine_Scheduler */
    private $scheduler;

    /**
     * Constructor.
     *
     * @param Projects $db_projects Projects DB service.
     * @param Modules $db_modules Modules DB service.
     * @param Scheduler $scheduler Scheduler service.
     */
    public function __construct(
        Projects $db_projects,
        Modules $db_modules,
        Scheduler $scheduler
    ) {
        $this->db_projects = $db_projects;
        $this->db_modules = $db_modules;
        $this->scheduler = $scheduler;

        // Register AJAX hooks for scheduling actions
        add_action('wp_ajax_dm_edit_schedule', [$this, 'handle_edit_schedule']);
        add_action('wp_ajax_dm_get_project_schedule_data', [$this, 'handle_get_project_schedule_data']);
    }

    /**
     * Handle the 'Edit Schedule' action.
     *
     * Placeholder - Functionality TBD (e.g., open modal, save data).
     */
    public function handle_edit_schedule() {
        // 1. Check nonce
        check_ajax_referer( 'dm_edit_schedule_nonce', 'nonce' );

        // 2. Check capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        // 3. Get and validate parameters
        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $interval = isset( $_POST['schedule_interval'] ) ? sanitize_text_field( $_POST['schedule_interval'] ) : 'manual';
        $status = isset( $_POST['schedule_status'] ) ? sanitize_text_field( $_POST['schedule_status'] ) : 'paused';
        // Get module schedules data (expecting an array like [module_id => ['interval' => '...', 'status' => '...']])
        $module_schedules = isset( $_POST['module_schedules'] ) && is_array($_POST['module_schedules']) ? $_POST['module_schedules'] : [];

        if ( empty( $project_id ) ) {
            wp_send_json_error( 'Missing project ID.', 400 );
        }

        // Basic validation (more specific validation happens in DB class method)
        // Remove 'manual' from allowed project intervals
        $allowed_intervals = Constants::get_project_cron_intervals();
        $allowed_statuses = ['active', 'paused'];
        if ( !in_array($interval, $allowed_intervals) || !in_array($status, $allowed_statuses) ) {
             // If interval is 'manual', maybe default it or log a specific error?
             // For now, just reject invalid values directly.
             wp_send_json_error( 'Invalid project schedule interval or status provided.', 400 );
        }

        // 4. Update database (ownership check is done within the update method)
        $user_id = get_current_user_id();

        // --- PRE-PROCESS MODULE SCHEDULES FOR 'FILES' INPUT ---
        $processed_module_schedules = [];
        foreach ($module_schedules as $mod_id => $schedule_data) {
            $module_id = absint($mod_id);
            if (empty($module_id)) continue; // Skip invalid module IDs

            // Fetch the individual module NOW to get the current data_source_type
            $module = $this->db_modules->get_module($module_id);

            // Basic check if module exists. More robust check happens during DB update.
            if (!$module) {
                			// Debug logging removed for production
                continue;
            }
            $module_type = $module->data_source_type ?? null;

            // If the module type is 'files', force schedule to paused and project_schedule
            if ($module_type === 'files') {
                 			// Debug logging removed for production
                 $processed_module_schedules[$module_id] = [
                    'interval' => 'project_schedule', // Default non-running interval
                    'status'   => 'paused'            // Always paused
                 ];
            } else {
                // Sanitize and validate non-file input modules
                $mod_interval = isset($schedule_data['interval']) ? sanitize_text_field($schedule_data['interval']) : 'project_schedule';
                $mod_status = isset($schedule_data['status']) ? sanitize_text_field($schedule_data['status']) : 'active';

                // Allow 'project_schedule' for modules, but not 'manual'
                $allowed_module_intervals = Constants::get_allowed_module_intervals_for_validation();
                $allowed_module_intervals = array_diff($allowed_module_intervals, ['manual']);
                $allowed_module_statuses = ['active', 'paused'];

                if (!in_array($mod_interval, $allowed_module_intervals) || !in_array($mod_status, $allowed_module_statuses)) {
                    // Log invalid data but perhaps proceed with defaults? Or skip? For now, skip.
                    			// Debug logging removed for production
                    continue;
                }

                 $processed_module_schedules[$module_id] = [
                    'interval' => $mod_interval,
                    'status'   => $mod_status
                 ];
            }
        }
        // --- END PRE-PROCESSING ---

        try {
            // Update project schedule
            $project_updated = $this->db_projects->update_project_schedule(
                $project_id,
                $interval, 
                $status,
                $user_id    
            );

            // Update module schedules (pass the processed array)
            $modules_updated = $this->db_modules->update_module_schedules(
                $project_id, 
                $user_id, 
                $processed_module_schedules // Use the pre-processed schedules
            );

            if ( $project_updated === false || $modules_updated === false ) {
                // Error occurred (false indicates error, 0 or more is rows affected)
                throw new Exception('Failed to update schedule settings in the database.');
            }

            // Get scheduler from property and update WP Cron schedules
            $schedule_updated = $this->scheduler->update_schedules_for_project(
                $project_id,
                $interval, // Project interval from POST
                $status,   // Project status from POST
                $processed_module_schedules, // Use the validated/processed module data
                $user_id
            );

            if (!$schedule_updated) {
                 throw new Exception('Failed to update WP Cron schedules via Scheduler.');
            }

            wp_send_json_success( [ 'message' => 'Schedule updated successfully.' ] );

        } catch (Exception $e) {
            		// Debug logging removed for production
            wp_send_json_error( 'Error updating schedule: ' . $e->getMessage(), 500 );
        }

        wp_die();
    }

    /**
     * AJAX handler to fetch project and module schedule data for the modal.
     */
    public function handle_get_project_schedule_data() {
        // 1. Check nonce
        check_ajax_referer( 'dm_get_schedule_data_nonce', 'nonce' );

        // 2. Check capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        // 3. Get project ID
        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        if ( empty( $project_id ) ) {
            wp_send_json_error( 'Missing project ID.', 400 );
        }

        // 4. Get Project Data (verify ownership)
        $user_id = get_current_user_id();
        $project = $this->db_projects->get_project( $project_id, $user_id );
        if ( ! $project ) {
            wp_send_json_error( 'Project not found or permission denied.', 404 );
        }

        // 5. Get Modules Data
        $modules = $this->db_modules->get_modules_for_project( $project_id, $user_id );
        if ($modules === null) { // Check for null specifically, empty array is okay
             wp_send_json_error( 'Could not retrieve modules for project.', 500 );
        }

        // 6. Prepare data for JSON response
        $project_data = [
            'project_id' => $project->project_id,
            'project_name' => $project->project_name,
            'schedule_interval' => $project->schedule_interval ?? 'manual',
            'schedule_status' => $project->schedule_status ?? 'paused'
        ];

        $modules_data = [];
        foreach ($modules as $module) {
             $modules_data[] = [
                'module_id' => $module->module_id,
                'module_name' => $module->module_name,
                'schedule_interval' => $module->schedule_interval ?? 'project_schedule',
                'schedule_status' => $module->schedule_status ?? 'active', // Default module status?
                'data_source_type' => $module->data_source_type ?? null // Add data source type
             ];
        }

        // 7. Send success response
        wp_send_json_success( [
            'project' => $project_data,
            'modules' => $modules_data
        ] );

        wp_die();
    }

} 