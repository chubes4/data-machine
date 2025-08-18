<?php
/**
 * Universal Modal AJAX Handler
 *
 * Handles all modal content requests through a single, universal AJAX endpoint.
 * Routes to the dm_modals filter system for component-specific content generation.
 *
 * This enables the universal modal architecture where any component can register
 * modal content via the dm_modals filter without needing custom AJAX handlers.
 * Eliminates component-specific modal AJAX handlers through unified architecture.
 *
 * @package DataMachine\Core\Admin\Modal
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Modal;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Universal Modal AJAX Handler Class
 *
 * Provides a single AJAX endpoint for all modal content requests across
 * the entire Data Machine admin interface. Components register modal content
 * via the dm_modals filter system.
 *
 * @since 1.0.0
 */
class ModalAjax
{
    /**
     * Constructor - Register AJAX actions
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        add_action('wp_ajax_dm_get_modal_content', [$this, 'handle_get_modal_content']);
    }

    /**
     * Handle modal content AJAX requests
     *
     * Routes to the dm_modals filter system for component-specific content.
     * Maintains WordPress security standards with nonce verification and
     * capability checks.
     *
     * @since 1.0.0
     */
    public function handle_get_modal_content()
    {
        // WordPress security verification
        if (!check_ajax_referer('dm_ajax_actions', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security verification failed', 'data-machine')
            ]);
        }

        // Capability check
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions', 'data-machine')
            ]);
        }

        // Extract and sanitize template parameter
        $template = sanitize_text_field(wp_unslash($_POST['template'] ?? ''));

        if (empty($template)) {
            wp_send_json_error([
                'message' => __('Template parameter is required', 'data-machine')
            ]);
        }

        // Two-layer modal architecture: check registered modals first
        $all_modals = apply_filters('dm_modals', []);
        $modal_data = $all_modals[$template] ?? null;

        if ($modal_data) {
            $title = $modal_data['title'] ?? ucfirst(str_replace('-', ' ', $template));
            
            // Check if content is pre-rendered or needs dynamic rendering
            if (isset($modal_data['content'])) {
                // Pre-rendered content (static modals)
                $content = $modal_data['content'];
            } elseif (isset($modal_data['template'])) {
                // Dynamic content via dm_render_template (has access to AJAX context)
                $context = $_POST['context'] ?? [];
                if (is_string($context)) {
                    $context = json_decode($context, true) ?: [];
                }
                
                // Special handling for dynamic modals that need processed context
                $content = $this->render_dynamic_modal_content($modal_data['template'], $context);
            } else {
                $content = '';
            }
            
            wp_send_json_success([
                'content' => $content,
                'template' => $title
            ]);
        } else {
            // Fallback to dynamic modal rendering for unregistered templates
            $context = $_POST['context'] ?? [];
            if (is_string($context)) {
                $context = json_decode($context, true) ?: [];
            }
            
            $content = $this->render_dynamic_modal_content($template, $context);
            
            if (!empty($content)) {
                $title = ucfirst(str_replace('-', ' ', $template));
                wp_send_json_success([
                    'content' => $content,
                    'template' => $title
                ]);
            } else {
                wp_send_json_error([
                    'message' => sprintf(
                        /* translators: %s: modal template name */
                        __('Modal content not found for template: %s', 'data-machine'),
                        $template
                    )
                ]);
            }
        }
    }
    
    /**
     * Render dynamic modal content with processed context
     * 
     * @param string $template Template name
     * @param array $context AJAX context data
     * @return string Rendered content
     */
    private function render_dynamic_modal_content(string $template, array $context): string {
        // Check if this is a handler-specific template (starts with 'handler-settings/')
        if (strpos($template, 'handler-settings/') === 0) {
            // Extract handler template slug (e.g., 'files', 'wordpress_fetch', etc.)
            $handler_template_slug = substr($template, strlen('handler-settings/'));
            
            // Try handler-specific template first via dm_render_template filter
            $handler_specific_content = apply_filters('dm_render_template', '', "modal/handler-settings/{$handler_template_slug}", $context);
            
            if (!empty($handler_specific_content)) {
                return $handler_specific_content;
            }
            
            // Fall back to universal handler-settings template
            return apply_filters('dm_render_template', '', 'modal/handler-settings', $context);
        }
        
        // Use ONLY the universal template filter for non-handler-specific templates
        return apply_filters('dm_render_template', '', $template, $context);
    }
    
    
    /**
     * Render form field based on Settings field definition
     * 
     * @param string $field_name Field name attribute
     * @param array $field_config Field configuration from Settings class
     * @param mixed $current_value Current value for this field
     * @return string HTML form field element
     */
    public static function render_settings_field(string $field_name, array $field_config, $current_value = null): string {
        $field_type = $field_config['type'] ?? 'text';
        $label = $field_config['label'] ?? ucfirst(str_replace('_', ' ', $field_name));
        $description = $field_config['description'] ?? '';
        $options = $field_config['options'] ?? [];
        $attributes = $field_config['attributes'] ?? [];
        
        // Build attributes string
        $attrs = '';
        foreach ($attributes as $attr => $value) {
            $attrs .= ' ' . esc_attr($attr) . '="' . esc_attr($value) . '"';
        }
        
        $field_html = '';
        
        switch ($field_type) {
            case 'text':
            case 'url':
            case 'email':
                $field_html = sprintf(
                    '<input type="%s" id="%s" name="%s" value="%s" class="regular-text"%s />',
                    esc_attr($field_type),
                    esc_attr($field_name),
                    esc_attr($field_name),
                    esc_attr($current_value ?? ''),
                    $attrs
                );
                break;
                
            case 'number':
                $field_html = sprintf(
                    '<input type="number" id="%s" name="%s" value="%s" class="regular-text"%s />',
                    esc_attr($field_name),
                    esc_attr($field_name),
                    esc_attr($current_value ?? ''),
                    $attrs
                );
                break;
                
            case 'textarea':
                $field_html = sprintf(
                    '<textarea id="%s" name="%s" rows="5" class="large-text"%s>%s</textarea>',
                    esc_attr($field_name),
                    esc_attr($field_name),
                    $attrs,
                    esc_textarea($current_value ?? '')
                );
                break;
                
            case 'select':
                $field_html = sprintf('<select id="%s" name="%s" class="regular-text"%s>', 
                    esc_attr($field_name), 
                    esc_attr($field_name), 
                    $attrs
                );
                
                foreach ($options as $option_value => $option_label) {
                    $selected = ($current_value == $option_value) ? ' selected="selected"' : '';
                    $field_html .= sprintf(
                        '<option value="%s"%s>%s</option>',
                        esc_attr($option_value),
                        $selected,
                        esc_html($option_label)
                    );
                }
                $field_html .= '</select>';
                break;
                
            case 'checkbox':
                $checked = !empty($current_value) ? ' checked="checked"' : '';
                $field_html = sprintf(
                    '<label><input type="checkbox" id="%s" name="%s" value="1"%s%s /> %s</label>',
                    esc_attr($field_name),
                    esc_attr($field_name),
                    $checked,
                    $attrs,
                    esc_html($label)
                );
                // For checkboxes, don't show label separately
                $label = '';
                break;
                
            case 'readonly':
                // Read-only field for displaying values (like redirect URIs)
                $display_value = $field_config['value'] ?? $current_value ?? '';
                $field_html = sprintf(
                    '<input type="text" id="%s" name="%s" value="%s" class="regular-text" readonly style="background-color: #f9f9f9; color: #666;"%s />',
                    esc_attr($field_name),
                    esc_attr($field_name),
                    esc_attr($display_value),
                    $attrs
                );
                break;
                
            case 'section':
                // Section headers with description support
                $section_html = sprintf('<h4>%s</h4>', esc_html($label));
                if ($description) {
                    $section_html .= sprintf('<p>%s</p>', esc_html($description));
                }
                return $section_html;
                
            default:
                // Fallback to text input
                $field_html = sprintf(
                    '<input type="text" id="%s" name="%s" value="%s" class="regular-text"%s />',
                    esc_attr($field_name),
                    esc_attr($field_name),
                    esc_attr($current_value ?? ''),
                    $attrs
                );
                break;
        }
        
        // Wrap field with label and description
        $output = '';
        if ($field_type !== 'section') {
            $output .= '<div class="dm-form-field">';
            
            if ($label && $field_type !== 'checkbox') {
                $output .= sprintf('<label for="%s">%s</label>', esc_attr($field_name), esc_html($label));
            }
            
            $output .= $field_html;
            
            if ($description) {
                $output .= sprintf('<p class="description">%s</p>', esc_html($description));
            }
            
            $output .= '</div>';
        } else {
            $output = $field_html;
        }
        
        return $output;
    }
}