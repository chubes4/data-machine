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
            return [
                'css' => [
                    'dm-admin-pipelines' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/css/admin-pipelines.css',
                        'deps' => [],
                        'media' => 'all'
                    ]
                ],
                'js' => [
                    'dm-pipeline-builder' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/js/pipeline-builder.js',
                        'deps' => ['jquery', 'jquery-ui-sortable'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dmPipelineBuilder',
                            'data' => [
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'pipeline_ajax_nonce' => wp_create_nonce('dm_pipeline_ajax'),
                                'strings' => [
                                    'pipelineSteps' => __('Pipeline Steps', 'data-machine'),
                                    'addStep' => __('Add Step', 'data-machine'),
                                    'selectStepType' => __('Select step type...', 'data-machine'),
                                    'confirmRemoveStep' => __('Are you sure you want to remove this step?', 'data-machine'),
                                    'errorAddingStep' => __('Error adding pipeline step', 'data-machine'),
                                    'errorRemovingStep' => __('Error removing pipeline step', 'data-machine'),
                                    'saving' => __('Saving...', 'data-machine'),
                                    'loading' => __('Loading...', 'data-machine'),
                                    'pipelineNameRequired' => __('Pipeline name is required', 'data-machine'),
                                    'atLeastOneStep' => __('At least one step is required', 'data-machine')
                                ]
                            ]
                        ]
                    ],
                    'dm-pipeline-modal' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/js/pipeline-modal.js',
                        'deps' => ['jquery'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dmPipelineModal',
                            'data' => [
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'get_modal_content_nonce' => wp_create_nonce('dm_get_modal_content'),
                                'save_modal_config_nonce' => wp_create_nonce('dm_save_modal_config'),
                                'strings' => [
                                    'configureStep' => __('Configure Step', 'data-machine'),
                                    'saving' => __('Saving...', 'data-machine'),
                                    'save' => __('Save Configuration', 'data-machine'),
                                    'cancel' => __('Cancel', 'data-machine'),
                                ]
                            ]
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

        // Get component and context from POST data - using parameter-based discovery
        $component = sanitize_text_field(wp_unslash($_POST['component'] ?? ''));
        $context_raw = wp_unslash($_POST['context'] ?? '{}');
        $context = json_decode($context_raw, true);
        
        if (empty($component)) {
            wp_send_json_error(['message' => __('Component parameter is required', 'data-machine')]);
        }

        // Use existing dm_get_modal_content filter with parameter-based discovery (like database services)
        $content = apply_filters('dm_get_modal_content', null, $component, $context);
        
        if ($content === null) {
            wp_send_json_error(['message' => __('Modal content not found', 'data-machine')]);
        }

        wp_send_json_success([
            'content' => $content,
            'title' => $context['title'] ?? ucfirst(str_replace(['_', '-'], ' ', $component))
        ]);
    });
    
    // Modal content filter registration - Parameter-based discovery (like database services)
    add_filter('dm_get_modal_content', function($content, $component, $context) {
        if ($component === 'pipeline-step-delete') {
            // Get affected flows data for the warning
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
            
            $pipelines_instance = new Pipelines();
            return $pipelines_instance->render_template('modal/delete-step-warning', $enhanced_context);
        }
        return $content;
    }, 10, 3);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_pipelines_admin_page_filters();