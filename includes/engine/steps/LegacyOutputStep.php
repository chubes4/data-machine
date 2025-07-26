<?php
/**
 * Output Pipeline Step
 * 
 * Handles the fifth and final step of the processing pipeline: multi-platform output publishing.
 * This step takes the finalized content and publishes it to the configured output destinations
 * using the appropriate output handlers.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine/steps
 * @since      NEXT_VERSION
 */

namespace DataMachine\Engine\Steps;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class OutputStep extends BasePipelineStep {
    
    /**
     * Execute the output publishing step.
     *
     * @param int $job_id The job ID to process.
     * @return bool True on success, false on failure.
     */
    public function execute(int $job_id): bool {
        try {
            $logger = $this->get_logger();
            
            // Debug: Log exactly what we receive
            $logger?->info('Output handler called', [
                'job_id_received' => $job_id,
                'job_id_type' => gettype($job_id)
            ]);
            
            if (empty($job_id)) {
                $logger?->error('Output Job Handler: No job ID provided');
                return false;
            }
            
            // Get job and finalized data from database
            $job = $this->get_job_data($job_id);
            if (!$job) {
                $logger?->error('Output Job Handler: Job not found', ['job_id' => $job_id]);
                return false;
            }
            
            $finalized_data_json = $this->get_step_data_by_name($job_id, 'finalize');
            if (empty($finalized_data_json)) {
                $logger?->error('Output Job Handler: No finalized data available', ['job_id' => $job_id]);
                return $this->fail_job($job_id, 'Output job failed: No finalized data available');
            }
            
            $finalized_data = json_decode($finalized_data_json, true);
            $final_output = $finalized_data['final_output_string'] ?? '';
            
            if (empty($final_output)) {
                $logger?->error('Output Job Handler: Empty final output', ['job_id' => $job_id]);
                return $this->fail_job($job_id, 'Output job failed: Empty final output');
            }
            
            // Get input metadata from database
            $input_data_json = $this->get_step_data_by_name($job_id, 'input');
            $input_data_packet = json_decode($input_data_json, true);
            $input_metadata = $input_data_packet['metadata'] ?? [];
            
            // Debug metadata flow issue
            $logger?->info('Output handler metadata debug', [
                'job_id' => $job_id,
                'input_data_json_length' => strlen($input_data_json ?: ''),
                'input_data_packet_keys' => is_array($input_data_packet) ? array_keys($input_data_packet) : 'not_array',
                'metadata_keys' => is_array($input_metadata) ? array_keys($input_metadata) : 'not_array',
                'item_identifier_raw' => $input_metadata['item_identifier_to_log'] ?? 'NOT_FOUND'
            ]);
            
            // Get module config and user from job
            $module_config = json_decode($job->module_config, true);
            $user_id = $job->user_id;
            
            return $this->execute_output_logic($job_id, $final_output, $module_config, $user_id, $input_metadata);
            
        } catch (\Exception $e) {
            $this->get_logger()->error('Exception in output step', ['job_id' => $job_id, 'error' => $e->getMessage()]);
            return $this->fail_job($job_id, 'Output step failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute the core output logic.
     *
     * @param int $job_id The job ID.
     * @param string $final_output The final output content.
     * @param array $module_config The module configuration.
     * @param int $user_id The user ID.
     * @param array $input_metadata The input metadata.
     * @return bool True on success, false on failure.
     */
    private function execute_output_logic(int $job_id, string $final_output, array $module_config, int $user_id, array $input_metadata): bool {
        $logger = $this->get_logger();
        
        // CRITICAL FIX: Check if item has already been processed to prevent duplicate publishing
        $processed_items_manager = $this->get_processed_items_manager();
        if ($processed_items_manager) {
            $module_id = $module_config['module_id'] ?? 0;
            $source_type = $module_config['data_source_type'] ?? '';
            $item_identifier = $input_metadata['item_identifier_to_log'] ?? null;

            // FAIL FAST: If we can't track processed items, fail the job immediately
            if (!$module_id || !$source_type || !$item_identifier) {
                $logger?->error('Output Job Handler: Missing critical metadata for processed items tracking - failing job', [
                    'job_id' => $job_id,
                    'module_id' => $module_id,
                    'source_type' => $source_type,
                    'item_identifier' => $item_identifier,
                    'input_metadata_keys' => array_keys($input_metadata)
                ]);

                $error_message = 'Missing critical metadata: ' . 
                    (!$module_id ? 'module_id ' : '') . 
                    (!$source_type ? 'source_type ' : '') . 
                    (!$item_identifier ? 'item_identifier ' : '');
                return $this->fail_job($job_id, $error_message);
            }

            if ($processed_items_manager->is_item_processed($module_id, $source_type, $item_identifier)) {
                $logger?->info('Output Job Handler: Item already processed, skipping to prevent duplicate', [
                    'job_id' => $job_id,
                    'module_id' => $module_id,
                    'item_identifier' => $item_identifier
                ]);

                // Mark job as complete since item was already processed
                $job_status_manager = $this->get_job_status_manager();
                if ($job_status_manager) {
                    $result_data = ['status' => 'skipped', 'message' => 'Item already processed'];
                    $job_status_manager->complete($job_id, 'completed', $result_data, 'Item already processed');
                }
                return true;
            }
        }

        $handler_factory = $this->get_handler_factory();
        $output_type = $module_config['output_type'] ?? null;

        if (empty($output_type)) {
            $logger?->error('Output Job Handler: Output type not defined', [
                'job_id' => $job_id,
                'module_id' => $module_config['module_id'] ?? 'unknown'
            ]);
            return $this->fail_job($job_id, 'Output type not defined');
        }

        try {
            // Create the output handler
            $output_handler = $handler_factory->create_handler('output', $output_type);

            if (is_wp_error($output_handler)) {
                throw new \Exception('Could not create valid output handler: ' . $output_handler->get_error_message());
            }

            // Execute the output handler
            $logger?->info('Output Job Handler: Processing output via pipeline step', [
                'job_id' => $job_id,
                'module_id' => $module_config['module_id'] ?? 'unknown',
                'output_type' => $output_type
            ]);

            $result = $output_handler->handle($final_output, $module_config, $user_id, $input_metadata);

            // Check for errors
            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message());
            }

            // Mark main job complete after successful output processing
            $job_status_manager = $this->get_job_status_manager();
            if ($job_status_manager && $job_id) {
                $success_message = $result['message'] ?? 'Job completed: Output processed successfully';
                $job_completed = $job_status_manager->complete($job_id, 'completed', $result, $success_message);
                
                if ($job_completed) {
                    // Create handler-specific log data
                    $log_data = [
                        'job_id' => $job_id,
                        'module_id' => $module_config['module_id'] ?? 'unknown',
                        'output_type' => $output_type,
                        'status' => $result['status'] ?? 'unknown'
                    ];
                    
                    // Add relevant ID field based on what the handler returned
                    if (isset($result['local_post_id'])) {
                        $log_data['local_post_id'] = $result['local_post_id'];
                    } elseif (isset($result['remote_post_id'])) {
                        $log_data['remote_post_id'] = $result['remote_post_id'];
                    } elseif (isset($result['tweet_id'])) {
                        $log_data['tweet_id'] = $result['tweet_id'];
                    } elseif (isset($result['post_id'])) {
                        $log_data['post_id'] = $result['post_id'];
                    }
                    
                    $logger?->info('Output Job Handler: Job completed successfully', $log_data);
                    
                    // Schedule cleanup of large data fields after successful completion
                    $db_jobs = $this->get_db_jobs();
                    if ($db_jobs) {
                        $db_jobs->schedule_cleanup($job_id);
                        $logger?->debug('Scheduled data cleanup for completed job', ['job_id' => $job_id]);
                    }
                } else {
                    $logger?->error('Output Job Handler: Failed to mark main job as completed', [
                        'job_id' => $job_id,
                        'module_id' => $module_config['module_id'] ?? 'unknown'
                    ]);
                }
            }

            // Mark item as processed AFTER successful output - this prevents duplicates
            if ($processed_items_manager) {
                $module_id = $module_config['module_id'] ?? 0;
                $source_type = $module_config['data_source_type'] ?? '';
                $item_identifier = $input_metadata['item_identifier_to_log'] ?? null;
                
                $marked_success = $processed_items_manager->mark_item_processed($module_id, $source_type, $item_identifier, $job_id);
                
                if (!$marked_success) {
                    $logger?->warning('Output Job Handler: Failed to mark item as processed - may cause duplicates', [
                        'job_id' => $job_id,
                        'module_id' => $module_id,
                        'source_type' => $source_type,
                        'item_identifier' => $item_identifier
                    ]);
                }
            }

            $logger?->info('Output Job Handler: Output processed successfully', [
                'job_id' => $job_id,
                'module_id' => $module_config['module_id'] ?? 'unknown',
                'output_type' => $output_type
            ]);

            return true;

        } catch (\Exception $e) {
            $logger?->error('Output Job Handler: Failed - no retries', [
                'job_id' => $job_id,
                'module_id' => $module_config['module_id'] ?? 'unknown',
                'output_type' => $output_type ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return $this->fail_job($job_id, 'Output job failed: ' . $e->getMessage());
            
        } catch (\Throwable $t) {
            // CRITICAL: Catch ALL failures including fatal errors, type errors, etc.
            $logger?->error('Output Job Handler: Critical failure caught', [
                'job_id' => $job_id,
                'module_id' => $module_config['module_id'] ?? 'unknown',
                'error_type' => get_class($t),
                'error' => $t->getMessage(),
                'file' => $t->getFile(),
                'line' => $t->getLine()
            ]);
            
            return $this->fail_job($job_id, 'Critical output failure: ' . $t->getMessage());
        }
    }
    
    /**
     * Get prompt field definitions for the output step.
     * 
     * Output step doesn't require AI prompts as it only publishes processed content.
     *
     * @return array Empty array as no prompts are needed.
     */
    public static function get_prompt_fields(): array {
        return [];
    }
    
}