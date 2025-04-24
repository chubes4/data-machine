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
class Data_Machine_API_Finalize {

    /**
     * OpenAI API client instance.
     * @var Data_Machine_API_OpenAI
     */
    private $openai_api;

    /**
     * Constructor. Injects the OpenAI API client.
     * @param Data_Machine_API_OpenAI $openai_api
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
    public function finalize_response( $api_key, $system_prompt, $user_prompt, $process_data_results, $fact_check_results, array $module_job_config, array $input_metadata = [] ) {
        $api_endpoint = 'https://api.openai.com/v1/chat/completions'; // OpenAI Chat Completions API endpoint

        // Construct the user message by chaining all relevant data
        $user_message = $user_prompt; // Start with the module prompt
        if (!empty($process_data_results)) {
            $user_message .= "\n\nInitial Response:\n" . $process_data_results;
        }
        if (!empty($fact_check_results)) {
            $user_message .= "\n\nFact Check Results:\n" . $fact_check_results;
        }

        // --- Add POST_TITLE instruction if needed ---
        $output_type = $module_job_config['output_type'] ?? null;
        $output_config = $module_job_config['output_config'] ?? []; // Already decoded array
        if ($output_type === 'publish_local' || $output_type === 'publish_remote') {
        	$user_message .= "\n\nIMPORTANT: Please ensure the response starts *immediately* with a suitable post title formatted exactly like this (with no preceding text or blank lines):\nPOST_TITLE: [Your Suggested Title Here]\n\nFollow this title line immediately with the rest of your output. Do not print the post title again in the response.";
        }
        // --- End POST_TITLE instruction ---

        // --- Add Markdown Formatting Instruction (Conditional) ---
        if ($output_type === 'publish_local' || $output_type === 'publish_remote') {
        	$user_message .= "\n\nFormat the main content body using standard Markdown syntax (e.g., # H1, ## H2, *italic*, **bold**, - list item, [link text](URL), ```code```). Do not use Markdown for the initial directive lines (POST_TITLE, CATEGORY, TAGS).";
        } // End Markdown Instruction if block.

        // --- Append Source Link Instruction ---
        $source_link_string = '';
        if (!empty($input_metadata['source_url'])) {
            $source_url = esc_url($input_metadata['source_url']);
            $source_name = '';
            if (!empty($input_metadata['subreddit'])) {
                $source_name = 'r/' . esc_html($input_metadata['subreddit']);
            } elseif (!empty($input_metadata['feed_url'])) {
                $parsed_url = wp_parse_url($input_metadata['feed_url']);
                if (!empty($parsed_url['host'])) {
                    $source_name = esc_html($parsed_url['host']);
                } else {
                    $source_name = 'Original Feed';
                }
            } elseif (!empty($input_metadata['original_title'])) {
                 $source_name = esc_html($input_metadata['original_title']);
            } else {
                 $parsed_url = wp_parse_url($source_url);
                 if (!empty($parsed_url['host'])) {
                     $source_name = esc_html($parsed_url['host']);
                 } else {
                    $source_name = 'Original Source';
                 }
            }
            $source_link_string = sprintf(
                'Source: <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                $source_url,
                $source_name
            );
        }
        // --- End Source Link Instruction ---

        // --- Direct API Call using wp_remote_post for OpenAI ---
        $model = Data_Machine_Constants::AI_MODEL_FINALIZE;
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

        error_log("DM Finalize Debug: Request Args: " . print_r($args, true));
        $response = wp_remote_post($api_endpoint, $args);
        // --- End Direct API Call ---

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        // Log raw response body only if not 200
        if ( 200 !== $response_code ) {
            error_log("DM Finalize Debug: Raw Response Body (Non-200): " . $response_body);
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

        // Log final result before returning
        error_log("DM Finalize Debug: Final final_output: " . $final_output);

        return array(
            'status'            => 'success',
            'final_output' => $final_output,
        );
    }
}
