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