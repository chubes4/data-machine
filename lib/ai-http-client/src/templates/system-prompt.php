<?php
/**
 * System Prompt Field Template
 * 
 * Renders a textarea for system prompt configuration.
 * This is an optional component that can be included via configuration.
 *
 * Available variables:
 * @var string $unique_id - Unique form identifier
 * @var string $plugin_context - Plugin context for configuration isolation
 * @var array $provider_config - Provider configuration data
 * @var string $step_id - Optional step ID for step-aware field naming
 * @var array $config - Component-specific configuration
 */

defined('ABSPATH') || exit;

// Generate step-aware field name
$field_name = 'ai_system_prompt';
if (!empty($step_id)) {
    $field_name = 'ai_step_' . sanitize_key($step_id) . '_system_prompt';
}

// Get current system prompt value from step config (if step-aware) or all_config (step-scoped)
// System prompt is STEP-SCOPED, not provider-specific
$current_prompt = isset($all_config['system_prompt']) ? $all_config['system_prompt'] : '';
$label = $config['label'] ?? 'System Prompt';
$help_text = $config['help_text'] ?? 'Instructions that define the AI\'s behavior and role for this task.';
$placeholder = $config['placeholder'] ?? 'Enter system instructions for the AI...';
$rows = $config['rows'] ?? 4;
?>

<tr class="form-field">
    <th scope="row">
        <label for="<?php echo esc_attr($unique_id); ?>_system_prompt"><?php echo esc_html($label); ?></label>
    </th>
    <td>
        <textarea id="<?php echo esc_attr($unique_id); ?>_system_prompt" 
                  name="<?php echo esc_attr($field_name); ?>" 
                  rows="<?php echo esc_attr($rows); ?>" 
                  data-component-id="<?php echo esc_attr($unique_id); ?>" 
                  data-component-type="system_prompt_field" 
                  class="large-text" 
                  placeholder="<?php echo esc_attr($placeholder); ?>"><?php echo esc_textarea($current_prompt); ?></textarea>
        <br><small class="description"><?php echo esc_html($help_text); ?></small>
    </td>
</tr>