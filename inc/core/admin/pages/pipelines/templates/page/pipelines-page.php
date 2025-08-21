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
    
    <div class="dm-add-pipeline-section">
        <button type="button" class="button button-primary dm-add-new-pipeline-btn">
            <?php esc_html_e('Add New Pipeline', 'data-machine'); ?>
        </button>
    </div>

    <div class="dm-pipeline-cards-container">
        <div class="dm-pipelines-list">
            <?php if (!empty($all_pipelines)): ?>
                <?php $reversed_pipelines = array_reverse($all_pipelines); $total_pipelines = count($reversed_pipelines); ?>
                <?php foreach ($reversed_pipelines as $index => $pipeline): ?>
                    <?php 
                    // Load flows for this pipeline
                    $pipeline_id = $pipeline['pipeline_id'];
                    $existing_flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
                    
                    echo apply_filters('dm_render_template', '', 'page/pipeline-card', [
                        'pipeline' => $pipeline,
                        'existing_flows' => $existing_flows,
                        'pipelines_instance' => null  // No instance needed with template-based approach
                    ]); 
                    // Insert visual separator between cards (not after last)
                    if ($index < $total_pipelines - 1) {
                        echo '<div class="dm-separator" aria-hidden="true"></div>';
                    }
                    ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>