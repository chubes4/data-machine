<?php
/**
 * Base Pipeline Step Class
 * 
 * Provides common functionality and helper methods for all pipeline steps.
 * This base class handles the standard patterns like job data retrieval,
 * database operations, and service access through the global container.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine/steps
 * @since      NEXT_VERSION
 */

namespace DataMachine\Engine\Steps;

use DataMachine\Engine\Interfaces\PipelineStepInterface;
use DataMachine\Database\Jobs;
use DataMachine\Contracts\LoggerInterface;
use DataMachine\Helpers\{ActionScheduler, Logger};
use DataMachine\Engine\JobStatusManager;
use AI_HTTP_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

abstract class BasePipelineStep implements PipelineStepInterface {
    
    /**
     * Get job data from the database.
     *
     * @param int $job_id The job ID.
     * @return object|null The job object or null if not found.
     */
    protected function get_job_data(int $job_id) {
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
        return $db_jobs->get_job($job_id);
    }
    
    /**
     * Store data packet for current step (simplified closed-door approach).
     *
     * @param int $job_id The job ID.
     * @param \DataMachine\DataPacket $data_packet The data packet to store.
     * @return bool True on success, false on failure.
     */
    protected function store_step_data_packet(int $job_id, \DataMachine\DataPacket $data_packet): bool {
        $current_step = $this->get_current_step_name($job_id);
        if (!$current_step) {
            return false;
        }
        
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
        $json_data = $data_packet->toJson();
        
        return $db_jobs->update_step_data_by_name($job_id, $current_step, $json_data);
    }
    
    /**
     * Get data packet from previous step (simplified sequential flow).
     *
     * @param int $job_id The job ID.
     * @return \DataMachine\DataPacket|null The previous step's data packet or null if not found.
     */
    protected function get_previous_step_data_packet(int $job_id): ?\DataMachine\DataPacket {
        $current_position = $this->get_current_step_position($job_id);
        if ($current_position === null || $current_position === 0) {
            return null; // No previous step
        }
        
        $sequence = $this->get_job_step_sequence($job_id);
        $previous_step_name = $sequence[$current_position - 1] ?? null;
        
        if (!$previous_step_name) {
            return null;
        }
        
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
        $json_data = $db_jobs->get_step_data_by_name($job_id, $previous_step_name);
        
        if (!$json_data) {
            return null;
        }
        
        try {
            return \DataMachine\DataPacket::fromJson($json_data);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get all previous step data packets for fluid context aggregation.
     *
     * @param int $job_id The job ID.
     * @return array Array of DataPacket objects from all previous steps.
     */
    protected function get_all_previous_data_packets(int $job_id): array {
        $current_position = $this->get_current_step_position($job_id);
        if ($current_position === null || $current_position === 0) {
            return []; // No previous steps
        }
        
        $sequence = $this->get_job_step_sequence($job_id);
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
        $data_packets = [];
        
        // Get data packets from all previous steps
        for ($i = 0; $i < $current_position; $i++) {
            $step_name = $sequence[$i] ?? null;
            if (!$step_name) {
                continue;
            }
            
            $json_data = $db_jobs->get_step_data_by_name($job_id, $step_name);
            if (!$json_data) {
                continue;
            }
            
            try {
                $packet = \DataMachine\DataPacket::fromJson($json_data);
                if ($packet) {
                    $data_packets[] = $packet;
                }
            } catch (\Exception $e) {
                // Skip malformed packets
                continue;
            }
        }
        
        return $data_packets;
    }
    
    
    /**
     * Get step sequence for the current job.
     *
     * @param int $job_id The job ID.
     * @return array Array of step names in execution order.
     */
    protected function get_job_step_sequence(int $job_id): array {
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
        return $db_jobs->get_job_step_sequence($job_id);
    }
    
    /**
     * Get current step name for the job.
     *
     * @param int $job_id The job ID.
     * @return string|null Current step name or null if not found.
     */
    protected function get_current_step_name(int $job_id): ?string {
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
        return $db_jobs->get_current_step_name($job_id);
    }
    
    /**
     * Get current step position in the sequence (0-based index).
     *
     * @param int $job_id The job ID.
     * @return int|null Current step position or null if not found.
     */
    protected function get_current_step_position(int $job_id): ?int {
        $current_step = $this->get_current_step_name($job_id);
        $sequence = $this->get_job_step_sequence($job_id);
        
        if (empty($current_step) || empty($sequence)) {
            return null;
        }
        
        $position = array_search($current_step, $sequence);
        return $position !== false ? $position : null;
    }
    
    
    /**
     * Fail a job with an error message.
     *
     * @param int $job_id The job ID.
     * @param string $message The error message.
     * @return bool Always returns false for easy return usage.
     */
    protected function fail_job(int $job_id, string $message): bool {
        $job_status_manager = apply_filters('dm_get_service', null, 'job_status_manager');
        $logger = apply_filters('dm_get_service', null, 'logger');
        $job_status_manager->fail($job_id, $message);
        $logger->error($message, ['job_id' => $job_id]);
        return false;
    }
    
    /**
     * Get the logger service.
     *
     * @return LoggerInterface The logger service.
     */
    protected function get_logger() {
        return apply_filters('dm_get_service', null, 'logger');
    }
    
    /**
     * Get the AI HTTP client service.
     *
     * @return AI_HTTP_Client The AI HTTP client service.
     */
    protected function get_ai_client() {
        return apply_filters('dm_get_service', null, 'ai_http_client');
    }
    
    /**
     * Get the prompt builder service.
     *
     * @return object The prompt builder service.
     */
    protected function get_prompt_builder() {
        return apply_filters('dm_get_service', null, 'prompt_builder');
    }
    
    /**
     * Get the job status manager service.
     *
     * @return JobStatusManager The job status manager service.
     */
    protected function get_job_status_manager() {
        return apply_filters('dm_get_service', null, 'job_status_manager');
    }
    
    /**
     * Get the handler factory service.
     *
     * @return object The handler factory service.
     */
    protected function get_handler_factory() {
        return apply_filters('dm_get_service', null, 'handler_factory');
    }

    /**
     * Get the modules database service.
     *
     * @return object The modules database service.
     */
    protected function get_db_modules() {
        return apply_filters('dm_get_service', null, 'db_modules');
    }

    /**
     * Get the processed items manager service.
     *
     * @return object The processed items manager service.
     */
    protected function get_processed_items_manager() {
        return apply_filters('dm_get_service', null, 'processed_items_manager');
    }

    /**
     * Get the database jobs service.
     *
     * @return object The database jobs service.
     */
    protected function get_db_jobs() {
        return apply_filters('dm_get_service', null, 'db_jobs');
    }

    /**
     * Get the project prompts service.
     *
     * @return object The project prompts service.
     */
    protected function get_project_prompts_service() {
        return apply_filters('dm_get_service', null, 'project_prompts_service');
    }

    /**
     * Get a specific prompt for a pipeline step from the project configuration.
     *
     * @param int    $job_id      The job ID (used to find the associated project).
     * @param string $step_name   The name of the pipeline step (e.g., 'ai', 'output').
     * @param string $prompt_field The name of the prompt field (e.g., 'prompt').
     * @return string The prompt content, or empty string if not found.
     */
    protected function get_project_prompt(int $job_id, string $step_name, string $prompt_field): string {
        // Get job data to find the project_id
        $job_data = $this->get_job_data($job_id);
        if (!$job_data) {
            return '';
        }

        // Get module data to find the project_id
        $db_modules = apply_filters('dm_get_service', null, 'db_modules');
        $module = $db_modules->get_module($job_data->module_id);
        if (!$module) {
            return '';
        }

        // Get project prompts
        $project_prompts_service = $this->get_project_prompts_service();
        return $project_prompts_service->get_step_prompt($module->project_id, $step_name, $prompt_field);
    }
}