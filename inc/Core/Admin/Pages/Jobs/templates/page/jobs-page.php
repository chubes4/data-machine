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
            <button type="button" class="button button-secondary datamachine-open-modal" data-modal-id="datamachine-modal-jobs-admin">
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

    <!-- Pre-rendered Jobs Admin Modal (no AJAX loading) -->
    <div id="datamachine-modal-jobs-admin"
         class="datamachine-modal"
         aria-hidden="true"
         style="display: none;">
        <div class="datamachine-modal-overlay"></div>
        <div class="datamachine-modal-container">
            <div class="datamachine-modal-header">
                <h2 class="datamachine-modal-title">
                    <?php esc_html_e('Jobs Administration', 'data-machine'); ?>
                </h2>
                <button type="button" class="datamachine-modal-close" aria-label="<?php esc_attr_e('Close', 'data-machine'); ?>">
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
                            <?php esc_html_e('Administrative tools for managing job processing and testing workflows.', 'data-machine'); ?>
                        </p>
                    </div>

                    <!-- Clear Processed Items Section -->
                    <div class="datamachine-admin-section">
                        <h3><?php esc_html_e('Clear Processed Items', 'data-machine'); ?></h3>
                        <p class="description">
                            <?php esc_html_e('Clear processed item records to allow reprocessing during testing and development. This is useful when iteratively refining prompts and configurations.', 'data-machine'); ?>
                        </p>

                        <form id="datamachine-clear-processed-items-form" class="datamachine-admin-form">
                            <div class="datamachine-form-field">
                                <label for="datamachine-clear-type-select">
                                    <?php esc_html_e('Clear By', 'data-machine'); ?>
                                </label>
                                <select id="datamachine-clear-type-select" name="clear_type" class="regular-text" required>
                                    <option value=""><?php esc_html_e('— Select Type —', 'data-machine'); ?></option>
                                    <option value="pipeline"><?php esc_html_e('Entire Pipeline (all flows)', 'data-machine'); ?></option>
                                    <option value="flow"><?php esc_html_e('Specific Flow', 'data-machine'); ?></option>
                                </select>
                            </div>

                            <div class="datamachine-form-field datamachine-hidden" id="datamachine-pipeline-select-wrapper">
                                <label for="datamachine-clear-pipeline-select">
                                    <?php esc_html_e('Select Pipeline', 'data-machine'); ?>
                                </label>
                                <select id="datamachine-clear-pipeline-select" name="pipeline_id" class="regular-text">
                                    <option value=""><?php esc_html_e('— Select a Pipeline —', 'data-machine'); ?></option>
                                    <?php foreach ($pipelines as $pipeline_id => $pipeline_name): ?>
                                        <option value="<?php echo esc_attr($pipeline_id); ?>">
                                            <?php echo esc_html($pipeline_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('All processed items for ALL flows in this pipeline will be cleared.', 'data-machine'); ?>
                                </p>
                            </div>

                            <div class="datamachine-form-field datamachine-hidden" id="datamachine-flow-select-wrapper">
                                <label for="datamachine-clear-flow-select">
                                    <?php esc_html_e('Select Flow', 'data-machine'); ?>
                                </label>
                                <select id="datamachine-clear-flow-select" name="flow_id" class="regular-text">
                                    <option value=""><?php esc_html_e('— Select a Pipeline First —', 'data-machine'); ?></option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('All processed items for this specific flow will be cleared.', 'data-machine'); ?>
                                </p>
                            </div>

                            <div class="datamachine-form-actions">
                                <button type="submit" class="button button-primary" id="datamachine-clear-processed-btn">
                                    <?php esc_html_e('Clear Processed Items', 'data-machine'); ?>
                                </button>
                                <span class="spinner"></span>
                            </div>

                            <div class="datamachine-admin-notice datamachine-hidden" id="datamachine-clear-result"></div>
                        </form>
                    </div>

                    <!-- Clear Jobs Section -->
                    <div class="datamachine-admin-section">
                        <h3><?php esc_html_e('Clear Jobs', 'data-machine'); ?></h3>
                        <p class="description">
                            <?php esc_html_e('Delete job records from the database. Failed jobs can be cleared safely, while clearing all jobs removes historical execution data.', 'data-machine'); ?>
                        </p>

                        <form id="datamachine-clear-jobs-form" class="datamachine-admin-form">
                            <div class="datamachine-form-field">
                                <label><?php esc_html_e('Jobs to Clear', 'data-machine'); ?></label>
                                <fieldset>
                                    <label>
                                        <input type="radio" name="clear_jobs_type" value="failed" required>
                                        <?php esc_html_e('Failed jobs only (recommended)', 'data-machine'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="radio" name="clear_jobs_type" value="all" required>
                                        <?php esc_html_e('All jobs (removes all execution history)', 'data-machine'); ?>
                                    </label>
                                </fieldset>
                            </div>

                            <div class="datamachine-form-field">
                                <label>
                                    <input type="checkbox" name="cleanup_processed" value="1">
                                    <?php esc_html_e('Also clear processed items for deleted jobs', 'data-machine'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When enabled, this will also remove processed item records for all deleted jobs, allowing full reprocessing.', 'data-machine'); ?>
                                </p>
                            </div>

                            <div class="datamachine-form-actions">
                                <button type="submit" class="button button-primary" id="datamachine-clear-jobs-btn">
                                    <?php esc_html_e('Clear Jobs', 'data-machine'); ?>
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