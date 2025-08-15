<?php
/**
 * Temperature Slider Template
 * 
 * Renders a temperature/creativity slider for AI provider configuration.
 * This is an optional component that can be included via configuration.
 *
 * Available variables:
 * @var string $unique_id - Unique form identifier
 * @var array $provider_config - Provider configuration data
 * @var array $config - Component-specific configuration
 */

defined('ABSPATH') || exit;

// Use standard field name - AI HTTP Client no longer stores step-specific configuration
$field_name = 'ai_temperature';

// Get current temperature value from passed config (step-scoped)
// Temperature is OPTIONAL - no default value provided, only use if explicitly configured
$current_temp = isset($config['value']) ? $config['value'] : '';
$label = $config['label'] ?? 'Temperature (Optional)';
$help_text = $config['help_text'] ?? 'Controls randomness. Leave empty to use provider defaults. Note: Not supported by all models (e.g., OpenAI reasoning models).';
$min_value = $config['min'] ?? 0;
$max_value = $config['max'] ?? 1;
$step_value = $config['step'] ?? 0.1;
?>

<tr class="form-field">
    <th scope="row">
        <label for="<?php echo esc_attr($unique_id); ?>_temperature"><?php echo esc_html($label); ?></label>
    </th>
    <td>
        <div class="temperature-control">
            <input type="range" 
                   id="<?php echo esc_attr($unique_id); ?>_temperature" 
                   name="<?php echo esc_attr($field_name); ?>" 
                   value="<?php echo esc_attr($current_temp); ?>" 
                   min="<?php echo esc_attr($min_value); ?>" 
                   max="<?php echo esc_attr($max_value); ?>" 
                   step="<?php echo esc_attr($step_value); ?>" 
                   data-component-id="<?php echo esc_attr($unique_id); ?>" 
                   data-component-type="temperature_slider" 
                   class="temperature-slider" 
                   oninput="document.getElementById('<?php echo esc_js($unique_id); ?>_temp_display').textContent = this.value" />
            <span id="<?php echo esc_attr($unique_id); ?>_temp_display" class="temperature-display"><?php echo esc_html($current_temp); ?></span>
        </div>
        <br><small class="description"><?php echo esc_html($help_text); ?></small>
    </td>
</tr>