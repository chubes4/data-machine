<?php
/**
 * Pipelines Admin Page Component Filter Registration
 * 
 * Revolutionary "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as the Pipelines Admin Page's "main plugin file" - the complete
 * interface contract with the engine, demonstrating complete self-containment
 * and zero bootstrap dependencies.
 * 
 * @package DataMachine
 * @subpackage Core\Admin\Pages\Pipelines
 * @since 0.1.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Pipelines Admin Page component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Pipelines Admin Page capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_pipelines_admin_page_filters() {
    
    // Admin page registration - Pipelines declares itself via parameter-based system
    add_filter('dm_get_admin_page', function($config, $page_slug) {
        if ($page_slug === 'pipelines') {
            return [
                'page_title' => __('Pipelines', 'data-machine'),
                'menu_title' => __('Pipelines', 'data-machine'),
                'capability' => 'manage_options',
                'position' => 10
            ];
        }
        return $config;
    }, 10, 2);
    
    // Page content registration - Pipelines provides its content via filter
    add_filter('dm_render_admin_page', function($content, $page_slug) {
        if ($page_slug === 'pipelines') {
            $pipelines_instance = new Pipelines();
            ob_start();
            $pipelines_instance->render_content();
            return ob_get_clean();
        }
        return $content;
    }, 10, 2);
    
    // Asset registration - Pipelines provides its own CSS and JS assets  
    add_filter('dm_get_page_assets', function($assets, $page_slug) {
        if ($page_slug === 'pipelines') {
            // Initialize assets array if null
            if (!is_array($assets)) {
                $assets = [];
            }
            
            // Ensure CSS and JS arrays exist
            if (!isset($assets['css'])) {
                $assets['css'] = [];
            }
            if (!isset($assets['js'])) {
                $assets['js'] = [];
            }
            
            // Add pipeline-specific assets to existing array (instead of overwriting)
            $assets['css']['dm-admin-pipelines'] = [
                'file' => 'inc/core/admin/pages/pipelines/assets/css/admin-pipelines.css',
                'deps' => [],
                'media' => 'all'
            ];
            
            $assets['js']['dm-pipeline-builder'] = [ 
                'file' => 'inc/core/admin/pages/pipelines/assets/js/pipeline-builder.js',
                'deps' => ['jquery', 'jquery-ui-sortable', 'dm-core-modal'],
                'in_footer' => true,
                'localize' => [
                    'object' => 'dmPipelineBuilder',
                    'data' => [
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'pipeline_ajax_nonce' => wp_create_nonce('dm_pipeline_ajax'),
                        'get_modal_content_nonce' => wp_create_nonce('dm_get_modal_content'),
                        'strings' => [
                            'error' => __('An error occurred', 'data-machine'),
                            'success' => __('Success', 'data-machine'),
                            'confirm' => __('Are you sure?', 'data-machine'),
                            'cancel' => __('Cancel', 'data-machine'),
                            'delete' => __('Delete', 'data-machine'),
                            'errorRemovingStep' => __('Error removing pipeline step', 'data-machine'),
                            'saving' => __('Saving...', 'data-machine'),
                            'loading' => __('Loading...', 'data-machine'),
                            'pipelineNameRequired' => __('Pipeline name is required', 'data-machine'),
                            'atLeastOneStep' => __('At least one step is required', 'data-machine')
                        ]
                    ]
                ]
            ];
        }
        return $assets;
    }, 10, 2);
    
    // AJAX handler registration - Pipelines manages its own AJAX operations
    add_action('wp_ajax_dm_pipeline_ajax', function() {
        $ajax_handler = new PipelineAjax();
        $ajax_handler->handle_pipeline_ajax();
    });
    
    // Modal content AJAX handler - Using existing dm_get_modal_content filter system
    add_action('wp_ajax_dm_get_modal_content', function() {
        // Verify nonce
        if (!check_ajax_referer('dm_get_modal_content', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
        }

        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        // Get template from POST data - exact same pattern as handlers/steps (2 parameters only)
        $template = sanitize_text_field(wp_unslash($_POST['template'] ?? ''));
        
        if (empty($template)) {
            wp_send_json_error(['message' => __('Template parameter is required', 'data-machine')]);
        }

        // Pure 2-parameter pattern like all existing systems
        $content = apply_filters('dm_get_modal_content', null, $template);
        
        if ($content === null) {
            wp_send_json_error(['message' => __('Modal content not found', 'data-machine')]);
        }

        // Get title from context if available
        $context_raw = wp_unslash($_POST['context'] ?? '{}');
        $context = json_decode($context_raw, true);
        $title = $context['title'] ?? ucfirst(str_replace(['_', '-'], ' ', $template));
        
        wp_send_json_success([
            'content' => $content,
            'title' => $title
        ]);
    });
    
    // Modal content filter registration - Pure 2-parameter pattern like all existing systems
    add_filter('dm_get_modal_content', function($content, $template) {
        $pipelines_instance = new Pipelines();
        
        // Get context from $_POST directly (like templates access other data)
        $context_raw = wp_unslash($_POST['context'] ?? '{}');
        $context = json_decode($context_raw, true);
        
        switch ($template) {
            case 'step-selection':
                return $pipelines_instance->render_template('modal/step-selection-cards', $context);
                
            case 'handler-selection':
                $step_type = $context['step_type'] ?? 'unknown';
                return $pipelines_instance->render_template('modal/handler-selection-cards', [
                    'step_type' => $step_type
                ]);
                
            case 'delete-step':
                $pipeline_id = $context['pipeline_id'] ?? null;
                $step_type = $context['step_type'] ?? 'unknown';
                
                $affected_flows = [];
                if ($pipeline_id) {
                    $db_flows = apply_filters('dm_get_database_service', null, 'flows');
                    if ($db_flows) {
                        $affected_flows = $db_flows->get_flows_for_pipeline($pipeline_id);
                    }
                }
                
                // Enhance context with affected flows data
                $enhanced_context = array_merge($context, [
                    'step_label' => ucfirst(str_replace('_', ' ', $step_type)),
                    'affected_flows' => $affected_flows
                ]);
                
                return $pipelines_instance->render_template('modal/delete-step-warning', $enhanced_context);
                
            case 'handler-settings':
                $handler_type = $context['handler_type'] ?? 'unknown';
                return $pipelines_instance->render_template('modal/handler-settings-form', [
                    'handler_type' => $handler_type
                ]);
                
            case 'configure-step':
                $step_type = $context['step_type'] ?? 'unknown';
                $modal_type = $context['modal_type'] ?? 'default';
                $config_type = $context['config_type'] ?? 'default';
                
                // Allow components to register their own step configuration modals
                $step_config_content = apply_filters('dm_get_step_config_modal', null, $step_type, $context);
                
                if ($step_config_content !== null) {
                    return $step_config_content;
                }
                
                // Professional fallback for steps without custom configuration
                return '<div class="dm-step-config-placeholder">
                    <div class="dm-placeholder-header">
                        <h4>' . sprintf(__('%s Configuration', 'data-machine'), esc_html(ucfirst(str_replace('_', ' ', $step_type)))) . '</h4>
                    </div>
                    <div class="dm-placeholder-content">
                        <p>' . __('This step type does not require additional configuration.', 'data-machine') . '</p>
                        <div class="dm-step-info">
                            <strong>' . __('Step Type:', 'data-machine') . '</strong> ' . esc_html($step_type) . '<br>
                            <strong>' . __('Configuration Type:', 'data-machine') . '</strong> ' . esc_html($config_type) . '<br>
                            <strong>' . __('Modal Type:', 'data-machine') . '</strong> ' . esc_html($modal_type) . '
                        </div>
                    </div>
                    <div class="dm-placeholder-footer">
                        <p><em>' . __('Developers: Register custom configuration via the dm_get_step_config_modal filter.', 'data-machine') . '</em></p>
                    </div>
                </div>';
        }
        
        return $content;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_pipelines_admin_page_filters();