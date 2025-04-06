<?php
/**
 * Manages plugin settings using the WordPress Settings API.
 */
class Auto_Data_Collection_Settings {

    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function register_settings() {
        register_setting(
            'auto_data_collection_settings_group',
            'openai_api_key',
            array( $this, 'sanitize_openai_api_key' )
        );
        register_setting(
            'auto_data_collection_settings_group',
            'process_data_prompt',
            array( $this, 'sanitize_process_data_prompt' )
        );
        register_setting(
            'auto_data_collection_settings_group',
            'fact_check_prompt',
            array( $this, 'sanitize_fact_check_prompt' )
        );
        register_setting(
            'auto_data_collection_settings_group',
            'finalize_json_prompt',
            array( $this, 'sanitize_finalize_json_prompt' )
        );

        add_settings_section(
            'api_settings_section',
            'API Configuration',
            array( $this, 'print_api_settings_section_info' ),
            'auto-data-collection-settings-page'
        );

        add_settings_field(
            'openai_api_key',
            'OpenAI API Key',
            array( $this, 'openai_api_key_callback' ),
            'auto-data-collection-settings-page',
            'api_settings_section'
        );
        add_settings_field(
            'process_data_prompt',
            'Process Data Prompt',
            array( $this, 'process_data_prompt_callback' ),
            'auto-data-collection-settings-page',
            'api_settings_section'
        );
        add_settings_field(
            'fact_check_prompt',
            'Fact Check Prompt',
            array( $this, 'fact_check_prompt_callback' ),
            'auto-data-collection-settings-page',
            'api_settings_section'
        );
        add_settings_field(
            'finalize_json_prompt',
            'Finalize JSON Prompt',
            array( $this, 'finalize_json_prompt_callback' ),
            'auto-data-collection-settings-page',
            'api_settings_section'
        );
    }

    public function sanitize_openai_api_key( $input ) {
        return sanitize_text_field( $input );
    }
    public function sanitize_process_data_prompt( $input ) {
        return wp_kses_post( $input );
    }
    public function sanitize_fact_check_prompt( $input ) {
        return sanitize_text_field( $input );
    }
    public function sanitize_finalize_json_prompt( $input ) {
        return sanitize_text_field( $input );
    }

    public function print_api_settings_section_info() {
        echo 'Enter your OpenAI API key and prompts below:';
    }

    public function openai_api_key_callback() {
        printf(
            '<textarea id="openai_api_key" name="openai_api_key" style="width:100%%; min-height:200px; white-space:pre-wrap;">%s</textarea>',
            esc_textarea( get_option( 'openai_api_key' ) )
        );
    }

    public function process_data_prompt_callback() {
        printf(
            '<textarea id="process_data_prompt" name="process_data_prompt" style="width:100%%; min-height:200px; white-space:pre-wrap;">%s</textarea>',
            esc_textarea( get_option( 'process_data_prompt', 'The Frankenstein Prompt' ) )
        );
    }

    public function fact_check_prompt_callback() {
        printf(
            '<textarea id="fact_check_prompt" name="fact_check_prompt" style="width:100%%; min-height:200px; white-space:pre-wrap;">%s</textarea>',
            esc_textarea( get_option( 'fact_check_prompt', 'Please fact-check the following data:' ) )
        );
    }

    public function finalize_json_prompt_callback() {
        printf(
            '<textarea id="finalize_json_prompt" name="finalize_json_prompt" style="width:100%%; min-height:200px; white-space:pre-wrap;">%s</textarea>',
            esc_textarea( get_option( 'finalize_json_prompt', 'Please finalize the JSON output:' ) )
        );
    }
}

new Auto_Data_Collection_Settings();
