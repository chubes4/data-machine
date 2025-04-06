<?php

/**
 * Handles interaction with the o3-mini API for JSON finalization.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Auto_Data_Collection
 * @subpackage Auto_Data_Collection/includes/api
 */
class Auto_Data_Collection_API_JSONFinalize {

    /**
     * Finalize JSON data using o3-mini API.
     *
     * @since    0.1.0
     * @param    string    $api_key                OpenAI API Key.
     * @param    string    $finalize_json_prompt   Finalize JSON Prompt from settings.
     * @param    string    $process_data_prompt     Process Data Prompt from settings.
     * @param    string    $process_data_results    The initial JSON output from processing the PDF.
     * @param    string    $fact_check_results     The fact-check results.
     * @return   array|WP_Error                     API response data or WP_Error on failure.
     */
    public function finalize_json( $api_key, $finalize_json_prompt, $process_data_prompt, $process_data_results, $fact_check_results ) {
        $api_endpoint = 'https://api.openai.com/v1/chat/completions'; // OpenAI Chat Completions API endpoint
        $model = 'o3-mini';

        // Combine all values into a more structured plain text message for AI.
        $combined_message = "here is the initial request:\n\n" .
                            $process_data_prompt . "\n\n" .
                            "and the result:\n\n" .
                            $process_data_results . "\n\n" .
                            "and the fact check:\n\n" .
                            $fact_check_results . "\n\n" .
                            "and your assignment is:\n\n" .
                            $finalize_json_prompt;

        $messages = array(
            array(
                'role'    => 'user',
                'content' => $combined_message,
            ),
        );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model'    => $model,
                'messages' => $messages,
            ) ),
            'method'  => 'POST',
            'timeout' => 120,
        );

        $response = wp_remote_post( $api_endpoint, $args );

        if ( is_wp_error( $response ) ) {
            return $response; // Return WP_Error object.
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( 200 !== $response_code ) {
            return new WP_Error( 'openai_api_error', 'o3-mini API error: ' . $response_code . ' - ' . $response_body );
        }

        $decoded_response = json_decode( $response_body, true );

        if ( ! is_array( $decoded_response ) || ! isset( $decoded_response['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 'openai_api_response_error', 'Invalid o3-mini API response: ' . $response_body );
        }

        $final_json_output = $decoded_response['choices'][0]['message']['content'];

        // Remove potential backticks and "json" marker from the API response
        $final_json_output = preg_replace('/^```json\s*/i', '', $final_json_output);
        $final_json_output = preg_replace('/```$/i', '', $final_json_output);
        $final_json_output = trim($final_json_output);

        return array(
            'status'            => 'success',
            'final_json_output' => $final_json_output,
        );
    }
}
