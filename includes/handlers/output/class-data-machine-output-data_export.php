<?php
/**
 * Handles the 'Data Export' output type.
 *
 * Simply returns the final AI output string for copying.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/output
 * @since      0.7.0
 */
class Data_Machine_Output_Data_Export {

	/**
	 * Handles the processed data for the 'Data Export' type.
	 *
	 * Returns the AI output string directly.
	 *
	 * @param string $ai_output_string The raw output string from the Finalize API step.
	 * @param array  $module_job_config Simplified module configuration array for the job (not used by this handler).
	 * @param int|null $user_id The current user ID.
	 * @param array $input_metadata Metadata from the original input source.
	 * @return array|WP_Error Result array containing the final output or WP_Error on failure.
	 */
	public function handle( string $ai_output_string, array $module_job_config, ?int $user_id, array $input_metadata ): array|WP_Error {
		// For data export, the main result is the final string itself.
		// The orchestrator already includes this in the final result array.
		// We just need to return a success indicator and potentially a message.
		// $input_metadata is available here if needed in the future.
		return array(
			'status'  => 'success', // Indicate success
			'message' => __( 'Data ready for copying.', 'data-machine' ),
			'final_output' => $ai_output_string // Pass it along for consistency if needed by JS
		);
	}

	/**
	 * Get settings fields for the Data Export output handler.
	 *
	 * @return array Associative array of field definitions (empty for this handler).
	 */
	public function get_settings_fields(array $current_config = []): array {
		// This handler currently has no configurable settings.
		return [];
	}

	/**
	 * Get the user-friendly label for this handler.
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return 'Data Export';
	}

	/**
	 * Sanitize settings for the Data Export output handler.
	 * This handler currently has no specific settings.
	 *
	 * @param array $raw_settings
	 * @return array
	 */
	public function sanitize_settings(array $raw_settings): array {
		return $raw_settings;
	}
} // End class Data_Machine_Output_Data_Export