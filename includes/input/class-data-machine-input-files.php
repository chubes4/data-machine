<?php
/**
 * Handles file uploads as a data source.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/input
 * @since      0.7.0
 */
class Data_Machine_Input_Files implements Data_Machine_Input_Handler_Interface {

	/**
	 * The main plugin instance.
	 * @var Data_Machine
	 */
	private $plugin;

	/**
	 * Processing Orchestrator instance.
	 * @var Data_Machine_Processing_Orchestrator
	 */
	private $orchestrator;

	/**
	 * Database Modules instance.
	 * @var Data_Machine_Database_Modules
	 */
	private $db_modules;

	/**
	 * Constructor. Dependencies are injected.
	 *
	 * @param Data_Machine_Processing_Orchestrator $orchestrator Processing orchestrator.
	 * @param Data_Machine_Database_Modules $db_modules Database modules handler.
	 */
	public function __construct(Data_Machine_Processing_Orchestrator $orchestrator, Data_Machine_Database_Modules $db_modules) {
	 // $this->plugin = $plugin; // Removed plugin dependency
	 $this->orchestrator = $orchestrator;
	 $this->db_modules = $db_modules;
	}

	/**
	 * Fetches and prepares the file input data into a standardized format.
	 *
	 * @param array $post_data Data from the $_POST superglobal.
	 * @param array $files_data Data from the $_FILES superglobal.
	 * @param array $source_config Decoded data_source_config for the specific module run.
	 * @param int   $user_id The ID of the user context.
	 * @return array The standardized input data packet.
	 * @throws Exception If file is missing, invalid, or cannot be processed.
	 */
	public function get_input_data(array $post_data, array $files_data, array $source_config, int $user_id): array {
		// Ensure user ID is valid (passed from context)
		if ( empty($user_id) ) {
			 throw new Exception(__( 'User ID not provided for file processing.', 'data-machine' ));
		}

		$module_id = isset( $post_data['module_id'] ) ? absint( $post_data['module_id'] ) : 0;
		if ( empty( $module_id ) ) {
			throw new Exception(__( 'Missing module ID.', 'data-machine' ));
		}

		// Ownership check (using the passed user_id)
		$module = $this->db_modules->get_module($module_id); // Removed user_id check here
		if (!$module || !isset($module->project_id)) {
			 throw new Exception(__( 'Invalid module or project association missing.', 'data-machine' ));
		}
		// Need project DB instance - Assuming it's available via locator
		$db_projects = $this->locator->get('database_projects');
		if (!$db_projects || !$db_projects->get_project($module->project_id, $user_id)) { // Check ownership using passed user_id
			 throw new Exception(__( 'Permission denied for this module.', 'data-machine' ));
		}

		// File handling logic starts here...
		// Check if file data is present in $files_data (for uploads)
		if (!empty($files_data['file_upload'])) {
			$file = $files_data['file_upload'];

			// Check for upload errors
			if ( $file['error'] !== UPLOAD_ERR_OK ) {
				throw new Exception($this->get_upload_error_message($file['error']));
			}

			// Validate file type (using config from $source_config if provided)
			$allowed_types = $source_config['allowed_file_types'] ?? ['text/plain', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']; // Example defaults
			if ( ! in_array( $file['type'], $allowed_types ) ) {
				throw new Exception( sprintf( __( 'Invalid file type: %s. Allowed types: %s', 'data-machine' ), $file['type'], implode(', ', $allowed_types) ) );
			}

			// Move uploaded file to a persistent location
			$upload_dir = wp_upload_dir();
			$persistent_dir = $upload_dir['basedir'] . '/dm_persistent_jobs/';
			wp_mkdir_p( $persistent_dir ); // Ensure directory exists

			// Create a unique filename
			$file_extension = pathinfo( $file['name'], PATHINFO_EXTENSION );
			$new_filename = 'job_' . $module_id . '_' . time() . '_' . wp_generate_password( 8, false ) . '.' . $file_extension;
			$persistent_path = $persistent_dir . $new_filename;

			if ( ! move_uploaded_file( $file['tmp_name'], $persistent_path ) ) {
				throw new Exception(__( 'Failed to move uploaded file to persistent storage.', 'data-machine' ));
			}

			// Extract content (basic text for now)
			$content_string = file_get_contents( $persistent_path ); // TODO: Add PDF/DOCX parsing
			if ($content_string === false) {
				 // Cleanup if read fails
				 unlink($persistent_path);
				 throw new Exception(__( 'Failed to read file content.', 'data-machine' ));
			}

			// Prepare packet
			$input_data_packet = [
				'content_string' => $content_string,
				'file_info' => [
					'original_name' => $file['name'],
					'mime_type' => $file['type'],
					'size' => $file['size'],
					'persistent_path' => $persistent_path // Important for cleanup later
				],
				'metadata' => [
					'source_type' => 'files',
					'module_id' => $module_id
				]
			];
		} else {
			// Handle case where no file was uploaded (e.g., cron run expecting a pre-existing file path in config?)
			// TODO: Define how 'files' type should work without an upload (e.g., read path from $source_config)
			return ['error' => true, 'message' => 'no_input_data', 'details' => 'File upload data missing for \'files\' type module.']; // Or throw Exception?
		}

		return $input_data_packet;
	}

	/**
	 * Get settings fields for the Files input handler.
	 *
	 * @return array Associative array of field definitions (empty for this handler).
	 */
	public static function get_settings_fields() {
		// This handler currently has no specific settings.
		return [];
	}
/**
 * Sanitize settings for the Files input handler.
 * This handler currently has no specific settings.
 *
 * @param array $raw_settings
 * @return array
 */
public function sanitize_settings(array $raw_settings): array {
	return $raw_settings;
}

/**
 * Get the user-friendly label for this handler.
 *
 * @return string The label.
 */
public static function get_label(): string {
	return __( 'Files', 'data-machine' );
}

}

