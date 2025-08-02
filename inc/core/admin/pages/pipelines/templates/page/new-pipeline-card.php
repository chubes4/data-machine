<?php
/**
 * Pipeline Card Template
 *
 * Universal editable pipeline form template.
 * Used for both new pipelines and existing pipelines.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Extract pipeline data (always expects a valid pipeline object/array)
$pipeline_id = is_object($pipeline) ? $pipeline->pipeline_id : $pipeline['pipeline_id'];
$pipeline_name = is_object($pipeline) ? $pipeline->pipeline_name : $pipeline['pipeline_name'];
$pipeline_steps = is_object($pipeline) ? json_decode($pipeline->step_configuration, true) : json_decode($pipeline['step_configuration'], true);
$pipeline_steps = is_array($pipeline_steps) ? $pipeline_steps : [];
$step_count = count($pipeline_steps);

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
        </div>
    </div>
    
    <!-- Pipeline Steps Section (Template Level) -->
    <div class="dm-pipeline-steps-section">
        <div class="dm-section-header">
            <h4><?php esc_html_e('Pipeline Steps', 'data-machine'); ?></h4>
            <p class="dm-section-description"><?php esc_html_e('Define the step sequence for this pipeline', 'data-machine'); ?></p>
        </div>
        <div class="dm-pipeline-steps">
            <?php if (!empty($pipeline_steps)): ?>
                <?php foreach ($pipeline_steps as $step): ?>
                    <?php 
                    $step_type = $step['step_type'] ?? 'unknown';
                    $step_label = $step['label'] ?? ucfirst(str_replace('_', ' ', $step_type));
                    ?>
                    <div class="dm-step-card dm-pipeline-step" data-step-type="<?php echo esc_attr($step_type); ?>">
                        <div class="dm-step-header">
                            <div class="dm-step-title"><?php echo esc_html($step_label); ?></div>
                            <div class="dm-step-actions">
                                <button type="button" class="button button-small button-link-delete dm-modal-trigger" 
                                        data-template="delete-step"
                                        data-context='{"step_type":"<?php echo esc_attr($step_type); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
                                    <?php esc_html_e('Delete', 'data-machine'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="dm-step-body">
                            <div class="dm-step-type-badge dm-step-<?php echo esc_attr($step_type); ?>">
                                <?php echo esc_html(ucfirst($step_type)); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Always available Add Step button for independent pipeline editing -->
            <div class="dm-step-card dm-placeholder-step">
                <div class="dm-placeholder-step-content">
                    <button type="button" class="button button-primary dm-modal-trigger"
                            data-template="step-selection"
                            data-context='{"context":"pipeline_builder","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
                        <?php esc_html_e('Add Step', 'data-machine'); ?>
                    </button>
                    <p class="dm-placeholder-description"><?php esc_html_e('Choose a step type to add to your pipeline', 'data-machine'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Associated Flows -->
    <div class="dm-pipeline-flows">
        <div class="dm-flows-header">
            <h4><?php esc_html_e('Flow Instances', 'data-machine'); ?></h4>
            <p class="dm-section-description"><?php esc_html_e('Each flow is a configured instance of the pipeline above', 'data-machine'); ?></p>
        </div>
        <div class="dm-flows-list">
            <div class="dm-flow-instance-card dm-placeholder-flow" data-flow-id="new">
                <div class="dm-flow-header">
                    <div class="dm-flow-title-section">
                        <?php echo '<input type="text" class="dm-flow-title-input" placeholder="' . esc_attr__('Enter flow name...', 'data-machine') . '" />'; ?>
                        <div class="dm-flow-status">
                            <span class="dm-schedule-status dm-status-inactive">
                                <?php esc_html_e('Inactive', 'data-machine'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="dm-flow-actions">
                        <!-- Flow saving managed at pipeline level -->
                    </div>
                </div>
                
                <!-- Flow Steps (mirrors all pipeline steps) -->
                <div class="dm-flow-steps-section">
                    <div class="dm-flow-steps">
                        <?php if (!empty($pipeline_steps)): ?>
                            <?php foreach ($pipeline_steps as $step): ?>
                                <?php 
                                $step_type = $step['step_type'] ?? 'unknown';
                                $step_label = $step['label'] ?? ucfirst(str_replace('_', ' ', $step_type));
                                
                                // Dynamic handler discovery using parameter-based filter system
                                $available_handlers = apply_filters('dm_get_handlers', null, $step_type);
                                $has_handlers = !empty($available_handlers);
                                
                                // AI steps don't use traditional handlers - they use internal multi-provider client
                                $step_uses_handlers = ($step_type !== 'ai');
                                ?>
                                <div class="dm-step-card dm-flow-step" data-flow-id="new" data-step-type="<?php echo esc_attr($step_type); ?>">
                                    <div class="dm-step-header">
                                        <div class="dm-step-title"><?php echo esc_html($step_label); ?></div>
                                        <div class="dm-step-actions">
                                            <?php if ($has_handlers && $step_uses_handlers): ?>
                                                <button type="button" class="button button-small dm-modal-trigger" 
                                                        data-template="handler-selection"
                                                        data-context='{"step_type":"<?php echo esc_attr($step_type); ?>","flow_id":"new"}'>
                                                    <?php esc_html_e('Add Handler', 'data-machine'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="dm-step-body">
                                        <div class="dm-step-type-badge dm-step-<?php echo esc_attr($step_type); ?>">
                                            <?php echo esc_html(ucfirst($step_type)); ?>
                                        </div>
                                        
                                        <!-- Configured Handlers for this step (only for steps that use handlers) -->
                                        <?php if ($step_uses_handlers): ?>
                                            <div class="dm-step-handlers">
                                                <div class="dm-no-handlers">
                                                    <span><?php esc_html_e('No handlers configured', 'data-machine'); ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- Always show flow placeholder for consistent state management -->
                        <div class="dm-step-card dm-flow-step dm-placeholder-flow-step">
                            <div class="dm-placeholder-step-content">
                                <p class="dm-placeholder-description">
                                    <?php if (empty($pipeline_steps)): ?>
                                        <?php esc_html_e('Add steps to the pipeline above to configure handlers for this flow', 'data-machine'); ?>
                                    <?php else: ?>
                                        <?php esc_html_e('Flow steps will appear here as you add pipeline steps', 'data-machine'); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="dm-flow-meta">
                    <small class="dm-placeholder-text"><?php esc_html_e('Add steps to the pipeline above to configure handlers for this flow', 'data-machine'); ?></small>
                </div>
            </div>
        </div>
    </div>
</div>