<?php
/**
 * Finalize Pipeline Step
 * 
 * Handles the fourth step of the processing pipeline: content finalization.
 * This step takes the processed and fact-checked data and finalizes it into
 * the final output format using the AI HTTP Client library.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine/steps
 * @since      NEXT_VERSION
 */

namespace DataMachine\Engine\Steps;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class FinalizeStep extends BasePipelineStep {
    
    /**
     * Execute the content finalization step.
     *
     * @param int $job_id The job ID to process.
     * @return bool True on success, false on failure.
     */
    public function execute(int $job_id): bool {
        try {
            $job = $this->get_job_data($job_id);
            if (!$job) {
                return $this->fail_job($job_id, 'Job not found in database for finalize step');
            }

            $input_data_json = $this->get_step_data($job_id, 1);
            if (empty($input_data_json)) {
                return $this->fail_job($job_id, 'No input data available for finalize step');
            }

            $input_data_packet = json_decode($input_data_json, true);
            if (empty($input_data_packet)) {
                return $this->fail_job($job_id, 'Failed to parse input data for finalize step');
            }

            $module_job_config = json_decode($job->module_config, true);
            
            return $this->execute_finalize_logic($job_id, $input_data_packet, $module_job_config);
            
        } catch (\Exception $e) {
            $this->get_logger()->error('Exception in finalize step', ['job_id' => $job_id, 'error' => $e->getMessage()]);
            return $this->fail_job($job_id, 'Finalize step failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute the core finalization logic.
     *
     * @param int $job_id The job ID.
     * @param array $input_data_packet The input data packet.
     * @param array $module_job_config The module job configuration.
     * @return bool True on success, false on failure.
     */
    private function execute_finalize_logic(int $job_id, array $input_data_packet, array $module_job_config): bool {
        $project_id = absint($module_job_config['project_id'] ?? 0);
        $prompt_builder = $this->get_prompt_builder();
        $system_prompt = $prompt_builder->build_system_prompt($project_id, $module_job_config['user_id'] ?? 0);
        $finalize_response_prompt = $module_job_config['finalize_response_prompt'] ?? '';

        // Get data from previous steps
        $processed_data_json = $this->get_step_data($job_id, 2);
        $processed_data = json_decode($processed_data_json, true);
        $initial_output = $processed_data['processed_output'] ?? '';

        $factcheck_data_json = $this->get_step_data($job_id, 3);
        $factcheck_data = json_decode($factcheck_data_json, true);
        $fact_checked_content = $factcheck_data['fact_checked_content'] ?? '';

        try {
            $enhanced_finalize_prompt = $prompt_builder->build_finalize_prompt($finalize_response_prompt, $module_job_config, $input_data_packet);
            $finalize_user_message = $prompt_builder->build_finalize_user_message($enhanced_finalize_prompt, $initial_output, $fact_checked_content, $module_job_config, $input_data_packet['metadata'] ?? []);

            // Use AI client directly instead of Finalize API handler
            $ai_client = $this->get_ai_client();
            if (!$ai_client) {
                return $this->fail_job($job_id, 'AI HTTP Client not available');
            }
            
            $messages = [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $finalize_user_message]
            ];

            $finalize_result = $ai_client->send_step_request('finalize', [
                'messages' => $messages
            ]);

            if (!$finalize_result['success']) {
                $this->get_logger()->error('Finalize step failed', [
                    'job_id' => $job_id,
                    'step' => 'finalize',
                    'error' => $finalize_result['error'] ?? 'Unknown error',
                    'provider' => $finalize_result['provider'] ?? 'unknown',
                    'raw_response' => $finalize_result['raw_response'] ?? null
                ]);
                return false;
            }

            $final_output_string = $finalize_result['data']['content'] ?? '';
            if (empty($final_output_string)) {
                $this->get_logger()->error('Finalize step returned empty content', ['job_id' => $job_id]);
                return false;
            }

            // Store result in database
            $success = $this->store_step_data($job_id, 4, wp_json_encode(['final_output_string' => $final_output_string]));
            if (!$success) {
                $this->get_logger()->error('Failed to store finalized data', ['job_id' => $job_id]);
                return false;
            }
            
            return true;

        } catch (\Exception $e) {
            return false;
        }
    }
    
}