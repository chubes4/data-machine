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
        $project_prompt = '';

        if ($project_id > 0 && $this->db_projects && method_exists($this->db_projects, 'get_project')) {
            $project = $this->db_projects->get_project($project_id, $user_id);
            if ($project && !empty($project->project_prompt)) {
                $project_prompt = $project->project_prompt;
            }
        }

        // Get the current date
        $current_date = date('F j, Y');

        // Append the date instruction to the fetched prompt
        // Add a newline before the instruction if the original prompt is not empty
        if (!empty($project_prompt)) {
             $project_prompt .= "\n\n";
        }
        $project_prompt .= "IMPORTANT: The current date is {$current_date}. Please write your response from this real-time perspective, ignoring the knowledge base cutoff.";

        return $project_prompt;
    }
}