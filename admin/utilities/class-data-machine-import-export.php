<?php
/**
 * Handles project and module import/export functionality.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Data_Machine_Import_Export
 *
 * Manages the import and export of projects and modules.
 */
class Data_Machine_Import_Export {

	/**
	 * Instance of the Projects database class.
	 * @var Data_Machine_Database_Projects
	 */
	private $db_projects;

	/**
	 * Instance of the Modules database class.
	 * @var Data_Machine_Database_Modules
	 */
	private $db_modules;

	/**
	 * Constructor.
	 * Stores DB handlers and adds action hooks.
	 *
	 * @param Data_Machine_Database_Projects $db_projects The Projects DB service.
	 * @param Data_Machine_Database_Modules  $db_modules  The Modules DB service.
	 */
	public function __construct(
		Data_Machine_Database_Projects $db_projects,
		Data_Machine_Database_Modules $db_modules
	) {
		$this->db_projects = $db_projects;
		$this->db_modules = $db_modules;

		// Hook the export function to the admin_post action
		add_action( 'admin_post_dm_export_project', array( $this, 'handle_project_export' ) );
		// Hook the import function to the admin_post action
		add_action( 'admin_post_dm_import_project', array( $this, 'handle_project_import' ) );
        // Hook notice display
        add_action( 'admin_notices', array( $this, 'display_import_notices' ) );
        // Hook delete action
        add_action( 'admin_post_dm_delete_project', array( $this, 'handle_project_delete' ) );
	}

	/**
	 * Handles the project export request.
	 * Fetches project and module data, formats it as JSON, and triggers a download.
	 */
	public function handle_project_export() {
		// 1. Security Checks (Nonce & Capabilities)
		if ( ! isset( $_GET['project_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			wp_die( esc_html__( 'Invalid export request.', 'data-machine' ) );
		}

		$project_id = absint( $_GET['project_id'] );
		$nonce      = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'dm_export_project_' . $project_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'data-machine' ) );
		}

		// Check if user has permission (adjust capability as needed - 'manage_options' is a common default)
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export projects.', 'data-machine' ) );
		}

		// 2. Fetch Data
		// Use get_project, passing user_id for ownership check
		$current_user_id = get_current_user_id();
		$project = $this->db_projects->get_project( $project_id, $current_user_id );
		if ( ! $project ) {
			wp_die( esc_html__( 'Project not found or you do not have permission to access it.', 'data-machine' ) );
		}

		// Fetch modules, passing user_id as required by the method
		$modules = $this->db_modules->get_modules_for_project( $project_id, $current_user_id );

		// 4. Prepare Data for Export (Exclude IDs and User ID)
		$export_data = array(
			'plugin_version' => DATA_MACHINE_VERSION, // Add plugin version for compatibility checks on import
			'export_format' => '1.0', // Versioning for the export format itself
			'project' => array(),
			'modules' => array(),
		);

		// Define allowed fields for project (excluding project_id, user_id)
		$allowed_project_fields = array(
			'project_name', 'schedule_interval', 'schedule_status', 'project_prompt',
			// 'last_run_at', // Maybe exclude? Will be reset on import run.
			// 'created_at', 'updated_at' // Usually reset on import
		);
		foreach ( $allowed_project_fields as $field ) {
			if ( isset( $project->$field ) ) {
				$export_data['project'][ $field ] = $project->$field;
			}
		}
		// TODO: Add project metadata export if applicable and safe

		// Define allowed fields for modules (excluding module_id, project_id, user_id)
		$allowed_module_fields = array(
			'module_name', 'module_type', 'connection_details', 'data_source_config',
			'data_transformation_rules', 'target_table', 'schedule_interval', 'schedule_status',
			'process_data_prompt', 'fact_check_prompt', 'finalize_response_prompt', // Added prompts based on search results
			'data_source_type', 'output_type', 'output_config', // Added common config fields
			// 'last_run_status', 'last_run_message', 'last_run_at', // Exclude run status/time
			// 'created_at', 'updated_at' // Exclude timestamps
		);
		if ( ! empty( $modules ) && is_array( $modules ) ) {
			foreach ( $modules as $module ) {
				$module_export = array();
				foreach ( $allowed_module_fields as $field ) {
					if ( isset( $module->$field ) ) {
						// Decode JSON strings if needed (e.g., connection_details, data_source_config, output_config)
						if ( in_array($field, ['connection_details', 'data_source_config', 'output_config']) && is_string($module->$field) ) {
							$decoded = json_decode( $module->$field, true );
							// Only include if successfully decoded or if it wasn't JSON originally
                            $module_export[$field] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $module->$field;
						} else {
							$module_export[ $field ] = $module->$field;
						}
					}
				}
				// TODO: Add module metadata export if applicable and safe

				if ( ! empty( $module_export ) ) {
					$export_data['modules'][] = $module_export;
				}
			}
		}

		// 5. Generate JSON and Trigger Download
		$project_slug = sanitize_title( $project->project_name ?: 'project' );
		$filename     = sprintf( 'dm-export-%s-%s.json', $project_slug, date( 'Ymd-His' ) );
		$json_data    = wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( $json_data === false ) {
			wp_die( sprintf( esc_html__( 'Failed to encode data to JSON. Error: %s', 'data-machine' ), json_last_error_msg() ) );
		}

		// Set headers for download
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-' );
		header( 'Expires: 0' );

		// Output JSON - Use echo for admin-post.php handlers
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON data intended for file download.
		echo $json_data;
		exit;
	}

	/**
	 * Handles the project import request from the uploaded file.
	 */
	public function handle_project_import() {
		// 1. Security Checks (Nonce & Capabilities)
		if ( ! isset( $_POST['dm_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dm_import_nonce'] ) ), 'dm_import_project_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'data-machine' ) );
		}

		// Check user capability
		if ( ! current_user_can( 'manage_options' ) ) { // Use appropriate capability
			wp_die( esc_html__( 'You do not have permission to import projects.', 'data-machine' ) );
		}

		// 2. File Upload Check
		if ( ! isset( $_FILES['dm_import_file'] ) || $_FILES['dm_import_file']['error'] !== UPLOAD_ERR_OK ) {
			$this->redirect_with_notice( 'error', __( 'File upload failed. Please try again.', 'data-machine' ) );
			return;
		}

		$file = $_FILES['dm_import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Handled below

		// Check file type (MIME type) - Basic check
		$file_info = wp_check_filetype( basename( $file['name'] ) );

		if ( strtolower( $file_info['ext'] ) !== 'json' ) {
			$this->redirect_with_notice( 'error', __( 'Invalid file type. Please upload a .json file.', 'data-machine' ) );
			return;
		}

		// 3. Read and Decode JSON File
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Using file path directly is necessary here. Security is handled by nonce/capability checks.
		$json_content = file_get_contents( $file['tmp_name'] ); 
		if ( $json_content === false ) {
			$this->redirect_with_notice( 'error', __( 'Could not read the uploaded file.', 'data-machine' ) );
			return;
		}

		$import_data = json_decode( $json_content, true ); // Decode as associative array
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->redirect_with_notice( 'error', sprintf( __( 'Invalid JSON file. Error: %s', 'data-machine' ), json_last_error_msg() ) );
			return;
		}

		// 4. Validate Data Structure
		if ( ! is_array( $import_data ) || ! isset( $import_data['project'] ) || ! isset( $import_data['modules'] ) || ! is_array( $import_data['project'] ) || ! is_array( $import_data['modules'] ) ) {
			$this->redirect_with_notice( 'error', __( 'Invalid import file format. Missing required project or modules data.', 'data-machine' ) );
			return;
		}

		// Optional: Check 'plugin_version' or 'export_format' for compatibility here

		// 5. Get Current User ID
		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			wp_die( esc_html__( 'Could not determine current user.', 'data-machine' ) ); // Should not happen for logged-in users
		}

		// 6. Create Project
		$project_data = $import_data['project'];
		// Ensure required fields exist (e.g., project_name)
		if ( empty( $project_data['project_name'] ) ) {
			$this->redirect_with_notice( 'error', __( 'Import failed: Project name is missing in the import file.', 'data-machine' ) );
			return;
		}

		// Sanitize project data before insertion
		$sanitized_project_data = array(
			'project_name' => sanitize_text_field( $project_data['project_name'] ),
			'schedule_interval' => isset( $project_data['schedule_interval'] ) ? sanitize_key( $project_data['schedule_interval'] ) : 'manual',
			'schedule_status' => isset( $project_data['schedule_status'] ) ? sanitize_key( $project_data['schedule_status'] ) : 'paused',
			'project_prompt' => isset( $project_data['project_prompt'] ) ? wp_kses_post( $project_data['project_prompt'] ) : null,
			'user_id' => $current_user_id, // Assign to the current user
		);

		// Add any other allowed project fields here after sanitization

		$new_project_id = $this->db_projects->create_project( $sanitized_project_data );

		if ( is_wp_error( $new_project_id ) ) {
			$this->redirect_with_notice( 'error', sprintf( __( 'Failed to create project: %s', 'data-machine' ), $new_project_id->get_error_message() ) );
			return;
		} elseif ( ! $new_project_id ) {
			$this->redirect_with_notice( 'error', __( 'Failed to create project. Unknown database error.', 'data-machine' ) );
			return;
		}

		// 8. Create Modules
		$modules_data = $import_data['modules'];
		$modules_created_count = 0;
		$module_errors = array();

		if ( ! empty( $modules_data ) && is_array( $modules_data ) ) {
			foreach ( $modules_data as $module_data ) {
				// Ensure required fields exist (e.g., module_name, module_type)
				if ( empty( $module_data['module_name'] ) || empty( $module_data['module_type'] ) ) {
					$module_errors[] = __( 'Skipped module: Missing name or type.', 'data-machine' );
					continue;
				}

				// Sanitize module data before insertion
				$sanitized_module_data = array(
					'project_id' => $new_project_id,
					'user_id' => $current_user_id,
					'module_name' => sanitize_text_field( $module_data['module_name'] ),
					'module_type' => sanitize_key( $module_data['module_type'] ),
					'schedule_interval' => isset( $module_data['schedule_interval'] ) ? sanitize_key( $module_data['schedule_interval'] ) : 'manual',
					'schedule_status' => isset( $module_data['schedule_status'] ) ? sanitize_key( $module_data['schedule_status'] ) : 'paused',
					'data_source_type' => isset( $module_data['data_source_type'] ) ? sanitize_key( $module_data['data_source_type'] ) : null,
					'output_type' => isset( $module_data['output_type'] ) ? sanitize_key( $module_data['output_type'] ) : null,
					// Prompts - Use wp_kses_post for potential HTML
					'process_data_prompt' => isset( $module_data['process_data_prompt'] ) ? wp_kses_post( $module_data['process_data_prompt'] ) : null,
					'fact_check_prompt' => isset( $module_data['fact_check_prompt'] ) ? wp_kses_post( $module_data['fact_check_prompt'] ) : null,
					'finalize_response_prompt' => isset( $module_data['finalize_response_prompt'] ) ? wp_kses_post( $module_data['finalize_response_prompt'] ) : null,
					// JSON fields - Encode after potential sanitization/validation of the array itself
					'connection_details' => isset( $module_data['connection_details'] ) && is_array( $module_data['connection_details'] ) ? wp_json_encode( $module_data['connection_details'] ) : null,
					'data_source_config' => isset( $module_data['data_source_config'] ) && is_array( $module_data['data_source_config'] ) ? wp_json_encode( $module_data['data_source_config'] ) : null,
					'output_config' => isset( $module_data['output_config'] ) && is_array( $module_data['output_config'] ) ? wp_json_encode( $module_data['output_config'] ) : null,
					// Deprecated/Other fields - Handle as needed or ignore
					// 'data_transformation_rules' => ..., 
					// 'target_table' => ...,
				);

				// Add any other allowed module fields here after sanitization

				$new_module_id = $this->db_modules->create_module( $sanitized_module_data );

				if ( is_wp_error( $new_module_id ) ) {
					$module_errors[] = sprintf( __( 'Failed to create module "%1$s": %2$s', 'data-machine' ), esc_html( $sanitized_module_data['module_name'] ), $new_module_id->get_error_message() );
				} elseif ( $new_module_id ) {
					$modules_created_count++;
				} else {
					$module_errors[] = sprintf( __( 'Failed to create module "%s". Unknown database error.', 'data-machine' ), esc_html( $sanitized_module_data['module_name'] ) );
				}
			}
		}

		// 9. Prepare Notice and Redirect
		$project_name = esc_html( $sanitized_project_data['project_name'] );
		if ( empty( $module_errors ) ) {
			$notice_message = sprintf(
				__( 'Project "%1$s" imported successfully with %2$d module(s).', 'data-machine' ),
				$project_name,
				$modules_created_count
			);
			$this->redirect_with_notice( 'success', $notice_message );
		} else {
			$notice_message = sprintf(
				__( 'Project "%1$s" imported with %2$d module(s), but some errors occurred:', 'data-machine' ),
				$project_name,
				$modules_created_count
			);
			$notice_message .= '<ul>';
			foreach ( $module_errors as $error ) {
				$notice_message .= '<li>' . esc_html( $error ) . '</li>';
			}
			$notice_message .= '</ul>';
			$this->redirect_with_notice( 'warning', $notice_message ); // Use warning type for partial success
		}
	}

	/**
	 * Handles the project deletion request.
	 */
	public function handle_project_delete() {
		// 1. Security Checks (Nonce & Capabilities)
		if ( ! isset( $_GET['project_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			wp_die( esc_html__( 'Invalid delete request.', 'data-machine' ) );
		}

		$project_id = absint( $_GET['project_id'] );
		$nonce      = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'dm_delete_project_' . $project_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'data-machine' ) );
		}

		// Check user capability
		if ( ! current_user_can( 'manage_options' ) ) { // Adjust capability if needed
			wp_die( esc_html__( 'You do not have permission to delete projects.', 'data-machine' ) );
		}

		// 2. Get Current User ID
		$current_user_id = get_current_user_id();

		// 3. Perform Deletion (using DB class methods)
		$deleted = $this->db_projects->delete_project( $project_id, $current_user_id );

		if ( is_wp_error( $deleted ) ) {
			// Handle WP_Error (e.g., permission denied, not found)
			$this->redirect_with_notice( 'error', sprintf( __( 'Error deleting project: %s', 'data-machine' ), $deleted->get_error_message() ) );
		} elseif ( $deleted === false ) {
			// Handle general DB deletion failure
			$this->redirect_with_notice( 'error', __( 'Failed to delete project due to a database error.', 'data-machine' ) );
		} elseif ( $deleted === 0 ) {
			 // Handle case where project didn't exist or wasn't deleted (might be covered by WP_Error)
			 $this->redirect_with_notice( 'warning', __( 'Project not found or already deleted.', 'data-machine' ) );
		} else {
			// Success!
			// Note: Modules associated with the project should be handled by the delete_project method (e.g., CASCADE or trigger).
			// We should confirm this behavior in Data_Machine_Database_Projects::delete_project.
			$this->redirect_with_notice( 'success', __( 'Project deleted successfully.', 'data-machine' ) );
		}
	}

	/**
	 * Redirects back to the main settings page with a notice.
	 *
	 * @param string $type    Notice type ('success', 'error', 'warning', 'info').
	 * @param string $message The notice message.
	 */
	private function redirect_with_notice( $type, $message ) {
        set_transient( 'dm_import_notice', array( 'type' => $type, 'message' => $message ), 30 ); // Store for 30 seconds
        $this->redirect_back();
	}

    /**
     * Redirects back to the referring page (likely the project dashboard).
     */
    private function redirect_back() {
        $redirect_url = wp_get_referer();
        if ( ! $redirect_url ) {
            // Fallback to the main admin page if referer is not available
            $redirect_url = admin_url( 'admin.php?page=data-machine' );
        }
        wp_safe_redirect( $redirect_url );
        exit;
    }

	/**
	 * Displays import/delete notices stored in transients.
	 */
	public function display_import_notices() {
        if ( $notice = get_transient( 'dm_import_notice' ) ) {
            $type    = isset( $notice['type'] ) ? sanitize_key( $notice['type'] ) : 'info';
            $message = isset( $notice['message'] ) ? wp_kses_post( $notice['message'] ) : ''; // Allow basic HTML

            if ( $message ) {
                printf(
                    '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                    esc_attr( $type ),
                    $message // Already sanitized with wp_kses_post
                );
            }
            delete_transient( 'dm_import_notice' );
        }
    }

}