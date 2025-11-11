<?php
/**
 * Modal Utilities
 *
 * Provides utility functions for modal field rendering.
 * Used by Pipelines page and other components that need dynamic field rendering.
 *
 * @package DataMachine
 * @subpackage Core\Admin\Modal
 * @since NEXT_VERSION
 */

namespace DataMachine\Core\Admin\Modal;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ModalUtils
{
    /**
     * Render settings field from configuration.
     *
     * Generates HTML for a form field based on configuration array.
     * Uses the datamachine_render_template filter to render via template system.
     *
     * @param string $field_name Field identifier
     * @param array $field_config Field configuration (type, label, description, options, attributes)
     * @param mixed $current_value Current field value
     * @return string Rendered HTML
     */
    public static function render_settings_field(string $field_name, array $field_config, $current_value = null): string
    {
        $attributes = $field_config['attributes'] ?? [];

        // Build HTML attributes
        $attrs = '';
        foreach ($attributes as $attr => $value) {
            $attrs .= ' ' . esc_attr($attr) . '="' . esc_attr($value) . '"';
        }

        // Prepare field template data
        $template_data = [
            'field_name' => $field_name,
            'field_config' => $field_config,
            'current_value' => $current_value,
            'attrs' => $attrs,
            'label' => $field_config['label'] ?? ucfirst(str_replace('_', ' ', $field_name)),
            'description' => $field_config['description'] ?? '',
            'options' => $field_config['options'] ?? []
        ];

        return apply_filters('datamachine_render_template', '', 'modal/fields', $template_data);
    }
}
