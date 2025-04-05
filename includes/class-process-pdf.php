<?php

/**
 * Handles PDF processing workflow using OpenAI API.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Auto_Data_Collection
 * @subpackage Auto_Data_Collection/includes
 */
class Auto_Data_Collection_Process_PDF {

    /**
     * The main plugin instance.
     *
     * @since    0.1.0
     * @var      Auto_Data_Collection    $plugin    The main plugin instance.
     */
    private $plugin;

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.1.0
     * @param    Auto_Data_Collection    $plugin    The main plugin instance.
     */
    public function __construct( $plugin ) {
        $this->plugin = $plugin;
    }

/**
 * Process PDF using OpenAI API.
 *
 * This method:
 * 1. Uploads the PDF via the Files API (as user_data).
 * 2. Sends a prompt along with the file ID to the Responses API.
 * 3. Returns the generated output from the model.
 *
 * @since    0.1.0
 * @param    string    $api_key              OpenAI API Key.
 * @param    string    $process_pdf_prompt   Prompt instructing the model how to process the PDF.
 * @param    array     $pdf_file             Uploaded PDF file array.
 * @return   array                           API response data.
 */
public function process_pdf( $api_key, $process_pdf_prompt, $pdf_file ) {
    // Initialize the API helper.
    $api = new Auto_Data_Collection_API_OpenAI( $this->plugin );

    // 1. Upload PDF to OpenAI Files API.
    $file_response = $api->upload_pdf_to_openai( $api_key, $pdf_file );
    if ( is_wp_error( $file_response ) ) {
        $this->plugin->log_error( 'PDF upload failed: ' . $file_response->get_error_message() );
        return $file_response;
    }
    $file_id = $file_response['id']; // Use the file ID from the successful upload.

    // 2. Create a response using the uploaded file and your processing prompt.
    $response = $api->create_response_with_file( $api_key, $file_id, $process_pdf_prompt );
    if ( is_wp_error( $response ) ) {
        $this->plugin->log_error( 'Response creation failed: ' . $response->get_error_message() );
        return $response;
    }
    
    // Extract the output text.
    $json_output = isset($response['output_text']) ? trim($response['output_text']) : null;
    
    return array(
        'status'      => 'success',
        'json_output' => $json_output,
    );
}


}
