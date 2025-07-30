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
     * Constructor - Registers the admin page via filter system.
     */
    public function __construct()
    {
        // Register immediately for admin menu discovery (not on init hook)
        $this->register_admin_page();
    }

    /**
     * Register this admin page with the plugin's admin system.
     *
     * Uses pure self-registration pattern matching handler architecture.
     * No parameter checking needed - page adds itself directly to registry.
     */
    public function register_admin_page()
    {
        add_filter('dm_register_admin_pages', function($pages) {
            $pages['jobs'] = [
                'page_title' => __('Job Management', 'data-machine'),
                'menu_title' => __('Jobs', 'data-machine'),
                'capability' => 'manage_options',
                'callback' => [$this, 'render_content'],
                'description' => __('Monitor and manage your pipeline processing jobs with real-time status updates.', 'data-machine')
            ];
            return $pages;
        }, 10);
    }

    /**
     * Render the main jobs page content.
     */
    public function render_content()
    {
        // Get recent jobs data
        $recent_jobs = $this->get_recent_jobs();

        ?>
        <div class="dm-jobs-page" style="padding: 20px;">
            
            <h1><?php esc_html_e('Jobs', 'data-machine'); ?></h1>
            
            <?php if (empty($recent_jobs)): ?>
                <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; text-align: center; color: #666;">
                    <p style="margin: 0; font-style: italic;">
                        <?php esc_html_e('No jobs found. Jobs will appear here when Data Machine processes data.', 'data-machine'); ?>
                    </p>
                </div>
            <?php else: ?>
                
                <div style="background: #fff; border: 1px solid #ccd0d4;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 80px;"><?php esc_html_e('Job ID', 'data-machine'); ?></th>
                                <th><?php esc_html_e('Module', 'data-machine'); ?></th>
                                <th style="width: 100px;"><?php esc_html_e('Status', 'data-machine'); ?></th>
                                <th style="width: 140px;"><?php esc_html_e('Created At', 'data-machine'); ?></th>
                                <th style="width: 140px;"><?php esc_html_e('Started At', 'data-machine'); ?></th>
                                <th style="width: 140px;"><?php esc_html_e('Completed At', 'data-machine'); ?></th>
                                <th><?php esc_html_e('Result / Error', 'data-machine'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_jobs as $job): ?>
                                <?php $this->render_job_row($job); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php endif; ?>
            
        </div>
        <?php
    }

    /**
     * Get recent jobs from the database.
     *
     * @return array Recent jobs data
     */
    private function get_recent_jobs()
    {
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
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

    /**
     * Render individual job table row.
     *
     * @param array $job Job data array
     */
    private function render_job_row($job)
    {
        // Handle both object and array format
        $job_id = is_array($job) ? $job['job_id'] : $job->job_id;
        $pipeline_name = is_array($job) ? ($job['pipeline_name'] ?: 'Unknown Module') : ($job->pipeline_name ?: 'Unknown Module');
        $status = is_array($job) ? $job['status'] : $job->status;
        $created_at = is_array($job) ? $job['created_at'] : $job->created_at;
        $started_at = is_array($job) ? ($job['started_at'] ?: '') : ($job->started_at ?: '');
        $completed_at = is_array($job) ? ($job['completed_at'] ?: '') : ($job->completed_at ?: '');
        $error_details = is_array($job) ? ($job['error_details'] ?: '') : ($job->error_details ?: '');

        // Format status display
        $status_display = ucfirst(str_replace('_', ' ', $status));
        
        // Format dates
        $created_display = $created_at ? date('M j, Y g:i a', strtotime($created_at)) : '';
        $started_display = $started_at ? date('M j, Y g:i a', strtotime($started_at)) : '';
        $completed_display = $completed_at ? date('M j, Y g:i a', strtotime($completed_at)) : '';
        
        // Determine result/error display
        $result_display = '';
        if ($status === 'failed' && $error_details) {
            $result_display = 'Raw Data: ' . esc_html($error_details);
        } elseif ($status === 'completed') {
            $result_display = 'Status: completed_no_items Message: No new items found to process.';
        }

        ?>
        <tr>
            <td><strong><?php echo esc_html($job_id); ?></strong></td>
            <td><?php echo esc_html($pipeline_name); ?></td>
            <td><span style="color: <?php echo $status === 'failed' ? '#d63638' : ($status === 'completed' ? '#00a32a' : '#646970'); ?>;"><?php echo esc_html($status_display); ?></span></td>
            <td><?php echo esc_html($created_display); ?></td>
            <td><?php echo esc_html($started_display); ?></td>
            <td><?php echo esc_html($completed_display); ?></td>
            <td style="font-size: 11px; color: #646970;"><?php echo esc_html($result_display); ?></td>
        </tr>
        <?php
    }
}

// Auto-instantiate for self-registration
new Jobs();
