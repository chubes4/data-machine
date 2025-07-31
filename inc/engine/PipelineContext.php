<?php
/**
 * Pipeline Context Service - Pure Filter-Based Architecture
 * 
 * Provides pipeline flow context including step sequences, positions, and pipeline management functionality.
 * This service centralizes all pipeline context operations for steps and AI processing.
 * 
 * CORE RESPONSIBILITIES:
 * - Step sequence management and retrieval
 * - Pipeline position tracking and calculation
 * - Current step name and position resolution
 * - Pipeline flow context for steps and AI processing
 * 
 * FILTER-BASED ARCHITECTURE:
 * - Accessed via apply_filters('dm_get_pipeline_context', null)
 * - Parameter-less constructor with filter-based service dependencies
 * - External plugins can override via filter priority
 * - Complete independence from constructor dependencies
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine
 * @since      NEXT_VERSION
 */

namespace DataMachine\Engine;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class PipelineContext {
    
    /**
     * Get step sequence for the current job.
     *
     * @param int $job_id The job ID.
     * @return array Array of step names in execution order.
     */
    public function get_job_step_sequence(int $job_id): array {
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
        if (!$db_jobs) {
            return [];
        }
        
        return $db_jobs->get_job_step_sequence($job_id);
    }
    
    /**
     * Get current step name for the job.
     *
     * @param int $job_id The job ID.
     * @return string|null Current step name or null if not found.
     */
    public function get_current_step_name(int $job_id): ?string {
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
        if (!$db_jobs) {
            return null;
        }
        
        return $db_jobs->get_current_step_name($job_id);
    }
    
    /**
     * Get current step position in the sequence (0-based index).
     *
     * @param int $job_id The job ID.
     * @return int|null Current step position or null if not found.
     */
    public function get_current_step_position(int $job_id): ?int {
        $current_step = $this->get_current_step_name($job_id);
        $sequence = $this->get_job_step_sequence($job_id);
        
        if (empty($current_step) || empty($sequence)) {
            return null;
        }
        
        $position = array_search($current_step, $sequence);
        return $position !== false ? $position : null;
    }
    
    /**
     * Get step name by position in the sequence.
     *
     * @param int $job_id The job ID.
     * @param int $position The position in the sequence (0-based).
     * @return string|null Step name at position or null if not found.
     */
    public function get_step_name_by_position(int $job_id, int $position): ?string {
        $sequence = $this->get_job_step_sequence($job_id);
        
        if (empty($sequence) || $position < 0 || $position >= count($sequence)) {
            return null;
        }
        
        return $sequence[$position] ?? null;
    }
    
    /**
     * Get previous step name relative to current position.
     *
     * @param int $job_id The job ID.
     * @return string|null Previous step name or null if at beginning or not found.
     */
    public function get_previous_step_name(int $job_id): ?string {
        $current_position = $this->get_current_step_position($job_id);
        
        if ($current_position === null || $current_position === 0) {
            return null; // No previous step
        }
        
        return $this->get_step_name_by_position($job_id, $current_position - 1);
    }
    
    /**
     * Get next step name relative to current position.
     *
     * @param int $job_id The job ID.
     * @return string|null Next step name or null if at end or not found.
     */
    public function get_next_step_name(int $job_id): ?string {
        $current_position = $this->get_current_step_position($job_id);
        $sequence = $this->get_job_step_sequence($job_id);
        
        if ($current_position === null || $current_position >= count($sequence) - 1) {
            return null; // No next step
        }
        
        return $this->get_step_name_by_position($job_id, $current_position + 1);
    }
    
    /**
     * Check if current step is the first step in the pipeline.
     *
     * @param int $job_id The job ID.
     * @return bool True if first step, false otherwise.
     */
    public function is_first_step(int $job_id): bool {
        $position = $this->get_current_step_position($job_id);
        return $position === 0;
    }
    
    /**
     * Check if current step is the last step in the pipeline.
     *
     * @param int $job_id The job ID.
     * @return bool True if last step, false otherwise.
     */
    public function is_last_step(int $job_id): bool {
        $position = $this->get_current_step_position($job_id);
        $sequence = $this->get_job_step_sequence($job_id);
        
        if ($position === null || empty($sequence)) {
            return false;
        }
        
        return $position === count($sequence) - 1;
    }
    
    /**
     * Get total number of steps in the pipeline.
     *
     * @param int $job_id The job ID.
     * @return int Total step count.
     */
    public function get_total_step_count(int $job_id): int {
        $sequence = $this->get_job_step_sequence($job_id);
        return count($sequence);
    }
    
    /**
     * Get pipeline progress as percentage (0-100).
     *
     * @param int $job_id The job ID.
     * @return float Pipeline progress percentage.
     */
    public function get_pipeline_progress(int $job_id): float {
        $position = $this->get_current_step_position($job_id);
        $total = $this->get_total_step_count($job_id);
        
        if ($total === 0 || $position === null) {
            return 0.0;
        }
        
        // Progress is based on completed steps, so add 1 to position for completed count
        return round(($position + 1) / $total * 100, 2);
    }
    
    /**
     * Get all previous step names from current position.
     *
     * @param int $job_id The job ID.
     * @return array Array of previous step names in execution order.
     */
    public function get_all_previous_step_names(int $job_id): array {
        $current_position = $this->get_current_step_position($job_id);
        $sequence = $this->get_job_step_sequence($job_id);
        
        if ($current_position === null || $current_position === 0 || empty($sequence)) {
            return []; // No previous steps
        }
        
        return array_slice($sequence, 0, $current_position);
    }
    
    /**
     * Get all remaining step names from current position.
     *
     * @param int $job_id The job ID.
     * @return array Array of remaining step names in execution order.
     */
    public function get_all_remaining_step_names(int $job_id): array {
        $current_position = $this->get_current_step_position($job_id);
        $sequence = $this->get_job_step_sequence($job_id);
        
        if ($current_position === null || empty($sequence)) {
            return [];
        }
        
        // Get steps after current position (excluding current)
        return array_slice($sequence, $current_position + 1);
    }
    
    /**
     * Get pipeline context summary for debugging and logging.
     *
     * @param int $job_id The job ID.
     * @return array Pipeline context summary.
     */
    public function get_pipeline_context_summary(int $job_id): array {
        $current_step = $this->get_current_step_name($job_id);
        $current_position = $this->get_current_step_position($job_id);
        $sequence = $this->get_job_step_sequence($job_id);
        $total_steps = $this->get_total_step_count($job_id);
        $progress = $this->get_pipeline_progress($job_id);
        
        return [
            'job_id' => $job_id,
            'current_step' => $current_step,
            'current_position' => $current_position,
            'total_steps' => $total_steps,
            'progress_percent' => $progress,
            'is_first_step' => $this->is_first_step($job_id),
            'is_last_step' => $this->is_last_step($job_id),
            'previous_step' => $this->get_previous_step_name($job_id),
            'next_step' => $this->get_next_step_name($job_id),
            'sequence' => $sequence,
            'context' => 'pipeline_context_service'
        ];
    }
}