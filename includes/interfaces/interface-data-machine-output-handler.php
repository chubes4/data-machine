<?php
/**
 * Interface for output handlers.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/interfaces
 * @since      0.1.0
 */
interface Data_Machine_Output_Handler_Interface {

	/**
	 * Process the AI output string based on the module's configuration.
	 *
	 * @param string $ai_output_string The finalized string from the AI.
	 * @param array  $module_job_config Specific configuration for this output job (e.g., post type, category).
	 * @param int|null $user_id The ID of the user context for the operation (e.g., post author).
	 * @param array $input_metadata Metadata from the original input source (e.g., original URL, timestamp).
	 * @return array|WP_Error Array with results (e.g., post ID, success message) or WP_Error on failure.
	 */
	public function handle( string $ai_output_string, array $module_job_config, ?int $user_id, array $input_metadata ): array|WP_Error;

	/**
	 * Get settings fields specific to this output handler.
	 *
	 * @return array Associative array of field definitions.
	 */
	public static function get_settings_fields(array $current_config = []): array;

    /**
     * Get the user-friendly label for this handler.
     *
     * @return string
     */
    public static function get_label(): string;
} 