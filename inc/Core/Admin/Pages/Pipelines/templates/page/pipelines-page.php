<?php
/**
 * Pipelines Main Page Template
 *
 * Overall page structure for the Pipelines admin page.
 * Contains header, pipeline list, and overall page layout.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

$all_pipelines = apply_filters('dm_get_pipelines', []);

// Get selected pipeline ID with priority: URL parameter → saved preference → newest pipeline
$selected_pipeline_id = isset($_GET['selected_pipeline_id']) ? sanitize_text_field(wp_unslash($_GET['selected_pipeline_id'])) : '';

if (empty($selected_pipeline_id)) {
    // Check user's saved preference
    $selected_pipeline_id = get_user_meta(get_current_user_id(), 'dm_selected_pipeline_id', true);
}

if (empty($selected_pipeline_id) && !empty($all_pipelines)) {
    // Default to newest pipeline (first in reversed array)
    $selected_pipeline_id = $all_pipelines[0]['pipeline_id'];
}

// Validate that the selected pipeline ID actually exists in available pipelines
if (!empty($selected_pipeline_id) && !empty($all_pipelines)) {
    $valid_pipeline_ids = array_column($all_pipelines, 'pipeline_id');
    if (!in_array($selected_pipeline_id, $valid_pipeline_ids, true)) {
        // Selected pipeline doesn't exist, fall back to first available pipeline
        $selected_pipeline_id = $all_pipelines[0]['pipeline_id'];
    }
}

?>
<div class="dm-admin-wrap dm-pipelines-page">
    <div class="dm-admin-header">
        <div class="dm-admin-header-left">
            <h1 class="dm-admin-title">
                <?php esc_html_e('Pipeline + Flow Management', 'data-machine'); ?>
            </h1>
            <p class="dm-admin-subtitle">
                <?php esc_html_e('Configure automated workflow pipelines', 'data-machine'); ?>
            </p>
        </div>
        
        <div class="dm-admin-header-right">
            <button type="button" class="button dm-import-export-btn">
                <?php esc_html_e('Import / Export', 'data-machine'); ?>
            </button>
        </div>
    </div>
    
    <div class="dm-pipeline-page-header">
        <select class="dm-pipeline-dropdown <?php echo empty($all_pipelines) ? 'dm-hidden' : ''; ?>" id="dm-pipeline-selector">
            <?php foreach ($all_pipelines as $pipeline): ?>
                <option value="<?php echo esc_attr($pipeline['pipeline_id']); ?>" 
                    <?php selected($selected_pipeline_id, $pipeline['pipeline_id']); ?>>
                    <?php echo esc_html($pipeline['pipeline_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <button type="button" class="button button-primary dm-modal-open" data-template="pipeline-templates">
            <?php esc_html_e('Add New Pipeline', 'data-machine'); ?>
        </button>
    </div>

    <div class="dm-pipeline-cards-container">
        <div class="dm-pipelines-list">
            <?php if (!empty($all_pipelines)): ?>
                <?php foreach ($all_pipelines as $pipeline): ?>
                    <?php 
                    $pipeline_id = $pipeline['pipeline_id'];
                    $is_selected = ($pipeline_id === $selected_pipeline_id);
                    
                    // Only show selected pipeline, hide others
                    $hidden_class = $is_selected ? '' : 'dm-hidden';
                    
                    // Load flows for this pipeline
                    $existing_flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
                    ?>
                    <div class="dm-pipeline-wrapper <?php echo esc_attr($hidden_class); ?>" data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">
                        <?php
                        echo wp_kses(apply_filters('dm_render_template', '', 'page/pipeline-card', [
                            'pipeline' => $pipeline,
                            'existing_flows' => $existing_flows,
                            'pipelines_instance' => null
                        ]), dm_allowed_html());
                        ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>