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
     * Fact-check JSON data using gpt-4o-search-preview API.
     *
     * @since    0.1.0
     * @param    string    $api_key          OpenAI API Key.
     * @param    string    $content_to_check Content data to fact-check.
     * @param    string    $fact_check_prompt Fact Check Prompt from settings.
     * @return   array|WP_Error                API response data or WP_Error on failure.
     */
    public function fact_check_response( $api_key, $system_prompt, $user_prompt, $content_to_check ) {
        // Add robust, universal fact-checking directive
        $fact_check_directive = <<<PROMPT
FACT-CHECKING DIRECTIVE:
- When fact-checking, treat the user-provided content and post content as the primary source of truth unless you find a direct, credible, and more recent source that clearly disproves it.
- Do not hedge, speculate, or state that information is "not officially announced" or "unconfirmed" unless you find a direct, credible source explicitly stating so.
- If the user-provided content is a news announcement, event, update, or other factual statement, and you do not find a credible contradiction, you must treat it as accurate and confirmed.
- If in doubt, defer to the user-provided content and do not introduce uncertainty.
- Always cite any source that directly contradicts or disproves the user-provided content.
PROMPT;
        $system_prompt = $fact_check_directive . "\n\n" . $system_prompt;

        $api_endpoint = 'https://api.openai.com/v1/responses'; // Use Responses API endpoint

        // Construct the user message by combining the module prompt and the content to check
        $user_message = $user_prompt;
        if (!empty($content_to_check)) {
            $user_message .= "\n\nContent to Fact Check:\n" . $content_to_check;
        }

        // --- Prepare API Request using 'tools' parameter ---
        $model = Data_Machine_Constants::AI_MODEL_FACT_CHECK;

        // Use the fact check prompt from settings to instruct the model.
        // Include both system and user messages
        $messages = [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_message],
        ];

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model'              => $model,
                // Use 'input' instead of 'messages' for Responses API
                'input'              => $messages, // Pass the messages array as input
                // Use 'tools' parameter as defined for Responses API web search
                'tools' => [
                    [
                        'type' => 'web_search_preview',
                        'search_context_size' => 'low' // Set context size to low to save $
                    ]
                ],
                // Optionally force tool usage if needed
                'tool_choice' => ['type' => 'web_search_preview']
            ) ),
            'method'  => 'POST',
            'timeout' => 60,
        );

        // Debug: Log request args
        error_log("DM FactCheck Debug: Request Args: " . print_r($args, true));
        $response = wp_remote_post( $api_endpoint, $args );

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        // Log raw response body only if not 200
        if ( 200 !== $response_code ) {
            error_log("DM FactCheck Debug: Raw Response Body (Non-200): " . $response_body);
            return new WP_Error( 'openai_api_error', $model . ' API error: ' . $response_code . ' - ' . $response_body );
        }

        $decoded_response = json_decode( $response_body, true );

        // Adjust response parsing for Responses API format
        // Look for 'output_text' within the response structure
        $fact_check_results = null;
        // Check if the response structure is valid and contains the 'output' array
        if (is_array($decoded_response) && isset($decoded_response['output']) && is_array($decoded_response['output'])) {
            // Iterate through the 'output' array to find the assistant's message
            foreach ($decoded_response['output'] as $output_item) {
                // Check if this item is the assistant's message
                if (isset($output_item['type']) && $output_item['type'] === 'message' && isset($output_item['role']) && $output_item['role'] === 'assistant') {
                    // Check if the content array and the first content item exist
                    if (isset($output_item['content'][0]) && is_array($output_item['content'][0])) {
                        // Check if the first content item is of type 'output_text' and has 'text'
                        if (isset($output_item['content'][0]['type']) && $output_item['content'][0]['type'] === 'output_text' && isset($output_item['content'][0]['text'])) {
                            $fact_check_results = $output_item['content'][0]['text'];
                            break; // Found the main text output, exit the loop
                        }
                    }
                }
            }
        }

        if ($fact_check_results === null) {
             return new WP_Error( 'openai_api_response_error', 'Invalid or unexpected ' . $model . ' API response format: ' . $response_body );
        }

        // Log final result before returning
        error_log("DM FactCheck Debug: Final fact_check_results: " . $fact_check_results);

        return array(
            'status'             => 'success',
            'fact_check_results' => trim($fact_check_results), // Trim whitespace
        );
    }
}
