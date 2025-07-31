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
                                'get_pipeline_steps_nonce' => wp_create_nonce('dm_get_pipeline_steps'),
                                'add_pipeline_step_nonce' => wp_create_nonce('dm_add_pipeline_step'),
                                'remove_pipeline_step_nonce' => wp_create_nonce('dm_remove_pipeline_step'),
                                'reorder_pipeline_steps_nonce' => wp_create_nonce('dm_reorder_pipeline_steps'),
                                'get_dynamic_step_types_nonce' => wp_create_nonce('dm_get_dynamic_step_types'),
                                'get_available_handlers_nonce' => wp_create_nonce('dm_get_available_handlers'),
                                'strings' => [
                                    'pipelineSteps' => __('Pipeline Steps', 'data-machine'),
                                    'addStep' => __('Add Step', 'data-machine'),
                                    'selectStepType' => __('Select step type...', 'data-machine'),
                                    'confirmRemoveStep' => __('Are you sure you want to remove this step?', 'data-machine'),
                                    'errorAddingStep' => __('Error adding pipeline step', 'data-machine'),
                                    'errorRemovingStep' => __('Error removing pipeline step', 'data-machine'),
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
}

// Auto-register when file loads - achieving complete self-containment
dm_register_pipelines_admin_page_filters();