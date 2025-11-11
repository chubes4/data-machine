<?php
/**
 * Universal modal AJAX handler with filter-based routing.
 *
 * @package DataMachine\Core\Admin\Modal
 */

namespace DataMachine\Core\Admin\Modal;

defined('ABSPATH') || exit;

/**
 * Single AJAX endpoint for all modal content via datamachine_modals filter system.
 */
class ModalAjax
{
    /**
     * Register modal AJAX endpoints.
     */
    public function __construct()
    {
        add_action('wp_ajax_datamachine_get_modal_content', [$this, 'handle_get_modal_content']);
    }

    /**
     * Route modal requests via filter-based discovery.
     */
    public function handle_get_modal_content()
    {
        // Security verification
        if (!check_ajax_referer('dm_ajax_actions', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security verification failed', 'data-machine')
            ]);
        }

        // Permission check
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions', 'data-machine')
            ]);
        }

        // Extract template parameter
        $template = sanitize_text_field(wp_unslash($_POST['template'] ?? ''));

        if (empty($template)) {
            wp_send_json_error([
                'message' => __('Template parameter is required', 'data-machine')
            ]);
        }

        // Check registered modals
        $all_modals = apply_filters('datamachine_modals', []);
        $modal_data = $all_modals[$template] ?? null;

        // Handler-specific modal fallback
        if (!$modal_data && strpos($template, 'handler-settings/') === 0) {
            $modal_data = $all_modals['handler-settings'] ?? null;
            if ($modal_data && isset($modal_data['dynamic_template'])) {
                // Pass full template path for dynamic resolution
                $modal_data['template'] = $template;
            }
        }

        if ($modal_data) {
            $title = $modal_data['title'] ?? ucfirst(str_replace('-', ' ', $template));
            
            // Determine content rendering method
            if (isset($modal_data['content'])) {
                // Static modal content
                $content = $modal_data['content'];
            } elseif (isset($modal_data['template'])) {
                // Dynamic content rendering with AJAX context
                if (isset($_POST['context']) && is_array($_POST['context'])) {
                    $context_raw = array_map('sanitize_text_field', wp_unslash($_POST['context']));
                } else {
                    $context_raw = sanitize_text_field(wp_unslash($_POST['context'] ?? ''));
                }

                // Process context data after unslashing
                if (is_string($context_raw)) {
                    $decoded = json_decode($context_raw, true) ?: [];
                    $context = is_array($decoded) ? array_map('sanitize_text_field', $decoded) : [];
                } else {
                    $context = array_map('sanitize_text_field', (array) $context_raw);
                }
                
                // Render with processed context
                $content = $this->render_dynamic_modal_content($modal_data['template'], $context);
            } else {
                $content = '';
            }
            
            wp_send_json_success([
                'content' => $content,
                'template' => $title
            ]);
        } else {
            // Only registered modals permitted
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
     * Render dynamic modal content with context.
     */
    private function render_dynamic_modal_content(string $template, array $context): string {
        // Check for handler-specific template
        if (strpos($template, 'handler-settings/') === 0) {
            // Extract handler template slug
            $handler_template_slug = substr($template, strlen('handler-settings/'));
            
            // Try handler-specific template first
            $handler_specific_content = apply_filters('datamachine_render_template', '', "modal/handler-settings/{$handler_template_slug}", $context);
            
            if (!empty($handler_specific_content)) {
                return $handler_specific_content;
            }
            
            // Fallback to universal handler-settings template
            return apply_filters('datamachine_render_template', '', 'modal/handler-settings', $context);
        }
        
        // Flow-schedule modal data preparation
        if ($template === 'modal/flow-schedule') {
            $context['intervals'] = apply_filters('datamachine_scheduler_intervals', []);
            
            // Get flow scheduling data
            if (!empty($context['flow_id'])) {
                // Query flow data from database
                $all_databases = apply_filters('datamachine_db', []);
                $db_flows = $all_databases['flows'] ?? null;
                
                if ($db_flows) {
                    $flow = apply_filters('datamachine_get_flow', null, $context['flow_id']);
                    if ($flow) {
                        // Handle flow scheduling configuration
                        $scheduling_config = $flow['scheduling_config'] ?? [];
                        
                        $context['current_interval'] = $scheduling_config['interval'] ?? 'manual';
                        $context['last_run_at'] = $scheduling_config['last_run_at'] ?? null;
                        
                        // Query next scheduled run time
                        $next_run_time = null;
                        if (function_exists('as_next_scheduled_action')) {
                            $next_action = as_next_scheduled_action('datamachine_run_flow_now', [absint($context['flow_id'])], 'data-machine');
                            if ($next_action) {
                                $next_run_time = wp_date('Y-m-d H:i:s', $next_action);
                            }
                        }
                        $context['next_run_time'] = $next_run_time;
                        $context['flow_name'] = $flow['flow_name'] ?? 'Flow';
                    } else {
                        // Critical error: flow not found
                        do_action('datamachine_log', 'error', 'Flow-schedule modal failed - flow not found', [
                            'flow_id' => $context['flow_id']
                        ]);
                        return '<!-- Flow not found: Critical system error -->';
                    }
                } else {
                    // Critical error: database service unavailable
                    do_action('datamachine_log', 'error', 'Flow-schedule modal failed - database service unavailable', [
                        'flow_id' => $context['flow_id']
                    ]);
                    return '<!-- Database service unavailable: Critical system error -->';
                }
            }
        }
        
        // Use universal template filter
        return apply_filters('datamachine_render_template', '', $template, $context);
    }
    
    
    /**
     * Render settings field from configuration.
     */
    public static function render_settings_field(string $field_name, array $field_config, $current_value = null): string {
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