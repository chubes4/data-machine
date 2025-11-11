<?php
/**
 * Step Selection Cards Template
 *
 * Pure rendering template for step selection modal content.
 * Displays available step types for pipeline building.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

?>
<div class="datamachine-step-selection-container" data-pipeline-id="<?php echo esc_attr($pipeline_id ?? ''); ?>">
    <input type="hidden" name="pipeline_id" value="<?php echo esc_attr($pipeline_id ?? ''); ?>" />
    
    <div class="datamachine-step-selection-header">
        <p><?php esc_html_e('Select a step type to add to your pipeline', 'data-machine'); ?></p>
    </div>
    
    <div class="datamachine-step-cards">
        <?php 
        $all_steps = apply_filters('datamachine_step_types', []);
        uasort($all_steps, function($a, $b) {
            $pos_a = $a['position'] ?? 999;
            $pos_b = $b['position'] ?? 999;
            return $pos_a <=> $pos_b;
        });
        
        foreach ($all_steps as $step_type => $step_config): ?>
            <?php
            $label = $step_config['label'] ?? ucfirst($step_type);
            $description = $step_config['description'] ?? '';
            
            $handlers_list = '';
            $handlers = apply_filters('datamachine_handlers', [], $step_type);
            
            if (!empty($handlers)) {
                $handler_labels = [];
                foreach ($handlers as $handler_slug => $handler_config) {
                    $handler_labels[] = $handler_config['label'] ?? ucfirst($handler_slug);
                }
                $handlers_list = implode(', ', $handler_labels);
            }
            ?>
            <div class="datamachine-step-selection-card datamachine-modal-close" 
                 data-template="add-step-action"
                 data-context='<?php echo esc_attr(wp_json_encode(['step_type' => $step_type, 'pipeline_id' => $pipeline_id ?? ''])); ?>'
                 data-step-type="<?php echo esc_attr($step_type); ?>"
                 role="button" 
                 tabindex="0">
                <div class="datamachine-step-card-header">
                    <h5 class="datamachine-step-card-title"><?php echo esc_html($label); ?></h5>
                </div>
                <?php if ($description): ?>
                    <div class="datamachine-step-card-body">
                        <p class="datamachine-step-card-description"><?php echo esc_html($description); ?></p>
                        <?php if (!empty($handlers_list)): ?>
                            <div class="datamachine-step-handlers">
                                <span class="datamachine-handlers-label"><?php esc_html_e('Available handlers:', 'data-machine'); ?></span>
                                <span class="datamachine-handlers-list"><?php echo esc_html($handlers_list); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>