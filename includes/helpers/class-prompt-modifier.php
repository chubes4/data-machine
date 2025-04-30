<?php
/**
 * Helper for modifying AI prompts based on module configuration and context.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/helpers
 * @since      NEXT_VERSION
 */

class Data_Machine_Prompt_Modifier
{
    /**
     * Modify the finalize prompt based on output type and config.
     * Injects available categories/tags for "Let Model Decide" scenarios.
     *
     * @param string $original_prompt The original finalize prompt.
     * @param array $module_job_config The job config array.
     * @param array $input_data_packet Optional input data packet (e.g., for image URL).
     * @return string The modified prompt.
     */
    public static function modify_finalize_prompt(string $original_prompt, array $module_job_config, array $input_data_packet = []): string
    {
        $output_type = $module_job_config['output_type'] ?? '';
        $output_config = $module_job_config['output_config'] ?? [];

        $directive_block = "\n--- RESPONSE FORMATTING AND INSTRUCTIONS ---";

        // --- General Content Instructions (Apply to all types requiring structured output) ---
        $directive_block .= "\n1.  **Strict Adherence:** Follow all instructions below precisely.";

        // --- Specific Instructions based on Output Type ---
        if ($output_type === 'twitter') {
            $twitter_config = $output_config['twitter'] ?? [];
            $char_limit = $twitter_config['twitter_char_limit'] ?? 280;
            $link_placeholder_length = 25; // Generous estimate for t.co link + space
            $text_limit = $char_limit - $link_placeholder_length;
            $directive_block .= "\n2.  **Twitter Length Limit:** Keep the MAIN content text under {$text_limit} characters. The system will add a source link later.";
            $directive_block .= "\n3.  **Begin Content:** Provide the tweet content immediately after these instructions.";
        } elseif ($output_type === 'bluesky') {
            $char_limit = 300;
            $link_placeholder_length = 30; // Estimate for newline, newline, avg URL
            $text_limit = $char_limit - $link_placeholder_length;
            $directive_block .= "\n2.  **Bluesky Length Limit:** Keep the MAIN content text under {$text_limit} characters. The system will add a source link later.";
            $directive_block .= "\n3.  **Begin Content:** Provide the post content immediately after these instructions.";
        }
        // Post/Remote Publish: Title/Taxonomy/Markdown Directives
        elseif ($output_type === 'publish_remote' || $output_type === 'publish_local') {
            $directive_block .= "\n2.  **Content Formatting:** Use standard Markdown for the main content body.";
            $directive_block .= "\n3.  **Title Exclusion:** If a POST_TITLE directive is required below, do NOT repeat the title within the main content body itself (NO H1).";
            $category_mode = null;
            $tag_mode = null;
            $site_info = [];
            $publish_config = [];
            $custom_tax_configs = []; // Format: ['slug' => 'mode']

            // --- Extract Configs ---
            if ($output_type === 'publish_remote') {
                $publish_config = $output_config['publish_remote'] ?? [];
                $site_info = $publish_config['remote_site_info'] ?? $output_config['remote_site_info'] ?? [];
                $category_mode = $publish_config['selected_remote_category_id'] ?? 'model_decides';
                $tag_mode = $publish_config['selected_remote_tag_id'] ?? 'model_decides';
            } elseif ($output_type === 'publish_local') {
                $publish_config = $output_config['publish_local'] ?? [];
                $category_mode = $publish_config['selected_local_category_id'] ?? 'model_decides';
                $tag_mode = $publish_config['selected_local_tag_id'] ?? 'model_decides';
            }

            // --- CORRECTED: Extract custom taxonomy configs from selected_custom_taxonomy_values ---
            $custom_tax_configs = []; // Reset just in case
            if (isset($publish_config['selected_custom_taxonomy_values']) && is_array($publish_config['selected_custom_taxonomy_values'])) {
                foreach ($publish_config['selected_custom_taxonomy_values'] as $tax_slug => $value) {
                    // Sanitize slug just in case
                    $tax_slug = sanitize_key($tax_slug); 
                    if (empty($tax_slug)) continue;

                    // Check if the value is instruct_model or a numeric ID
                    if (is_string($value) && ($value === 'instruct_model')) {
                        $custom_tax_configs[$tax_slug] = $value; // Store 'instruct_model'
                    } elseif (is_numeric($value) && $value > 0) {
                        $custom_tax_configs[$tax_slug] = intval($value); // Store numeric ID
                    } // Ignore empty strings or 0
                }
            }
            // --- End Corrected Extraction ---

            // Adjusting numbering for clarity
            $directive_block .= "\n4.  **Format:** Your response MUST start *immediately* with the following directives, each on a new line. Do NOT include any other text before these lines:";
            $directive_block .= "\n    POST_TITLE: [Your calculated post title]"; // Always needed

            $taxonomy_instructions = []; // Collect instructions here
            $directive_counter = 5; // Start further instructions from 5

            // Category
            if (is_string($category_mode) && ($category_mode === 'instruct_model')) {
                $directive_block .= "\n    CATEGORY: [Your chosen category name]";
                    $taxonomy_instructions[] = "- CATEGORY: Determine the category based on the user instructions in the prompt below.";
            }

            // Tags
            if (is_string($tag_mode) && ($tag_mode === 'instruct_model')) {
                $directive_block .= "\n    TAGS: [Your chosen comma-separated tags]";
                    $taxonomy_instructions[] = "- TAGS: Determine the most appropriate tag(s) based ONLY on the user instructions in the prompt below. Output comma-separated.";
            }

            // Custom Taxonomies
            foreach ($custom_tax_configs as $tax_slug => $tax_mode) {
                if (is_string($tax_mode) && ($tax_mode === 'instruct_model')) {
                    $directive_block .= "\n    TAXONOMY[{$tax_slug}]: [Your chosen comma-separated '{$tax_slug}' terms]";
                    $tax_label = ucfirst(str_replace('_', ' ', $tax_slug));
                        $taxonomy_instructions[] = "- TAXONOMY[{$tax_slug}]: Determine the most appropriate {$tax_label} term(s) based ONLY on the user instructions in the prompt below. Output comma-separated.";
                }
            }

            // Adding the Taxonomy Selection block
            $directive_block .= "\n\n{$directive_counter}.  **Taxonomy Selection Instructions (if applicable):** Follow these instructions VERY carefully.";
            if (!empty($taxonomy_instructions)) {
                 $directive_block .= "\n" . implode("\n", $taxonomy_instructions);
            } else {
                $directive_block .= " N/A";
            }
            $directive_counter++;

            // Adding the Strict Adherence block
            $directive_block .= "\n\n{$directive_counter}.  **Taxonomy Precision:** If taxonomy instructions above mention 'based ONLY on the user instructions', you MUST follow the user's prompt below precisely regarding those taxonomies. Do not add terms not requested or implied by the user prompt.";
             $directive_counter++;

            // Adding the Begin Content block
            $directive_block .= "\n\n{$directive_counter}.  **Begin Content:** Immediately following the directives above, provide the main post content using standard Markdown (No H1 title).";

        } else {
             // Default for other output types (e.g., raw text)
             $directive_block .= "\n2.  **Begin Content:** Provide the content immediately after these instructions.";
        }

        $directive_block .= "\n--- END RESPONSE FORMATTING AND INSTRUCTIONS ---";

        // Prepend the instructions to the original prompt
        $final_prompt = $directive_block . "\n\n" . $original_prompt;

        return $final_prompt;
    }
}