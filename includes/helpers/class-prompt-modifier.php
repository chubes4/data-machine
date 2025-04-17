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

        // Apply modifications only for publish_remote and publish_local
        if ($output_type === 'publish_remote' || $output_type === 'publish_local') {
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

            // --- Build the Combined Directive and Instruction Block --- 
            $directive_block = "\n--- RESPONSE FORMATTING AND INSTRUCTIONS ---";
            $directive_block .= "\n1.  **Format:** Your response MUST start *immediately* with the following directives, each on a new line. Do NOT include any other text before these lines:";
            $directive_block .= "\n    POST_TITLE: [Your calculated post title]"; // Always needed

            $taxonomy_instructions = []; // Collect instructions here

            // Category
            if (is_string($category_mode) && ($category_mode === 'model_decides' || $category_mode === 'instruct_model')) {
                $directive_block .= "\n    CATEGORY: [Your chosen category name]";
                if ($category_mode === 'model_decides') {
                    // Get terms
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
                $directive_block .= "\n    TAGS: [Your chosen comma-separated tags]";
                 if ($tag_mode === 'model_decides') {
                    $terms_to_list = []; $term_names = [];
                    if ($output_type === 'publish_local') { $fetched_terms = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => false]); if (!is_wp_error($fetched_terms)) $terms_to_list = $fetched_terms; }
                    else { $terms_to_list = $site_info['taxonomies']['post_tag']['terms'] ?? []; }
                    foreach ($terms_to_list as $term) { if (is_object($term) && isset($term->name)) $term_names[] = $term->name; elseif (is_array($term) && isset($term['name'])) $term_names[] = $term['name']; }
                    $term_names = array_filter($term_names);
                     if (!empty($term_names)) { $taxonomy_instructions[] = "- TAGS: Choose relevant tags from: [" . implode(', ', $term_names) . "]. Output comma-separated."; }
                     else { $taxonomy_instructions[] = "- TAGS: Determine relevant tags. Output comma-separated."; }
                } else { // instruct_model
                    $taxonomy_instructions[] = "- TAGS: Determine tags based on user instructions in the prompt below. Output comma-separated.";
                }
            }

            // Custom Taxonomies
            foreach ($custom_tax_configs as $tax_slug => $tax_mode) {
                if (is_string($tax_mode) && ($tax_mode === 'model_decides' || $tax_mode === 'instruct_model')) {
                    $directive_block .= "\n    TAXONOMY[{$tax_slug}]: [Your chosen comma-separated '{$tax_slug}' terms]";
                    $tax_label = ucfirst(str_replace('_', ' ', $tax_slug));
                     if ($tax_mode === 'model_decides') {
                        $terms_to_list = $site_info['taxonomies'][$tax_slug]['terms'] ?? [];
                        $term_names = [];
                        foreach ($terms_to_list as $term) { if (is_array($term) && isset($term['name'])) $term_names[] = $term['name']; }
                        $term_names = array_filter($term_names);
                        if (!empty($term_names)) { $taxonomy_instructions[] = "- TAXONOMY[{$tax_slug}]: Choose relevant {$tax_label} terms from: [" . implode(', ', $term_names) . "]. Output comma-separated."; }
                        else { $taxonomy_instructions[] = "- TAXONOMY[{$tax_slug}]: Determine relevant {$tax_label} terms. Output comma-separated."; }
                    } else { // instruct_model
                        $taxonomy_instructions[] = "- TAXONOMY[{$tax_slug}]: Determine {$tax_label} terms based on user instructions in the prompt below. Output comma-separated.";
                    }
                }
            }

            $directive_block .= "\n\n2.  **Taxonomy Selection Instructions (if applicable):**";
            if (!empty($taxonomy_instructions)) {
                 $directive_block .= "\n" . implode("\n", $taxonomy_instructions);
            } else {
                $directive_block .= " N/A";
            }

            $directive_block .= "\n\n3.  **Content Formatting:** Format the main post content using standard Markdown.";
            $directive_block .= "\n\n4.  **Title Exclusion:** Do NOT repeat the post title within the main content body itself. It should ONLY be included as POST_TITLE (NO H1).";
            $directive_block .= "\n\n5.  **Begin Content:** Immediately following the directives above, provide the main post content.";
            $directive_block .= "\n--- END RESPONSE FORMATTING AND INSTRUCTIONS ---";

        } // End publish_remote or publish_local check

        // Prepend the instructions to the original prompt
        $final_prompt = $directive_block . "\n\n" . $original_prompt;

        return $final_prompt;
    }
}