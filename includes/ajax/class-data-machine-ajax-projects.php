<?php
/**
 * Handles AJAX requests related to projects from the dashboard.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      0.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Data_Machine_Ajax_Projects {

	/** @var Data_Machine_Database_Projects */
	private $db_projects;

	/** @var Data_Machine_Database_Modules */
	private $db_modules;

	/** @var Data_Machine_Database_Jobs */
	private $db_jobs;

	/** @var Data_Machine_Service_Locator */
	private $locator;

	/** @var Data_Machine_Job_Executor */
	private $job_executor;

	/**
	 * Constructor: wires hooks and dependencies.
	 */
	public function __construct(
		Data_Machine_Database_Projects  $db_projects,
		Data_Machine_Database_Modules   $db_modules,
		Data_Machine_Database_Jobs      $db_jobs,
		Data_Machine_Service_Locator    $locator,
		Data_Machine_Job_Executor       $job_executor
	) {
		$this->db_projects  = $db_projects;
		$this->db_modules   = $db_modules;
		$this->db_jobs      = $db_jobs;
		$this->locator      = $locator;
		$this->job_executor = $job_executor;

		add_action( 'wp_ajax_dm_run_now',              [ $this, 'handle_run_now' ] );
		add_action( 'wp_ajax_dm_get_project_modules',  [ $this, 'get_project_modules_ajax_handler' ] );
		add_action( 'wp_ajax_dm_create_project',       [ $this, 'create_project_ajax_handler' ] );
		add_action( 'wp_ajax_dm_edit_project_prompt',  [ $this, 'handle_edit_project_prompt' ] );
	}

	/* ---------------------------------------------------------------------
	 *  Project‑prompt editing
	 * -------------------------------------------------------------------*/
	public function handle_edit_project_prompt() {
		check_ajax_referer( 'dm_edit_project_prompt_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.', 403 );
		}

		$project_id     = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		$project_prompt = isset( $_POST['project_prompt'] ) ? wp_kses_post( wp_unslash( $_POST['project_prompt'] ) ) : '';

		if ( ! $project_id ) {
			wp_send_json_error( 'Missing project ID.', 400 );
		}

		$user_id = get_current_user_id();
		$project = $this->db_projects->get_project( $project_id, $user_id );

		if ( ! $project ) {
			wp_send_json_error( 'Project not found or permission denied.', 404 );
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'dm_projects';
		$updated = $wpdb->update(
			$table,
			[ 'project_prompt' => $project_prompt ],
			[ 'project_id' => $project_id, 'user_id' => $user_id ],
			[ '%s' ],
			[ '%d', '%d' ]
		);

		if ( false === $updated ) {
			wp_send_json_error( 'Failed to update project prompt.', 500 );
		}

		wp_send_json_success(
			[
				'message'        => 'Project prompt updated successfully.',
				'project_prompt' => $project_prompt,
			]
		);
	}

	/* ---------------------------------------------------------------------
	 *  "Run Now" – schedule every eligible module immediately
	 * -------------------------------------------------------------------*/
	public function handle_run_now() {
		check_ajax_referer( 'dm_run_now_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'data-machine' ) ] );
			wp_die();
		}

		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		$user_id    = get_current_user_id();

		if ( empty( $project_id ) || empty( $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing project ID or user ID.', 'data-machine' ) ] );
			wp_die();
		}

		$project = $this->db_projects->get_project( $project_id );
		if ( ! $project || intval( $project->user_id ) !== intval( $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Project not found or permission denied.', 'data-machine' ) ] );
			wp_die();
		}

		$modules = $this->db_modules->get_modules_for_project( $project_id, $user_id );
		if ( ! $modules ) {
			wp_send_json_success( [ 'message' => __( 'No modules found in this project to schedule.', 'data-machine' ) ] );
			wp_die();
		}

		$jobs_scheduled  = 0;
		$scheduled_names = [];
		$errors          = [];
		$logger          = $this->locator->get( 'logger' );
		$db_processed    = $this->locator->get( 'database_processed_items' );

		foreach ( $modules as $module ) {
			$module_id   = $module->module_id;
			$module_name = $module->module_name;

			/* ----- eligibility checks ----- */
			if ( ( $module->schedule_status ?? 'active' ) !== 'active' ) {
				$this->log( $logger, $project_id, $module_id, $module_name, 'Skipping – paused.' );
				continue;
			}
			if ( ( $module->data_source_type ?? '' ) === 'files' ) {
				$this->log( $logger, $project_id, $module_id, $module_name, 'Skipping – input type files.' );
				continue;
			}
			if ( ( $module->schedule_interval ?? '' ) !== 'project_schedule' ) {
				$this->log( $logger, $project_id, $module_id, $module_name, 'Skipping – schedule not project_schedule.' );
				continue;
			}

			// Removed: Skip modules if they already have processed items (this check is done by Job Executor)
			// $db_processed_items = $this->locator->get( 'database_processed_items' );
			// if ( $db_processed_items && $db_processed_items->has_any_processed_items_for_module( $module_id ) ) {
			// 	$this->log( $logger, $project_id, $module_id, $module_name, 'Skipping - already has processed items.' );
			// 	continue;
			// }

			/* ----- build config ----- */
			// No need to build config here, schedule_job_from_config will handle it
			// $module_config_data = [ ... ]; // REMOVED

			// d. Fetch Input Data Packet using the appropriate handler // REMOVED - Data fetching moved to worker
			// $input_handler_key = ... // REMOVED
			// $input_handler = ... // REMOVED
			// if (!$input_handler ...) { ... } // REMOVED

			// try { // REMOVED try/catch block around fetching
			//     $post_data_arg = ... // REMOVED
			//     $input_data_packet = ... // REMOVED
			//     if (is_wp_error($input_data_packet)) { ... } // REMOVED
			//     if (is_array($input_data_packet) && isset($input_data_packet['status']) && $input_data_packet['status'] === 'no_new_items') { ... } // REMOVED
			//     if (empty($input_data_packet) || !is_array($input_data_packet)) { ... } // REMOVED
			//     $input_data_json = ... // REMOVED
			//     if (false === $input_data_json) { ... } // REMOVED
			// } catch (Exception $e) { ... } // REMOVED

			// NEW: Call Job Executor to schedule the job
			try {
				// Ensure the job_executor property is set
				if (empty($this->job_executor)) {
					throw new Exception('Job Executor service is not available in Ajax Projects handler.');
				}

				// Pass the module object, user ID, and context
				// schedule_job_from_config handles preparing config, creating job, scheduling event
				$job_id = $this->job_executor->schedule_job_from_config($module, $user_id, 'run_now');

				if (is_wp_error($job_id)) {
					$error_message = sprintf("Failed to schedule job for module '%s': %s", $module_name, $job_id->get_error_message());
					$this->log($logger, $project_id, $module_id, $module_name, $error_message, 'error');
					$errors[] = $error_message;
					continue; // Skip to the next module
				}

				if ($job_id > 0) {
					$jobs_scheduled++;
					$scheduled_names[] = $module_name;
					$this->log($logger, $project_id, $module_id, $module_name, "Job ID {$job_id} scheduled via Job Executor.");
				} else {
					// schedule_job_from_config might return 0 or other non-error if needed (e.g., skip condition)
					// For now, assume it only returns Job ID or WP_Error
					$this->log($logger, $project_id, $module_id, $module_name, "Job scheduling via Job Executor did not return a valid Job ID.", 'warning');
				}
			} catch (Exception $e) {
				$error_message = sprintf("Error triggering job schedule for module '%s': %s", $module_name, $e->getMessage());
				$this->log($logger, $project_id, $module_id, $module_name, $error_message, 'error');
				$errors[] = $error_message;
				continue; // Skip to the next module
			}
		}

		$summary = sprintf(
			/* translators: 1: count, 2: project name, 3: list */
			__( 'Scheduled %1$d module(s) for immediate background processing in project "%2$s". Modules: %3$s.', 'data-machine' ),
			$jobs_scheduled,
			esc_html( $project->project_name ),
			$scheduled_names ? implode( ', ', array_map( 'esc_html', $scheduled_names ) ) : 'None'
		);

		if ( $errors ) {
			wp_send_json_error(
				[
					'message' => __( 'Completed scheduling with errors.', 'data-machine' ) . ' ' . $summary,
					'errors'  => $errors,
				]
			);
		} else {
			wp_send_json_success( [ 'message' => $summary ] );
		}

		wp_die();
	}

	/* ---------------------------------------------------------------------
	 *  Load modules for a project
	 * -------------------------------------------------------------------*/
	public function get_project_modules_ajax_handler() {
		check_ajax_referer( 'dm_get_project_modules_nonce', 'nonce' );

		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		$user_id    = get_current_user_id();

		if ( ! $project_id || ! $user_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing project ID or user ID.', 'data-machine' ) ] );
			return;
		}

		$modules = $this->db_modules->get_modules_for_project( $project_id, $user_id );
		if ( null === $modules ) {
			wp_send_json_error( [ 'message' => __( 'Project not found or permission denied.', 'data-machine' ) ] );
			return;
		}

		wp_send_json_success(
			[
				'modules' => array_map(
					fn ( $m ) => [
						'module_id'   => $m->module_id,
						'module_name' => $m->module_name,
						'schedule_status' => $m->schedule_status,
						'schedule_interval' => $m->schedule_interval,
						'data_source_type' => $m->data_source_type,
						'data_source_config' => $m->data_source_config,
						'output_type' => $m->output_type,
						'output_config' => $m->output_config,
					],
					$modules
				),
			]
		);
	}

	/* ---------------------------------------------------------------------
	 *  Create a new project
	 * -------------------------------------------------------------------*/
	public function create_project_ajax_handler() {
		check_ajax_referer( 'dm_create_project_nonce', 'nonce' );

		$project_name = isset( $_POST['project_name'] ) ? sanitize_text_field( wp_unslash( $_POST['project_name'] ) ) : '';
		$user_id      = get_current_user_id();

		if ( '' === trim( $project_name ) || ! $user_id ) {
			wp_send_json_error( [ 'message' => __( 'Project name is required.', 'data-machine' ) ] );
			return;
		}

		$project_id = $this->db_projects->create_project( $user_id, $project_name );
		if ( false === $project_id ) {
			wp_send_json_error( [ 'message' => __( 'Failed to create project in database.', 'data-machine' ) ] );
			return;
		}

		wp_send_json_success(
			[
				'message'      => __( 'Project created successfully.', 'data-machine' ),
				'project_id'   => $project_id,
				'project_name' => $project_name,
			]
		);
	}

	/* ---------------------------------------------------------------------
	 *  Small internal helper – centralised logging
	 * -------------------------------------------------------------------*/
	private function log( $logger, $project_id, $module_id, $module_name, $msg, $level = 'info' ) {
		$line = sprintf(
			'Run Now (Project %d) – Module %d (%s): %s',
			$project_id,
			$module_id,
			$module_name,
			$msg
		);

		error_log( $line );

		if ( $logger && method_exists( $logger, $level ) ) {
			$logger->{$level}(
				$line,
				[
					'project_id' => $project_id,
					'module_id'  => $module_id,
					'module'     => $module_name,
				]
			);
		}
	}
}
