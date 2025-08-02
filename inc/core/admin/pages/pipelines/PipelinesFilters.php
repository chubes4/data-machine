<?php
/**
 * Pipelines Admin Page Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * Unified admin page architecture with embedded asset configuration and modal integration.
 * Demonstrates complete self-containment through direct dm_get_admin_page registration
 * with zero bridge systems or legacy compatibility layers.
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
    
    // Unified admin page registration with embedded asset configuration
    // Eliminates bridge systems through direct dm_get_admin_page integration
    add_filter('dm_get_admin_page', function($config, $page_slug) {
        if ($page_slug === 'pipelines') {
            $pipelines_instance = new Pipelines();
            
            return [
                'page_title' => __('Pipelines', 'data-machine'),
                'menu_title' => __('Pipelines', 'data-machine'),
                'capability' => 'manage_options',
                'position' => 10,
                'content_callback' => [$pipelines_instance, 'render_content'],
                'assets' => [
                    'css' => [
                        'dm-core-modal' => [
                            'file' => 'inc/core/admin/modal/assets/css/core-modal.css',
                            'deps' => [],
                            'media' => 'all'
                        ],
                        'dm-admin-pipelines' => [
                            'file' => 'inc/core/admin/pages/pipelines/assets/css/admin-pipelines.css',
                            'deps' => [],
                            'media' => 'all'
                        ]
                    ],
                    'js' => [
                        'dm-core-modal' => [
                            'file' => 'inc/core/admin/modal/assets/js/core-modal.js',
                            'deps' => ['jquery'],
                            'in_footer' => true,
                            'localize' => [
                                'object' => 'dmCoreModal',
                                'data' => [
                                    'ajax_url' => admin_url('admin-ajax.php'),
                                    'get_modal_content_nonce' => wp_create_nonce('dm_get_modal_content'),
                                    'strings' => [
                                        'loading' => __('Loading...', 'data-machine'),
                                        'error' => __('Error', 'data-machine'),
                                        'close' => __('Close', 'data-machine')
                                    ]
                                ]
                            ]
                        ],
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
                        ]
                    ]
                ]
            ];
        }
        return $config;
    }, 10, 2);
    
    // AJAX handler registration - Pipelines manages its own AJAX operations
    add_action('wp_ajax_dm_pipeline_ajax', function() {
        $ajax_handler = new PipelineAjax();
        $ajax_handler->handle_pipeline_ajax();
    });
    
    // Universal modal AJAX integration - no component-specific handlers needed
    // All modal content routed through unified ModalAjax.php endpoint
    
    // Modal content filter registration - Pure 2-parameter pattern like all existing systems
    add_filter('dm_get_modal', function($content, $template) {
        $pipelines_instance = new Pipelines();
        
        // Get context from $_POST directly (like templates access other data)
        $context_raw = wp_unslash($_POST['context'] ?? '{}');
        $context = json_decode($context_raw, true);
        
        switch ($template) {
            case 'step-selection':
                // Dual-Mode Step Discovery Pattern
                // DISCOVERY MODE: apply_filters('dm_get_steps', []) - Returns ALL registered step types
                $all_steps = apply_filters('dm_get_steps', []);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[DM Modal] Step discovery returned: ' . print_r($all_steps, true));
                }
                
                return $pipelines_instance->render_template('modal/step-selection-cards', array_merge($context, [
                    'all_steps' => $all_steps
                ]));
                
            case 'handler-selection':
                $step_type = $context['step_type'] ?? 'unknown';
                
                // Get available handlers using parameter-based filter discovery
                $available_handlers = apply_filters('dm_get_handlers', null, $step_type);
                
                if (empty($available_handlers)) {
                    return '<div class="dm-no-handlers">
                        <p>' . sprintf(__('No handlers available for %s steps', 'data-machine'), esc_html($step_type)) . '</p>
                    </div>';
                }
                
                return $pipelines_instance->render_template('modal/handler-selection-cards', [
                    'step_type' => $step_type,
                    'handlers' => $available_handlers
                ]);
                
            case 'delete-step':
                $pipeline_id = $context['pipeline_id'] ?? null;
                $step_type = $context['step_type'] ?? 'unknown';
                
                $affected_flows = [];
                if ($pipeline_id && is_numeric($pipeline_id)) {
                    $db_flows = apply_filters('dm_get_database_service', null, 'flows');
                    if ($db_flows) {
                        $affected_flows = $db_flows->get_flows_for_pipeline((int)$pipeline_id);
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
                
                // Extensible Step Configuration Modal Pattern
                //
                // This filter enables any step type to register custom configuration interfaces:
                // add_filter('dm_get_modal', function($content, $template) {
                //     if ($template === 'configure-step' && $step_type === 'my_custom_step') {
                //         $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
                //         return '<div>Custom step configuration form</div>';
                //     }
                //     return $content;
                // }, 10, 2);
                //
                // This maintains the architecture's extensibility - step types can provide
                // sophisticated configuration interfaces without modifying core modal code.
                // Step types register their own modal content for 'configure-step' template
                // Note: Removed recursive filter call - step types handle their own content via this same filter
                
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
                        <p><em>' . __('Developers: Register custom configuration via the dm_get_modal filter with appropriate template names.', 'data-machine') . '</em></p>
                    </div>
                </div>';
        }
        
        return $content;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_pipelines_admin_page_filters();