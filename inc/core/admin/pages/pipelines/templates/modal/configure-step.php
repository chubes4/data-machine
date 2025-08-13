<?php
/**
 * Configure Step Modal Template
 *
 * Pure rendering template for step configuration modal content.
 * Handles AI step configuration with proper modal action buttons.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Context auto-resolved by PipelineContextManager filter before template renders
// All required context variables are available: $step_type, $pipeline_id, $pipeline_step_id

?>
<div class="dm-configure-step-container">
    <div class="dm-configure-step-header">
        <h3><?php echo esc_html(sprintf(__('Configure %s Step', 'data-machine'), ucfirst($step_type))); ?></h3>
        <p><?php echo esc_html(sprintf(__('Set up your %s step configuration below.', 'data-machine'), $step_type)); ?></p>
    </div>
    
    <?php
    // Get step configuration from step definition via filter discovery
    $all_step_settings = apply_filters('dm_step_settings', []);
    $step_config = $all_step_settings[$step_type] ?? null;
    
    // Render based on step-provided configuration
    if ($step_config && ($step_config['config_type'] ?? '') === 'ai_configuration'):
        // FAIL FAST - require pipeline_step_id for unique AI step configuration
        if (!$pipeline_step_id) {
            echo '<div class="dm-error">
                <h4>' . __('Configuration Error', 'data-machine') . '</h4>
                <p>' . __('Pipeline Step ID is required for AI step configuration.', 'data-machine') . '</p>
                <p><em>' . __('Missing: pipeline_step_id', 'data-machine') . '</em></p>
            </div>';
            return;
        }
        
        // Get step configuration to make saved models available to JavaScript
        $step_config = apply_filters('dm_get_flow_step_config', [], $pipeline_step_id);
        
        // Add hidden fields for saved models per provider
        if (!empty($step_config['providers'])) {
            foreach ($step_config['providers'] as $provider => $provider_config) {
                if (!empty($provider_config['model'])) {
                    echo '<input type="hidden" id="saved_' . esc_attr($provider) . '_model" value="' . esc_attr($provider_config['model']) . '" />';
                }
            }
        }
        
        // Render AI HTTP Client components using template-based filter system
        echo apply_filters('ai_render_component', '', [
            'step_id' => $pipeline_step_id, // Unique step-aware configuration
            'show_save_button' => false, // Hide built-in save button - Data Machine provides custom save
            'temperature' => [
                'label' => __('Temperature', 'data-machine'),
                'help_text' => __('Controls randomness in AI responses. 0 = deterministic, 1 = maximum randomness.', 'data-machine')
            ],
            'system_prompt' => [
                'label' => __('AI Processing Instructions', 'data-machine'),
                'placeholder' => __('Define how the AI should process data from previous pipeline steps...', 'data-machine'),
                'help_text' => __('Instructions that guide AI behavior for this pipeline step. The AI will receive data from all previous steps automatically.', 'data-machine'),
                'rows' => 6
            ]
        ]);
    elseif ($step_config):
        // Future: Other step types can define their own configuration rendering
        ?>
        <div class="dm-generic-step-config">
            <p><?php echo esc_html(sprintf(__('Configuration for %s steps is available.', 'data-machine'), $step_type)); ?></p>
        </div>
        <?php
    else:
        // No configuration available for this step type
        ?>
        <div class="dm-no-config">
            <p><?php echo esc_html(sprintf(__('No configuration available for %s steps.', 'data-machine'), $step_type)); ?></p>
        </div>
        <?php
    endif;
    ?>
    
    <div class="dm-step-config-actions">
        <button type="button" class="button button-secondary dm-cancel-settings">
            <?php esc_html_e('Cancel', 'data-machine'); ?>
        </button>
        <button type="button" class="button button-primary dm-modal-close" 
                data-template="configure-step-action"
                data-context='{"step_type":"<?php echo esc_attr($step_type ?? ''); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id ?? ''); ?>","pipeline_step_id":"<?php echo esc_attr($pipeline_step_id ?? ''); ?>"}'>
            <?php esc_html_e('Save Step Configuration', 'data-machine'); ?>
        </button>
    </div>
</div>