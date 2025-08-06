<?php
/**
 * Universal Modal AJAX Handler
 *
 * Handles all modal content requests through a single, universal AJAX endpoint.
 * Routes to the dm_get_modal filter system for component-specific content generation.
 *
 * This enables the universal modal architecture where any component can register
 * modal content via the dm_get_modal filter without needing custom AJAX handlers.
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
 * via the dm_get_modal filter system.
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
     * Routes to the dm_get_modal filter system for component-specific content.
     * Maintains WordPress security standards with nonce verification and
     * capability checks.
     *
     * @since 1.0.0
     */
    public function handle_get_modal_content()
    {
        // WordPress security verification
        if (!check_ajax_referer('dm_get_modal_content', 'nonce', false)) {
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
        $all_modals = apply_filters('dm_get_modals', []);
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
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: modal template name */
                    __('Modal content not found for template: %s', 'data-machine'),
                    $template
                )
            ]);
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
        switch ($template) {
            case 'modal/step-selection-cards':
                return $this->render_step_selection_modal($context);
                
            case 'modal/handler-selection-cards':
                return $this->render_handler_selection_modal($context);
                
            case 'modal/confirm-delete':
                return $this->render_confirm_delete_modal($context);
                
            case 'modal/configure-step':
                return ''; // Extensible - step types can register their own content
                
            case 'modal/flow-schedule':
                return $this->render_flow_schedule_modal($context);
                
            case 'modal/handler-settings-form':
                return $this->render_handler_settings_modal($context);
                
            default:
                return apply_filters('dm_render_template', '', $template, $context);
        }
    }
    
    /**
     * Render step selection modal with dynamic step discovery
     */
    private function render_step_selection_modal(array $context): string {
        $all_steps = apply_filters('dm_get_steps', []);
        uasort($all_steps, function($a, $b) {
            $pos_a = $a['position'] ?? 999;
            $pos_b = $b['position'] ?? 999;
            return $pos_a <=> $pos_b;
        });
        
        return apply_filters('dm_render_template', '', 'modal/step-selection-cards', array_merge($context, [
            'all_steps' => $all_steps
        ]));
    }
    
    /**
     * Render handler selection modal with filtered handlers
     */
    private function render_handler_selection_modal(array $context): string {
        $step_type = $context['step_type'] ?? 'unknown';
        $all_handlers = apply_filters('dm_get_handlers', []);
        $available_handlers = array_filter($all_handlers, function($handler) use ($step_type) {
            return ($handler['type'] ?? '') === $step_type;
        });
        
        return apply_filters('dm_render_template', '', 'modal/handler-selection-cards', [
            'step_type' => $step_type,
            'handlers' => $available_handlers,
            'pipeline_id' => $context['pipeline_id'] ?? null,
            'flow_id' => $context['flow_id'] ?? null
        ]);
    }
    
    /**
     * Render confirm delete modal with enriched context and required parameters
     */
    private function render_confirm_delete_modal(array $context): string {
        $delete_type = $context['delete_type'] ?? 'step';
        $pipeline_id = $context['pipeline_id'] ?? null;
        $flow_id = $context['flow_id'] ?? null;
        $step_id = $context['step_id'] ?? null;
        
        $all_databases = apply_filters('dm_get_database_services', []);
        
        // Fetch required names from database (core template requires these)
        $pipeline_name = null;
        $flow_name = null;
        $step_name = null;
        
        if ($delete_type === 'pipeline' && $pipeline_id) {
            $db_pipelines = $all_databases['pipelines'] ?? null;
            if ($db_pipelines) {
                $pipeline = $db_pipelines->get_pipeline($pipeline_id);
                $pipeline_name = $pipeline->pipeline_name ?? "Pipeline #{$pipeline_id}";
            }
        } elseif ($delete_type === 'flow' && $flow_id) {
            $db_flows = $all_databases['flows'] ?? null;
            if ($db_flows) {
                $flow = $db_flows->get_flow($flow_id);
                $flow_name = $flow->flow_name ?? "Flow #{$flow_id}";
            }
        } elseif ($delete_type === 'step' && $step_id) {
            $step_name = $context['step_name'] ?? "Step #{$step_id}";
        }
        
        // Enrich context with flow data based on deletion type
        $affected_flows = [];
        $affected_jobs = [];
        
        if ($delete_type === 'pipeline' && $pipeline_id) {
            $db_flows = $all_databases['flows'] ?? null;
            $db_jobs = $all_databases['jobs'] ?? null;
            
            if ($db_flows) {
                $flows = $db_flows->get_flows_for_pipeline($pipeline_id);
                foreach ($flows as $flow) {
                    $affected_flows[] = [
                        'name' => $flow->flow_name,
                        'created_at' => $flow->created_at
                    ];
                }
            }
            
            if ($db_jobs) {
                $affected_jobs = $db_jobs->get_jobs_for_pipeline($pipeline_id);
            }
        } elseif ($delete_type === 'step' && $pipeline_id) {
            $db_flows = $all_databases['flows'] ?? null;
            
            if ($db_flows) {
                $flows = $db_flows->get_flows_for_pipeline($pipeline_id);
                foreach ($flows as $flow) {
                    $affected_flows[] = [
                        'name' => $flow->flow_name,
                        'created_at' => $flow->created_at
                    ];
                }
            }
        }
        
        // Build enriched context with all required parameters for core template
        $enriched_context = array_merge($context, [
            'delete_type' => $delete_type,
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'flow_id' => $flow_id,
            'flow_name' => $flow_name,
            'step_id' => $step_id,
            'step_name' => $step_name,
            'affected_flows' => $affected_flows,
            'affected_jobs' => $affected_jobs
        ]);
        
        return apply_filters('dm_render_template', '', 'confirm-delete', $enriched_context);
    }
    
    /**
     * Render flow schedule modal with scheduler integration
     */
    private function render_flow_schedule_modal(array $context): string {
        $flow_id = (int)($context['flow_id'] ?? 0);
        
        if (!$flow_id) {
            return '<div class="dm-modal-error"><p>' . esc_html__('Flow ID is required', 'data-machine') . '</p></div>';
        }
        
        // Get flow data from database
        $all_databases = apply_filters('dm_get_database_services', []);
        $flows_db = $all_databases['flows'] ?? null;
        if (!$flows_db) {
            return '<div class="dm-modal-error"><p>' . esc_html__('Database service unavailable', 'data-machine') . '</p></div>';
        }
        
        $flow = $flows_db->get_flow($flow_id);
        if (!$flow) {
            return '<div class="dm-modal-error"><p>' . esc_html__('Flow not found', 'data-machine') . '</p></div>';
        }
        
        // Parse current scheduling config
        $scheduling_config = is_array($flow['scheduling_config'] ?? null) 
            ? $flow['scheduling_config'] 
            : json_decode($flow['scheduling_config'] ?? '{}', true);
        $current_interval = $scheduling_config['interval'] ?? 'manual';
        $last_run_at = $scheduling_config['last_run_at'] ?? null;
        
        // Get scheduler service and scheduling data
        $scheduler = apply_filters('dm_get_scheduler', null);
        $intervals = $scheduler ? $scheduler->get_intervals() : [];
        $next_run_time = $scheduler ? $scheduler->get_next_run_time($flow_id) : null;
        
        $template_data = [
            'flow_id' => $flow_id,
            'flow_name' => $flow['flow_name'] ?? 'Flow',
            'current_interval' => $current_interval,
            'intervals' => $intervals,
            'last_run_at' => $last_run_at,
            'next_run_time' => $next_run_time
        ];
        
        return apply_filters('dm_render_template', '', 'modal/flow-schedule', $template_data);
    }
    
    /**
     * Render handler settings modal (generic - redirects to specific handlers)
     */
    private function render_handler_settings_modal(array $context): string {
        // For generic handler-settings requests, try to determine specific handler
        $handler_slug = $context['handler_slug'] ?? null;
        
        if ($handler_slug) {
            // Get handler settings instance
            $all_settings = apply_filters('dm_get_handler_settings', []);
            $settings_instance = $all_settings[$handler_slug] ?? null;
            
            // Get handler config
            $all_handlers = apply_filters('dm_get_handlers', []);
            $handler_config = $all_handlers[$handler_slug] ?? [];
            
            $template_data = [
                'handler_slug' => $handler_slug,
                'handler_config' => $handler_config,
                'step_type' => $context['step_type'] ?? 'output',
                'settings_available' => ($settings_instance !== null),
                'handler_settings' => $settings_instance,
                'pipeline_id' => $context['pipeline_id'] ?? null,
                'flow_id' => $context['flow_id'] ?? null
            ];
            
            return apply_filters('dm_render_template', '', 'modal/handler-settings-form', $template_data);
        }
        
        return '<div class="dm-modal-error">Handler not specified for settings modal.</div>';
    }
}