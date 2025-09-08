<?php
/**
 * Modal Form Fields Template
 *
 * Unified template for rendering all form field types in modals.
 * Replaces direct HTML generation with clean template separation.
 *
 * @package DataMachine\Core\Admin\Modal\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

$field_type = $field_config['type'] ?? 'text';
$label = $field_config['label'] ?? '';
$description = $field_config['description'] ?? '';
$options = $field_config['options'] ?? [];
$global_indicator = $field_config['global_indicator'] ?? '';
$is_disabled = !empty($field_config['disabled']);
$disabled_attr = $is_disabled ? ' disabled' : '';
?>

<?php if ($field_type === 'section'): ?>
    <h4><?php echo esc_html($label); ?></h4>
    <?php if ($description): ?>
        <p><?php echo esc_html($description); ?></p>
    <?php endif; ?>

<?php else: ?>
    <div class="dm-form-field">
        <?php if ($label && $field_type !== 'checkbox'): ?>
            <label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($label); ?></label>
        <?php endif; ?>
        
        <?php
        switch ($field_type) {
            case 'text':
            case 'url':
            case 'email':
                ?>
                <input type="<?php echo esc_attr($field_type); ?>" 
                       id="<?php echo esc_attr($field_name); ?>" 
                       name="<?php echo esc_attr($field_name); ?>" 
                       value="<?php echo esc_attr($current_value ?? ''); ?>" 
                       class="regular-text"<?php echo $attrs; ?><?php echo $disabled_attr; ?> />
                <?php
                break;
                
            case 'number':
                ?>
                <input type="number" 
                       id="<?php echo esc_attr($field_name); ?>" 
                       name="<?php echo esc_attr($field_name); ?>" 
                       value="<?php echo esc_attr($current_value ?? ''); ?>" 
                       class="regular-text"<?php echo $attrs; ?><?php echo $disabled_attr; ?> />
                <?php
                break;
                
            case 'textarea':
                ?>
                <textarea id="<?php echo esc_attr($field_name); ?>" 
                          name="<?php echo esc_attr($field_name); ?>" 
                          rows="5" 
                          class="large-text"<?php echo $attrs; ?><?php echo $disabled_attr; ?>><?php echo esc_textarea($current_value ?? ''); ?></textarea>
                <?php
                break;
                
            case 'select':
                ?>
                <select id="<?php echo esc_attr($field_name); ?>" 
                        name="<?php echo esc_attr($field_name); ?>" 
                        class="regular-text"<?php echo $attrs; ?><?php echo $disabled_attr; ?>>
                    <?php foreach ($options as $option_value => $option_label): ?>
                        <option value="<?php echo esc_attr($option_value); ?>"<?php selected($current_value, $option_value); ?>>
                            <?php echo esc_html($option_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;
                
            case 'checkbox':
                ?>
                <label>
                    <input type="checkbox" 
                           id="<?php echo esc_attr($field_name); ?>" 
                           name="<?php echo esc_attr($field_name); ?>" 
                           value="1"<?php checked(!empty($current_value)); ?><?php echo $attrs; ?><?php echo $disabled_attr; ?> />
                    <?php echo esc_html($label); ?>
                </label>
                <?php
                break;
                
            case 'readonly':
                ?>
                <input type="text" 
                       id="<?php echo esc_attr($field_name); ?>" 
                       name="<?php echo esc_attr($field_name); ?>" 
                       value="<?php echo esc_attr($field_config['value'] ?? $current_value ?? ''); ?>" 
                       class="regular-text dm-readonly-field" 
                       readonly<?php echo $attrs; ?> />
                <?php
                break;
        }
        ?>
        
        <?php if ($global_indicator): ?>
            <p class="description dm-global-indicator">
                <strong><?php echo esc_html($global_indicator); ?></strong>
            </p>
        <?php elseif ($description): ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>