<?php
/**
 * Unified template for rendering all form field types in modals
 *
 * @package DataMachine\Core\Admin\Modal\Templates
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
        // Handle custom attributes: extract class for merging, keep other attributes
        $extra_attrs = $attrs;
        $custom_classes = '';
        if (preg_match('/\bclass="([^"]*)"/', $attrs, $matches)) {
            $custom_classes = $matches[1];
            $extra_attrs = preg_replace('/\bclass="[^"]*"/', '', $attrs);
        }

        switch ($field_type) {
            case 'text':
            case 'url':
            case 'email':
                $merged_classes = $custom_classes ? 'regular-text ' . $custom_classes : 'regular-text';
                ?>
                <input type="<?php echo esc_attr($field_type); ?>"
                       id="<?php echo esc_attr($field_name); ?>"
                       name="<?php echo esc_attr($field_name); ?>"
                       value="<?php echo esc_attr($current_value ?? ''); ?>"
                       class="<?php echo esc_attr($merged_classes); ?>"<?php echo $extra_attrs; ?><?php echo esc_attr($disabled_attr); ?> />
                <?php
                break;
                
            case 'number':
                $merged_classes = $custom_classes ? 'regular-text ' . $custom_classes : 'regular-text';
                ?>
                <input type="number"
                       id="<?php echo esc_attr($field_name); ?>"
                       name="<?php echo esc_attr($field_name); ?>"
                       value="<?php echo esc_attr($current_value ?? ''); ?>"
                       class="<?php echo esc_attr($merged_classes); ?>"<?php echo $extra_attrs; ?><?php echo esc_attr($disabled_attr); ?> />
                <?php
                break;
                
            case 'textarea':
                $merged_classes = $custom_classes ? 'large-text ' . $custom_classes : 'large-text';
                ?>
                <textarea id="<?php echo esc_attr($field_name); ?>"
                          name="<?php echo esc_attr($field_name); ?>"
                          rows="5"
                          class="<?php echo esc_attr($merged_classes); ?>"<?php echo $extra_attrs; ?><?php echo esc_attr($disabled_attr); ?>><?php echo esc_textarea($current_value ?? ''); ?></textarea>
                <?php
                break;
                
            case 'select':
                $merged_classes = $custom_classes ? 'regular-text ' . $custom_classes : 'regular-text';
                ?>
                <select id="<?php echo esc_attr($field_name); ?>"
                        name="<?php echo esc_attr($field_name); ?>"
                        class="<?php echo esc_attr($merged_classes); ?>"<?php echo $extra_attrs; ?><?php echo esc_attr($disabled_attr); ?>>
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
                           value="1"<?php if ($custom_classes): ?> class="<?php echo esc_attr($custom_classes); ?>"<?php endif; ?><?php checked(!empty($current_value)); ?><?php echo $extra_attrs; ?><?php echo esc_attr($disabled_attr); ?> />
                    <?php echo esc_html($label); ?>
                </label>
                <?php
                break;
                
            case 'readonly':
                $merged_classes = $custom_classes ? 'regular-text dm-readonly-field ' . $custom_classes : 'regular-text dm-readonly-field';
                ?>
                <input type="text"
                       id="<?php echo esc_attr($field_name); ?>"
                       name="<?php echo esc_attr($field_name); ?>"
                       value="<?php echo esc_attr($field_config['value'] ?? $current_value ?? ''); ?>"
                       class="<?php echo esc_attr($merged_classes); ?>"
                       readonly<?php echo $extra_attrs; ?> />
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