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

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Get pipelines data directly
$all_databases = apply_filters('dm_get_database_services', []);
$db_pipelines = $all_databases['pipelines'] ?? null;
$db_flows = $all_databases['flows'] ?? null;

$all_pipelines = [];
if ($db_pipelines) {
    $all_pipelines = $db_pipelines->get_all_pipelines();
}

?>
<div class="dm-admin-wrap dm-pipelines-page">
    <!-- Page Header -->
    <div class="dm-admin-header">
        <h1 class="dm-admin-title">
            <?php esc_html_e('Pipeline + Flow Management', 'data-machine'); ?>
        </h1>
        <p class="dm-admin-subtitle">
            <?php esc_html_e('Configure automated workflow pipelines', 'data-machine'); ?>
        </p>
        
        <!-- Add New Pipeline Button -->
        <div class="dm-add-pipeline-section">
            <button type="button" class="button button-primary dm-add-new-pipeline-btn">
                <?php esc_html_e('Add New Pipeline', 'data-machine'); ?>
            </button>
        </div>
    </div>

    <!-- Universal Pipeline Cards Container -->
    <div class="dm-pipeline-cards-container">
        <div class="dm-pipelines-list">
            <!-- Show existing pipelines (latest first) -->
            <?php if (!empty($all_pipelines)): ?>
                <?php foreach (array_reverse($all_pipelines) as $pipeline): ?>
                    <?php 
                    // Load flows for this pipeline
                    $pipeline_id = is_object($pipeline) ? $pipeline->pipeline_id : $pipeline['pipeline_id'];
                    $flows_db = $all_databases['flows'] ?? null;
                    $existing_flows = $flows_db ? $flows_db->get_flows_for_pipeline($pipeline_id) : [];
                    
                    echo apply_filters('dm_render_template', '', 'page/pipeline-card', [
                        'pipeline' => $pipeline,
                        'existing_flows' => $existing_flows,
                        'pipelines_instance' => null  // No instance needed with template-based approach
                    ]); 
                    ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>