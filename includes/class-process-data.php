<?php

/**
 * Handles Data processing workflow using OpenAI API.
 *
 * This method now supports processing various file types (PDFs, images, etc.)
 * by dynamically adjusting the request to OpenAI based on the detected file type.
 *
 * It:
 * 1. Processes the uploaded file (PDF, image, etc.) with the appropriate method.
 * 2. Sends a prompt along with the file (or file data) to the Responses API.
 * 3. Returns the generated output from the model.
 *
 * @since    0.1.0
 * @param    string    $api_key             OpenAI API Key.
 * @param    string    $process_data_prompt Prompt instructing the model how to process the file.
 * @param    array     $data_file           Uploaded file array.
 * @return   array                           API response data.
 */
class Auto_Data_Collection_process_data {

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
    public function __construct($plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Process Data using OpenAI API.
     *
     * This method now dynamically handles different file types.
     * It uses the unified create_response_with_file() method which:
     * - For PDFs: uploads the file to OpenAI Files API and processes it.
     * - For images: encodes them to Base64 and sends directly.
     *
     * @since    0.1.0
     * @param    string    $api_key             OpenAI API Key.
     * @param    string    $process_data_prompt Prompt instructing the model how to process the file.
     * @param    array     $data_file           Uploaded file array.
     * @return   array                           API response data.
     */
    public function process_data($api_key, $process_data_prompt, $data_file) {
        // Initialize API helper and default response
        $api = new Auto_Data_Collection_API_OpenAI($this->plugin);
        $default_response = [
            'status' => 'error',
            'message' => '',
            'json_output' => null
        ];

        try {
            $response = $api->create_response_with_file($api_key, $data_file, $process_data_prompt);
            
            // Handle WP_Error responses
            if (is_wp_error($response)) {
                $error_message = 'OpenAI API Error: ' . $response->get_error_message();
                $default_response['message'] = $error_message;
                $this->plugin->log_error($error_message);
                return $default_response;
            }

            // Handle API error responses
            if (isset($response['is_error']) && $response['is_error']) {
                $error_message = $response['error_message'] ?? 'Unknown API error occurred';
                $default_response['message'] = $error_message;
                $this->plugin->log_error($error_message);
                return $default_response;
            }

            // Handle successful responses
            if (isset($response['output_text'])) {
                return [
                    'status' => 'success',
                    'json_output' => trim($response['output_text'])
                ];
            }

            // Fallback error if no output text
            $error_message = 'API returned no output text';
            $default_response['message'] = $error_message;
            $this->plugin->log_error($error_message);
            return $default_response;

        } catch (Exception $e) {
            $error_message = 'Processing error: ' . $e->getMessage();
            $default_response['message'] = $error_message;
            $this->plugin->log_error($error_message);
            return $default_response;
        }
    }
}
