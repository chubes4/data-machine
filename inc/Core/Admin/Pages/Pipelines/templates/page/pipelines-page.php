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

// Get lightweight pipelines list for dropdown (optimization: only IDs and names)
$pipelines_list = apply_filters('dm_get_pipelines_list', []);

// Get selected pipeline ID with priority: URL parameter → saved preference → newest pipeline
$selected_pipeline_id = '';
if (isset($_GET['selected_pipeline_id']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'dm_ajax_actions')) {
    $selected_pipeline_id = sanitize_text_field(wp_unslash($_GET['selected_pipeline_id']));
}

if (empty($selected_pipeline_id)) {
    // Check user's saved preference
    $selected_pipeline_id = get_user_meta(get_current_user_id(), 'dm_selected_pipeline_id', true);
}

if (empty($selected_pipeline_id) && !empty($pipelines_list)) {
    // Default to first pipeline in alphabetical list
    $selected_pipeline_id = $pipelines_list[0]['pipeline_id'];
}

// Validate that the selected pipeline ID actually exists in available pipelines
if (!empty($selected_pipeline_id) && !empty($pipelines_list)) {
    $valid_pipeline_ids = array_column($pipelines_list, 'pipeline_id');
    if (!in_array($selected_pipeline_id, $valid_pipeline_ids, true)) {
        // Selected pipeline doesn't exist, fall back to first available pipeline
        $selected_pipeline_id = $pipelines_list[0]['pipeline_id'];
    }
}

// Load only the selected pipeline's full data (optimization: single pipeline load)
$selected_pipeline = null;
$selected_pipeline_flows = [];
if (!empty($selected_pipeline_id)) {
    $selected_pipeline = apply_filters('dm_get_pipelines', [], $selected_pipeline_id);
    if ($selected_pipeline) {
        $selected_pipeline_flows = apply_filters('dm_get_pipeline_flows', [], $selected_pipeline_id);
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
        <select class="dm-pipeline-dropdown <?php echo empty($pipelines_list) ? 'dm-hidden' : ''; ?>" id="dm-pipeline-selector">
            <?php foreach ($pipelines_list as $pipeline): ?>
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
            <?php if (!empty($selected_pipeline)): ?>
                <div class="dm-pipeline-wrapper" data-pipeline-id="<?php echo esc_attr($selected_pipeline_id); ?>">
                    <?php
                    echo wp_kses(apply_filters('dm_render_template', '', 'page/pipeline-card', [
                        'pipeline' => $selected_pipeline,
                        'existing_flows' => $selected_pipeline_flows,
                        'pipelines_instance' => null
                    ]), dm_allowed_html());
                    ?>
                </div>
            <?php elseif (!empty($pipelines_list)): ?>
                <div class="dm-pipeline-loading">
                    <?php esc_html_e('Loading pipeline...', 'data-machine'); ?>
                </div>
            <?php else: ?>
                <div class="dm-no-pipelines">
                    <p><?php esc_html_e('No pipelines found.', 'data-machine'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>