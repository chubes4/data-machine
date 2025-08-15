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

// Get step title from registered step configuration
$all_steps = apply_filters('dm_steps', []);
$step_config_data = $all_steps[$step_type] ?? null;
$step_title = $step_config_data['label'] ?? ucfirst(str_replace('_', ' ', $step_type));

?>
<div class="dm-configure-step-container">
    <div class="dm-configure-step-header">
        <h3><?php echo esc_html(sprintf(__('Configure %s Step', 'data-machine'), $step_title)); ?></h3>
        <p><?php echo esc_html(sprintf(__('Set up your %s step configuration below.', 'data-machine'), $step_type)); ?></p>
        <?php if ($step_type === 'ai'): ?>
            <div class="dm-step-settings-note">
                <p class="description">
                    <strong><?php esc_html_e('Note:', 'data-machine'); ?></strong>
                    <?php esc_html_e('These settings will apply to all AI steps at this position across all flows in this pipeline.', 'data-machine'); ?>
                </p>
            </div>
        <?php endif; ?>
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
        
        // Get saved step configuration from pipeline database
        $saved_step_config = apply_filters('dm_get_pipeline_step_config', [], $pipeline_step_id);
        
        // Extract saved values with defaults - use first available provider instead of hardcoding OpenAI
        $all_providers = apply_filters('ai_providers', []);
        $llm_providers = array_filter($all_providers, function($provider) {
            return isset($provider['type']) && $provider['type'] === 'llm';
        });
        // No default: only use saved value, otherwise empty
        $selected_provider = isset($saved_step_config['provider']) ? $saved_step_config['provider'] : '';
        $selected_model = $saved_step_config['model'] ?? '';
        $system_prompt_value = $saved_step_config['system_prompt'] ?? '';
        
        // Check for provider-specific models
        if (empty($selected_model) && !empty($saved_step_config['providers'][$selected_provider]['model'])) {
            $selected_model = $saved_step_config['providers'][$selected_provider]['model'];
        }
        // Render AI HTTP Client components using template-based filter system
        echo apply_filters('ai_render_component', '', [
            'selected_provider' => $selected_provider,
            'selected_model' => $selected_model,
            'system_prompt_value' => $system_prompt_value,
            'show_save_button' => false, // Hide built-in save button - Data Machine provides custom save
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