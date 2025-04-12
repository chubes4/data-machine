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

        // Only apply for publish_remote (can expand for other types in future)
        if ($output_type === 'publish_remote') {
            $publish_config = $output_config['publish_remote'] ?? [];
            // Prefer handler-specific remote_site_info, fallback to top-level
            $remote_info = $publish_config['remote_site_info'] ?? $output_config['remote_site_info'] ?? [];

            // Always instruct the AI to output the post title/category/tags as directives, regardless of mode.
            $prompt .= "\n\nAt the very top of your response, always include:\nPOST_TITLE: [title]\nCATEGORY: [category]\nTAGS: [comma-separated tags]\nFollow this with the main post content.";

            // --- Category List Injection (independent) ---
            $category_id = $publish_config['selected_remote_category_id'] ?? null;
            if ($category_id === -1 && !empty($remote_info['taxonomies']['category']['terms'])) {
                $category_names = array_map(
                    function($cat) { return $cat['name'] ?? ''; },
                    $remote_info['taxonomies']['category']['terms']
                );
                $category_names = array_filter($category_names); // Remove empty
                if (!empty($category_names)) {
                    $cat_list = implode(', ', $category_names);
                    $prompt .= "\n\nAvailable Categories: [{$cat_list}].\nChoose one category from this list for the post and output it as:\nCATEGORY: [chosen category]\n(at the very top of your response, before the main content).";
                }
            } elseif ($category_id === 0) {
                $prompt .= "\n\nSet the CATEGORY based on the user's instructions in the prompt.";
            }

            // --- Tag List Injection (independent) ---
            $tag_id = $publish_config['selected_remote_tag_id'] ?? null;
            if ($tag_id === -1 && !empty($remote_info['taxonomies']['post_tag']['terms'])) {
                $tag_names = array_map(
                    function($tag) { return $tag['name'] ?? ''; },
                    $remote_info['taxonomies']['post_tag']['terms']
                );
                $tag_names = array_filter($tag_names);
                if (!empty($tag_names)) {
                    $tag_list = implode(', ', $tag_names);
                    $prompt .= "\n\nAvailable Tags: [{$tag_list}].\nIf appropriate, choose one or more tags from this list for the post and output them as:\nTAGS: [comma-separated tags]\n(at the very top of your response, before the main post content).";
                }
            } elseif ($tag_id === 0) {
                $prompt .= "\n\nSet the TAGS based on the user's instructions in the prompt.";
            }

        }

        return $prompt;
    }
}