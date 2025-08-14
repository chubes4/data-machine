<?php
/**
 * Max Tokens Input Template
 * 
 * Renders a number input for maximum tokens configuration.
 * This is an optional component that can be included via configuration.
 *
 * Available variables:
 * @var string $unique_id - Unique form identifier
 * @var array $provider_config - Provider configuration data
 * @var array $config - Component-specific configuration
 */

defined('ABSPATH') || exit;

// Use standard field name - AI HTTP Client no longer stores step-specific configuration
$field_name = 'ai_max_tokens';

// Get current max tokens value and component configuration
$current_max_tokens = $provider_config['max_tokens'] ?? '';
$label = $config['label'] ?? 'Max Tokens';
$help_text = $config['help_text'] ?? 'Maximum number of tokens to generate. Leave empty for provider default.';
$placeholder = $config['placeholder'] ?? 'e.g., 1000';
$min_value = $config['min'] ?? 1;
$max_value = $config['max'] ?? 4096;
?>

<tr class="form-field">
    <th scope="row">
        <label for="<?php echo esc_attr($unique_id); ?>_max_tokens"><?php echo esc_html($label); ?></label>
    </th>
    <td>
        <input type="number" 
               id="<?php echo esc_attr($unique_id); ?>_max_tokens" 
               name="<?php echo esc_attr($field_name); ?>" 
               value="<?php echo esc_attr($current_max_tokens); ?>" 
               min="<?php echo esc_attr($min_value); ?>" 
               max="<?php echo esc_attr($max_value); ?>" 
               data-component-id="<?php echo esc_attr($unique_id); ?>" 
               data-component-type="max_tokens_input" 
               class="small-text" 
               placeholder="<?php echo esc_attr($placeholder); ?>" />
        <br><small class="description"><?php echo esc_html($help_text); ?></small>
    </td>
</tr>