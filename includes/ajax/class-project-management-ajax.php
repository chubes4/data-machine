<?php
/**
 * Handles AJAX requests related to project management dashboard.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      0.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Data_Machine_Project_Management_Ajax {

	/** @var Data_Machine_Database_Projects */
	private $db_projects;

	/** @var Data_Machine_Database_Modules */
	private $db_modules;

	/** @var Data_Machine_Database_Jobs */
	private $db_jobs;

	/** @var Data_Machine_Database_Processed_Items */
	private $db_processed_items;

	/** @var ?Data_Machine_Logger */
	private $logger;

	/** @var Data_Machine_Job_Executor */
	private $job_executor;

	/**
	 * Constructor: wires hooks and dependencies.
	 *
	 * @param Data_Machine_Database_Projects $db_projects Projects DB service.
	 * @param Data_Machine_Database_Modules $db_modules Modules DB service.
	 * @param Data_Machine_Database_Jobs $db_jobs Jobs DB service.
	 * @param Data_Machine_Database_Processed_Items $db_processed_items Processed Items DB service.
	 * @param Data_Machine_Job_Executor $job_executor Job Executor service.
	 * @param Data_Machine_Logger|null $logger Logger service (optional).
	 */
	public function __construct(
		Data_Machine_Database_Projects  $db_projects,
		Data_Machine_Database_Modules   $db_modules,
		Data_Machine_Database_Jobs      $db_jobs,
		Data_Machine_Database_Processed_Items $db_processed_items,
		Data_Machine_Job_Executor       $job_executor,
		?Data_Machine_Logger            $logger = null // Add optional logger
	) {
		$this->db_projects          = $db_projects;
		$this->db_modules           = $db_modules;
		$this->db_jobs              = $db_jobs;
		$this->db_processed_items   = $db_processed_items;
		$this->job_executor         = $job_executor;
		$this->logger               = $logger;

		add_action( 'wp_ajax_dm_run_now',              [ $this, 'handle_run_now' ] );
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

		// Clean up stuck jobs before processing (12 hour timeout)
		$stuck_jobs_cleaned = $this->db_jobs->cleanup_stuck_jobs(12);
		if ($stuck_jobs_cleaned > 0 && $this->logger) {
			$this->logger->info("Run Now: Cleaned up {$stuck_jobs_cleaned} stuck jobs (>12 hours old).", [
				'project_id' => $project_id, 
				'cleaned_count' => $stuck_jobs_cleaned,
				'context' => 'run_now'
			]);
		}

		$jobs_scheduled  = 0;
		$scheduled_names = [];
		$errors          = [];
		$logger          = $this->logger; // Use injected logger
		$db_processed    = $this->db_processed_items; // Use injected processed items DB

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

			// NEW: Call Job Executor to schedule the job
			try {
				// Ensure the job_executor property is set
				if (empty($this->job_executor)) {
					throw new Exception('Job Executor service is not available in Ajax Projects handler.');
				}

				$job_id = $this->job_executor->schedule_job_from_config($module, $user_id, 'run_now');

				if (is_wp_error($job_id)) {
					$error_message = sprintf("Failed to schedule job for module '%s': %s", $module_name, $job_id->get_error_message());
					$this->log($logger, $project_id, $module_id, $module_name, $error_message, 'error');
					$errors[] = $error_message;
					continue;
				}

				if ($job_id > 0) {
					$jobs_scheduled++;
					$scheduled_names[] = $module_name;
					$this->log($logger, $project_id, $module_id, $module_name, "Job ID {$job_id} scheduled via Job Executor.");
				} elseif ($job_id === 0) {
					$this->log($logger, $project_id, $module_id, $module_name, "Job not created - module has active jobs (check jobs table for stuck jobs).", 'info');
				} else {
					$this->log($logger, $project_id, $module_id, $module_name, "Job scheduling via Job Executor did not return a valid Job ID.", 'warning');
				}
			} catch (Exception $e) {
				$error_message = sprintf("Error triggering job schedule for module '%s': %s", $module_name, $e->getMessage());
				$this->log($logger, $project_id, $module_id, $module_name, $error_message, 'error');
				$errors[] = $error_message;
				continue;
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
