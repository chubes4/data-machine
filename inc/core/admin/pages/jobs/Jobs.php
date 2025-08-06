<?php
/**
 * Jobs Admin Page
 *
 * Job monitoring interface for Data Machine featuring:
 * - Real-time job status display (pending, running, completed, failed)
 * - Job management actions (cancel, retry, delete)
 * - Pagination and filtering capabilities
 * - Integration with Action Scheduler monitoring
 *
 * Follows the plugin's filter-based architecture with self-registration
 * and uses the existing JobStatusManager service.
 *
 * @package DataMachine\Core\Admin\Pages\Jobs
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Jobs;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Jobs admin page implementation.
 *
 * Provides comprehensive job monitoring similar to Action Scheduler's interface
 * with Data Machine specific job status management and pipeline context.
 */
class Jobs
{
    /**
     * Constructor - Admin page registration now handled by JobsFilters.php.
     */
    public function __construct()
    {
        // Admin page registration and asset registration now handled by JobsFilters.php
    }

    /**
     * Render the main jobs page content.
     */
    public function render_content()
    {
        // Get recent jobs data
        $recent_jobs = $this->get_recent_jobs();

        // Use universal template system
        echo apply_filters('dm_render_template', '', 'page/jobs-page', [
            'recent_jobs' => $recent_jobs
        ]);
    }

    /**
     * Get recent jobs from the database.
     *
     * @return array Recent jobs data
     */
    private function get_recent_jobs()
    {
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_jobs = $all_databases['jobs'] ?? null;
        if (!$db_jobs) {
            return [];
        }

        // Get recent jobs using the list table method
        $args = [
            'orderby' => 'j.job_id',
            'order' => 'DESC',
            'per_page' => 50, // Show last 50 jobs
            'offset' => 0
        ];

        return $db_jobs->get_jobs_for_list_table($args);
    }

}

// Auto-instantiate for self-registration
new Jobs();
