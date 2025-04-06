<?php

/**
 * The main plugin class.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Auto_Data_Collection
 * @subpackage Auto_Data_Collection/includes
 */

// Include settings class - IMPORTANT: Include this BEFORE using the class
require_once plugin_dir_path( __FILE__ ) . '../admin/class-auto-data-collection-settings.php';

// Include Admin Page Class
require_once plugin_dir_path( __FILE__ ) . '../admin/class-auto-data-collection-admin-page.php'; // Include the new admin page class

// Include API classes
require_once plugin_dir_path( __FILE__ ) . 'api/class-auto-data-collection-api-openai.php';
require_once plugin_dir_path( __FILE__ ) . 'api/class-auto-data-collection-api-factcheck.php';
require_once plugin_dir_path( __FILE__ ) . 'api/class-auto-data-collection-api-jsonfinalize.php';
// Include Process Data class
require_once plugin_dir_path( __FILE__ ) . 'class-process-data.php';

/**
 * The main plugin class.
 */
class Auto_Data_Collection {

	/**
	 * The plugin version.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $version    The current plugin version.
	 */
	private $version;

	/**
	 * Settings class instance.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      Auto_Data_Collection_Settings    $settings    Settings class instance.
	 */
	private $settings;

    /**
     * Admin Page class instance.
     *
     * @since    0.1.0
     * @access   private
     * @var      Auto_Data_Collection_Admin_Page    $admin_page    Admin Page class instance.
     */
    private $admin_page; // Add admin_page class instance

	/**
	 * OpenAI API class instance.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      Auto_Data_Collection_API_OpenAI    $openai_api    OpenAI API class instance.
	 */
	private $openai_api;

	/**
	 * FactCheck API class instance.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      Auto_Data_Collection_API_FactCheck    $factcheck_api    FactCheck API class instance.
	 */
	private $factcheck_api;

	/**
	 * JSONFinalize API class instance.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      Auto_Data_Collection_API_JSONFinalize    $jsonfinalize_api    JSONFinalize API class instance.
	 */
	private $jsonfinalize_api;

	/**
	 * Process Data class instance.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      Auto_Data_Collection_process_data    $process_data    Process Data class instance.
	 */
	private $process_data;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.1.0
	 */
	public function __construct() {
		$this->version = AUTO_DATA_COLLECTION_VERSION;
		$this->settings = new Auto_Data_Collection_Settings(); // Instantiate settings class
        $this->admin_page = new Auto_Data_Collection_Admin_Page($this->version); // Instantiate admin page class with version
		$this->openai_api = new Auto_Data_Collection_API_OpenAI( $this ); // Instantiate OpenAI API class
		$this->factcheck_api = new Auto_Data_Collection_API_FactCheck(); // Instantiate FactCheck API class
		$this->jsonfinalize_api = new Auto_Data_Collection_API_JSONFinalize(); // Instantiate JSONFinalize API class
		$this->process_data = new Auto_Data_Collection_process_data( $this ); // Instantiate Process Data class with plugin instance
	}

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since    0.1.0
	 */
	public function run() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin_page, 'enqueue_admin_assets' ) ); // Use admin page class for assets
		add_action( 'wp_ajax_process_data', array( $this, 'process_data_ajax_handler' ) );
		add_action( 'wp_ajax_fact_check_json', array( $this, 'fact_check_json_ajax_handler' ) );
		add_action( 'wp_ajax_finalize_json', array( $this, 'finalize_json_ajax_handler' ) );
		add_action( 'admin_notices', array( $this->admin_page, 'display_admin_notices' ) ); // Use admin page class for notices
	}

	/**
	 * Add admin menu for the plugin.
	 *
	 * @since    0.1.0
	 */
	public function add_admin_menu() {
		$this->admin_page->add_admin_menu();
	}

	/**
	 * Process Data upload and OpenAI API call - AJAX handler.
	 *
	 * @since    0.1.0
	 */
	public function process_data_ajax_handler() {
		check_ajax_referer( 'file_processing_nonce', 'nonce' );

		$api_key = get_option( 'openai_api_key' ); // Get API key from settings using get_option()
		$process_data_prompt = get_option( 'process_data_prompt', 'The Frankenstein Prompt' ); // Get system prompt from settings, default to "The Frankenstein Prompt"
		$data_file = isset( $_FILES['data_file'] ) ? $_FILES['data_file'] : null;

		if ( empty( $api_key ) ) {
			$this->log_error( 'OpenAI API Key is missing. Please enter it in the plugin settings.' );
			wp_send_json_error( array( 'message' => 'OpenAI API Key is missing. Please enter it in the plugin settings.' ) );
			return;
		}

		if ( empty( $process_data_prompt ) ) {
			$this->log_error( 'System Prompt is missing. Please enter it in the plugin settings.' );
			wp_send_json_error( array( 'message' => 'System Prompt is missing.' ) );
			return;
		}

		if ( empty( $data_file ) || $data_file['error'] !== 0 ) {
			$this->log_error( 'PDF file upload failed.' );
			wp_send_json_error( array( 'message' => 'PDF file upload failed.' ) );
			return;
		}

		// Use OpenAI API class to Process Data
		$api_response = $this->process_data->process_data( $api_key, $process_data_prompt, $data_file );

		if ( is_wp_error( $api_response ) ) {
			$error_message = 'OpenAI API Error: ' . $api_response->get_error_message();
			$error_data = array(
				'error_code'    => $api_response->get_error_code(),
				'error_message' => $api_response->get_error_message(),
			);
			$this->log_error( $error_message, $error_data ); // Log detailed error info
			wp_send_json_error( array( 'message' => 'Failed to process Data. Please check plugin errors for details.', 'error_detail' => $error_message ) );
			return;
		}

		$response_data = array(
			'status'      => 'processing-success',
			'json_output' => $api_response['json_output'],
		);

		wp_send_json_success( $response_data );
	}

/**
 * Process Fact Check AJAX handler.
 *
 * @since 0.1.0
 */
public function fact_check_json_ajax_handler() {
    check_ajax_referer( 'fact_check_nonce', 'nonce' );

    $api_key = get_option( 'openai_api_key' );
    $fact_check_prompt = get_option( 'fact_check_prompt', 'Please fact-check the following data:' );
    // Retrieve JSON data from the correct POST field:
    $json_to_fact_check = isset($_POST['json_data']) ? $_POST['json_data'] : '';

    if ( empty( $api_key ) ) {
        wp_send_json_error( array( 'message' => 'OpenAI API Key is missing.' ) );
        return;
    }
    if ( empty( $json_to_fact_check ) ) {
        wp_send_json_error( array( 'message' => 'No JSON data provided for fact checking.' ) );
        return;
    }

    // Instantiate the FactCheck API class.
    $fact_check_api = new Auto_Data_Collection_API_FactCheck();
    $fact_check_response = $fact_check_api->fact_check_json( $api_key, $json_to_fact_check, $fact_check_prompt );

    if ( is_wp_error( $fact_check_response ) ) {
        $error_message = 'Fact check failed: ' . $fact_check_response->get_error_message();
        $error_data = array(
            'error_code'    => $fact_check_response->get_error_code(),
            'error_message' => $fact_check_response->get_error_message(),
        );
        $this->log_error( $error_message, $error_data ); // Log detailed error info
        wp_send_json_error( array( 'message' => 'Fact check failed. Please check plugin errors for details.', 'error_detail' => $error_message ) );
        return;
    }

    wp_send_json_success( array( 'fact_check_results' => $fact_check_response['fact_check_results'] ) );
}



/**
 * Finalize JSON output - AJAX handler.
 *
 * @since    0.1.0
 */
public function finalize_json_ajax_handler() {
    check_ajax_referer( 'finalize_json_nonce', 'nonce' );

    $api_key = get_option( 'openai_api_key' ); // Get API key from settings
    $fact_checked_json = isset( $_POST['fact_checked_json'] ) ? wp_kses_post( wp_unslash( $_POST['fact_checked_json'] ) ) : '';
    $process_data_results = isset( $_POST['process_data_results'] ) ? wp_kses_post( wp_unslash( $_POST['process_data_results'] ) ) : '';

    if ( empty( $fact_checked_json ) ) {
        $this->log_error( 'Fact-checked JSON data is missing for finalization.' );
        wp_send_json_error( array( 'message' => 'Fact-checked JSON data is missing for finalization.' ) );
        return;
    }
    if ( empty( $process_data_results ) ) {
        $this->log_error( 'Process Data results are missing for finalization.' );
        wp_send_json_error( array( 'message' => 'Process Data results are missing for finalization.' ) );
        return;
    }
    if ( empty( $api_key ) ) {
        $this->log_error( 'OpenAI API Key is missing.' );
        wp_send_json_error( array( 'message' => 'OpenAI API Key is missing.' ) );
        return;
    }

    // Retrieve additional prompts from settings.
    $finalize_json_prompt = get_option( 'finalize_json_prompt', 'Please finalize the JSON output:' );
    $process_data_prompt   = get_option( 'process_data_prompt', 'The Frankenstein Prompt' );

    // Use JSONFinalize API class to finalize JSON
    $api_response = $this->jsonfinalize_api->finalize_json( 
        $api_key, 
        $finalize_json_prompt, 
        $process_data_prompt, 
        $process_data_results, 
        $fact_checked_json 
    );

    if ( is_wp_error( $api_response ) ) {
        $this->log_error( 'JSON Finalization API Error: ' . $api_response->get_error_message() );
        wp_send_json_error( array( 
            'message'      => 'Failed to finalize JSON. Please check plugin errors for details.', 
            'error_detail' => $api_response->get_error_message() 
        ) );
        return;
    }

    $response_data = array(
        'status'            => 'json-finalize-success',
        'final_json_output' => $api_response['final_json_output'],
    );

    wp_send_json_success( $response_data );
}



	/**
	 * Log error messages with details.
	 *
	 * @since    0.1.0
	 * @param    string    $error_message    The error message to log.
	 * @param    array     $error_details    Array of error details (optional).
	 */
	public function log_error( $error_message, $error_details = array() ) {
		$errors = get_transient( 'auto_data_collection_errors' );
		if ( ! is_array( $errors ) ) {
			$errors = array();
		}
		$error_item = array(
			'message' => $error_message,
			'details' => $error_details, // Store error details
			'time'    => current_time( 'timestamp' ) // Add timestamp to error
		);
		$errors[] = $error_item;
		set_transient( 'auto_data_collection_errors', $errors, 60 * 60 ); // Store for 1 hour
	}

}
