<?php
/**
 * Jobs Admin Modal Template
 *
 * Provides administrative functions for the Jobs page, particularly focused on
 * clearing processed items during development and testing workflows.
 *
 * Primary use case: When iteratively testing flows and refining prompts, this allows
 * clearing processed items so the same content can be reprocessed without database access.
 *
 * @package DataMachine\Core\Admin\Pages\Jobs
 * @since NEXT_VERSION
 */

if (!defined('WPINC')) {
    die;
}

// Get all pipelines for the dropdown
$all_databases = apply_filters('dm_db', []);
$db_pipelines = $all_databases['pipelines'] ?? null;

$pipelines = [];
if ($db_pipelines) {
    $pipelines_list = $db_pipelines->get_pipelines_list();
    foreach ($pipelines_list as $pipeline) {
        $pipelines[$pipeline['pipeline_id']] = $pipeline['pipeline_name'];
    }
}
?>

<div class="dm-jobs-modal-content dm-jobs-admin-modal">
    <div class="dm-modal-description">
        <p class="description">
            <?php esc_html_e('Administrative tools for managing job processing and testing workflows.', 'data-machine'); ?>
        </p>
    </div>
        <!-- Clear Processed Items Section -->
        <div class="dm-admin-section">
            <h3><?php esc_html_e('Clear Processed Items', 'data-machine'); ?></h3>
            <p class="description">
                <?php esc_html_e('Clear processed item records to allow reprocessing during testing and development. This is useful when iteratively refining prompts and configurations.', 'data-machine'); ?>
            </p>
            
            <form id="dm-clear-processed-items-form" class="dm-admin-form">
                <div class="dm-form-field">
                    <label for="dm-clear-type-select">
                        <?php esc_html_e('Clear By', 'data-machine'); ?>
                    </label>
                    <select id="dm-clear-type-select" name="clear_type" class="regular-text" required>
                        <option value=""><?php esc_html_e('— Select Type —', 'data-machine'); ?></option>
                        <option value="pipeline"><?php esc_html_e('Entire Pipeline (all flows)', 'data-machine'); ?></option>
                        <option value="flow"><?php esc_html_e('Specific Flow', 'data-machine'); ?></option>
                    </select>
                </div>
                
                <div class="dm-form-field dm-hidden" id="dm-pipeline-select-wrapper">
                    <label for="dm-clear-pipeline-select">
                        <?php esc_html_e('Select Pipeline', 'data-machine'); ?>
                    </label>
                    <select id="dm-clear-pipeline-select" name="pipeline_id" class="regular-text">
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
                
                <div class="dm-form-field dm-hidden" id="dm-flow-select-wrapper">
                    <label for="dm-clear-flow-select">
                        <?php esc_html_e('Select Flow', 'data-machine'); ?>
                    </label>
                    <select id="dm-clear-flow-select" name="flow_id" class="regular-text">
                        <option value=""><?php esc_html_e('— Select a Pipeline First —', 'data-machine'); ?></option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('All processed items for this specific flow will be cleared.', 'data-machine'); ?>
                    </p>
                </div>
                
                <div class="dm-form-actions">
                    <button type="submit" class="button button-primary" id="dm-clear-processed-btn">
                        <?php esc_html_e('Clear Processed Items', 'data-machine'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
                
                <div class="dm-admin-notice dm-hidden" id="dm-clear-result"></div>
            </form>
        </div>
        
        <!-- Clear Jobs Section -->
        <div class="dm-admin-section">
            <h3><?php esc_html_e('Clear Jobs', 'data-machine'); ?></h3>
            <p class="description">
                <?php esc_html_e('Delete job records from the database. Failed jobs can be cleared safely, while clearing all jobs removes historical execution data.', 'data-machine'); ?>
            </p>
            
            <form id="dm-clear-jobs-form" class="dm-admin-form">
                <div class="dm-form-field">
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
                
                <div class="dm-form-field">
                    <label>
                        <input type="checkbox" name="cleanup_processed" value="1">
                        <?php esc_html_e('Also clear processed items for deleted jobs', 'data-machine'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('When enabled, this will also remove processed item records for all deleted jobs, allowing full reprocessing.', 'data-machine'); ?>
                    </p>
                </div>
                
                <div class="dm-form-actions">
                    <button type="submit" class="button button-primary" id="dm-clear-jobs-btn">
                        <?php esc_html_e('Clear Jobs', 'data-machine'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
                
                <div class="dm-admin-notice dm-hidden" id="dm-clear-jobs-result"></div>
            </form>
        </div>
</div>

