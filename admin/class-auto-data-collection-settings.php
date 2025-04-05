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
	}

	/**
	 * Register settings using WordPress Settings API.
	 *
	 * @since    0.1.0
	 */
	public function register_settings() {
		// Register settings group
		register_setting(
			'auto_data_collection_settings_group', // Option group
			'openai_api_key', // Option name
			array( $this, 'sanitize_openai_api_key' ) // Sanitize callback
		);
		register_setting(
			'auto_data_collection_settings_group', // Option group
			'process_pdf_prompt', // Option name
			array( $this, 'sanitize_process_pdf_prompt' ) // Sanitize callback
		);
		register_setting(
			'auto_data_collection_settings_group', // Option group
			'fact_check_prompt', // Option name for fact-checking
			array( $this, 'sanitize_fact_check_prompt' ) // Sanitize callback
		);
		register_setting(
			'auto_data_collection_settings_group', // Option group
			'finalize_json_prompt', // Option name for finalizing JSON
			array( $this, 'sanitize_finalize_json_prompt' ) // Sanitize callback
		);

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
		add_settings_field(
			'process_pdf_prompt', // ID
			'Process PDF Prompt', // Title
			array( $this, 'process_pdf_prompt_callback' ), // Callback
			'auto-data-collection-settings-page', // Page
			'api_settings_section' // Section
		);
		add_settings_field(
			'fact_check_prompt', // ID
			'Fact Check Prompt', // Title
			array( $this, 'fact_check_prompt_callback' ), // Callback for fact-check prompt
			'auto-data-collection-settings-page', // Page
			'api_settings_section' // Section
		);
		add_settings_field(
			'finalize_json_prompt', // ID
			'Finalize JSON Prompt', // Title
			array( $this, 'finalize_json_prompt_callback' ), // Callback for finalize JSON prompt
			'auto-data-collection-settings-page', // Page
			'api_settings_section' // Section
		);
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

	/**
	 * Sanitize the Process PDF Prompt input.
	 *
	 * @since    0.1.0
	 * @param    string    $input    The unsanitized input.
	 * @return   string             The sanitized input.
	 */
	public function sanitize_process_pdf_prompt( $input ) {
		return wp_kses_post( $input );
	}

	/**
	 * Sanitize the Fact Check Prompt input.
	 *
	 * @since    0.1.0
	 * @param    string    $input    The unsanitized input.
	 * @return   string             The sanitized input.
	 */
	public function sanitize_fact_check_prompt( $input ) {
		return sanitize_text_field( $input );
	}

	/**
	 * Sanitize the Finalize JSON Prompt input.
	 *
	 * @since    0.1.0
	 * @param    string    $input    The unsanitized input.
	 * @return   string             The sanitized input.
	 */
	public function sanitize_finalize_json_prompt( $input ) {
		return sanitize_text_field( $input );
	}

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

	/**
	 * Process PDF Prompt field callback.
	 *
	 * @since    0.1.0
	 */
	public function process_pdf_prompt_callback() {
		printf(
			'<textarea id="process_pdf_prompt" name="process_pdf_prompt" rows="5" cols="60">%s</textarea>',
			esc_textarea( get_option( 'process_pdf_prompt', 'The Frankenstein Prompt' ) )
		);
	}

	/**
	 * Fact Check Prompt field callback.
	 *
	 * @since    0.1.0
	 */
	public function fact_check_prompt_callback() {
		printf(
			'<textarea id="fact_check_prompt" name="fact_check_prompt" rows="5" cols="60">%s</textarea>',
			esc_textarea( get_option( 'fact_check_prompt', 'Please fact-check the following data:' ) )
		);
	}

	/**
	 * Finalize JSON Prompt field callback.
	 *
	 * @since    0.1.0
	 */
	public function finalize_json_prompt_callback() {
		printf(
			'<textarea id="finalize_json_prompt" name="finalize_json_prompt" rows="5" cols="60">%s</textarea>',
			esc_textarea( get_option( 'finalize_json_prompt', 'Please finalize the JSON output:' ) )
		);
	}
}

new Auto_Data_Collection_Settings();
