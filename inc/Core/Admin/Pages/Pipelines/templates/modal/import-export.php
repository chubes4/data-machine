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
$all_pipelines = apply_filters('dm_get_pipelines', []);

// Pre-load all pipeline steps and flows to avoid N+1 queries
$pipeline_steps_counts = [];
$pipeline_flows_counts = [];
foreach ($all_pipelines as $pipeline) {
    $pipeline_id = $pipeline['pipeline_id'];
    $steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
    $flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
    $pipeline_steps_counts[$pipeline_id] = count($steps);
    $pipeline_flows_counts[$pipeline_id] = count($flows);
}
?>
<div class="dm-modal-tabs">
    <button class="dm-modal-tab active" data-tab="export"><?php esc_html_e('Export', 'data-machine'); ?></button>
    <button class="dm-modal-tab" data-tab="import"><?php esc_html_e('Import', 'data-machine'); ?></button>
</div>

<div class="dm-modal-tab-content" id="export-tab">
    <p><?php esc_html_e('Select pipelines to export:', 'data-machine'); ?></p>
    <table class="dm-export-table widefat">
        <thead>
            <tr>
                <th><input type="checkbox" class="dm-select-all"></th>
                <th><?php esc_html_e('Pipeline Name', 'data-machine'); ?></th>
                <th><?php esc_html_e('Steps', 'data-machine'); ?></th>
                <th><?php esc_html_e('Flows', 'data-machine'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($all_pipelines as $pipeline):
                $pipeline_id = $pipeline['pipeline_id'];
                $steps_count = $pipeline_steps_counts[$pipeline_id] ?? 0;
                $flows_count = $pipeline_flows_counts[$pipeline_id] ?? 0;
            ?>
            <tr>
                <td><input type="checkbox" class="dm-pipeline-checkbox" value="<?php echo esc_attr($pipeline_id); ?>"></td>
                <td><?php echo esc_html($pipeline['pipeline_name']); ?></td>
                <td><?php echo esc_html($steps_count); ?></td>
                <td><?php echo esc_html($flows_count); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <button class="button button-primary dm-export-selected" disabled>
        <?php esc_html_e('Export Selected', 'data-machine'); ?>
    </button>
</div>

<div class="dm-modal-tab-content" id="import-tab">
    <div class="dm-import-dropzone">
        <p><?php esc_html_e('Drag CSV file here or click to browse', 'data-machine'); ?></p>
        <input type="file" class="dm-import-file" accept=".csv">
    </div>
    <div class="dm-import-preview"></div>
    <button class="button button-primary dm-import-pipelines" disabled>
        <?php esc_html_e('Import Pipelines', 'data-machine'); ?>
    </button>
</div>