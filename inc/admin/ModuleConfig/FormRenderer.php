<?php
/**
 * Form Renderer for Handler Settings
 * 
 * Converts field definition arrays into HTML forms programmatically,
 * eliminating the need for template files and creating a unified form system.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/ModuleConfig
 * @since      0.1.0
 */

namespace DataMachine\Admin\ModuleConfig;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class FormRenderer {

    /**
     * Render form fields from field definition array.
     *
     * @param array $fields Field definitions from get_settings_fields()
     * @param array $current_config Current configuration values
     * @param string $handler_slug Handler slug for field name prefixes
     * @return string HTML form table
     */
    public static function render_form_fields(array $fields, array $current_config = [], string $handler_slug = ''): string {
        if (empty($fields)) {
            return '<p>' . __('No configuration options available for this handler.', 'data-machine') . '</p>';
        }

        $html = '<table class="form-table">';
        
        foreach ($fields as $field_name => $field_config) {
            $html .= self::render_field($field_name, $field_config, $current_config, $handler_slug);
        }
        
        $html .= '</table>';
        
        return $html;
    }

    /**
     * Render a single form field.
     *
     * @param string $field_name Field name/key
     * @param array $field_config Field configuration
     * @param array $current_config Current values
     * @param string $handler_slug Handler slug for naming
     * @return string HTML table row
     */
    private static function render_field(string $field_name, array $field_config, array $current_config, string $handler_slug): string {
        $field_type = $field_config['type'] ?? 'text';
        $label = $field_config['label'] ?? ucfirst(str_replace('_', ' ', $field_name));
        $description = $field_config['description'] ?? '';
        $required = $field_config['required'] ?? false;
        $current_value = $current_config[$field_name] ?? ($field_config['default'] ?? '');
        
        // Generate field ID and name
        $field_id = "data_source_{$handler_slug}_{$field_name}";
        $field_name_attr = "data_source_config[{$handler_slug}][{$field_name}]";
        
        $html = '<tr>';
        $html .= '<th scope="row">';
        $html .= '<label for="' . esc_attr($field_id) . '">' . esc_html($label);
        if ($required) {
            $html .= ' <span class="required">*</span>';
        }
        $html .= '</label>';
        $html .= '</th>';
        $html .= '<td>';
        
        // Render field based on type
        switch ($field_type) {
            case 'text':
                $html .= self::render_text_field($field_id, $field_name_attr, $current_value, $field_config);
                break;
            case 'url':
                $html .= self::render_url_field($field_id, $field_name_attr, $current_value, $field_config);
                break;
            case 'number':
                $html .= self::render_number_field($field_id, $field_name_attr, $current_value, $field_config);
                break;
            case 'select':
                $html .= self::render_select_field($field_id, $field_name_attr, $current_value, $field_config);
                break;
            case 'textarea':
                $html .= self::render_textarea_field($field_id, $field_name_attr, $current_value, $field_config);
                break;
            case 'checkbox':
                $html .= self::render_checkbox_field($field_id, $field_name_attr, $current_value, $field_config);
                break;
            default:
                $html .= self::render_text_field($field_id, $field_name_attr, $current_value, $field_config);
        }
        
        if ($description) {
            $html .= '<p class="description">' . esc_html($description) . '</p>';
        }
        
        $html .= '</td>';
        $html .= '</tr>';
        
        return $html;
    }

    /**
     * Render text input field.
     */
    private static function render_text_field(string $id, string $name, $value, array $config): string {
        $placeholder = isset($config['placeholder']) ? ' placeholder="' . esc_attr($config['placeholder']) . '"' : '';
        $required = isset($config['required']) && $config['required'] ? ' required' : '';
        $class = $config['class'] ?? 'regular-text';
        
        return '<input type="text" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="' . esc_attr($class) . '"' . $placeholder . $required . '>';
    }

    /**
     * Render URL input field.
     */
    private static function render_url_field(string $id, string $name, $value, array $config): string {
        $placeholder = isset($config['placeholder']) ? ' placeholder="' . esc_attr($config['placeholder']) . '"' : '';
        $required = isset($config['required']) && $config['required'] ? ' required' : '';
        $class = $config['class'] ?? 'regular-text';
        
        return '<input type="url" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="' . esc_attr($class) . '"' . $placeholder . $required . '>';
    }

    /**
     * Render number input field.
     */
    private static function render_number_field(string $id, string $name, $value, array $config): string {
        $min = isset($config['min']) ? ' min="' . esc_attr($config['min']) . '"' : '';
        $max = isset($config['max']) ? ' max="' . esc_attr($config['max']) . '"' : '';
        $step = isset($config['step']) ? ' step="' . esc_attr($config['step']) . '"' : '';
        $required = isset($config['required']) && $config['required'] ? ' required' : '';
        $class = $config['class'] ?? 'small-text';
        
        return '<input type="number" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="' . esc_attr($class) . '"' . $min . $max . $step . $required . '>';
    }

    /**
     * Render select dropdown field.
     */
    private static function render_select_field(string $id, string $name, $value, array $config): string {
        $required = isset($config['required']) && $config['required'] ? ' required' : '';
        $class = $config['class'] ?? '';
        $disabled = isset($config['disabled']) && $config['disabled'] ? ' disabled' : '';
        
        $html = '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" class="' . esc_attr($class) . '"' . $required . $disabled . '>';
        
        $options = $config['options'] ?? [];
        foreach ($options as $option_value => $option_label) {
            $selected = selected($value, (string)$option_value, false);
            $html .= '<option value="' . esc_attr($option_value) . '"' . $selected . '>' . esc_html($option_label) . '</option>';
        }
        
        $html .= '</select>';
        
        return $html;
    }

    /**
     * Render textarea field.
     */
    private static function render_textarea_field(string $id, string $name, $value, array $config): string {
        $placeholder = isset($config['placeholder']) ? ' placeholder="' . esc_attr($config['placeholder']) . '"' : '';
        $required = isset($config['required']) && $config['required'] ? ' required' : '';
        $class = $config['class'] ?? 'large-text';
        $rows = $config['rows'] ?? 5;
        $cols = $config['cols'] ?? 50;
        
        return '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" class="' . esc_attr($class) . '" rows="' . esc_attr($rows) . '" cols="' . esc_attr($cols) . '"' . $placeholder . $required . '>' . esc_textarea($value) . '</textarea>';
    }

    /**
     * Render checkbox field.
     */
    private static function render_checkbox_field(string $id, string $name, $value, array $config): string {
        $checked = checked($value, '1', false);
        $checkbox_value = $config['value'] ?? '1';
        
        $html = '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($checkbox_value) . '"' . $checked . '>';
        
        if (isset($config['label_text'])) {
            $html .= ' <label for="' . esc_attr($id) . '">' . esc_html($config['label_text']) . '</label>';
        }
        
        return $html;
    }
}