<?php
/**
 * Jobs Admin Page Template
 *
 * Template for the main jobs administration page.
 *
 * @package DataMachine\Core\Admin\Pages\Jobs
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}
?>

<div class="datamachine-jobs-page">
    
    <div class="datamachine-page-header">
        <h1><?php esc_html_e('Jobs', 'data-machine'); ?></h1>
        <div class="datamachine-page-actions">
            <button type="button" class="button button-secondary datamachine-modal-open" data-template="jobs-admin">
                <?php esc_html_e('Admin', 'data-machine'); ?>
            </button>
        </div>
    </div>

    <!-- Loading indicator -->
    <div class="datamachine-jobs-loading" style="display: none;">
        <p><?php esc_html_e('Loading jobs...', 'data-machine'); ?></p>
    </div>

    <!-- Empty state (shown by JavaScript if no jobs) -->
    <div class="datamachine-jobs-empty-state" style="display: none;">
        <p class="datamachine-jobs-empty-message">
            <?php esc_html_e('No jobs found. Jobs will appear here when Data Machine processes data.', 'data-machine'); ?>
        </p>
    </div>

    <!-- Jobs table container (populated by JavaScript) -->
    <div class="datamachine-jobs-table-container" style="display: none;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="datamachine-col-job-id"><?php esc_html_e('Job ID', 'data-machine'); ?></th>
                    <th><?php esc_html_e('Pipeline / Flow', 'data-machine'); ?></th>
                    <th class="datamachine-col-status"><?php esc_html_e('Status', 'data-machine'); ?></th>
                    <th class="datamachine-col-created"><?php esc_html_e('Created At', 'data-machine'); ?></th>
                    <th class="datamachine-col-completed"><?php esc_html_e('Completed At', 'data-machine'); ?></th>
                </tr>
            </thead>
            <tbody id="datamachine-jobs-tbody">
                <!-- Jobs will be rendered here by JavaScript -->
            </tbody>
        </table>
    </div>
    
</div>