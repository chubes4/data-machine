<?php
/**
 * Universal modal AJAX handler using dm_modals filter system
 */

namespace DataMachine\Core\Admin\Modal;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Single AJAX endpoint for all modal content via filter system
 */
class ModalAjax
{
    /**
     * Register AJAX handler
     */
    public function __construct()
    {
        add_action('wp_ajax_dm_get_modal_content', [$this, 'handle_get_modal_content']);
    }

    /**
     * Route modal requests through dm_modals filter system
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
            // Dynamic modal rendering for unregistered templates
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
     * Render handler-specific or universal modal templates
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
     * Render form field from Settings configuration
     */
    public static function render_settings_field(string $field_name, array $field_config, $current_value = null): string {
        $attributes = $field_config['attributes'] ?? [];
        
        // Build attributes string
        $attrs = '';
        foreach ($attributes as $attr => $value) {
            $attrs .= ' ' . esc_attr($attr) . '="' . esc_attr($value) . '"';
        }
        
        // Prepare template data
        $template_data = [
            'field_name' => $field_name,
            'field_config' => $field_config,
            'current_value' => $current_value,
            'attrs' => $attrs,
            'label' => $field_config['label'] ?? ucfirst(str_replace('_', ' ', $field_name)),
            'description' => $field_config['description'] ?? '',
            'options' => $field_config['options'] ?? []
        ];
        
        return apply_filters('dm_render_template', '', 'modal/fields', $template_data);
    }
}