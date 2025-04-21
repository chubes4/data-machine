<?php
/**
 * Handles fetching and modifying the project-level system prompt.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/helpers
 * @since      NEXT_VERSION
 */
class Data_Machine_Project_Prompt {

    /**
     * Database handler for projects.
     * @var Data_Machine_Database_Projects
     */
    private $db_projects;

    /**
     * Constructor.
     *
     * @param Data_Machine_Database_Projects $db_projects Instance of the project database handler.
     */
    public function __construct(Data_Machine_Database_Projects $db_projects) {
        $this->db_projects = $db_projects;
    }

    /**
     * Gets the project-level system prompt, modified with the current date.
     *
     * @param int $project_id The ID of the project.
     * @param int $user_id    The ID of the current user.
     * @return string The modified system prompt. Returns an empty string if project not found or has no prompt.
     */
    public function get_system_prompt(int $project_id, int $user_id): string {
        $project_prompt_base = '';

        if ($project_id > 0 && $this->db_projects && method_exists($this->db_projects, 'get_project')) {
            $project = $this->db_projects->get_project($project_id, $user_id);
            if ($project && !empty($project->project_prompt)) {
                $project_prompt_base = $project->project_prompt;
            }
        }

        // --- Start: Choose ONE of the stronger options ---
        // Get current date and time with timezone using WordPress function
        $current_datetime_str = wp_date('F j, Y, g:i a T'); // Respects WP Timezone
        $current_date_str = wp_date('F j, Y'); // Respects WP Timezone for date logic

        $date_instruction = <<<PROMPT
--- MANDATORY TIME CONTEXT ---
CURRENT DATE & TIME: {$current_datetime_str}
RULE: You MUST treat {$current_date_str} as the definitive 'today' for determining past/present/future tense.
ACTION: Frame all events relative to {$current_date_str}. Use past tense for completed events. Use present/future tense appropriately ONLY for events happening on or after {$current_date_str}.
CONSTRAINT: DO NOT discuss events completed before {$current_date_str} as if they are still upcoming.
KNOWLEDGE CUTOFF: Your internal knowledge cutoff is irrelevant; operate solely based on this date and provided context.
--- END TIME CONTEXT ---
PROMPT;
        // --- End: Chosen option ---

        // Prepend the date instruction
        $final_prompt = $date_instruction;

        // Add the original project prompt after the date instruction, with separation
        if (!empty($project_prompt_base)) {
             $final_prompt .= "\n\n" . $project_prompt_base; // Add newline separation
        }

        return $final_prompt;
    }
}