<?php
/**
 * FactCheck Pipeline Step
 * 
 * Handles the third step of the processing pipeline: AI-powered fact checking.
 * This step takes the processed data from step 2 and fact-checks it using 
 * the AI HTTP Client library with web search capabilities.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine/steps
 * @since      NEXT_VERSION
 */

namespace DataMachine\Engine\Steps;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class FactCheckStep extends BasePipelineStep {
    
    /**
     * Execute the fact checking step.
     *
     * @param int $job_id The job ID to process.
     * @return bool True on success, false on failure.
     */
    public function execute(int $job_id): bool {
        try {
            $job = $this->get_job_data($job_id);
            if (!$job) {
                return $this->fail_job($job_id, 'Job not found in database for fact check step');
            }

            $input_data_json = $this->get_step_data($job_id, 1);
            if (empty($input_data_json)) {
                return $this->fail_job($job_id, 'No input data available for fact check step');
            }

            $input_data_packet = json_decode($input_data_json, true);
            if (empty($input_data_packet)) {
                return $this->fail_job($job_id, 'Failed to parse input data for fact check step');
            }

            $module_job_config = json_decode($job->module_config, true);
            
            return $this->execute_factcheck_logic($job_id, $input_data_packet, $module_job_config);
            
        } catch (\Exception $e) {
            $this->get_logger()->error('Exception in factcheck step', ['job_id' => $job_id, 'error' => $e->getMessage()]);
            return $this->fail_job($job_id, 'Fact check step failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute the core fact checking logic.
     *
     * @param int $job_id The job ID.
     * @param array $input_data_packet The input data packet.
     * @param array $module_job_config The module job configuration.
     * @return bool True on success, false on failure.
     */
    private function execute_factcheck_logic(int $job_id, array $input_data_packet, array $module_job_config): bool {
        $skip_fact_check = isset($module_job_config['skip_fact_check']) ? (bool) $module_job_config['skip_fact_check'] : false;
        
        if ($skip_fact_check) {
            // Store empty fact check result and continue
            $success = $this->store_step_data($job_id, 3, wp_json_encode(['fact_checked_content' => '']));
            if (!$success) {
                $this->get_logger()->error('Failed to store fact check data (skipped)', ['job_id' => $job_id]);
                return false;
            }
            return true;
        }

        $project_id = absint($module_job_config['project_id'] ?? 0);
        $prompt_builder = $this->get_prompt_builder();
        $system_prompt = $prompt_builder->build_system_prompt($project_id, $module_job_config['user_id'] ?? 0);
        $fact_check_prompt = $module_job_config['fact_check_prompt'] ?? '';

        // Get processed data from previous step
        $processed_data_json = $this->get_step_data($job_id, 2);
        $processed_data = json_decode($processed_data_json, true);
        $initial_output = $processed_data['processed_output'] ?? '';

        try {
            $enhanced_fact_check_prompt = $prompt_builder->build_fact_check_prompt($fact_check_prompt);
            
            // Use AI client directly instead of FactCheck API handler
            $ai_client = $this->get_ai_client();
            if (!$ai_client) {
                return $this->fail_job($job_id, 'AI HTTP Client not available');
            }
            
            // Build fact check message with content to check
            $user_message = $enhanced_fact_check_prompt;
            if (!empty($initial_output)) {
                $user_message .= "\n\nContent to Fact Check:\n" . $initial_output;
            }
            
            $messages = [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_message]
            ];

            // Get web search tools for fact-checking
            $tools = $this->get_web_search_tools();

            $factcheck_result = $ai_client->send_step_request('factcheck', [
                'messages' => $messages,
                'tools' => $tools
            ]);

            if (!$factcheck_result['success']) {
                $this->get_logger()->error('Fact check step failed', [
                    'job_id' => $job_id,
                    'step' => 'factcheck',
                    'error' => $factcheck_result['error'] ?? 'Unknown error',
                    'provider' => $factcheck_result['provider'] ?? 'unknown',
                    'raw_response' => $factcheck_result['raw_response'] ?? null
                ]);
                return false;
            }

            $fact_checked_content = $factcheck_result['data']['content'] ?? '';
            
            // Store result in database
            $success = $this->store_step_data($job_id, 3, wp_json_encode(['fact_checked_content' => $fact_checked_content]));
            if (!$success) {
                $this->get_logger()->error('Failed to store fact check data', ['job_id' => $job_id]);
                return false;
            }
            
            return true;

        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get web search tools for fact-checking.
     * Copied from FactCheck API class.
     *
     * @return array Web search tool definitions.
     */
    private function get_web_search_tools(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'web_search',
                    'description' => 'Search the web to verify facts and gather current information',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'The search query to verify facts'
                            ]
                        ],
                        'required' => ['query']
                    ]
                ]
            ]
        ];
    }
    
}