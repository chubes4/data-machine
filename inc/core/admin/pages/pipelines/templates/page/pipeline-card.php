<?php
/**
 * Pipeline Card Template
 *
 * Universal pipeline card template - purely data-driven.
 * Renders blank card for new pipelines, populated card for existing pipelines.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Data-driven rendering - handle both new (empty) and existing (populated) pipelines
$pipeline_id = null;
$pipeline_name = '';
$pipeline_steps = [];

if (isset($pipeline) && !empty($pipeline)) {
    $pipeline_id = is_object($pipeline) ? $pipeline->pipeline_id : ($pipeline['pipeline_id'] ?? null);
    $pipeline_name = is_object($pipeline) ? $pipeline->pipeline_name : ($pipeline['pipeline_name'] ?? '');
    $step_config = is_object($pipeline) ? $pipeline->step_configuration : ($pipeline['step_configuration'] ?? '');
    $pipeline_steps = !empty($step_config) ? json_decode($step_config, true) : [];
    $pipeline_steps = is_array($pipeline_steps) ? $pipeline_steps : [];
}

$step_count = count($pipeline_steps);
$is_new_pipeline = empty($pipeline_id);

?>
<div class="dm-pipeline-card dm-pipeline-form" data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">
    <!-- Pipeline Header with Editable Title -->
    <div class="dm-pipeline-header">
        <div class="dm-pipeline-title-section">
            <input type="text" class="dm-pipeline-title-input" 
                   value="<?php echo esc_attr($pipeline_name); ?>" 
                   placeholder="<?php esc_attr_e('Enter pipeline name...', 'data-machine'); ?>" />
            <div class="dm-pipeline-meta">
                <span class="dm-step-count"><?php echo esc_html(sprintf(__('%d steps', 'data-machine'), $step_count)); ?></span>
                <span class="dm-flow-count"><?php esc_html_e('0 flows', 'data-machine'); ?></span>
            </div>
        </div>
        <div class="dm-pipeline-actions">
            <button type="button" class="button button-primary dm-save-pipeline-btn" disabled>
                <?php esc_html_e('Save Pipeline', 'data-machine'); ?>
            </button>
            <?php if (!$is_new_pipeline): ?>
                <button type="button" class="button button-secondary dm-modal-open" 
                        data-template="confirm-delete"
                        data-context='{"delete_type":"pipeline","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>","pipeline_name":"<?php echo esc_attr($pipeline_name); ?>"}'>
                    <?php esc_html_e('Delete Pipeline', 'data-machine'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Pipeline Steps Section (Template Level) -->
    <div class="dm-pipeline-steps-section">
        <div class="dm-section-header">
            <h4><?php esc_html_e('Pipeline Steps', 'data-machine'); ?></h4>
            <p class="dm-section-description"><?php esc_html_e('Define the step sequence for this pipeline', 'data-machine'); ?></p>
        </div>
        <div class="dm-pipeline-steps">
            <?php 
            // Data-driven approach: always append an empty step for "Add Step" functionality
            $display_steps = $pipeline_steps;
            $display_steps[] = [
                'step_type' => '',
                'position' => count($pipeline_steps),
                'label' => '',
                'is_empty' => true,
                'step_config' => []
            ];
            
            foreach ($display_steps as $index => $step): 
            ?>
                <?php echo apply_filters('dm_render_template', '', 'page/step-card', [
                    'context' => 'pipeline',
                    'step' => $step,
                    'pipeline_id' => $pipeline_id,
                    'is_first_step' => ($index === 0)
                ]); ?>
                <!-- Arrow rendering handled by universal step-card template -->
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Associated Flows -->
    <div class="dm-pipeline-flows">
        <div class="dm-flows-header">
            <div class="dm-flows-header-content">
                <h4><?php esc_html_e('Flow Instances', 'data-machine'); ?></h4>
                <p class="dm-section-description"><?php esc_html_e('Each flow is a configured instance of the pipeline above', 'data-machine'); ?></p>
            </div>
            <div class="dm-flows-header-actions">
                <button type="button" class="button button-primary dm-add-flow-btn" 
                        data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>"
                        <?php echo $is_new_pipeline ? 'disabled title="' . esc_attr__('Save pipeline first to add flows', 'data-machine') . '"' : ''; ?>>
                    <?php esc_html_e('Add Flow', 'data-machine'); ?>
                </button>
            </div>
        </div>
        <div class="dm-flows-list">
            <!-- Existing Flows from Database -->
            <?php if (!empty($existing_flows)): ?>
                <?php foreach ($existing_flows as $flow): ?>
                    <?php echo apply_filters('dm_render_template', '', 'page/flow-instance-card', [
                        'flow' => $flow,
                        'pipeline_steps' => $pipeline_steps
                    ]); ?>
                <?php endforeach; ?>
            <?php endif; ?>
            
        </div>
    </div>
</div>