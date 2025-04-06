<?php
/**
 * Manages plugin settings using the WordPress Settings API.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Auto_Data_Collection
 * @subpackage Auto_Data_Collection/includes
 */

/**
 * Manages plugin settings.
 */
class Auto_Data_Collection_Settings {

	/**
	 * Initialize settings.
	 *
	 * @since    0.1.0
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_module_selection_save' ) ); // Add hook for custom save handler
	}

	/**
	 * Register settings using WordPress Settings API.
	 *
	 * @since    0.1.0
	 */
	public function register_settings() {
		// Register module management settings
		// Removed register_setting for auto_data_collection_current_module - using user meta now
		register_setting(
			'auto_data_collection_settings_group',
			'auto_data_collection_new_module_name',
			'sanitize_text_field'
		);

		// Register processing settings
		register_setting(
			'auto_data_collection_settings_group', // Option group
			'openai_api_key', // Option name
			array( $this, 'sanitize_openai_api_key' ) // Sanitize callback
		);
		// Note: Prompt settings are now saved per-module via validate_module_selection
		// register_setting(
		// 	'auto_data_collection_settings_group', // Option group
		// 	'process_data_prompt', // Option name
		// 	array( $this, 'sanitize_process_data_prompt' ) // Sanitize callback
		// );
		// register_setting(
		// 	'auto_data_collection_settings_group', // Option group
		// 	'fact_check_prompt', // Option name for fact-checking
		// 	array( $this, 'sanitize_fact_check_prompt' ) // Sanitize callback
		// );
		// register_setting(
		// 	'auto_data_collection_settings_group', // Option group
		// 	'finalize_json_prompt', // Option name for finalizing JSON
		// 	array( $this, 'sanitize_finalize_json_prompt' ) // Sanitize callback
		// );

		// Add settings section
		add_settings_section(
			'api_settings_section', // ID
			'API Configuration', // Title
			array( $this, 'print_api_settings_section_info' ), // Callback
			'auto-data-collection-settings-page' // Page
		);

		// Add settings fields
		add_settings_field(
			'openai_api_key', // ID
			'OpenAI API Key', // Title
			array( $this, 'openai_api_key_callback' ), // Callback
			'auto-data-collection-settings-page', // Page
			'api_settings_section' // Section
		);
		// Note: Prompt fields are now part of the dynamic module settings UI
		// add_settings_field(
		// 	'process_data_prompt', // ID
		// 	'Process Data Prompt', // Title
		// 	array( $this, 'process_data_prompt_callback' ), // Callback
		// 	'auto-data-collection-settings-page', // Page
		// 	'api_settings_section' // Section
		// );
		// add_settings_field(
		// 	'fact_check_prompt', // ID
		// 	'Fact Check Prompt', // Title
		// 	array( $this, 'fact_check_prompt_callback' ), // Callback for fact-check prompt
		// 	'auto-data-collection-settings-page', // Page
		// 	'api_settings_section' // Section
		// );
		// add_settings_field(
		// 	'finalize_json_prompt', // ID
		// 	'Finalize Prompt', // Title
		// 	array( $this, 'finalize_json_prompt_callback' ), // Callback for finalize JSON prompt
		// 	'auto-data-collection-settings-page', // Page
		// 	'api_settings_section' // Section
		// );
	}

	/**
	 * Sanitize the OpenAI API Key input.
	 *
	 * @since    0.1.0
	 * @param    string    $input    The unsanitized input.
	 * @return   string             The sanitized input.
	 */
	public function sanitize_openai_api_key( $input ) {
		return sanitize_text_field( $input );
	}

	// Note: Prompt sanitize functions removed as prompts are saved via module validation

	/**
	 * Print the API Settings section information.
	 *
	 * @since    0.1.0
	 */
	public function print_api_settings_section_info() {
		print 'Enter your OpenAI API key and prompts below:';
	}

	/**
	 * OpenAI API Key field callback.
	 *
	 * @since    0.1.0
	 */
	public function openai_api_key_callback() {
		printf(
			'<input type="text" id="openai_api_key" name="openai_api_key" value="%s" class="regular-text" />',
			esc_attr( get_option( 'openai_api_key' ) )
		);
	}

	// Note: Prompt callback functions removed as fields are now dynamic

	/**
	 * Handle saving module selection and data directly on admin_init.
	 *
	 * @since    0.1.0 // Update version as needed
	 */
	public function handle_module_selection_save() {
		// Check if our settings form was submitted
		if ( isset( $_POST['option_page'] ) && $_POST['option_page'] === 'auto_data_collection_settings_group' ) {
			// Verify nonce (WordPress handles this via settings_fields/options.php submission)
			// check_admin_referer('auto_data_collection_settings_group-options'); // Usually needed for custom actions, but options.php handles it. Still good practice if unsure.

			// Check if the module selection field is set
			if ( isset( $_POST['auto_data_collection_current_module'] ) ) {
				$submitted_value = sanitize_text_field( $_POST['auto_data_collection_current_module'] );
				$user_id = get_current_user_id();
				$db_modules = new Auto_Data_Collection_Database_Modules();

				// Retrieve submitted prompt values
				$module_name = isset($_POST['module_name']) ? sanitize_text_field($_POST['module_name']) : '';
				$process_prompt = isset($_POST['process_data_prompt']) ? wp_kses_post($_POST['process_data_prompt']) : '';
				$fact_check_prompt = isset($_POST['fact_check_prompt']) ? wp_kses_post($_POST['fact_check_prompt']) : '';
				$finalize_prompt = isset($_POST['finalize_json_prompt']) ? wp_kses_post($_POST['finalize_json_prompt']) : '';

				// Handle new module creation
				if ($submitted_value === 'new') {
					if (empty($module_name)) {
						add_settings_error('auto_data_collection_messages', 'auto_data_collection_message', __('Module name cannot be empty when creating a new module.', 'auto-data-collection'), 'error');
						return; // Stop processing
					}

					$module_data = array(
						'module_name' => $module_name,
						'process_data_prompt' => $process_prompt,
						'fact_check_prompt' => $fact_check_prompt,
						'finalize_json_prompt' => $finalize_prompt,
					);

					$module_id = $db_modules->create_module($user_id, $module_data);

					if ($module_id) {
						add_settings_error('auto_data_collection_messages', 'auto_data_collection_message', __('New module created and selected.', 'auto-data-collection'), 'updated');
						// Save the new module ID as the user's current selection
						update_user_meta($user_id, 'auto_data_collection_current_module', $module_id);
					} else {
						add_settings_error('auto_data_collection_messages', 'auto_data_collection_message', __('Failed to create new module.', 'auto-data-collection'), 'error');
					}
					return; // Stop processing after handling 'new'
				}

				// --- If $submitted_value was NOT 'new', proceed to update logic ---

				// Handle updating an existing module
				$module_id_to_update = absint($submitted_value);
				$existing_module = $db_modules->get_module($module_id_to_update, $user_id);

				// Check if the selected module is valid and belongs to the user
				if ($existing_module) {
					// Prepare data for potential update
					$update_data = array();
					if (isset($_POST['module_name']) && $_POST['module_name'] !== $existing_module->module_name) {
						$update_data['module_name'] = sanitize_text_field($_POST['module_name']);
					}
					if (isset($_POST['process_data_prompt']) && $_POST['process_data_prompt'] !== $existing_module->process_data_prompt) {
						$update_data['process_data_prompt'] = wp_kses_post($_POST['process_data_prompt']);
					}
					if (isset($_POST['fact_check_prompt']) && $_POST['fact_check_prompt'] !== $existing_module->fact_check_prompt) {
						$update_data['fact_check_prompt'] = wp_kses_post($_POST['fact_check_prompt']);
					}
					if (isset($_POST['finalize_json_prompt']) && $_POST['finalize_json_prompt'] !== $existing_module->finalize_json_prompt) {
						$update_data['finalize_json_prompt'] = wp_kses_post($_POST['finalize_json_prompt']);
					}

					// Only update the database if there are actual changes
					if (!empty($update_data)) {
						$updated = $db_modules->update_module($module_id_to_update, $update_data, $user_id);
						if ($updated !== false) {
							add_settings_error('auto_data_collection_messages', 'auto_data_collection_message', __('Module settings updated.', 'auto-data-collection'), 'updated');
						} else {
							add_settings_error('auto_data_collection_messages', 'auto_data_collection_message', __('Failed to update module settings.', 'auto-data-collection'), 'error');
						}
					}

					// Always save the selected module ID as the user's current choice if it was valid
					update_user_meta($user_id, 'auto_data_collection_current_module', $module_id_to_update);

				} else {
					// The submitted ID was not 'new' but was invalid or didn't belong to the user.
					add_settings_error('auto_data_collection_messages', 'auto_data_collection_message', __('Invalid module selection or permission denied.', 'auto-data-collection'), 'error');
					// Do not update user meta in this case, leave the previous selection.
				}
			}
		}
	}

	// Removed sanitize_and_save_module_data function - logic moved to handle_module_selection_save
}

new Auto_Data_Collection_Settings();
