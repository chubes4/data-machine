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
<div class="datamachine-handler-selection-container">
    
    <div class="datamachine-handler-selection-header">
        <?php /* translators: %s: Step type (fetch, publish, update) */ ?>
        <p><?php echo esc_html(sprintf(__('Select a %s handler to configure', 'data-machine'), $step_type)); ?></p>
    </div>
    
    <div class="datamachine-handler-cards">
        <?php
        $step_type = $step_type ?? 'unknown';
        $handlers = apply_filters('datamachine_handlers', [], $step_type);

        foreach ($handlers as $handler_slug => $handler_config): 
            $template_slug = $handler_slug;
            ?>
            <div class="datamachine-handler-selection-card datamachine-modal-content" 
                 data-template="handler-settings/<?php echo esc_attr($template_slug); ?>"
                 data-context='<?php echo esc_attr(wp_json_encode(['handler_slug' => $handler_slug, 'step_type' => $step_type, 'pipeline_id' => $pipeline_id ?? '', 'flow_step_id' => $flow_step_id ?? ''])); ?>'
                 data-handler-slug="<?php echo esc_attr($handler_slug); ?>" 
                 data-step-type="<?php echo esc_attr($step_type); ?>"
                 role="button" 
                 tabindex="0">
                <h4 class="datamachine-handler-card-title"><?php echo esc_html($handler_config['label'] ?? ucfirst($handler_slug)); ?></h4>
                <?php if (!empty($handler_config['description'])): ?>
                    <p class="datamachine-handler-card-description"><?php echo esc_html($handler_config['description']); ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>