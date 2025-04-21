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

        $directive_block = ""; // Initialize directive block

        // --- Build the Combined Directive and Instruction Block ---
        $directive_block = "
--- RESPONSE FORMATTING AND INSTRUCTIONS ---";

        // --- General Content Instructions (Apply to all types requiring structured output) ---
        $directive_block .= "
1.  **Strict Adherence:** Follow all instructions below precisely.";
        $directive_block .= "
2.  **Content Formatting:** Use standard Markdown for the main content body.";
        $directive_block .= "
3.  **Title Exclusion:** If a POST_TITLE directive is required below, do NOT repeat the title within the main content body itself (NO H1).";

        // --- Specific Instructions based on Output Type ---

        // Twitter/Bluesky: Length Constraints
        if ($output_type === 'twitter') {
            $twitter_config = $output_config['twitter'] ?? [];
            $char_limit = $twitter_config['twitter_char_limit'] ?? 280;
            // Assume a placeholder length for the link for instruction purposes
            $link_placeholder_length = 25; // Generous estimate for t.co link + space
            $text_limit = $char_limit - $link_placeholder_length;
            $directive_block .= "
4.  **Twitter Length Limit:** Keep the MAIN content text under {$text_limit} characters. The system will add a source link later.";
            $directive_block .= "
5.  **Begin Content:** Provide the tweet content immediately after these instructions.";
        } elseif ($output_type === 'bluesky') {
            // Bluesky limit is 300 graphemes. Use characters as approximation.
            $char_limit = 300;
             // Assume a placeholder length for the link for instruction purposes
             // Link is often appended with 
            $link_placeholder_length = 30; // Estimate for newline, newline, avg URL
            $text_limit = $char_limit - $link_placeholder_length;
             $directive_block .= "
4.  **Bluesky Length Limit:** Keep the MAIN content text under {$text_limit} characters. The system will add a source link later.";
             $directive_block .= "
5.  **Begin Content:** Provide the post content immediately after these instructions.";
        }
        // Post/Remote Publish: Title/Taxonomy Directives
        elseif ($output_type === 'publish_remote' || $output_type === 'publish_local') {
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
                // Local site info (terms) will be fetched directly if needed
            }

            // Extract custom taxonomy configs (rest_...)
            foreach ($publish_config as $key => $value) {
                if (preg_match('/^rest_([a-zA-Z0-9_]+)$/', $key, $matches)) {
                    $tax_slug = $matches[1];
                    // Ensure value is a string mode or int ID
                    if (is_string($value) && ($value === 'model_decides' || $value === 'instruct_model')) {
                         $custom_tax_configs[$tax_slug] = $value;
                    } elseif (is_numeric($value)) {
                        $custom_tax_configs[$tax_slug] = intval($value);
                    }
                }
            }

            // Adjusting numbering for clarity
            $directive_block .= "
4.  **Format:** Your response MUST start *immediately* with the following directives, each on a new line. Do NOT include any other text before these lines:";
            $directive_block .= "
    POST_TITLE: [Your calculated post title]"; // Always needed

            $taxonomy_instructions = []; // Collect instructions here
            $directive_counter = 5; // Start further instructions from 5

            // Category
            if (is_string($category_mode) && ($category_mode === 'model_decides' || $category_mode === 'instruct_model')) {
                $directive_block .= "
    CATEGORY: [Your chosen category name]";
                if ($category_mode === 'model_decides') {
                    $terms_to_list = []; $term_names = [];
                    if ($output_type === 'publish_local') {$fetched_terms = get_terms(['taxonomy' => 'category', 'hide_empty' => false]); if (!is_wp_error($fetched_terms)) $terms_to_list = $fetched_terms; }
                    else {$terms_to_list = $site_info['taxonomies']['category']['terms'] ?? [];}
                    foreach ($terms_to_list as $term) { if (is_object($term) && isset($term->name)) $term_names[] = $term->name; elseif (is_array($term) && isset($term['name'])) $term_names[] = $term['name']; }
                    $term_names = array_filter($term_names);
                    if (!empty($term_names)) { $taxonomy_instructions[] = "- CATEGORY: Choose ONE category from: [" . implode(', ', $term_names) . "]"; }
                    else { $taxonomy_instructions[] = "- CATEGORY: Determine the single most appropriate category."; }
                } else { // instruct_model
                    $taxonomy_instructions[] = "- CATEGORY: Determine the category based on the user instructions in the prompt below.";
                }
            }

            // Tags
            if (is_string($tag_mode) && ($tag_mode === 'model_decides' || $tag_mode === 'instruct_model')) {
                $directive_block .= "
    TAGS: [Your chosen comma-separated tags]";
                 if ($tag_mode === 'model_decides') {
                    $terms_to_list = []; $term_names = [];
                    if ($output_type === 'publish_local') { $fetched_terms = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => false]); if (!is_wp_error($fetched_terms)) $terms_to_list = $fetched_terms; }
                    else { $terms_to_list = $site_info['taxonomies']['post_tag']['terms'] ?? []; }
                    foreach ($terms_to_list as $term) { if (is_object($term) && isset($term->name)) $term_names[] = $term->name; elseif (is_array($term) && isset($term['name'])) $term_names[] = $term['name']; }
                    $term_names = array_filter($term_names);
                     if (!empty($term_names)) { $taxonomy_instructions[] = "- TAGS: Choose relevant tags from: [" . implode(', ', $term_names) . "]. Output comma-separated."; }
                     else { $taxonomy_instructions[] = "- TAGS: Determine relevant tags. Output comma-separated."; }
                } else { // instruct_model
                    $taxonomy_instructions[] = "- TAGS: Determine the SINGLE most appropriate tag based ONLY on the user instructions in the prompt below. Output only this single tag name.";
                }
            }

            // Custom Taxonomies
            foreach ($custom_tax_configs as $tax_slug => $tax_mode) {
                if (is_string($tax_mode) && ($tax_mode === 'model_decides' || $tax_mode === 'instruct_model')) {
                    $directive_block .= "
    TAXONOMY[{$tax_slug}]: [Your chosen comma-separated '{$tax_slug}' terms]";
                    $tax_label = ucfirst(str_replace('_', ' ', $tax_slug));
                     if ($tax_mode === 'model_decides') {
                        $terms_to_list = $site_info['taxonomies'][$tax_slug]['terms'] ?? [];
                        $term_names = [];
                        foreach ($terms_to_list as $term) { if (is_array($term) && isset($term['name'])) $term_names[] = $term['name']; }
                        $term_names = array_filter($term_names);
                        if (!empty($term_names)) { $taxonomy_instructions[] = "- TAXONOMY[{$tax_slug}]: Choose relevant {$tax_label} terms from: [" . implode(', ', $term_names) . "]. Output comma-separated."; }
                        else { $taxonomy_instructions[] = "- TAXONOMY[{$tax_slug}]: Determine relevant {$tax_label} terms. Output comma-separated."; }
                    } else { // instruct_model
                        $taxonomy_instructions[] = "- TAXONOMY[{$tax_slug}]: Determine the SINGLE most appropriate {$tax_label} term based ONLY on the user instructions in the prompt below. Output only this single term name.";
                    }
                }
            }

            // Adding the Taxonomy Selection block
            $directive_block .= "

{$directive_counter}.  **Taxonomy Selection Instructions (if applicable):** Follow these instructions VERY carefully.";
            if (!empty($taxonomy_instructions)) {
                 $directive_block .= "
" . implode("
", $taxonomy_instructions);
            } else {
                $directive_block .= " N/A";
            }
            $directive_counter++;

            // Adding the Strict Adherence block
            $directive_block .= "

{$directive_counter}.  **Taxonomy Precision:** If taxonomy instructions above mention 'based ONLY on the user instructions', you MUST follow the user's prompt below precisely regarding those taxonomies. Do not add terms not requested or implied by the user prompt.";
             $directive_counter++;

            // Adding the Begin Content block
            $directive_block .= "

{$directive_counter}.  **Begin Content:** Immediately following the directives above, provide the main post content using standard Markdown (No H1 title).";

        } else {
             // Default for other output types (e.g., raw text)
             $directive_block .= "
4.  **Begin Content:** Provide the content immediately after these instructions.";
        }

        $directive_block .= "
--- END RESPONSE FORMATTING AND INSTRUCTIONS ---";

        // Prepend the instructions to the original prompt
        $final_prompt = $directive_block . "

" . $original_prompt;

        return $final_prompt;
    }
}