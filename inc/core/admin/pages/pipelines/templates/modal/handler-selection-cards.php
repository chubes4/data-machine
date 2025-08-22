<?php
/**
 * Handler Selection Cards Template
 *
 * Pure rendering template for handler selection modal content.
 * Grid-based layout with simple button-style handler selection.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

?>
<div class="dm-handler-selection-container">
    
    <div class="dm-handler-selection-header">
        <p><?php echo esc_html(sprintf(__('Select a %s handler to configure', 'data-machine'), $step_type)); ?></p>
    </div>
    
    <div class="dm-handler-cards">
        <?php 
        $step_type = $step_type ?? 'unknown';
        $all_handlers = apply_filters('dm_handlers', []);
        $handlers = array_filter($all_handlers, function($handler) use ($step_type) {
            return ($handler['type'] ?? '') === $step_type;
        });
        
        foreach ($handlers as $handler_slug => $handler_config): 
            $template_slug = $handler_slug;
            ?>
            <div class="dm-handler-selection-card dm-modal-content" 
                 data-template="handler-settings/<?php echo esc_attr($template_slug); ?>"
                 data-context='{"handler_slug":"<?php echo esc_attr($handler_slug); ?>","step_type":"<?php echo esc_attr($step_type); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id ?? ''); ?>","flow_step_id":"<?php echo esc_attr($flow_step_id ?? ''); ?>"}'
                 data-handler-slug="<?php echo esc_attr($handler_slug); ?>" 
                 data-step-type="<?php echo esc_attr($step_type); ?>"
                 role="button" 
                 tabindex="0">
                <h4 class="dm-handler-card-title"><?php echo esc_html($handler_config['label'] ?? ucfirst($handler_slug)); ?></h4>
                <?php if (!empty($handler_config['description'])): ?>
                    <p class="dm-handler-card-description"><?php echo esc_html($handler_config['description']); ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>