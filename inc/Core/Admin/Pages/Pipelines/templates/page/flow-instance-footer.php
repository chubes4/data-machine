<?php
/**
 * Flow Instance Footer Template
 *
 * Displays last run and next run times for a flow instance.
 * This template is used by both the main flow card and AJAX refresh endpoint.
 *
 * @package    Data_Machine
 * @subpackage Core\Admin\Pages\Pipelines
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Ensure we have the required flow data
$flow_id = $flow_id ?? null;
$scheduling_config = $scheduling_config ?? [];

if (!$flow_id) {
    return;
}

// Calculate last run time
$last_run = null;
if (!empty($scheduling_config['last_run_at'])) {
    $last_run = $scheduling_config['last_run_at'];
} else {
    // Fallback: check recent jobs for this flow
    $all_databases = apply_filters('dm_db', []);
    $jobs_db = $all_databases['jobs'] ?? null;
    if ($jobs_db) {
        $jobs = $jobs_db->get_jobs_for_flow($flow_id);
        $last_run = !empty($jobs) ? ($jobs[0]['completed_at'] ?? $jobs[0]['created_at']) : null;
    }
}

// Calculate next run time
$next_run = null;
if (function_exists('as_next_scheduled_action')) {
    $next_action = as_next_scheduled_action('dm_run_flow_now', [absint($flow_id)], 'data-machine');
    if ($next_action) {
        $next_run = wp_date('Y-m-d H:i:s', $next_action);
    }
}
?>

<div class="dm-flow-meta">
    <small>
        <?php
        /* translators: %1$s: Last run date/time or 'Never', %2$s: Next run date/time or 'Manual' */
        echo esc_html(sprintf(__('Last: %1$s | Next: %2$s', 'data-machine'),
            $last_run ? wp_date('M j, Y g:i a', strtotime($last_run)) : __('Never', 'data-machine'),
            $next_run ? wp_date('M j, Y g:i a', strtotime($next_run)) : __('Manual', 'data-machine')
        )); ?>
    </small>
</div>