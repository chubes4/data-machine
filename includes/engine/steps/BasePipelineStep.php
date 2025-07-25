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
     * Get step data for a specific step number.
     *
     * @param int $job_id The job ID.
     * @param int $step The step number (1-5).
     * @return string|null The step data as JSON string or null if not found.
     */
    protected function get_step_data(int $job_id, int $step) {
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
        return $db_jobs->get_step_data($job_id, $step);
    }
    
    /**
     * Store step data for a specific step number.
     *
     * @param int $job_id The job ID.
     * @param int $step The step number (1-5).
     * @param string $data The data to store as JSON string.
     * @return bool True on success, false on failure.
     */
    protected function store_step_data(int $job_id, int $step, string $data): bool {
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
        return $db_jobs->update_step_data($job_id, $step, $data);
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
}