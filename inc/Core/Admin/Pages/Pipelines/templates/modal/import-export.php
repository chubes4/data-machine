<?php
/**
 * Import/Export Modal Template
 * 
 * Two-tab interface for pipeline import/export functionality.
 * Export tab shows checkbox table of all pipelines.
 * Import tab provides drag-drop CSV upload area.
 * 
 * @package DataMachine
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

// Get pipelines for export table
$all_pipelines = apply_filters('datamachine_get_pipelines', []);

// Pre-load all pipeline steps and flows to avoid N+1 queries
$pipeline_steps_counts = [];
$pipeline_flows_counts = [];
foreach ($all_pipelines as $pipeline) {
    $pipeline_id = $pipeline['pipeline_id'];
    $steps = apply_filters('datamachine_get_pipeline_steps', [], $pipeline_id);
    $flows = apply_filters('datamachine_get_pipeline_flows', [], $pipeline_id);
    $pipeline_steps_counts[$pipeline_id] = count($steps);
    $pipeline_flows_counts[$pipeline_id] = count($flows);
}
?>
<div class="datamachine-modal-tabs">
    <button class="datamachine-modal-tab active" data-tab="export"><?php esc_html_e('Export', 'datamachine'); ?></button>
    <button class="datamachine-modal-tab" data-tab="import"><?php esc_html_e('Import', 'datamachine'); ?></button>
</div>

<div class="datamachine-modal-tab-content" id="export-tab">
    <p><?php esc_html_e('Select pipelines to export:', 'datamachine'); ?></p>
    <table class="datamachine-export-table widefat">
        <thead>
            <tr>
                <th><input type="checkbox" class="datamachine-select-all"></th>
                <th><?php esc_html_e('Pipeline Name', 'datamachine'); ?></th>
                <th><?php esc_html_e('Steps', 'datamachine'); ?></th>
                <th><?php esc_html_e('Flows', 'datamachine'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($all_pipelines as $pipeline):
                $pipeline_id = $pipeline['pipeline_id'];
                $steps_count = $pipeline_steps_counts[$pipeline_id] ?? 0;
                $flows_count = $pipeline_flows_counts[$pipeline_id] ?? 0;
            ?>
            <tr>
                <td><input type="checkbox" class="datamachine-pipeline-checkbox" value="<?php echo esc_attr($pipeline_id); ?>"></td>
                <td><?php echo esc_html($pipeline['pipeline_name']); ?></td>
                <td><?php echo esc_html($steps_count); ?></td>
                <td><?php echo esc_html($flows_count); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <button class="button button-primary datamachine-export-selected" disabled>
        <?php esc_html_e('Export Selected', 'datamachine'); ?>
    </button>
</div>

<div class="datamachine-modal-tab-content" id="import-tab">
    <div class="datamachine-import-dropzone">
        <p><?php esc_html_e('Drag CSV file here or click to browse', 'datamachine'); ?></p>
        <input type="file" class="datamachine-import-file" accept=".csv">
    </div>
    <div class="datamachine-import-preview"></div>
    <button class="button button-primary datamachine-import-pipelines" disabled>
        <?php esc_html_e('Import Pipelines', 'datamachine'); ?>
    </button>
</div>