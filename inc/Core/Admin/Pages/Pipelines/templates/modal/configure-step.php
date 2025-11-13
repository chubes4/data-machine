<?php
/**
 * Step configuration modal content with AI model settings integration
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 */

if (!defined('WPINC')) {
    die;
}


$all_steps = apply_filters('datamachine_step_types', []);
$step_config_data = $all_steps[$step_type] ?? null;
$step_title = $step_config_data['label'] ?? ucfirst(str_replace('_', ' ', $step_type));

?>
<div class="datamachine-configure-step-container">
    <div class="datamachine-configure-step-header">
        <?php /* translators: %s: Step title/name */ ?>
        <h3><?php echo esc_html(sprintf(__('Configure %s Step', 'datamachine'), $step_title)); ?></h3>
        <?php /* translators: %s: Step type */ ?>
        <p><?php echo esc_html(sprintf(__('Set up your %s step configuration below.', 'datamachine'), $step_type)); ?></p>
        <?php if ($step_type === 'ai'): ?>
            <div class="datamachine-step-settings-note">
                <p class="description">
                    <strong><?php esc_html_e('Note:', 'datamachine'); ?></strong>
                    <?php esc_html_e('These settings will apply to all AI steps at this position across all flows in this pipeline.', 'datamachine'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php
    $all_step_settings = apply_filters('datamachine_step_settings', []);
    $step_config = $all_step_settings[$step_type] ?? null;
    
    if ($step_config && ($step_config['config_type'] ?? '') === 'ai_configuration'):
        if (!$pipeline_step_id) {
            echo '<div class="datamachine-error">
                <h4>' . esc_html__('Configuration Error', 'datamachine') . '</h4>
                <p>' . esc_html__('Pipeline Step ID is required for AI step configuration.', 'datamachine') . '</p>
                <p><em>' . esc_html__('Missing: pipeline_step_id', 'datamachine') . '</em></p>
            </div>';
            return;
        }
        
        $saved_step_config = apply_filters('datamachine_get_pipeline_step_config', [], $pipeline_step_id);
        
        $all_providers = apply_filters('ai_providers', []);
        $llm_providers = array_filter($all_providers, function($provider) {
            return isset($provider['type']) && $provider['type'] === 'llm';
        });
        $selected_provider = $saved_step_config['provider'] ?? '';
        $selected_model = $saved_step_config['model'] ?? '';
        
        if (empty($selected_model) && !empty($saved_step_config['providers'][$selected_provider]['model'])):
            $selected_model = $saved_step_config['providers'][$selected_provider]['model'];
        endif;
        // AI provider configuration now handled by React component
        // The ai_render_component filter has been removed from the library
        // This should be implemented as a React component in the frontend
    elseif ($step_config):
        ?>
        <div class="datamachine-generic-step-config">
            <?php /* translators: %s: Step type */ ?>
            <p><?php echo esc_html(sprintf(__('Configuration for %s steps is available.', 'datamachine'), $step_type)); ?></p>
        </div>
        <?php
    else:
        ?>
        <div class="datamachine-no-config">
            <?php /* translators: %s: Step type */ ?>
            <p><?php echo esc_html(sprintf(__('No configuration available for %s steps.', 'datamachine'), $step_type)); ?></p>
        </div>
        <?php
    endif;
    ?>
    
    <div class="datamachine-step-config-actions">
        <button type="button" class="button button-secondary datamachine-cancel-settings">
            <?php esc_html_e('Cancel', 'datamachine'); ?>
        </button>
        <button type="button" class="button button-primary datamachine-modal-close" 
                data-template="configure-step-action"
                data-context='<?php echo esc_attr(wp_json_encode(['step_type' => $step_type ?? '', 'pipeline_id' => $pipeline_id ?? '', 'pipeline_step_id' => $pipeline_step_id ?? ''])); ?>'>
            <?php esc_html_e('Save AI Model Settings', 'datamachine'); ?>
        </button>
    </div>
</div>