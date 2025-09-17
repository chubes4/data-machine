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

        // Handler modal fallback lookup for templates like 'handler-settings/files'
        if (!$modal_data && strpos($template, 'handler-settings/') === 0) {
            $modal_data = $all_modals['handler-settings'] ?? null;
            if ($modal_data && isset($modal_data['dynamic_template'])) {
                // Override template to pass full path to dynamic resolution
                $modal_data['template'] = $template;
            }
        }

        if ($modal_data) {
            $title = $modal_data['title'] ?? ucfirst(str_replace('-', ' ', $template));
            
            // Check if content is pre-rendered or needs dynamic rendering
            if (isset($modal_data['content'])) {
                // Pre-rendered content (static modals)
                $content = $modal_data['content'];
            } elseif (isset($modal_data['template'])) {
                // Dynamic content via dm_render_template (has access to AJAX context)
                $context_raw = wp_unslash($_POST['context'] ?? []); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $context = is_string($context_raw)
                    ? array_map('sanitize_text_field', json_decode($context_raw, true) ?: [])
                    : array_map('sanitize_text_field', $context_raw);
                
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
            // Only registered modals are allowed - architectural security
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: modal template name */
                    __('Modal template not registered: %s', 'data-machine'),
                    $template
                )
            ]);
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
        
        // Special data preparation for flow-schedule modal
        if ($template === 'modal/flow-schedule') {
            $context['intervals'] = apply_filters('dm_scheduler_intervals', []);
            
            // Get flow-specific scheduling data if flow_id exists
            if (!empty($context['flow_id'])) {
                // Query actual flow data from database
                $all_databases = apply_filters('dm_db', []);
                $db_flows = $all_databases['flows'] ?? null;
                
                if ($db_flows) {
                    $flow = $db_flows->get_flow($context['flow_id']);
                    if ($flow) {
                        // Database method already decodes JSON - just handle missing data
                        $scheduling_config = $flow['scheduling_config'] ?? [];
                        
                        $context['current_interval'] = $scheduling_config['interval'] ?? 'manual';
                        $context['last_run_at'] = $scheduling_config['last_run_at'] ?? null;
                        
                        // Query actual next run time from Action Scheduler
                        $next_run_time = null;
                        if (function_exists('as_next_scheduled_action')) {
                            $next_action = as_next_scheduled_action('dm_run_flow_now', [$context['flow_id']], 'data-machine');
                            if ($next_action) {
                                $next_run_time = wp_date('Y-m-d H:i:s', $next_action);
                            }
                        }
                        $context['next_run_time'] = $next_run_time;
                        $context['flow_name'] = $flow['flow_name'] ?? 'Flow';
                    } else {
                        // Flow not found - this is a critical system error
                        do_action('dm_log', 'error', 'Flow-schedule modal failed - flow not found', [
                            'flow_id' => $context['flow_id']
                        ]);
                        return '<!-- Flow not found: Critical system error -->';
                    }
                } else {
                    // Database service unavailable - critical system error
                    do_action('dm_log', 'error', 'Flow-schedule modal failed - database service unavailable', [
                        'flow_id' => $context['flow_id']
                    ]);
                    return '<!-- Database service unavailable: Critical system error -->';
                }
            }
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