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

if (!defined('WPINC')) {
    die;
}

$pipeline_id = null;
$pipeline_name = '';
$pipeline_steps = [];

if (isset($pipeline) && !empty($pipeline)) {
    $pipeline_id = $pipeline['pipeline_id'] ?? null;
    $pipeline_name = $pipeline['pipeline_name'] ?? '';
    $pipeline_steps = $pipeline_id ? apply_filters('dm_get_pipeline_steps', [], $pipeline_id) : [];
    
    // Sort pipeline steps by execution_order for correct display order
    if (!empty($pipeline_steps)) {
        uasort($pipeline_steps, function($a, $b) {
            return ($a['execution_order'] ?? 0) <=> ($b['execution_order'] ?? 0);
        });
    }
}

$is_new_pipeline = empty($pipeline_id);
$has_steps = !empty($pipeline_steps);

?>
<div class="dm-pipeline-card dm-pipeline-form" data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">
    <div class="dm-pipeline-header">
        <div class="dm-pipeline-title-section">
            <input type="text" class="dm-pipeline-title-input" 
                   value="<?php echo esc_attr($pipeline_name); ?>" 
                   placeholder="<?php esc_attr_e('Enter pipeline name...', 'data-machine'); ?>" />
        </div>
        <div class="dm-pipeline-actions">
            <?php if (!$is_new_pipeline): ?>
                <button type="button" class="button button-secondary dm-modal-open" 
                        data-template="confirm-delete"
                        data-context='<?php echo esc_attr(wp_json_encode(['delete_type' => 'pipeline', 'pipeline_id' => $pipeline_id, 'pipeline_name' => $pipeline_name])); ?>'>
                    <?php esc_html_e('Delete Pipeline', 'data-machine'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="dm-pipeline-steps-section">
        <div class="dm-section-header">
            <h4><?php esc_html_e('Pipeline Steps', 'data-machine'); ?></h4>
            <p class="dm-section-description"><?php esc_html_e('Add steps to define the data flow', 'data-machine'); ?></p>
        </div>
        <div class="dm-pipeline-steps">
            <?php 
            $display_steps = [];
            foreach ($pipeline_steps as $step) {
                // Database structure validation - fail fast if corrupt
                if (!is_array($step)) {
                    do_action('dm_log', 'error', 'Pipeline data corruption: non-array step data', [
                        'pipeline_id' => $pipeline_id,
                        'step_data' => $step
                    ]);
                    throw new \RuntimeException(esc_html("Pipeline {$pipeline_id} contains corrupted step data - cannot render"));
                }
                
                // Validate required fields exist
                $required_fields = ['step_type', 'execution_order'];
                foreach ($required_fields as $field) {
                    if (!isset($step[$field])) {
                        do_action('dm_log', 'error', 'Pipeline step missing required field', [
                            'pipeline_id' => $pipeline_id,
                            'missing_field' => $field,
                            'step_data' => $step
                        ]);
                        throw new \RuntimeException(esc_html("Pipeline {$pipeline_id} step missing required field: {$field}"));
                    }
                }
                
                $step['is_empty'] = false; // Add template flag to database object
                $display_steps[] = $step;
            }
            
            $display_steps[] = [
                'step_type' => '',
                'execution_order' => count($pipeline_steps),
                'is_empty' => true,
                'step_data' => []
            ];
            
            foreach ($display_steps as $index => $step): 
            ?>
                <?php echo wp_kses(apply_filters('dm_render_template', '', 'page/pipeline-step-card', [
                    'step' => $step,
                    'pipeline_id' => $pipeline_id,
                    'is_first_step' => ($index === 0)
                ]), dm_allowed_html()); ?>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="dm-pipeline-flows">
        <div class="dm-flows-header">
            <div class="dm-flows-header-content">
                <h4><?php esc_html_e('Flow Instances', 'data-machine'); ?></h4>
                <p class="dm-section-description"><?php esc_html_e('Add flows to run data through the pipeline', 'data-machine'); ?></p>
            </div>
            <div class="dm-flows-header-actions">
                <button type="button" class="button button-primary dm-add-flow-btn" 
                        data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>"
                        <?php echo ($is_new_pipeline || empty($pipeline_name) || !$has_steps) ? 'disabled title="' . esc_attr__('Pipeline needs name and steps to add flows', 'data-machine') . '"' : ''; ?>>
                    <?php esc_html_e('Add Flow', 'data-machine'); ?>
                </button>
            </div>
        </div>
        <div class="dm-flows-list">
            <?php if (!empty($existing_flows)): ?>
                <?php foreach ($existing_flows as $flow): ?>
                    <?php echo wp_kses(apply_filters('dm_render_template', '', 'page/flow-instance-card', [
                        'flow' => $flow,
                        'pipeline_steps' => $pipeline_steps
                    ]), dm_allowed_html()); ?>
                <?php endforeach; ?>
            <?php endif; ?>
            
        </div>
    </div>
</div>