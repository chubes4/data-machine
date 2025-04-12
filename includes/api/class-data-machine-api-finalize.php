<?php

/**
 * Handles interaction with the o3-mini API for JSON finalization.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/api
 */
class Data_Machine_API_Finalize {

    /**
     * Finalize JSON data using o3-mini API.
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
    public function finalize_response( $api_key, $finalize_response_prompt, $process_data_prompt, $process_data_results, $fact_check_results, array $module_job_config, array $input_metadata = [] ) {
        $api_endpoint = 'https://api.openai.com/v1/chat/completions'; // OpenAI Chat Completions API endpoint
        $model = 'o3-mini';

        // Combine all values into a more structured plain text message for AI.
      
        // --- Add POST_TITLE instruction if needed ---
        $output_type = $module_job_config['output_type'] ?? null;
        $output_config = $module_job_config['output_config'] ?? []; // Already decoded array
      
        if ($output_type === 'publish_local' || $output_type === 'publish_remote') {
        	$finalize_response_prompt .= "\n\nIMPORTANT: Please ensure the response starts *immediately* with a suitable post title formatted exactly like this (with no preceding text or blank lines):\nPOST_TITLE: [Your Suggested Title Here]\n\nFollow this title line immediately with the rest of your output. Do not print the post title again in the response.";
        }
        // --- End POST_TITLE instruction ---
      
      
        // --- Add Markdown Formatting Instruction (Conditional) ---
        // Only add this if the output type is publish (local or remote)
        if ($output_type === 'publish_local' || $output_type === 'publish_remote') {
        	$finalize_response_prompt .= "\n\nFormat the main content body using standard Markdown syntax (e.g., # H1, ## H2, *italic*, **bold**, - list item, [link text](URL), ```code```). Do not use Markdown for the initial directive lines (POST_TITLE, REMOTE_CATEGORY, REMOTE_TAGS).";
        } // End Markdown Instruction if block.

        // --- Dynamically Add Taxonomy Instructions ---
        $taxonomy_context = '';
        $taxonomy_instructions = [];
        $local_taxonomy_instructions = []; // Separate for local
        $remote_taxonomy_instructions = []; // Separate for remote

        // --- Remote Publishing Logic ---
        if ($output_type === 'publish_remote') {
            $remote_info = $output_config['remote_site_info'] ?? [];
            $remote_cats = $remote_info['taxonomies']['category']['terms'] ?? [];
            $remote_tags = $remote_info['taxonomies']['post_tag']['terms'] ?? [];
            // Use 0 for "Instruct Model", -1 for "Determine from Content"
            $cat_mode = $output_config['selected_remote_category_mode'] ?? -1;
            $tag_mode = $output_config['selected_remote_tag_mode'] ?? -1;

            // Remote Category Handling
            if ($cat_mode == -1 && !empty($remote_cats)) { // Determine from Content
                $taxonomy_context .= "\n\nAvailable Remote Categories:\n";
                foreach ($remote_cats as $cat) { $taxonomy_context .= "- " . $cat['name'] . "\n"; }
                $remote_taxonomy_instructions[] = "Determine the single most appropriate category NAME from the 'Available Remote Categories' list based on the content. Prepend your response (before the main content) with 'REMOTE_CATEGORY: [Chosen Category Name]'. If no category is appropriate, use 'REMOTE_CATEGORY: '.";
            } elseif ($cat_mode == 0) { // Instruct Model
                 $remote_taxonomy_instructions[] = "Follow the user's prompt instructions regarding the remote category. Prepend your response (before the main content) with 'REMOTE_CATEGORY: [Chosen Category Name based on user prompt instructions]'. If the prompt does not specify or no category is appropriate, use 'REMOTE_CATEGORY: '.";
            } // Other modes or empty remote_cats are ignored for category instruction

            // Remote Tag Handling
            if ($tag_mode == -1 && !empty($remote_tags)) { // Determine from Content
                $taxonomy_context .= "\n\nAvailable Remote Tags:\n";
                foreach ($remote_tags as $tag) { $taxonomy_context .= "- " . $tag['name'] . "\n"; }
                 $remote_taxonomy_instructions[] = "Determine one or more relevant tag NAMES from the 'Available Remote Tags' list based on the content. Prepend your response (after the REMOTE_CATEGORY line, if present) with 'REMOTE_TAGS: [Tag Name 1], [Tag Name 2], ...' (comma-separated). If no tags are appropriate, use 'REMOTE_TAGS: '.";
            } elseif ($tag_mode == 0) { // Instruct Model
                 $remote_taxonomy_instructions[] = "Follow the user's prompt instructions regarding remote tags. Prepend your response (after the REMOTE_CATEGORY line, if present) with 'REMOTE_TAGS: [Tag Name 1 based on user prompt], [Tag Name 2 based on user prompt], ...' (comma-separated). If the prompt does not specify tags or none are appropriate, use 'REMOTE_TAGS: '.";
            } // Other modes or empty remote_tags are ignored for tag instruction
        }
        // --- End Remote Publishing Logic ---

        // --- Local Publishing Logic ---
        elseif ($output_type === 'publish_local') {
             // Use 0 for "Instruct Model", -1 for "Determine from Content"
             // Ensure these keys exist in your $module_job_config['output_config'] for local publishing
            $local_cat_mode = $output_config['selected_local_category_mode'] ?? -1;
            $local_tag_mode = $output_config['selected_local_tag_mode'] ?? -1;

            // Local Category Handling
            if ($local_cat_mode == -1) { // Determine from Content
                $local_cats = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
                if (!is_wp_error($local_cats) && !empty($local_cats)) {
                    $taxonomy_context .= "\n\nAvailable Local Categories:\n";
                    foreach ($local_cats as $cat) { $taxonomy_context .= "- " . $cat->name . "\n"; }
                    $local_taxonomy_instructions[] = "Determine the single most appropriate category NAME from the 'Available Local Categories' list based on the content. Prepend your response (before the main content) with 'LOCAL_CATEGORY: [Chosen Category Name]'. If no category is appropriate, use 'LOCAL_CATEGORY: '.";
                } else {
                    // Handle error or no terms found, maybe add a note?
                     $local_taxonomy_instructions[] = "Could not retrieve local categories. Use 'LOCAL_CATEGORY: '.";
                }
            } elseif ($local_cat_mode == 0) { // Instruct Model
                 $local_taxonomy_instructions[] = "Follow the user's prompt instructions regarding the local category. Prepend your response (before the main content) with 'LOCAL_CATEGORY: [Chosen Category Name based on user prompt instructions]'. If the prompt does not specify or no category is appropriate, use 'LOCAL_CATEGORY: '.";
            } // Other modes are ignored

            // Local Tag Handling
            if ($local_tag_mode == -1) { // Determine from Content
                $local_tags = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => false]);
                 if (!is_wp_error($local_tags) && !empty($local_tags)) {
                     $taxonomy_context .= "\n\nAvailable Local Tags:\n";
                     foreach ($local_tags as $tag) { $taxonomy_context .= "- " . $tag->name . "\n"; }
                      $local_taxonomy_instructions[] = "Determine one or more relevant tag NAMES from the 'Available Local Tags' list based on the content. Prepend your response (after the LOCAL_CATEGORY line, if present) with 'LOCAL_TAGS: [Tag Name 1], [Tag Name 2], ...' (comma-separated). If no tags are appropriate, use 'LOCAL_TAGS: '.";
                 } else {
                    // Handle error or no terms found
                    $local_taxonomy_instructions[] = "Could not retrieve local tags. Use 'LOCAL_TAGS: '.";
                 }
            } elseif ($local_tag_mode == 0) { // Instruct Model
                 $local_taxonomy_instructions[] = "Follow the user's prompt instructions regarding local tags. Prepend your response (after the LOCAL_CATEGORY line, if present) with 'LOCAL_TAGS: [Tag Name 1 based on user prompt], [Tag Name 2 based on user prompt], ...' (comma-separated). If the prompt does not specify tags or none are appropriate, use 'LOCAL_TAGS: '.";
            } // Other modes are ignored
        }
        // --- End Local Publishing Logic ---

        // Combine instructions based on output type
        if ($output_type === 'publish_remote') {
             $taxonomy_instructions = $remote_taxonomy_instructions;
             $instruction_title = "--- Remote Taxonomy Selection Instructions ---";
        } elseif ($output_type === 'publish_local') {
            $taxonomy_instructions = $local_taxonomy_instructions;
             $instruction_title = "--- Local Taxonomy Selection Instructions ---";
        } else {
            $taxonomy_instructions = []; // No instructions for other types
        }

        // Append instructions and context to the main finalize prompt
        if (!empty($taxonomy_instructions)) {
             $finalize_response_prompt .= "\n\n" . $instruction_title . "\n" . implode("\n", $taxonomy_instructions) . $taxonomy_context;
        }
        // --- End Taxonomy Instructions ---
      
        // --- Append Source Link Instruction --- 
        $source_link_string = '';
        if (!empty($input_metadata['source_url'])) {
            $source_url = esc_url($input_metadata['source_url']);
            $source_name = '';

            // Try to determine a good source name
            if (!empty($input_metadata['subreddit'])) {
                $source_name = 'r/' . esc_html($input_metadata['subreddit']);
            } elseif (!empty($input_metadata['feed_url'])) {
                // Try to get domain from feed URL
                $parsed_url = wp_parse_url($input_metadata['feed_url']);
                if (!empty($parsed_url['host'])) {
                    $source_name = esc_html($parsed_url['host']);
                } else {
                    $source_name = 'Original Feed'; // Fallback
                }
            } elseif (!empty($input_metadata['original_title'])) {
                 $source_name = esc_html($input_metadata['original_title']); // Use original title if available
            } else {
                 // Fallback to domain of the source_url itself
                 $parsed_url = wp_parse_url($source_url);
                 if (!empty($parsed_url['host'])) {
                     $source_name = esc_html($parsed_url['host']);
                 } else {
                    $source_name = 'Original Source'; // Generic fallback
                 }
            }

            // Construct the HTML link
            $source_link_string = sprintf(
                'Source: <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                $source_url,
                $source_name
            );

            // Add instruction to the prompt
            $finalize_response_prompt .= sprintf(
                "\n\nFinally, append the following source link exactly as provided at the very end of your response (after all other content):\n%s",
                $source_link_string
            );

        }
        // --- End Source Link Instruction ---

        // Combine all values into a structured message for the AI
        $combined_message = "Initial Request Prompt:\n" . $process_data_prompt . "\n\n" .
        					"Initial Processing Results:\n" . $process_data_results . "\n\n" .
        					"Fact Check Results:\n" . $fact_check_results . "\n\n" .
        					"Final Assignment (including any taxonomy instructions):\n" . $finalize_response_prompt;

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
