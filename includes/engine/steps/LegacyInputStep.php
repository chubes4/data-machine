<?php
/**
 * Input Pipeline Step
 * 
 * Handles the first step of the processing pipeline: data collection from input sources.
 * This step fetches data using the appropriate input handler based on the module configuration,
 * validates the data, and stores it for subsequent pipeline steps.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine/steps
 * @since      NEXT_VERSION
 */

namespace DataMachine\Engine\Steps;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class InputStep extends BasePipelineStep {
    
    /**
     * Execute the input data collection step.
     *
     * @param int $job_id The job ID to process.
     * @return bool True on success, false on failure.
     */
    public function execute(int $job_id): bool {
        try {
            // Mark job as started
            $job_status_manager = $this->get_job_status_manager();
            if (!$job_status_manager || !$job_status_manager->start($job_id)) {
                return false;
            }

            $job = $this->get_job_data($job_id);
            if (!$job) {
                return $this->fail_job($job_id, 'Job not found in database');
            }

            $module_job_config = json_decode($job->module_config, true);
            $module_id = $module_job_config['module_id'] ?? 0;
            $data_source_type = $module_job_config['data_source_type'] ?? '';

            if (empty($data_source_type)) {
                $this->get_logger()->error('Missing data source type for input step', ['job_id' => $job_id, 'module_id' => $module_id]);
                return $this->fail_job($job_id, 'Missing data source type for input step');
            }

            // Get input handler and fetch data directly via Constants
            $handler_info = \DataMachine\Constants::get_input_handler($data_source_type);
            if (!$handler_info || !class_exists($handler_info['class'])) {
                $error_message = 'Input handler not found or invalid: ' . $data_source_type;
                $this->get_logger()->error('Failed to create input handler', ['job_id' => $job_id, 'handler_type' => $data_source_type]);
                return $this->fail_job($job_id, $error_message);
            }
            $input_handler = new $handler_info['class']();

            // Get the full module object from database  
            $user_id = $module_job_config['user_id'] ?? 0;
            $db_modules = $this->get_db_modules();
            $module = $db_modules->get_module($module_id, $user_id);
            if (!$module) {
                $this->get_logger()->error('Module not found for input step', ['job_id' => $job_id, 'module_id' => $module_id, 'user_id' => $user_id]);
                return $this->fail_job($job_id, 'Module not found for input step');
            }

            // Extract source config from module
            $source_config = json_decode($module->data_source_config ?? '{}', true);
            if (!is_array($source_config)) {
                $this->get_logger()->error('Invalid data source config in module', ['job_id' => $job_id, 'module_id' => $module_id]);
                return $this->fail_job($job_id, 'Invalid data source config in module');
            }

            // Fetch input data using the correct method signature
            $input_data_packet = $input_handler->get_input_data($module, $source_config, $user_id);
            if (is_wp_error($input_data_packet)) {
                $error_message = 'Input handler failed: ' . $input_data_packet->get_error_message();
                $this->get_logger()->error('Input handler failed to fetch data', ['job_id' => $job_id, 'handler_type' => $data_source_type]);
                return $this->fail_job($job_id, $error_message);
            }

            if (empty($input_data_packet)) {
                $this->get_logger()->error('Input handler returned empty data', ['job_id' => $job_id, 'handler_type' => $data_source_type]);
                return $this->fail_job($job_id, 'Input handler returned empty data');
            }

            // Enhanced logging to track metadata flow
            $metadata = $input_data_packet['metadata'] ?? [];
            $item_identifier = $metadata['item_identifier_to_log'] ?? null;
            $this->get_logger()->info('Orchestrator: Input data received with metadata', [
                'job_id' => $job_id,
                'handler_type' => $data_source_type,
                'metadata_keys' => array_keys($metadata),
                'item_identifier_to_log' => $item_identifier,
                'source_url' => $metadata['source_url'] ?? 'NOT_SET'
            ]);

            // FAIL FAST: If input handler didn't provide critical metadata, fail immediately
            if (empty($item_identifier)) {
                $this->get_logger()->error('Orchestrator: Input handler missing critical item_identifier_to_log - failing job', [
                    'job_id' => $job_id,
                    'handler_type' => $data_source_type,
                    'metadata_provided' => $metadata
                ]);
                return $this->fail_job($job_id, 'Input handler failed to provide item_identifier_to_log for processed items tracking');
            }

            // Store input data
            $json_data = wp_json_encode($input_data_packet);
            if ($json_data === false) {
                $this->get_logger()->error('Failed to JSON encode input data', ['job_id' => $job_id, 'json_error' => json_last_error_msg()]);
                return $this->fail_job($job_id, 'Failed to JSON encode input data: ' . json_last_error_msg());
            }
            
            $this->get_logger()->info('JSON encoded input data successfully', ['job_id' => $job_id, 'data_size' => strlen($json_data)]);
            
            $success = $this->store_step_data_by_name($job_id, 'input', $json_data);
            if (!$success) {
                $this->get_logger()->error('Failed to store input data', ['job_id' => $job_id]);
                return $this->fail_job($job_id, 'Failed to store input data in database');
            }

            return true;

        } catch (\Exception $e) {
            $this->get_logger()->error('Exception in input step', ['job_id' => $job_id, 'error' => $e->getMessage()]);
            return $this->fail_job($job_id, 'Input step failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get prompt field definitions for the input step.
     * 
     * Input step doesn't require AI prompts as it only collects data from sources.
     *
     * @return array Empty array as no prompts are needed.
     */
    public static function get_prompt_fields(): array {
        return [];
    }
    
}