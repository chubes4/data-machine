<?php
/**
 * Jobs Admin Page Template
 *
 * Template for the main jobs administration page.
 *
 * @package DataMachine\Core\Admin\Pages\Jobs
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Get jobs data using filter-based discovery
$all_databases = apply_filters('dm_db', []);
$db_jobs = $all_databases['jobs'] ?? null;

$recent_jobs = [];
if ($db_jobs) {
    $args = [
        'orderby' => 'j.job_id',
        'order' => 'DESC',
        'per_page' => 50,
        'offset' => 0
    ];
    $recent_jobs = $db_jobs->get_jobs_for_list_table($args);
}

/**
 * Render individual job table row.
 *
 * @param array $job Job data array
 */
function dm_render_job_row($job) {
    // Handle both object and array format
    $job_id = is_array($job) ? $job['job_id'] : $job->job_id;
    $pipeline_name = is_array($job) ? ($job['pipeline_name'] ?: 'Unknown Pipeline') : ($job->pipeline_name ?: 'Unknown Pipeline');
    $flow_name = is_array($job) ? ($job['flow_name'] ?? 'Unknown Flow') : ($job->flow_name ?? 'Unknown Flow');
    $status = is_array($job) ? $job['status'] : $job->status;
    $created_at = is_array($job) ? $job['created_at'] : $job->created_at;
    $started_at = is_array($job) ? ($job['started_at'] ?: '') : ($job->started_at ?: '');
    $completed_at = is_array($job) ? ($job['completed_at'] ?: '') : ($job->completed_at ?: '');

    // Format status display
    $status_display = ucfirst(str_replace('_', ' ', $status));
    
    // Format dates
    $created_display = $created_at ? gmdate('M j, Y g:i a', strtotime($created_at)) : '';
    $started_display = $started_at ? gmdate('M j, Y g:i a', strtotime($started_at)) : '';
    $completed_display = $completed_at ? gmdate('M j, Y g:i a', strtotime($completed_at)) : '';
    

    ?>
    <tr>
        <td><strong><?php echo esc_html($job_id); ?></strong></td>
        <td><?php echo esc_html($pipeline_name . ' → ' . $flow_name); ?></td>
        <td><span class="dm-job-status--<?php echo $status === 'failed' ? 'failed' : ($status === 'completed' ? 'completed' : 'other'); ?>"><?php echo esc_html($status_display); ?></span></td>
        <td><?php echo esc_html($created_display); ?></td>
        <td><?php echo esc_html($started_display); ?></td>
        <td><?php echo esc_html($completed_display); ?></td>
    </tr>
    <?php
}
?>

<div class="dm-jobs-page">
    
    <h1><?php esc_html_e('Jobs', 'data-machine'); ?></h1>
    
    <?php if (empty($recent_jobs)): ?>
        <div class="dm-jobs-empty-state">
            <p class="dm-jobs-empty-message">
                <?php esc_html_e('No jobs found. Jobs will appear here when Data Machine processes data.', 'data-machine'); ?>
            </p>
        </div>
    <?php else: ?>
        
        <div class="dm-jobs-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="dm-col-job-id"><?php esc_html_e('Job ID', 'data-machine'); ?></th>
                        <th><?php esc_html_e('Pipeline / Flow', 'data-machine'); ?></th>
                        <th class="dm-col-status"><?php esc_html_e('Status', 'data-machine'); ?></th>
                        <th class="dm-col-created"><?php esc_html_e('Created At', 'data-machine'); ?></th>
                        <th class="dm-col-started"><?php esc_html_e('Started At', 'data-machine'); ?></th>
                        <th class="dm-col-completed"><?php esc_html_e('Completed At', 'data-machine'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_jobs as $job): ?>
                        <?php dm_render_job_row($job); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php endif; ?>
    
</div>