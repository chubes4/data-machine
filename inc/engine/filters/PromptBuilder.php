<?php
/**
 * Simplified prompt builder for AI model interactions.
 * 
 * Lightweight interface that delegates all prompt construction to the AI HTTP Client library.
 * Uses library's modular system with Data Machine specific sections and filters.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine/filters
 * @since      NEXT_VERSION
 */

namespace DataMachine\Engine\Filters;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Ensure AI HTTP Client library is loaded
if (!class_exists('AI_HTTP_Prompt_Manager')) {
    require_once DATA_MACHINE_PATH . 'lib/ai-http-client/ai-http-client.php';
}

class PromptBuilder {

    /**
     * Constructor.
     * 
     * Validates that the AI HTTP Client library is loaded.
     */
    public function __construct() {
        // Validate library is loaded
        if (!class_exists('AI_HTTP_Prompt_Manager')) {
            throw new \Exception('AI HTTP Client library is required for PromptBuilder');
        }
    }

    /**
     * Build the complete system prompt including project context and date/time.
     *
     * @param int $project_id The ID of the project.
     * @param int $user_id    The ID of the current user.
     * @return string The complete system prompt.
     */
    public function build_system_prompt(int $project_id, int $user_id): string {
        // Use library's modular system prompt builder with Data Machine sections
        return AI_HTTP_Prompt_Manager::build_modular_system_prompt([
            'sections' => ['datetime', 'project_context'],
            'context' => [
                'project_id' => $project_id,
                'user_id' => $user_id
            ],
            'plugin_context' => 'data-machine'
        ]);
    }

    /**
     * Build the process data prompt with image analysis instructions if applicable.
     *
     * @param string $base_prompt The base process data prompt from module configuration.
     * @param array  $input_data_packet The input data packet.
     * @return string The enhanced process data prompt.
     */
    public function build_process_data_prompt(string $base_prompt, array $input_data_packet): string {
        // Determine if image is present
        $file_info = $input_data_packet['file_info'] ?? [];
        $has_image = !empty($file_info['url']) || (!empty($file_info['persistent_path']) && $this->is_image_file($file_info['persistent_path']));
        
        // Use library's modular system with image analysis section if needed
        $sections = $has_image ? ['image_analysis'] : [];
        
        return AI_HTTP_Prompt_Manager::build_modular_system_prompt([
            'base_prompt' => $base_prompt,
            'sections' => $sections,
            'context' => [
                'has_image' => $has_image,
                'file_info' => $file_info,
                'input_data' => $input_data_packet
            ],
            'plugin_context' => 'data-machine'
        ]);
    }

    /**
     * Build the fact check prompt with robust fact-checking directive.
     *
     * @param string $base_prompt The base fact check prompt from module configuration.
     * @return string The enhanced fact check prompt.
     */
    public function build_fact_check_prompt(string $base_prompt): string {
        // Use library's modular system with fact-checking directive section
        return AI_HTTP_Prompt_Manager::build_modular_system_prompt([
            'base_prompt' => $base_prompt,
            'sections' => ['fact_check_directive'],
            'context' => [
                'fact_check_mode' => true,
                'directive_type' => 'fact_checking'
            ],
            'plugin_context' => 'data-machine'
        ]);
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

        // Use library's modular system with output directives section
        return AI_HTTP_Prompt_Manager::build_modular_system_prompt([
            'base_prompt' => $base_prompt,
            'sections' => ['output_directives'],
            'context' => [
                'output_type' => $output_type,
                'output_config' => $output_config,
                'input_data' => $input_data_packet
            ],
            'plugin_context' => 'data-machine'
        ]);
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
        // Use library's complete prompt builder with all enhancements and content sections
        return AI_HTTP_Prompt_Manager::build_complete_prompt([
            'base_prompt' => $enhanced_prompt,
            'variables' => [
                '{initial_response}' => $initial_output,
                '{fact_check_results}' => $fact_check_results
            ],
            'sections' => ['content_sections'],
            'context' => [
                'initial_output' => $initial_output,
                'fact_check_results' => $fact_check_results,
                'module_config' => $module_job_config,
                'input_metadata' => $input_metadata,
                'output_type' => $module_job_config['output_type'] ?? null
            ],
            'plugin_context' => 'data-machine'
        ]);
    }
    
    /**
     * Build content sections for finalize user message.
     * Provides structured content including initial response and fact-check results.
     *
     * @param string $initial_output The initial processing output.
     * @param string $fact_check_results The fact check results.
     * @param array  $module_job_config The module job configuration.
     * @return string The content sections.
     */
    public function build_content_sections(string $initial_output, string $fact_check_results, array $module_job_config): string {
        $sections = [];
        
        if (!empty($initial_output)) {
            $sections[] = "\n\nInitial Response:\n" . $initial_output;
        }

        if (!empty($fact_check_results)) {
            $sections[] = "\n\nFact Check Results:\n" . $fact_check_results;
        }

        // Add POST_TITLE instruction for publishing outputs
        $output_type = $module_job_config['output_type'] ?? null;
        if ($output_type === 'publish_local' || $output_type === 'publish_remote') {
            $sections[] = "\n\nIMPORTANT: Please ensure the response starts *immediately* with a suitable post title formatted exactly like this (with no preceding text or blank lines):\nPOST_TITLE: [Your Suggested Title Here]\n\nFollow this title line immediately with the rest of your output. Do not print the post title again in the response.";
            $sections[] = "\n\nReminder: Do not use formatting markup for the initial directive lines (POST_TITLE, CATEGORY, TAGS).";
        }

        return implode('', $sections);
    }

    /**
     * Build output-specific directive blocks for Data Machine.
     * Centralized management of all output formatting instructions.
     * 
     * @param string $output_type The output type
     * @param array $output_config The output configuration
     * @return string The directive block
     */
    public function build_output_directives(string $output_type, array $output_config): string {
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
                $directive_block .= "\n    TAGS: [Your chosen single tag]";
                $taxonomy_instructions[] = "- TAGS: Determine the most appropriate single tag based ONLY on the user instructions in the prompt below. Output exactly one tag name only.";
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
        
        return $directive_block;
    }

    /**
     * Build datetime section for system prompts.
     * Provides current date/time context with strict temporal framing rules.
     * 
     * @return string The datetime section content
     */
    public function build_datetime_section(): string {
        $current_datetime = wp_date('F j, Y, g:i a T');
        $current_date = wp_date('F j, Y');
        
        return <<<PROMPT
--- MANDATORY TIME CONTEXT ---
CURRENT DATE & TIME: {$current_datetime}
RULE: You MUST treat {$current_date} as the definitive 'today' for determining past/present/future tense.
ACTION: Frame all events relative to {$current_date}. Use past tense for completed events. Use present/future tense appropriately ONLY for events happening on or after {$current_date}.
CONSTRAINT: DO NOT discuss events completed before {$current_date} as if they are still upcoming.
KNOWLEDGE CUTOFF: Your internal knowledge cutoff is irrelevant; operate solely based on this date and provided context.
--- END TIME CONTEXT ---
PROMPT;
    }

    /**
     * Build project context section for system prompts.
     * Includes project-specific instructions and context.
     * 
     * @param int $project_id The project ID
     * @param int $user_id The user ID
     * @return string The project context content
     */
    public function build_project_context_section(int $project_id, int $user_id): string {
        $db_projects = apply_filters('dm_get_db_projects', null);
        
        if ($project_id > 0 && $db_projects && method_exists($db_projects, 'get_project')) {
            $project = $db_projects->get_project($project_id, $user_id);
            if ($project && !empty($project->project_prompt)) {
                return $project->project_prompt;
            }
        }
        
        return '';
    }

    /**
     * Build image analysis directive section.
     * Provides instructions for processing visual content.
     * 
     * @param bool $has_image Whether an image is present
     * @return string The image analysis directive content
     */
    public function build_image_analysis_section(bool $has_image): string {
        if ($has_image) {
            return "IMPORTANT INSTRUCTION: An image has been provided. Analyze the visual content of the image carefully. Prioritize information directly observed in the image, especially for identifying people, objects, or specific visual details, over potentially conflicting information in the text below.";
        }
        
        return '';
    }

    /**
     * Build fact-checking directive section.
     * Provides robust fact-checking instructions and guidelines.
     * 
     * @return string The fact-checking directive content
     */
    public function build_fact_check_directive_section(): string {
        return <<<PROMPT
FACT-CHECKING DIRECTIVE:
- When fact-checking, treat the user-provided content and post content as the primary source of truth unless you find a direct, credible, and more recent source that clearly disproves it.
- Do not hedge, speculate, or state that information is "not officially announced" or "unconfirmed" unless you find a direct, credible source explicitly stating so.
- If the user-provided content is a news announcement, event, update, or other factual statement, and you do not find a credible contradiction, you must treat it as accurate and confirmed.
- If in doubt, defer to the user-provided content and do not introduce uncertainty.
- Always cite any source that directly contradicts or disproves the user-provided content.
PROMPT;
    }

    /**
     * Register all Data Machine prompt sections with the AI HTTP Client library.
     * Centralizes all section registration for clean architecture.
     */
    public function register_all_sections(): void {
        // DateTime section for system prompts
        add_filter('ai_http_client_section_datetime', function($content, $context, $plugin_context) {
            if ($plugin_context !== 'data-machine') {
                return $content;
            }
            return $this->build_datetime_section();
        }, 10, 3);
        
        // Project context section for system prompts
        add_filter('ai_http_client_section_project_context', function($content, $context, $plugin_context) {
            if ($plugin_context !== 'data-machine') {
                return $content;
            }
            
            $project_id = $context['project_id'] ?? 0;
            $user_id = $context['user_id'] ?? 0;
            return $this->build_project_context_section($project_id, $user_id);
        }, 10, 3);
        
        // Image analysis directive section
        add_filter('ai_http_client_section_image_analysis', function($content, $context, $plugin_context) {
            if ($plugin_context !== 'data-machine') {
                return $content;
            }
            
            $has_image = $context['has_image'] ?? false;
            return $this->build_image_analysis_section($has_image);
        }, 10, 3);
        
        // Fact-checking directive section
        add_filter('ai_http_client_section_fact_check_directive', function($content, $context, $plugin_context) {
            if ($plugin_context !== 'data-machine') {
                return $content;
            }
            return $this->build_fact_check_directive_section();
        }, 10, 3);
        
        // Output formatting directives section
        add_filter('ai_http_client_section_output_directives', function($content, $context, $plugin_context) {
            if ($plugin_context !== 'data-machine') {
                return $content;
            }
            
            $output_type = $context['output_type'] ?? '';
            $output_config = $context['output_config'] ?? [];
            return $this->build_output_directives($output_type, $output_config);
        }, 10, 3);
        
        // Content sections for finalize prompts
        add_filter('ai_http_client_section_content_sections', function($content, $context, $plugin_context) {
            if ($plugin_context !== 'data-machine') {
                return $content;
            }
            
            $initial_output = $context['initial_output'] ?? '';
            $fact_check_results = $context['fact_check_results'] ?? '';
            $module_config = $context['module_config'] ?? [];
            return $this->build_content_sections($initial_output, $fact_check_results, $module_config);
        }, 10, 3);
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

} 