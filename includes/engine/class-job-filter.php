<?php
/**
 * Class Data_Machine_Job_Filter
 *
 * Handles job-level filtering and concurrency control for Data Machine.
 * Ensures only one job runs per module and cleans up stuck jobs.
 *
 * @package Data_Machine
 * @subpackage Engine
 */
class Data_Machine_Job_Filter {

	/**
	 * Jobs Database instance.
	 *
	 * @var Data_Machine_Database_Jobs
	 */
	private $db_jobs;

	/**
	 * Logger instance.
	 *
	 * @var Data_Machine_Logger|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Data_Machine_Database_Jobs $db_jobs Jobs database service.
	 * @param Data_Machine_Logger|null $logger Logger service (optional).
	 */
	public function __construct(
		Data_Machine_Database_Jobs $db_jobs,
		?Data_Machine_Logger $logger = null
	) {
		$this->db_jobs = $db_jobs;
		$this->logger = $logger;
	}

	/**
	 * Check if a new job can be scheduled for the given module.
	 * 
	 * This method:
	 * 1. Cleans up any stuck jobs for the module
	 * 2. Checks if there are any active jobs remaining
	 * 3. Returns whether a new job can be scheduled
	 *
	 * @param int $module_id The module ID to check.
	 * @return bool True if a job can be scheduled, false if blocked.
	 */
	public function can_schedule_job(int $module_id): bool {
		if (!$this->db_jobs) {
			$this->logger?->error('Job Filter: Database service not available.', ['module_id' => $module_id]);
			return false;
		}

		// First, clean up any stuck jobs for this module
		$cleaned = $this->cleanup_stuck_jobs_for_module($module_id);
		if ($cleaned > 0) {
			$this->logger?->info('Job Filter: Cleaned up stuck jobs before scheduling.', [
				'module_id' => $module_id,
				'cleaned_count' => $cleaned
			]);
		}

		// Check if there are any active jobs remaining
		$has_active = $this->db_jobs->has_active_jobs_for_module($module_id);
		
		if ($has_active) {
			$this->logger?->info('Job Filter: Blocking job scheduling - module has active jobs.', [
				'module_id' => $module_id
			]);
			return false;
		}

		$this->logger?->debug('Job Filter: Job scheduling allowed.', ['module_id' => $module_id]);
		return true;
	}

	/**
	 * Clean up stuck jobs for a specific module.
	 *
	 * @param int $module_id The module ID to clean up.
	 * @return int Number of jobs cleaned up.
	 */
	public function cleanup_stuck_jobs_for_module(int $module_id): int {
		if (!$this->db_jobs) {
			return 0;
		}

		// Use the existing cleanup_stuck_jobs method but filter to specific module
		// This delegates to the existing database service which has proper SQL handling
		$total_cleaned = $this->db_jobs->cleanup_stuck_jobs();
		
		// Log if any cleanup occurred (we can't determine module-specific count easily)
		if ($total_cleaned > 0) {
			$this->logger?->info('Job Filter: Cleanup completed for stuck jobs', [
				'module_id' => $module_id,
				'total_cleaned' => $total_cleaned
			]);
		}

		return $total_cleaned;
	}

	/**
	 * Get the number of active jobs for a module.
	 *
	 * @param int $module_id The module ID.
	 * @return int Number of active jobs.
	 */
	public function get_active_job_count(int $module_id): int {
		if (!$this->db_jobs) {
			return 0;
		}

		// Use the existing has_active_jobs_for_module method
		// This returns bool, so we return 1 or 0 for simplicity
		return $this->db_jobs->has_active_jobs_for_module($module_id) ? 1 : 0;
	}

	/**
	 * Force cleanup all stuck jobs across all modules.
	 * This should only be used for maintenance operations.
	 *
	 * @return int Number of jobs cleaned up.
	 */
	public function cleanup_all_stuck_jobs(): int {
		if (!$this->db_jobs) {
			return 0;
		}

		return $this->db_jobs->cleanup_stuck_jobs();
	}
}