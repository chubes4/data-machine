<?php
/**
 * Centralized prompt builder for AI model interactions.
 * 
 * Consolidates all prompt construction logic from across the codebase into a single,
 * maintainable class. Replaces scattered prompt modifications in multiple files.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/helpers
 * @since      NEXT_VERSION
 */

namespace DataMachine\Engine\Filters;

use DataMachine\Database\Projects;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class PromptBuilder {

    /**
     * Database handler for projects.
     * @var Projects
     */
    private $db_projects;

    /**
     * Constructor.
     *
     * @param Projects $db_projects Instance of the project database handler.
     */
    public function __construct(Projects $db_projects) {
        $this->db_projects = $db_projects;
    }

    /**
     * Build the complete system prompt including project context and date/time.
     *
     * @param int $project_id The ID of the project.
     * @param int $user_id    The ID of the current user.
     * @return string The complete system prompt.
     */
    public function build_system_prompt(int $project_id, int $user_id): string {
        $project_prompt_base = '';

        if ($project_id > 0 && $this->db_projects && method_exists($this->db_projects, 'get_project')) {
            $project = $this->db_projects->get_project($project_id, $user_id);
            if ($project && !empty($project->project_prompt)) {
                $project_prompt_base = $project->project_prompt;
            }
        }

        // Add current date and time context
        $current_datetime_str = wp_date('F j, Y, g:i a T');
        $current_date_str = wp_date('F j, Y');

        $date_instruction = <<<PROMPT
--- MANDATORY TIME CONTEXT ---
CURRENT DATE & TIME: {$current_datetime_str}
RULE: You MUST treat {$current_date_str} as the definitive 'today' for determining past/present/future tense.
ACTION: Frame all events relative to {$current_date_str}. Use past tense for completed events. Use present/future tense appropriately ONLY for events happening on or after {$current_date_str}.
CONSTRAINT: DO NOT discuss events completed before {$current_date_str} as if they are still upcoming.
KNOWLEDGE CUTOFF: Your internal knowledge cutoff is irrelevant; operate solely based on this date and provided context.
--- END TIME CONTEXT ---
PROMPT;

        $final_prompt = $date_instruction;

        if (!empty($project_prompt_base)) {
            $final_prompt .= "\n\n" . $project_prompt_base;
        }

        return $final_prompt;
    }

    /**
     * Build the process data prompt with image analysis instructions if applicable.
     *
     * @param string $base_prompt The base process data prompt from module configuration.
     * @param array  $input_data_packet The input data packet.
     * @return string The enhanced process data prompt.
     */
    public function build_process_data_prompt(string $base_prompt, array $input_data_packet): string {
        $enhanced_prompt = $base_prompt;

        // Add image-specific instruction if an image is present
        $file_info = $input_data_packet['file_info'] ?? [];
        $has_image = !empty($file_info['url']) || (!empty($file_info['persistent_path']) && $this->is_image_file($file_info['persistent_path']));

        if ($has_image) {
            $image_directive = "IMPORTANT INSTRUCTION: An image has been provided. Analyze the visual content of the image carefully. Prioritize information directly observed in the image, especially for identifying people, objects, or specific visual details, over potentially conflicting information in the text below.";
            $enhanced_prompt = $image_directive . "\n\n---\n\n" . $enhanced_prompt;
        }

        return $enhanced_prompt;
    }

    /**
     * Build the fact check prompt with robust fact-checking directive.
     *
     * @param string $base_prompt The base fact check prompt from module configuration.
     * @return string The enhanced fact check prompt.
     */
    public function build_fact_check_prompt(string $base_prompt): string {
        $fact_check_directive = <<<PROMPT
FACT-CHECKING DIRECTIVE:
- When fact-checking, treat the user-provided content and post content as the primary source of truth unless you find a direct, credible, and more recent source that clearly disproves it.
- Do not hedge, speculate, or state that information is "not officially announced" or "unconfirmed" unless you find a direct, credible source explicitly stating so.
- If the user-provided content is a news announcement, event, update, or other factual statement, and you do not find a credible contradiction, you must treat it as accurate and confirmed.
- If in doubt, defer to the user-provided content and do not introduce uncertainty.
- Always cite any source that directly contradicts or disproves the user-provided content.
PROMPT;

        return $fact_check_directive . "\n\n" . $base_prompt;
    }

    /**
     * Build the finalize prompt with output-specific formatting instructions.
     *
     * @param string $base_prompt The base finalize prompt from module configuration.
     * @param array  $module_job_config The complete module job configuration.
     * @param array  $input_data_packet The input data packet for context.
     * @return string The enhanced finalize prompt.
     */
    public function build_finalize_prompt(string $base_prompt, array $module_job_config, array $input_data_packet = []): string {
        $output_type = $module_job_config['output_type'] ?? '';
        $output_config = $module_job_config['output_config'] ?? [];

        $directive_block = "\n--- RESPONSE FORMATTING AND INSTRUCTIONS ---";
        $directive_block .= "\n1.  **Strict Adherence:** Follow all instructions below precisely.";

        // Output-specific instructions
        if ($output_type === 'twitter') {
            $twitter_config = $output_config['twitter'] ?? [];
            $char_limit = $twitter_config['twitter_char_limit'] ?? 280;
            $link_placeholder_length = 25;
            $text_limit = $char_limit - $link_placeholder_length;
            $directive_block .= "\n2.  **Twitter Length Limit:** Keep the MAIN content text under {$text_limit} characters. The system will add a source link later.";
            $directive_block .= "\n3.  **Begin Content:** Provide the tweet content immediately after these instructions.";

        } elseif ($output_type === 'publish_local' || $output_type === 'publish_remote') {
            // Publishing-specific instructions
            $directive_block .= "\n2.  **Content Format:** Your response MUST use Gutenberg block markup. Examples:";
            $directive_block .= "\n    <!-- wp:paragraph --><p>Your content here.</p><!-- /wp:paragraph -->";
            $directive_block .= "\n    <!-- wp:heading {\"level\":2} --><h2>Section Title</h2><!-- /wp:heading -->";
            $directive_block .= "\n    <!-- wp:list --><ul><li>List item</li></ul><!-- /wp:list -->";
            $directive_block .= "\n3.  **Content Structure:** Create engaging, well-structured content appropriate for blog publishing.";
            $directive_block .= "\n4.  **Format:** Your response MUST start *immediately* with the following directives, each on a new line. Do NOT include any other text before these lines:";
            $directive_block .= "\n    POST_TITLE: [Your calculated post title]";

            // Add taxonomy instructions
            $taxonomy_instructions = [];
            $directive_counter = 5;

            // Category instructions - check both local and remote publishing keys
            $category_mode = $output_config[$output_type]['category_mode'] ?? 
                            $output_config[$output_type]['selected_remote_category_id'] ?? null;
            if (is_string($category_mode) && ($category_mode === 'instruct_model')) {
                $directive_block .= "\n    CATEGORY: [Your chosen category name]";
                $taxonomy_instructions[] = "- CATEGORY: Determine the category based on the user instructions in the prompt below.";
            }

            // Tag instructions - check both local and remote publishing keys
            $tag_mode = $output_config[$output_type]['tag_mode'] ?? 
                       $output_config[$output_type]['selected_remote_tag_id'] ?? null;
            if (is_string($tag_mode) && ($tag_mode === 'instruct_model')) {
                $directive_block .= "\n    TAGS: [Your chosen comma-separated tags]";
                $taxonomy_instructions[] = "- TAGS: Determine the most appropriate tag(s) based ONLY on the user instructions in the prompt below. Output comma-separated.";
            }

            // Custom taxonomy instructions
            $custom_tax_configs = $output_config[$output_type]['selected_custom_taxonomy_values'] ?? [];
            foreach ($custom_tax_configs as $tax_slug => $tax_mode) {
                if (is_string($tax_mode) && ($tax_mode === 'instruct_model')) {
                    $directive_block .= "\n    TAXONOMY[{$tax_slug}]: [Your chosen comma-separated '{$tax_slug}' terms]";
                    $tax_label = ucfirst(str_replace('_', ' ', $tax_slug));
                    $taxonomy_instructions[] = "- TAXONOMY[{$tax_slug}]: Determine the most appropriate {$tax_label} term(s) based ONLY on the user instructions in the prompt below. Output comma-separated.";
                }
            }

            // Add taxonomy selection block
            if (!empty($taxonomy_instructions)) {
                $directive_block .= "\n\n{$directive_counter}.  **Taxonomy Selection Instructions:** Follow these instructions VERY carefully.";
                $directive_block .= "\n" . implode("\n", $taxonomy_instructions);
                $directive_counter++;

                $directive_block .= "\n\n{$directive_counter}.  **Taxonomy Precision:** If taxonomy instructions above mention 'based ONLY on the user instructions', you MUST follow the user's prompt below precisely regarding those taxonomies. Do not add terms not requested or implied by the user prompt.";
                $directive_counter++;
            }

            $directive_block .= "\n\n{$directive_counter}.  **Begin Content:** Immediately following the directives above, provide the main post content using Gutenberg block markup. No H1 title.";

        } else {
            // Default for other output types
            $directive_block .= "\n2.  **Begin Content:** Provide the content immediately after these instructions.";
        }

        $directive_block .= "\n--- END RESPONSE FORMATTING AND INSTRUCTIONS ---";

        return $directive_block . "\n\n" . $base_prompt;
    }

    /**
     * Build the complete user message for finalization with all context.
     *
     * @param string $enhanced_prompt The enhanced finalize prompt.
     * @param string $initial_output The initial processing output.
     * @param string $fact_check_results The fact check results.
     * @param array  $module_job_config The module job configuration.
     * @param array  $input_metadata The input metadata.
     * @return string The complete user message.
     */
    public function build_finalize_user_message(string $enhanced_prompt, string $initial_output, string $fact_check_results, array $module_job_config, array $input_metadata = []): string {
        $user_message = $enhanced_prompt;

        if (!empty($initial_output)) {
            $user_message .= "\n\nInitial Response:\n" . $initial_output;
        }

        if (!empty($fact_check_results)) {
            $user_message .= "\n\nFact Check Results:\n" . $fact_check_results;
        }

        // Add POST_TITLE instruction for publishing outputs
        $output_type = $module_job_config['output_type'] ?? null;
        if ($output_type === 'publish_local' || $output_type === 'publish_remote') {
            $user_message .= "\n\nIMPORTANT: Please ensure the response starts *immediately* with a suitable post title formatted exactly like this (with no preceding text or blank lines):\nPOST_TITLE: [Your Suggested Title Here]\n\nFollow this title line immediately with the rest of your output. Do not print the post title again in the response.";
            $user_message .= "\n\nReminder: Do not use formatting markup for the initial directive lines (POST_TITLE, CATEGORY, TAGS).";
        }

        // Note: Source URL is handled programmatically by output handlers, not passed to AI

        return $user_message;
    }

    /**
     * Check if a file path represents an image file.
     *
     * @param string $file_path The file path to check.
     * @return bool True if it's an image file.
     */
    private function is_image_file(string $file_path): bool {
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        return in_array($extension, $image_extensions);
    }

    /**
     * Determine the appropriate source name from metadata.
     *
     * @param array  $input_metadata The input metadata.
     * @param string $source_url The source URL.
     * @return string The source name.
     */
    private function determine_source_name(array $input_metadata, string $source_url): string {
        if (!empty($input_metadata['subreddit'])) {
            return 'r/' . esc_html($input_metadata['subreddit']);
        } elseif (!empty($input_metadata['feed_url'])) {
            $parsed_url = wp_parse_url($input_metadata['feed_url']);
            if (!empty($parsed_url['host'])) {
                return esc_html($parsed_url['host']);
            } else {
                return 'Original Feed';
            }
        } elseif (!empty($input_metadata['original_title'])) {
            return esc_html($input_metadata['original_title']);
        } else {
            $parsed_url = wp_parse_url($source_url);
            if (!empty($parsed_url['host'])) {
                return esc_html($parsed_url['host']);
            } else {
                return 'Original Source';
            }
        }
    }
} 