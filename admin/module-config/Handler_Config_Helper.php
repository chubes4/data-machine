<?php
/**
 * Handler_Config_Helper: Utility for extracting handler-specific config from a module.
 *
 * @package    Data_Machine
 * @subpackage module-config
 * @since      NEXT_VERSION
 */

if (!defined('ABSPATH')) {
    exit;
}

class Handler_Config_Helper {
    /**
     * Extracts the config sub-array for a given handler type from the module object.
     *
     * @param object $module The module object (should have data_source_config property).
     * @param string $handler_type The handler type/slug (e.g., 'reddit', 'rss').
     * @return array The config sub-array for the handler.
     * @throws Exception If config is missing or malformed.
     */
    public static function get_handler_config($module, $handler_type) {
        if (!isset($module->data_source_config) || !is_array($module->data_source_config)) {
            throw new Exception('Module configuration is missing or invalid.');
        }
        if (!isset($module->data_source_config[$handler_type]) || !is_array($module->data_source_config[$handler_type])) {
            throw new Exception('No configuration found for handler type: ' . esc_html($handler_type));
        }
        return $module->data_source_config[$handler_type];
    }

    /**
     * Render a module config field (moved from template).
     *
     * @param string $handler_type
     * @param string $handler_slug
     * @param string $field_key
     * @param array $field_config
     * @param mixed $current_value
     */
    public static function dm_render_module_config_field($handler_type, $handler_slug, $field_key, $field_config, $current_value) {
        $field_id = esc_attr("{$handler_type}_{$handler_slug}_{$field_key}");
        $field_name = esc_attr("{$handler_type}_config[{$handler_slug}][{$field_key}]");
        $label = isset($field_config['label']) ? esc_html($field_config['label']) : '';
        $description = isset($field_config['description']) ? '<p class="description">' . esc_html($field_config['description']) . '</p>' : '';
        $type = $field_config['type'] ?? 'text';
        $options = $field_config['options'] ?? [];
        $default = $field_config['default'] ?? '';
        $value = $current_value ?? $default;

        $taxonomy_row_attrs = '';
        if (isset($field_config['post_types'])) {
            $taxonomy_row_attrs = ' class="dm-taxonomy-row" data-taxonomy="' . esc_attr($field_key) . '"';
        }
        echo '<tr' . $taxonomy_row_attrs . '>';
        echo '<th scope="row"><label for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label></th>';
        echo '<td>';

        switch ($type) {
            case 'text':
                echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                break;
            case 'url':
                echo '<input type="url" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                break;
            case 'password':
                echo '<input type="password" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="" class="regular-text" placeholder="' . esc_attr__('Leave blank to keep current password', 'data-machine') . '" autocomplete="new-password" />';
                break;
            case 'number':
                $min = isset($field_config['min']) ? ' min="' . esc_attr($field_config['min']) . '"' : '';
                $max = isset($field_config['max']) ? ' max="' . esc_attr($field_config['max']) . '"' : '';
                $step = isset($field_config['step']) ? ' step="' . esc_attr($field_config['step']) . '"' : '';
                echo '<input type="number" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="small-text"' . $min . $max . $step . ' />';
                break;
            case 'textarea':
                $rows = isset($field_config['rows']) ? $field_config['rows'] : 5;
                $cols = isset($field_config['cols']) ? $field_config['cols'] : 50;
                echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" rows="' . esc_attr($rows) . '" cols="' . esc_attr($cols) . '">' . esc_textarea($value) . '</textarea>';
                break;
            case 'select':
                $select_attrs = '';
                if (isset($field_config['post_types']) && is_array($field_config['post_types'])) {
                    $post_types_string = implode(',', array_map('esc_attr', $field_config['post_types']));
                    $select_attrs .= ' data-post-types="' . $post_types_string . '"';
                }
                echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '"' . $select_attrs . '>';
                foreach ($options as $opt_value => $opt_label) {
                    echo '<option value="' . esc_attr($opt_value) . '" ' . selected($opt_value, $value, false) . '>' . esc_html($opt_label) . '</option>';
                }
                echo '</select>';
                break;
            case 'multiselect':
                $wrapper_id = $field_config['wrapper_id'] ?? $field_id . '_wrapper';
                $wrapper_style = $field_config['wrapper_style'] ?? '';
                $select_style = $field_config['select_style'] ?? 'min-height: 100px; width: 100%;';
                $current_values = is_array($value) ? $value : [];
                echo '<div id="' . esc_attr($wrapper_id) . '" class="dm-tags-wrapper" style="' . esc_attr($wrapper_style) . '">';
                echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '[]" multiple style="' . esc_attr($select_style) . '">';
                foreach ($options as $opt_value => $opt_label) {
                    $is_selected = in_array((string)$opt_value, array_map('strval', $current_values));
                    echo '<option value="' . esc_attr($opt_value) . '" ' . ($is_selected ? 'selected' : '') . '>' . esc_html($opt_label) . '</option>';
                }
                echo '</select>';
                echo wp_kses_post($description);
                echo '</div>';
                $description = '';
                break;
            case 'checkbox':
                echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="1" ' . checked(1, $value, false) . ' />';
                break;
            case 'button':
                $button_id = $field_config['button_id'] ?? $field_id . '_button';
                $button_text = $field_config['button_text'] ?? 'Button';
                $button_class = $field_config['button_class'] ?? 'button dm-sync-button';
                $sync_type_attr = ($handler_type === 'data_source') ? 'data-sync-type="data_source"' : 'data-sync-type="output"';
                $feedback_id = $field_config['feedback_id'] ?? $button_id . '_feedback';
                echo '<button type="button" id="' . esc_attr($button_id) . '" class="' . esc_attr($button_class) . '" ' . $sync_type_attr . '>' . esc_html($button_text) . '</button>';
                echo '<span class="spinner" style="float: none; vertical-align: middle;"></span>';
                echo wp_kses_post($description);
                echo '<div id="' . esc_attr($feedback_id) . '" class="dm-sync-feedback" style="margin-top: 5px;"></div>';
                $description = '';
                break;
            default:
                echo '<!-- Field type ' . esc_html($type) . ' not implemented -->';
                break;
        }
        echo wp_kses_post($description);
        echo '</td>';
        echo '</tr>';
    }
} 