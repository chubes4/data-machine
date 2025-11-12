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
        <h1><?php esc_html_e('Jobs', 'datamachine'); ?></h1>
        <div class="datamachine-page-actions">
            <button type="button" class="button button-secondary datamachine-open-modal" data-modal-id="datamachine-modal-jobs-admin">
                <?php esc_html_e('Admin', 'datamachine'); ?>
            </button>
        </div>
    </div>

    <!-- Loading indicator -->
    <div class="datamachine-jobs-loading" style="display: none;">
        <p><?php esc_html_e('Loading jobs...', 'datamachine'); ?></p>
    </div>

    <!-- Empty state (shown by JavaScript if no jobs) -->
    <div class="datamachine-jobs-empty-state" style="display: none;">
        <p class="datamachine-jobs-empty-message">
            <?php esc_html_e('No jobs found. Jobs will appear here when Data Machine processes data.', 'datamachine'); ?>
        </p>
    </div>

    <!-- Jobs table container (populated by JavaScript) -->
    <div class="datamachine-jobs-table-container" style="display: none;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="datamachine-col-job-id"><?php esc_html_e('Job ID', 'datamachine'); ?></th>
                    <th><?php esc_html_e('Pipeline / Flow', 'datamachine'); ?></th>
                    <th class="datamachine-col-status"><?php esc_html_e('Status', 'datamachine'); ?></th>
                    <th class="datamachine-col-created"><?php esc_html_e('Created At', 'datamachine'); ?></th>
                    <th class="datamachine-col-completed"><?php esc_html_e('Completed At', 'datamachine'); ?></th>
                </tr>
            </thead>
            <tbody id="datamachine-jobs-tbody">
                <!-- Jobs will be rendered here by JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Pre-rendered Jobs Admin Modal (no AJAX loading) -->
    <div id="datamachine-modal-jobs-admin"
         class="datamachine-modal"
         aria-hidden="true"
         style="display: none;">
        <div class="datamachine-modal-overlay"></div>
        <div class="datamachine-modal-container">
            <div class="datamachine-modal-header">
                <h2 class="datamachine-modal-title">
                    <?php esc_html_e('Jobs Administration', 'datamachine'); ?>
                </h2>
                <button type="button" class="datamachine-modal-close" aria-label="<?php esc_attr_e('Close', 'datamachine'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="datamachine-modal-body">
                <?php
                // Get all pipelines for the dropdown
                $all_databases = apply_filters('datamachine_db', []);
                $db_pipelines = $all_databases['pipelines'] ?? null;

                $pipelines = [];
                if ($db_pipelines) {
                    $pipelines_list = $db_pipelines->get_pipelines_list();
                    foreach ($pipelines_list as $pipeline) {
                        $pipelines[$pipeline['pipeline_id']] = $pipeline['pipeline_name'];
                    }
                }
                ?>

                <div class="datamachine-jobs-modal-content datamachine-jobs-admin-modal">
                    <div class="datamachine-modal-description">
                        <p class="description">
                            <?php esc_html_e('Administrative tools for managing job processing and testing workflows.', 'datamachine'); ?>
                        </p>
                    </div>

                    <!-- Clear Processed Items Section -->
                    <div class="datamachine-admin-section">
                        <h3><?php esc_html_e('Clear Processed Items', 'datamachine'); ?></h3>
                        <p class="description">
                            <?php esc_html_e('Clear processed item records to allow reprocessing during testing and development. This is useful when iteratively refining prompts and configurations.', 'datamachine'); ?>
                        </p>

                        <form id="datamachine-clear-processed-items-form" class="datamachine-admin-form">
                            <div class="datamachine-form-field">
                                <label for="datamachine-clear-type-select">
                                    <?php esc_html_e('Clear By', 'datamachine'); ?>
                                </label>
                                <select id="datamachine-clear-type-select" name="clear_type" class="regular-text" required>
                                    <option value=""><?php esc_html_e('— Select Type —', 'datamachine'); ?></option>
                                    <option value="pipeline"><?php esc_html_e('Entire Pipeline (all flows)', 'datamachine'); ?></option>
                                    <option value="flow"><?php esc_html_e('Specific Flow', 'datamachine'); ?></option>
                                </select>
                            </div>

                            <div class="datamachine-form-field datamachine-hidden" id="datamachine-pipeline-select-wrapper">
                                <label for="datamachine-clear-pipeline-select">
                                    <?php esc_html_e('Select Pipeline', 'datamachine'); ?>
                                </label>
                                <select id="datamachine-clear-pipeline-select" name="pipeline_id" class="regular-text">
                                    <option value=""><?php esc_html_e('— Select a Pipeline —', 'datamachine'); ?></option>
                                    <?php foreach ($pipelines as $pipeline_id => $pipeline_name): ?>
                                        <option value="<?php echo esc_attr($pipeline_id); ?>">
                                            <?php echo esc_html($pipeline_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('All processed items for ALL flows in this pipeline will be cleared.', 'datamachine'); ?>
                                </p>
                            </div>

                            <div class="datamachine-form-field datamachine-hidden" id="datamachine-flow-select-wrapper">
                                <label for="datamachine-clear-flow-select">
                                    <?php esc_html_e('Select Flow', 'datamachine'); ?>
                                </label>
                                <select id="datamachine-clear-flow-select" name="flow_id" class="regular-text">
                                    <option value=""><?php esc_html_e('— Select a Pipeline First —', 'datamachine'); ?></option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('All processed items for this specific flow will be cleared.', 'datamachine'); ?>
                                </p>
                            </div>

                            <div class="datamachine-form-actions">
                                <button type="submit" class="button button-primary" id="datamachine-clear-processed-btn">
                                    <?php esc_html_e('Clear Processed Items', 'datamachine'); ?>
                                </button>
                                <span class="spinner"></span>
                            </div>

                            <div class="datamachine-admin-notice datamachine-hidden" id="datamachine-clear-result"></div>
                        </form>
                    </div>

                    <!-- Clear Jobs Section -->
                    <div class="datamachine-admin-section">
                        <h3><?php esc_html_e('Clear Jobs', 'datamachine'); ?></h3>
                        <p class="description">
                            <?php esc_html_e('Delete job records from the database. Failed jobs can be cleared safely, while clearing all jobs removes historical execution data.', 'datamachine'); ?>
                        </p>

                        <form id="datamachine-clear-jobs-form" class="datamachine-admin-form">
                            <div class="datamachine-form-field">
                                <label><?php esc_html_e('Jobs to Clear', 'datamachine'); ?></label>
                                <fieldset>
                                    <label>
                                        <input type="radio" name="clear_jobs_type" value="failed" required>
                                        <?php esc_html_e('Failed jobs only (recommended)', 'datamachine'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="radio" name="clear_jobs_type" value="all" required>
                                        <?php esc_html_e('All jobs (removes all execution history)', 'datamachine'); ?>
                                    </label>
                                </fieldset>
                            </div>

                            <div class="datamachine-form-field">
                                <label>
                                    <input type="checkbox" name="cleanup_processed" value="1">
                                    <?php esc_html_e('Also clear processed items for deleted jobs', 'datamachine'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When enabled, this will also remove processed item records for all deleted jobs, allowing full reprocessing.', 'datamachine'); ?>
                                </p>
                            </div>

                            <div class="datamachine-form-actions">
                                <button type="submit" class="button button-primary" id="datamachine-clear-jobs-btn">
                                    <?php esc_html_e('Clear Jobs', 'datamachine'); ?>
                                </button>
                                <span class="spinner"></span>
                            </div>

                            <div class="datamachine-admin-notice datamachine-hidden" id="datamachine-clear-jobs-result"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>