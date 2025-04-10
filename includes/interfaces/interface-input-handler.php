<?php
/**
 * Interface for Data Source Input Handlers.
 *
 * Defines the contract for classes that handle specific data source types
 * (e.g., files, RSS feeds, APIs) and initiate the processing workflow.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/interfaces
 * @since      0.7.0 // Or the next version number
 */
interface Data_Machine_Input_Handler_Interface {

    /**
     * Processes the input data for a given module.
     *
     * This method should handle validation specific to the input type,
     * prepare the initial data, and trigger the processing orchestrator.
     * It typically corresponds to an AJAX action handler.
     *
     * @since 0.7.0
     * @param array $post_data Data from the $_POST superglobal.
     * @param array $files_data Data from the $_FILES superglobal (if applicable).
     * @return void This method typically sends a JSON response and exits.
     */
    public function process_input( $post_data, $files_data );
	/**
	 * Fetches and prepares the input data into a standardized format.
	 *
	 * Should return an associative array containing either 'content_string' or 'file_info',
	 * plus a 'metadata' array.
	 * Example for text: ['content_string' => '...', 'metadata' => ['source_type' => '...']]
	 * Example for file: ['file_info' => ['tmp_name'=>..., 'name'=>...], 'metadata' => ['source_type' => '...']]
	 *
	 * @since 0.8.0 // Or next version
	 * @param array $post_data Data from the $_POST superglobal (or equivalent context).
	 * @param array $files_data Data from the $_FILES superglobal (if applicable).
     * @param array $source_config Decoded data_source_config for the specific module run.
     * @param int   $user_id The ID of the user context for this operation (e.g., owner or initiator).
	 * @return array The standardized input data packet.
	 * @throws Exception If input data is invalid or cannot be retrieved.
	 */
	public function get_input_data(array $post_data, array $files_data, array $source_config, int $user_id): array;

 /**
  * Get the settings fields specific to this input handler.
  *
  * Should return an associative array where keys are field names (used in config array)
  * and values are arrays describing the field (e.g., ['type' => 'text', 'label' => '...', 'description' => '...']).
  *
  * @return array Associative array of field definitions.
  */
 public static function get_settings_fields();

}