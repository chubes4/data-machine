<?php
/**
 * Handles AJAX requests related to projects from the dashboard.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      0.13.0 // Or appropriate version
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Data_Machine\Includes\Interfaces\Data_Machine_Input_Handler_Interface;
// REMOVED use Data_Machine\Includes\Engine\Data_Machine_Job_Executor;

class Data_Machine_Ajax_Projects {

    private $db_projects;
    private $db_modules;
    private $db_jobs;
    private $locator;
    private $job_executor;

    /**
     * Initialize hooks and dependencies.
     */
    public function __construct(
        Data_Machine_Database_Projects $db_projects,
        Data_Machine_Database_Modules $db_modules,
        Data_Machine_Database_Jobs $db_jobs,
        Data_Machine_Service_Locator $locator,
        Data_Machine_Job_Executor $job_executor
    ) {
        $this->db_projects = $db_projects;
        $this->db_modules = $db_modules;
        $this->db_jobs = $db_jobs;
        $this->locator = $locator;
        $this->job_executor = $job_executor;
        // Hooks for project dashboard actions
        add_action('wp_ajax_dm_run_now', [$this, 'handle_run_now']);
        // Hooks moved from Project_Ajax_Handler
        add_action('wp_ajax_dm_get_project_modules', [$this, 'get_project_modules_ajax_handler']);
        add_action('wp_ajax_dm_create_project', [$this, 'create_project_ajax_handler']);
        // Register AJAX handler for editing project prompt
        add_action('wp_ajax_dm_edit_project_prompt', [$this, 'handle_edit_project_prompt']);
    }

    /**
     * AJAX handler to update the project prompt.
     */
    public function handle_edit_project_prompt() {
        // 1. Check nonce for security
        check_ajax_referer('dm_edit_project_prompt_nonce', 'nonce');

        // 2. Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.', 403);
        }

        // 3. Get and validate parameters
        $project_id = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;
        $project_prompt = isset($_POST['project_prompt']) ? wp_kses_post(wp_unslash($_POST['project_prompt'])) : '';

        if (empty($project_id)) {
            wp_send_json_error('Missing project ID.', 400);
        }

        // 4. Verify user ownership of the project
        $user_id = get_current_user_id();
        $project = $this->db_projects->get_project($project_id, $user_id);
        if (!$project) {
            wp_send_json_error('Project not found or permission denied.', 404);
        }

        // 5. Update the project prompt in the database
        global $wpdb;
        $table = $wpdb->prefix . 'dm_projects';
        $updated = $wpdb->update(
            $table,
            ['project_prompt' => $project_prompt],
            ['project_id' => $project_id, 'user_id' => $user_id],
            ['%s'],
            ['%d', '%d']
        );

        if ($updated === false) {
            wp_send_json_error('Failed to update project prompt.', 500);
        }

        wp_send_json_success(['message' => 'Project prompt updated successfully.', 'project_prompt' => $project_prompt]);
    }

    /**
     * AJAX handler for the "Run Now" action on the dashboard.
     * Triggers job creation for all eligible modules in a project.
     * @since 0.15.0
     */
    public function handle_run_now() {
        check_ajax_referer('dm_run_now_nonce', 'nonce');

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'data-machine' ) ) );
            wp_die();
        }

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $user_id = get_current_user_id();

        if ( empty( $project_id ) || empty( $user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing project ID or user ID.', 'data-machine' ) ) );
            wp_die();
        }

        // 1. Get Project Details (primarily to check ownership)
        $project = $this->db_projects->get_project( $project_id );
        if ( ! $project || $project->user_id != $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Project not found or permission denied.', 'data-machine' ) ) );
            wp_die();
        }

        // 2. Get Modules for the Project
        $modules = $this->db_modules->get_modules_for_project( $project_id, $user_id );
        if ( empty( $modules ) ) {
            wp_send_json_success( array( 'message' => __( 'No modules found in this project to run.', 'data-machine' ) ) );
            wp_die();
        }

        $jobs_initiated_count = 0;
        $modules_processed_names = [];
        $errors = [];
        $logger = $this->locator->get('logger');

        // 3. Loop through modules and trigger job execution via Job Executor
        foreach ( $modules as $module ) {

            // Apply the necessary filters:
            // a. Only modules set to 'project_schedule'
            $schedule_interval = $module->schedule_interval ?? 'manual';
            if ($schedule_interval !== 'project_schedule') {
            	$log_msg = "Run Now (Project: {$project_id}): Skipping module {$module->module_id} ({$module->module_name}) - Schedule interval is not 'project_schedule' ('{$schedule_interval}').";
            	error_log($log_msg);
            	if ($logger) $logger->info($log_msg, ['project_id' => $project_id, 'module_id' => $module->module_id]);
            	continue; // Skip modules not set to project schedule
            }
         
            // b. Skip paused modules
            // b. Skip paused modules
            $module_status = $module->schedule_status ?? 'active';
            if ($module_status !== 'active') {
                $log_msg = "Run Now (Project: {$project_id}): Skipping module {$module->module_id} ({$module->module_name}) - Status is '{$module_status}'.";
                error_log($log_msg);
                if ($logger) $logger->info($log_msg, ['project_id' => $project_id, 'module_id' => $module->module_id]);
                continue;
            }

            // c. Skip 'files' input type
            $module_input_type = $module->data_source_type ?? null;
            if ($module_input_type === 'files') {
                $log_msg = "Run Now (Project: {$project_id}): Skipping module {$module->module_id} ({$module->module_name}) - Input type is 'files'.";
                error_log($log_msg);
                 if ($logger) $logger->info($log_msg, ['project_id' => $project_id, 'module_id' => $module->module_id]);
                continue;
            }

            // d. Execute the job via the Job Executor
            try {
                $job_result = $this->job_executor->execute_job($module, $user_id, 'process_now');

                if (is_wp_error($job_result)) {
                    // Throw exception to be caught below and added to errors array
                    throw new Exception($job_result->get_error_message());
                } elseif (is_int($job_result) && $job_result > 0) {
                    // Job successfully initiated
                    $jobs_initiated_count++;
                    $modules_processed_names[] = $module->module_name;
                    $log_msg = "Run Now (Project: {$project_id}): Successfully initiated job ID {$job_result} for module {$module->module_id} ({$module->module_name}).";
                    error_log($log_msg);
                    if ($logger) $logger->info($log_msg, ['project_id' => $project_id, 'module_id' => $module->module_id, 'job_id' => $job_result]);
                } else {
                    // Handle cases where execute_job doesn't return error or job ID (e.g., no input data)
                    // This might be logged within execute_job itself, but we can add a note here.
                    $log_msg = "Run Now (Project: {$project_id}): Job execution for module {$module->module_id} ({$module->module_name}) did not result in a new job (possibly no new input data).";
                    error_log($log_msg);
                    if ($logger) $logger->info($log_msg, ['project_id' => $project_id, 'module_id' => $module->module_id]);
                    // We don't count this as an error or a success for the summary message.
                }

            } catch (Exception $e) {
                $error_message = "Error processing module '{$module->module_name}' (ID: {$module->module_id}): " . $e->getMessage();
                error_log("Data Machine Run Now: " . $error_message);
                 if ($logger) $logger->error("Run Now Error: " . $error_message, ['project_id' => $project_id, 'module_id' => $module->module_id]);
                $errors[] = $error_message;
                // Continue to next module even if one fails
            }
        }

        // 4. Send response based on results
        $success_message = sprintf(
            'Triggered processing for %d module(s) in project \'%s\'. Modules: %s.',
            $jobs_initiated_count,
            esc_html($project->project_name),
            count($modules_processed_names) > 0 ? implode(', ', array_map('esc_html', $modules_processed_names)) : 'None'
        );

        if (!empty($errors)) {
            wp_send_json_error( array(
                'message' => 'Completed with errors. ' . $success_message,
                'errors' => $errors
            ) );
        } else {
             wp_send_json_success( array( 'message' => $success_message ) );
        }

        wp_die(); // this is required to terminate immediately and return a proper response
    }

    /**
     * AJAX handler to get modules for a specific project.
     * @since 0.12.0 (Moved 0.15.0)
     */
    public function get_project_modules_ajax_handler() {
        // Note: Nonce name matches JS expectation
        check_ajax_referer('dm_get_project_modules_nonce', 'nonce');

        $project_id = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;
        $user_id = get_current_user_id();

        if (empty($project_id) || empty($user_id)) {
            wp_send_json_error(['message' => __('Missing project ID or user ID.', 'data-machine')]);
            return;
        }

        // Use injected dependency
        $modules = $this->db_modules->get_modules_for_project($project_id, $user_id);

        if ($modules === null) {
            // This indicates either project not found or user doesn't own it
            wp_send_json_error(['message' => __('Project not found or permission denied.', 'data-machine')]);
            return;
        }

        // Prepare modules for JSON
        $modules_data = array_map(function($module) {
            return [
                'module_id' => $module->module_id,
                'module_name' => $module->module_name,
            ];
        }, $modules);

        wp_send_json_success(['modules' => $modules_data]);
    }

    /**
     * AJAX handler to create a new project.
     * @since 0.12.0 (Moved 0.15.0)
     */
    public function create_project_ajax_handler() {
        // Note: Nonce name matches JS expectation
        check_ajax_referer('dm_create_project_nonce', 'nonce');

        $project_name = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';
        $user_id = get_current_user_id();

        if (empty(trim($project_name)) || empty($user_id)) {
            wp_send_json_error(['message' => __('Project name is required.', 'data-machine')]);
            return;
        }

        // Use injected dependency
        $project_id = $this->db_projects->create_project($user_id, $project_name);

        if ($project_id === false) {
            wp_send_json_error(['message' => __('Failed to create project in database.', 'data-machine')]);
            return;
        }

        wp_send_json_success([
            'message' => __('Project created successfully.', 'data-machine'),
            'project_id' => $project_id,
            'project_name' => $project_name // Return sanitized name
        ]);
    }

} 