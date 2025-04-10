<?php

/**
 * Handles interaction with the gpt-4o-search-preview API for fact-checking.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/api
 */
class Data_Machine_API_FactCheck {

    /**
     * Fact-check JSON data using gpt-4o-search-preview API.
     *
     * @since    0.1.0
     * @param    string    $api_key          OpenAI API Key.
     * @param    string    $content_to_check Content data to fact-check.
     * @param    string    $fact_check_prompt Fact Check Prompt from settings.
     * @return   array|WP_Error                API response data or WP_Error on failure.
     */
    public function fact_check_response( $api_key, $content_to_check, $fact_check_prompt ) { // Renamed parameter
        $api_endpoint = 'https://api.openai.com/v1/chat/completions'; // Chat Completions endpoint
        $model = 'gpt-4o-search-preview';

        // Use the fact check prompt from settings to instruct the model.
        $messages = array(
            array(
                'role'    => 'user',
                'content' => $fact_check_prompt . "\n\n" . $content_to_check, // Use renamed parameter
            ),
        );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model'              => $model,
                'messages'           => $messages,
                'web_search_options' => array( 'search_context_size' => 'low' ),
            ) ),
            'method'  => 'POST',
            'timeout' => 60,
        );

        $response = wp_remote_post( $api_endpoint, $args );

        if ( is_wp_error( $response ) ) {
            return $response; // Return WP_Error
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( 200 !== $response_code ) {
            return new WP_Error( 'openai_api_error', 'gpt-4o-search-preview API error: ' . $response_code . ' - ' . $response_body );
        }

        $decoded_response = json_decode( $response_body, true );

        if ( ! is_array( $decoded_response ) || ! isset( $decoded_response['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 'openai_api_response_error', 'Invalid gpt-4o-search-preview API response: ' . $response_body );
        }

        $fact_check_results = $decoded_response['choices'][0]['message']['content'];

        return array(
            'status'             => 'success',
            'fact_check_results' => $fact_check_results,
        );
    }
}
