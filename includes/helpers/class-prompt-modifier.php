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
        $prompt = $original_prompt;

        // If the input contains an image, prepend an instruction for the AI to analyze it
        if (!empty($input_data_packet['file_info']['url'])) {
            $image_url = $input_data_packet['file_info']['url'];
            $prompt = "You are provided with an image at {$image_url}. Analyze the image and incorporate its content into your response as appropriate.\n\n" . $prompt;
        }

        // Apply modifications only for publish_remote and publish_local
        if ($output_type === 'publish_remote' || $output_type === 'publish_local') {
            $category_id = null;
            $tag_id = null;
            // Term lists are fetched conditionally later
            $remote_category_terms = [];
            $remote_tag_terms = [];
            $publish_config = [];

            if ($output_type === 'publish_remote') {
                $publish_config = $output_config['publish_remote'] ?? [];
                // Prefer handler-specific remote_site_info, fallback to top-level
                $site_info = $publish_config['remote_site_info'] ?? $output_config['remote_site_info'] ?? [];
                $category_id = $publish_config['selected_remote_category_id'] ?? null;
                $tag_id = $publish_config['selected_remote_tag_id'] ?? null;
                // Store remote terms for potential use later
                $remote_category_terms = $site_info['taxonomies']['category']['terms'] ?? [];
                $remote_tag_terms = $site_info['taxonomies']['post_tag']['terms'] ?? [];
            } elseif ($output_type === 'publish_local') {
                $publish_config = $output_config['publish_local'] ?? [];
                $category_id = $publish_config['selected_local_category_id'] ?? null;
                $tag_id = $publish_config['selected_local_tag_id'] ?? null;
                // Local terms will be fetched directly if needed (ID = -1)
            }

            $needs_category_directive = ($category_id !== null && $category_id <= 0);
            $needs_tag_directive = ($tag_id !== null && $tag_id <= 0);

            // Construct the initial directive string conditionally
            $directive_instructions = "\n\nAt the very top of your response, always include:\nPOST_TITLE: [title]";
            if ($needs_category_directive) {
                $directive_instructions .= "\nCATEGORY: [category]";
            }
            if ($needs_tag_directive) {
                $directive_instructions .= "\nTAGS: [comma-separated tags]";
            }
            $directive_instructions .= "\nFollow this with the main post content.";
            $prompt .= $directive_instructions;


            // --- Category List Injection (only if directive is needed) ---
            if ($needs_category_directive) {
                if ($category_id === -1) { // Let Model Decide
                    $terms_to_list = [];
                    if ($output_type === 'publish_local') {
                        // Fetch local terms directly for local publish
                        $fetched_terms = get_terms(array('taxonomy' => 'category', 'hide_empty' => false));
                        if (!is_wp_error($fetched_terms) && !empty($fetched_terms)) {
                            // Map WP_Term objects to the simple name structure expected by the rest of the code
                            $terms_to_list = array_map(function($term) { return ['name' => $term->name]; }, $fetched_terms);
                        }
                    } else { // publish_remote
                        $terms_to_list = $remote_category_terms; // Use terms fetched earlier
                    }

                    if (!empty($terms_to_list)) {
                        $category_names = array_map(
                            function($cat) { return $cat['name'] ?? ''; },
                            $terms_to_list
                        );
                        $category_names = array_filter($category_names); // Remove empty
                        if (!empty($category_names)) {
                            $cat_list = implode(', ', $category_names);
                            $prompt .= "\n\nAvailable Categories: [{$cat_list}].";
                            $prompt .= "\nChoose one category from this list for the post and output it using the CATEGORY directive shown above.";
                        }
                    }
                } elseif ($category_id === 0) { // Instruct Model
                    $prompt .= "\n\nSet the CATEGORY based on the user's instructions in the prompt, using the CATEGORY directive shown above.";
                }
            }


            // --- Tag List Injection (only if directive is needed) ---
             if ($needs_tag_directive) {
                if ($tag_id === -1) { // Let Model Decide
                    $terms_to_list = [];
                     if ($output_type === 'publish_local') {
                        // Fetch local terms directly
                        $fetched_terms = get_terms(array('taxonomy' => 'post_tag', 'hide_empty' => false));
                         if (!is_wp_error($fetched_terms) && !empty($fetched_terms)) {
                             // Map WP_Term objects to the simple name structure
                             $terms_to_list = array_map(function($term) { return ['name' => $term->name]; }, $fetched_terms);
                         }
                     } else { // publish_remote
                        $terms_to_list = $remote_tag_terms; // Use terms fetched earlier
                     }

                     if (!empty($terms_to_list)) {
                        $tag_names = array_map(
                            function($tag) { return $tag['name'] ?? ''; },
                            $terms_to_list
                        );
                        $tag_names = array_filter($tag_names);
                        if (!empty($tag_names)) {
                            $tag_list = implode(', ', $tag_names);
                            $prompt .= "\n\nAvailable Tags: [{$tag_list}].";
                            $prompt .= "\nIf appropriate, choose one or more tags from this list for the post and output them using the TAGS directive shown above.";
                        }
                    }
                } elseif ($tag_id === 0) { // Instruct Model
                     $prompt .= "\n\nSet the TAGS based on the user's instructions in the prompt, using the TAGS directive shown above.";
                }
            }
        } // End publish_remote or publish_local check

        return $prompt;
    }
}