<?php
/**
 * Pipeline Step Card Template
 *
 * Dedicated template for pipeline-level step cards only.
 * Simplified from universal template - no context switching complexity.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

$step = $step ?? [];
$pipeline_id = $pipeline_id ?? 0;

$is_empty = $step['is_empty'] ?? true;
$step_type = $step['step_type'] ?? '';
$step_execution_order = $step['execution_order'] ?? 0;
$pipeline_step_id = $step['pipeline_step_id'] ?? null;
$step_data = $step;

$step_title = $is_empty ? '' : ($step_data['label'] ?? ucfirst(str_replace('_', ' ', $step_type)));

if ($is_empty) {
    $step_settings_info = null;
} else {
    $all_step_settings = apply_filters('dm_step_settings', []);
    $step_settings_info = $all_step_settings[$step_type] ?? null;
}
$has_step_settings = !$is_empty && !empty($step_settings_info);

$pipeline_status_class = '';
if ($pipeline_id && !$is_empty && $pipeline_step_id) {
    $pipeline_status = apply_filters('dm_detect_status', 'green', 'pipeline_step_status', [
        'pipeline_id' => $pipeline_id,
        'pipeline_step_id' => $pipeline_step_id,
        'step_type' => $step_type
    ]);
    $pipeline_status_class = ' dm-pipeline-step-card--status-' . $pipeline_status;
}

?>
<div class="dm-step-container" 
     data-step-execution-order="<?php echo esc_attr($step_execution_order); ?>"
     data-step-type="<?php echo esc_attr($step_type); ?>"
     <?php if (!empty($pipeline_step_id)): ?>data-pipeline-step-id="<?php echo esc_attr($pipeline_step_id); ?>"<?php endif; ?>
     data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">

    <?php
    $show_arrow = ($step_execution_order > 0);
    if ($show_arrow): ?>
        <div class="dm-data-flow-arrow">
            <span class="dashicons dashicons-arrow-right-alt"></span>
        </div>
    <?php endif; ?>

    <div class="dm-step-card<?php echo $is_empty ? ' dm-step-card--empty' : ''; ?><?php echo esc_attr($pipeline_status_class); ?>">
        <?php if ($is_empty): ?>
            <div class="dm-step-empty-content">
                <button type="button" class="button button-secondary dm-modal-open dm-step-add-button"
                        data-template="step-selection"
                        data-context='{"context":"pipeline_builder","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
                    <?php esc_html_e('Add Step', 'data-machine'); ?>
                </button>
            </div>
        <?php else: ?>
            <div class="dm-step-header">
                <div class="dm-step-title">
                    <?php echo esc_html($step_title); ?>
                </div>
                <div class="dm-step-actions">
                    <button type="button" class="button button-small button-link-delete dm-modal-open" 
                            data-template="confirm-delete"
                            data-context='{"delete_type":"step","step_type":"<?php echo esc_attr($step_type); ?>","pipeline_step_id":"<?php echo esc_attr($pipeline_step_id); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
                        <?php esc_html_e('Delete', 'data-machine'); ?>
                    </button>
                    <?php if ($has_step_settings): ?>
                        <button type="button" class="button button-small button-link-configure dm-modal-open" 
                                data-template="configure-step"
                                data-context='{"step_type":"<?php echo esc_attr($step_type); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>","pipeline_step_id":"<?php echo esc_attr($pipeline_step_id); ?>","modal_type":"<?php echo esc_attr($step_settings_info['modal_type'] ?? ''); ?>","config_type":"<?php echo esc_attr($step_settings_info['config_type'] ?? ''); ?>"}'>
                            <?php echo esc_html($step_settings_info['button_text'] ?? __('Configure', 'data-machine')); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dm-step-body">
                <?php if ($step_type === 'ai' && !$is_empty && $pipeline_step_id): ?>
                    <?php
                    $ai_config = apply_filters('dm_ai_config', [], $pipeline_step_id);
                    $show_config = false;
                    if (!empty($ai_config) && isset($ai_config['selected_provider'])) {
                        $selected_provider = $ai_config['selected_provider'];
                        $model_name = $ai_config['model'] ?? '';
                        $prompt = $ai_config['system_prompt'] ?? '';
                        if ($selected_provider && $model_name) {
                            $show_config = true;
                        }
                    }
                    if ($show_config):
                        $prompt_excerpt = !empty($prompt) ? (strlen($prompt) > 100 ? substr($prompt, 0, 100) . '...' : $prompt) : 'No prompt set';
                        $status_class = 'dm-ai-configured';
                    ?>
                        <div class="dm-ai-step-info <?php echo esc_attr($status_class); ?>">
                            <div class="dm-model-name">
                                <strong><?php echo esc_html(ucfirst($selected_provider)); ?>: <?php echo esc_html($model_name); ?></strong>
                            </div>
                            <div class="dm-prompt-display">
                                <?php
                                // Calculate dynamic rows based on content length
                                $estimated_rows = max(4, min(20, ceil(strlen($prompt) / 80) + 2));
                                ?>
                                <textarea class="dm-ai-prompt-input" 
                                          data-pipeline-step-id="<?php echo esc_attr($pipeline_step_id); ?>"
                                          placeholder="<?php esc_attr_e('Define how the AI should process data from previous pipeline steps...', 'data-machine'); ?>"
                                          rows="<?php echo esc_attr($estimated_rows); ?>"><?php echo esc_textarea($prompt); ?></textarea>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="dm-placeholder-text"><?php esc_html_e('AI step not configured', 'data-machine'); ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>