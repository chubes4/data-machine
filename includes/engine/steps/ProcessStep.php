<?php
/**
 * Process Pipeline Step
 * 
 * Handles the second step of the processing pipeline: AI-powered data processing.
 * This step takes the input data and processes it using the AI HTTP Client library
 * with the configured processing prompts.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine/steps
 * @since      NEXT_VERSION
 */

namespace DataMachine\Engine\Steps;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ProcessStep extends BasePipelineStep {
    
    /**
     * Execute the AI processing step.
     *
     * @param int $job_id The job ID to process.
     * @return bool True on success, false on failure.
     */
    public function execute(int $job_id): bool {
        try {
            $job = $this->get_job_data($job_id);
            if (!$job) {
                return $this->fail_job($job_id, 'Job not found in database for process step');
            }

            $input_data_json = $this->get_step_data($job_id, 1);
            if (empty($input_data_json)) {
                return $this->fail_job($job_id, 'No input data available for process step');
            }

            $input_data_packet = json_decode($input_data_json, true);
            if (empty($input_data_packet)) {
                return $this->fail_job($job_id, 'Failed to parse input data for process step');
            }

            $module_job_config = json_decode($job->module_config, true);
            
            return $this->execute_process_logic($job_id, $input_data_packet, $module_job_config);
            
        } catch (\Exception $e) {
            $this->get_logger()->error('Exception in process step', ['job_id' => $job_id, 'error' => $e->getMessage()]);
            return $this->fail_job($job_id, 'Process step failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute the core processing logic.
     *
     * @param int $job_id The job ID.
     * @param array $input_data_packet The input data packet.
     * @param array $module_job_config The module job configuration.
     * @return bool True on success, false on failure.
     */
    private function execute_process_logic(int $job_id, array $input_data_packet, array $module_job_config): bool {
        $project_id = absint($module_job_config['project_id'] ?? 0);
        $prompt_builder = $this->get_prompt_builder();
        $system_prompt = $prompt_builder->build_system_prompt($project_id, $module_job_config['user_id'] ?? 0);
        $process_data_prompt = $module_job_config['process_data_prompt'] ?? '';

        try {
            $enhanced_process_prompt = $prompt_builder->build_process_data_prompt($process_data_prompt, $input_data_packet);
            
            // Use AI client directly instead of ProcessData handler - this is the refactor goal
            $ai_client = $this->get_ai_client();
            if (!$ai_client) {
                return $this->fail_job($job_id, 'AI HTTP Client not available');
            }
            
            // Extract content and build messages like ProcessData does
            $image_urls = $this->extract_image_urls($input_data_packet);
            $content_text = $this->extract_content_text($input_data_packet);
            $user_message = $enhanced_process_prompt . $content_text;

            $messages = [
                ['role' => 'system', 'content' => $system_prompt],
                [
                    'role' => 'user',
                    'content' => $user_message,
                    'image_urls' => $image_urls
                ]
            ];

            $process_result = $ai_client->send_step_request('process', [
                'messages' => $messages
            ]);

            if (!$process_result['success']) {
                $this->get_logger()->error('Process step failed', [
                    'job_id' => $job_id,
                    'step' => 'process',
                    'error' => $process_result['error'] ?? 'Unknown error',
                    'provider' => $process_result['provider'] ?? 'unknown',
                    'raw_response' => $process_result['raw_response'] ?? null
                ]);
                return false;
            }

            $initial_output = $process_result['data']['content'] ?? '';
            if (empty($initial_output)) {
                $this->get_logger()->error('Process step returned empty content', ['job_id' => $job_id]);
                return false;
            }

            // Store result in database
            $success = $this->store_step_data($job_id, 2, wp_json_encode(['processed_output' => $initial_output]));
            if (!$success) {
                $this->get_logger()->error('Failed to store processed data', ['job_id' => $job_id]);
                return false;
            }
            
            return true;

        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Extract image URLs from input data packet for multimodal processing.
     * Copied from ProcessData class.
     *
     * @param array $input_data_packet Input data packet.
     * @return array Array of image URLs.
     */
    private function extract_image_urls(array $input_data_packet): array {
        $file_info = $input_data_packet['file_info'] ?? ($input_data_packet['data']['file_info'] ?? null);
        
        if (empty($file_info)) {
            return [];
        }
        
        // Handle image URLs
        if (!empty($file_info['url']) && $this->is_image_file($file_info['url'])) {
            return [$file_info['url']];
        }
        
        // Handle local image files (convert to data URLs)
        if (!empty($file_info['persistent_path']) && $this->is_image_file($file_info['persistent_path'])) {
            $data_url = $this->convert_to_data_url($file_info['persistent_path']);
            if ($data_url) {
                return [$data_url];
            }
        }
        
        return [];
    }
    
    /**
     * Extract content text from input data packet.
     * Copied from ProcessData class.
     *
     * @param array $input_data_packet Input data packet.
     * @return string Formatted content text.
     */
    private function extract_content_text(array $input_data_packet): string {
        $content_string = $input_data_packet['content_string'] ?? ($input_data_packet['data']['content_string'] ?? '');
        
        if (empty($content_string)) {
            return '';
        }
        
        return "\n\nContent to Process:\n" . $content_string;
    }
    
    /**
     * Check if a file path represents an image file.
     * Copied from ProcessData class.
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
     * Convert local image file to data URL.
     * Copied from ProcessData class.
     *
     * @param string $file_path Local file path.
     * @return string|false Data URL or false on failure.
     */
    private function convert_to_data_url(string $file_path) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return false;
        }

        $mime_type = mime_content_type($file_path);
        if (!$mime_type || !str_starts_with($mime_type, 'image/')) {
            return false;
        }

        $file_data = file_get_contents($file_path);
        if ($file_data === false) {
            return false;
        }

        return 'data:' . $mime_type . ';base64,' . base64_encode($file_data);
    }
    
}