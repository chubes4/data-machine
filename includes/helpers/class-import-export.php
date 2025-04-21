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
	 * Instance of the Service Locator.
	 * @var Data_Machine_Service_Locator
	 */
	private $locator;

	/**
	 * Instance of the Projects database class.
	 * @var Data_Machine_Database_Projects|null
	 */
	private $db_projects = null;

	/**
	 * Instance of the Modules database class.
	 * @var Data_Machine_Database_Modules|null
	 */
	private $db_modules = null;

	/**
	 * Constructor.
	 * Stores the locator and adds action hooks.
	 *
	 * @param Data_Machine_Service_Locator $locator The service locator instance.
	 */
	public function __construct( Data_Machine_Service_Locator $locator ) {
		$this->locator = $locator;
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
	 * Initializes database handlers from the locator.
	 * Separated from constructor to avoid issues if DB classes aren't registered yet during initial instantiation.
	 *
	 * @return bool True if successful, false otherwise.
	 */
	private function init_db_handlers() {
		if ( $this->db_projects === null ) {
			$this->db_projects = $this->locator->get( 'database_projects' );
		}
		if ( $this->db_modules === null ) {
			$this->db_modules = $this->locator->get( 'database_modules' );
		}
		return ( $this->db_projects && $this->db_modules );
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

		// 2. Initialize DB Handlers
		if ( ! $this->init_db_handlers() ) {
			wp_die( esc_html__( 'Database services are unavailable. Cannot export project.', 'data-machine' ) );
		}

		// 3. Fetch Data
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

		// 5. Initialize DB Handlers
		if ( ! $this->init_db_handlers() ) {
			wp_die( esc_html__( 'Database services are unavailable. Cannot import project.', 'data-machine' ) );
			// Or use redirect_with_notice if preferred
			// $this->redirect_with_notice('error', __( 'Database services are unavailable. Cannot import project.', 'data-machine' ));
			// return;
		}

		// 6. Get Current User ID
		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			wp_die( esc_html__( 'Could not determine current user.', 'data-machine' ) ); // Should not happen for logged-in users
		}

		// 7. Create Project
		$project_data = $import_data['project'];
		// Ensure required fields exist (e.g., project_name)
		if ( empty( $project_data['project_name'] ) ) {
			$this->redirect_with_notice( 'error', __( 'Import failed: Project name is missing in the import file.', 'data-machine' ) );
			return;
		}

		// Prepare data for create_project (pass user ID and name)
        // Note: Assumes create_project takes user_id, name, and optionally an array of other data
        // Adjust if your create_project method signature is different
        $new_project_id = $this->db_projects->create_project( $current_user_id, $project_data['project_name'], $project_data );

		if ( ! $new_project_id || is_wp_error( $new_project_id ) ) {
			$error_message = is_wp_error( $new_project_id ) ? $new_project_id->get_error_message() : __( 'Failed to create the project in the database.', 'data-machine' );
			$this->redirect_with_notice( 'error', sprintf( __( 'Import failed: %s', 'data-machine' ), $error_message ) );
			return;
		}

		// 8. Create Modules
		$modules_data    = $import_data['modules'];
		$imported_count  = 0;
		$failed_modules = array();

		if ( ! empty( $modules_data ) ) {
			foreach ( $modules_data as $index => $module_data ) {
                // Ensure required fields like module_name exist
                if ( empty( $module_data['module_name'] ) ) {
                    $failed_modules[] = sprintf( __( 'Module #%d: Missing module name.', 'data-machine' ), $index + 1 );
                    continue; // Skip this module
                }

                // Encode JSON fields if they were decoded during export/import
                foreach (['connection_details', 'data_source_config', 'output_config'] as $json_field) {
                    if (isset($module_data[$json_field]) && is_array($module_data[$json_field])) {
                        $module_data[$json_field] = wp_json_encode($module_data[$json_field]);
                        if ($module_data[$json_field] === false) {
                             $failed_modules[] = sprintf( __( 'Module "%s": Failed to re-encode %s.', 'data-machine' ), esc_html($module_data['module_name']), $json_field );
                             continue 2; // Skip this module entirely if encoding fails
                        }
                    }
                }


				// Note: Assumes create_module takes project_id and an array of module data
				// Ensure user_id is NOT passed if create_module doesn't expect it or gets it via project_id lookup
				$new_module_id = $this->db_modules->create_module( $new_project_id, $module_data );

				if ( ! $new_module_id || is_wp_error( $new_module_id ) ) {
					$module_name = isset( $module_data['module_name'] ) ? esc_html( $module_data['module_name'] ) : __( 'Unnamed Module', 'data-machine' );
					$error_message = is_wp_error( $new_module_id ) ? $new_module_id->get_error_message() : __( 'Database error.', 'data-machine' );
					$failed_modules[] = sprintf( __( 'Module "%s": %s', 'data-machine' ), $module_name, $error_message );
				} else {
					$imported_count++;
				}
			}
		}

		// 9. Provide Feedback and Redirect
		if ( empty( $failed_modules ) ) {
			$message = sprintf( __( 'Project "%s" and %d module(s) imported successfully.', 'data-machine' ), esc_html( $project_data['project_name'] ), $imported_count );
			$this->redirect_with_notice( 'success', $message );
		} else {
			$message = sprintf( __( 'Project "%s" imported, but %d out of %d modules failed to import.', 'data-machine' ), esc_html( $project_data['project_name'] ), count( $failed_modules ), count( $modules_data ) );
			$message .= '<br/>' . __( 'Errors:', 'data-machine' ) . '<ul><li>' . implode( '</li><li>', $failed_modules ) . '</li></ul>';
			// Store the complex message in a transient because redirect might lose it
			set_transient( 'dm_import_notice', array('type' => 'warning', 'message' => $message), 60 ); // Store for 60 seconds
            $this->redirect_back(); // Redirect without adding notice directly
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
		if ( ! current_user_can( 'manage_options' ) ) { // Use appropriate capability
			wp_die( esc_html__( 'You do not have permission to delete projects.', 'data-machine' ) );
		}

		// 2. Initialize DB Handlers
		if ( ! $this->init_db_handlers() ) {
			$this->redirect_with_notice( 'error', __( 'Database services are unavailable. Cannot delete project.', 'data-machine' ) );
            return;
		}

		// 3. Verify Project Ownership (optional but recommended)
        $current_user_id = get_current_user_id();
        $project = $this->db_projects->get_project( $project_id, $current_user_id );
        if ( ! $project ) {
            $this->redirect_with_notice( 'error', __( 'Project not found or you do not have permission to delete it.', 'data-machine' ) );
            return;
        }
        $project_name = $project->project_name; // Store name for notice

		// 4. Delete Associated Modules
        // Assumes a 'delete_modules_for_project' method exists in Database_Modules
        $modules_deleted = $this->db_modules->delete_modules_for_project( $project_id ); 
        if ( $modules_deleted === false ) {
            // Log the error, but attempt to delete the project anyway? Or stop?
            error_log("DM Import/Export: Failed to delete modules for project ID {$project_id}. Proceeding with project deletion attempt.");
            // Optionally redirect with a warning here if module deletion failure is critical
            // $this->redirect_with_notice( 'warning', sprintf(__( 'Failed to delete associated modules, but attempting to delete project \'%s\'. Please check manually.', 'data-machine' ), esc_html($project_name) ) );
            // return; 
        }

		// 5. Delete Project
        // Assumes a 'delete_project' method exists in Database_Projects
        $project_deleted = $this->db_projects->delete_project( $project_id, $current_user_id ); // Pass user_id for final ownership check

		if ( $project_deleted ) {
			$this->redirect_with_notice( 'success', sprintf(__( 'Project \'%s\' and its associated modules were deleted successfully.', 'data-machine' ), esc_html($project_name) ) );
		} else {
			$this->redirect_with_notice( 'error', sprintf(__( 'Failed to delete the project \'%s\'. Associated modules may or may not have been deleted.', 'data-machine' ), esc_html($project_name) ) );
		}
	}

	/**
	 * Helper function to redirect back to the dashboard page with an admin notice.
	 * Uses transients for reliability across redirects.
	 *
	 * @param string $type    Notice type ('success', 'error', 'warning', 'info').
	 * @param string $message The message to display.
	 */
	private function redirect_with_notice( $type, $message ) {
		set_transient( 'dm_import_notice', array('type' => $type, 'message' => $message), 60 ); // Store for 60 seconds
		$this->redirect_back();
	}

    /**
     * Helper function to redirect back to the plugin's dashboard page.
     */
    private function redirect_back() {
        // Assumes your dashboard page slug is 'data-machine-dashboard'
        // Adjust the 'page' parameter if your slug is different
        $redirect_url = admin_url( 'admin.php?page=data-machine-project-dashboard-page' ); // Use correct slug
		wp_safe_redirect( $redirect_url );
		exit;
    }

	/**
	 * Display admin notices stored in the transient.
	 * Needs to be hooked to 'admin_notices'.
	 */
	public function display_import_notices() {
		if ( $notice = get_transient( 'dm_import_notice' ) ) {
			$type = isset($notice['type']) ? $notice['type'] : 'info';
            $message = isset($notice['message']) ? $notice['message'] : ''; // Already contains HTML formatting if complex
            // Use wp_kses_post for complex messages potentially containing basic HTML like lists
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), wp_kses_post( $message ) );
			delete_transient( 'dm_import_notice' );
		}
	}

} // End class 