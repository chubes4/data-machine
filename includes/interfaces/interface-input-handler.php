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
	 * Fetches and prepares input data from the specific source.
	 *
	 * @param object $module The full module object containing configuration and context.
	 * @param array  $source_config Decoded data_source_config specific to this handler.
	 * @param int    $user_id The ID of the user initiating the process (for ownership/context checks).
	 * @return array An array of standardized input data packets, or an array indicating no new items (e.g., ['status' => 'no_new_items']).
	 * @throws Exception If data cannot be retrieved or is invalid.
	 */
	public function get_input_data(object $module, array $source_config, int $user_id): array;

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