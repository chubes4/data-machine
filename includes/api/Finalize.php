<?php

/**
 * Handles interaction with the OpenAI API for JSON finalization.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/api
 */

namespace DataMachine\Api;

use DataMachine\Constants;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Finalize {

    /**
     * OpenAI API client instance.
     * @var OpenAi
     */
    private $openai_api;

    /**
     * Constructor. Injects the OpenAI API client.
     * @param OpenAi $openai_api
     */
    public function __construct($openai_api) {
        $this->openai_api = $openai_api;
    }

    /**
     * Finalize JSON data using  API.
     *
     * @since    0.1.0
     * @param    string    $api_key                  OpenAI API Key.
     * @param    string    $finalize_response_prompt Finalize JSON Prompt from settings.
     * @param    string    $process_data_prompt      Process Data Prompt from settings.
     * @param    string    $process_data_results     The initial JSON output from processing the PDF.
     * @param    string    $fact_check_results       The fact-check results.
     * @param    array     $module_job_config        Simplified module configuration array for the job.
     * @param    array     $input_metadata           Metadata from the original input data packet (optional).
     * @return   array|WP_Error                       API response data or WP_Error on failure.
     */
    public function finalize_response( $api_key, $system_prompt, $user_message, $process_data_results, $fact_check_results, array $module_job_config, array $input_metadata = [] ) {
        $api_endpoint = 'https://api.openai.com/v1/chat/completions'; // OpenAI Chat Completions API endpoint

        // NOTE: All prompt building logic has been moved to the centralized PromptBuilder class.
        // The user_message parameter now contains the complete, ready-to-use prompt.

        // --- Direct API Call using wp_remote_post for OpenAI ---
        $model = Constants::AI_MODEL_FINALIZE;
        $messages = [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_message],
        ];
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'    => $model,
                'messages' => $messages,
            ]),
            'method'  => 'POST',
            'timeout' => 120,
        ];

        $response = wp_remote_post($api_endpoint, $args);
        // --- End Direct API Call ---

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        // Log raw response body only if not 200
        if ( 200 !== $response_code ) {
            return new WP_Error( 'openai_api_error', 'OpenAI API error: ' . $response_code . ' - ' . $response_body );
        }

        $decoded_response = json_decode( $response_body, true );

        if ( ! is_array( $decoded_response ) || ! isset( $decoded_response['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 'openai_api_response_error', 'Invalid OpenAI API response: ' . $response_body );
        }

        $final_output = $decoded_response['choices'][0]['message']['content'];

        // Remove potential backticks and "json" marker from the API response
        $final_output = preg_replace('/^```json\s*/i', '', $final_output);
        $final_output = preg_replace('/```$/i', '', $final_output);
        $final_output = trim($final_output);

        return array(
            'status'            => 'success',
            'final_output' => $final_output,
        );
    }
}
