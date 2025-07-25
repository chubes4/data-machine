<?php
/**
 * Action Scheduler Interface
 *
 * Defines the contract for background job scheduling within the Data Machine plugin.
 * Enables dependency inversion and makes scheduling services mockable for testing.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/Contracts
 * @since      0.6.1
 */

namespace DataMachine\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

interface ActionSchedulerInterface {

    /**
     * Schedule a single background job.
     *
     * @param string $hook The hook name to execute.
     * @param array $args Arguments to pass to the hook.
     * @param int $timestamp When to run the job (Unix timestamp).
     * @return int|false The action ID on success, false on failure.
     */
    public function schedule_single_job(string $hook, array $args = [], int $timestamp = 0): int|false;

    /**
     * Schedule a recurring background job.
     *
     * @param string $hook The hook name to execute.
     * @param array $args Arguments to pass to the hook.
     * @param int $timestamp When to start the recurring job.
     * @param int $interval_in_seconds How often to repeat the job.
     * @return int|false The action ID on success, false on failure.
     */
    public function schedule_recurring_job(string $hook, array $args = [], int $timestamp = 0, int $interval_in_seconds = 3600): int|false;

    /**
     * Cancel a scheduled job.
     *
     * @param string $hook The hook name.
     * @param array $args The job arguments (for specificity).
     * @return int Number of jobs cancelled.
     */
    public function cancel_job(string $hook, array $args = []): int;

    /**
     * Check if a job is already scheduled.
     *
     * @param string $hook The hook name.
     * @param array $args The job arguments.
     * @return bool True if scheduled, false otherwise.
     */
    public function is_job_scheduled(string $hook, array $args = []): bool;
}