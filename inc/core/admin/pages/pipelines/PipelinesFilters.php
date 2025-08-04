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
            return [
                'page_title' => __('Pipelines', 'data-machine'),
                'menu_title' => __('Pipelines', 'data-machine'),
                'capability' => 'manage_options',
                'position' => 10,
                'templates' => __DIR__ . '/templates/',
                'assets' => [
                    'css' => [
                        'dm-core-modal' => [
                            'file' => 'inc/core/admin/modal/assets/css/core-modal.css',
                            'deps' => [],
                            'media' => 'all'
                        ],
                        'dm-admin-pipelines' => [
                            'file' => 'inc/core/admin/pages/pipelines/assets/css/admin-pipelines.css',
                            'deps' => ['dm-core-modal'],
                            'media' => 'all'
                        ],
                        'dm-modal-pipelines' => [
                            'file' => 'inc/core/admin/pages/pipelines/assets/css/modal-pipelines.css',
                            'deps' => ['dm-core-modal', 'dm-admin-pipelines'],
                            'media' => 'all'
                        ],
                        'ai-http-components' => [
                            'file' => 'lib/ai-http-client/assets/css/components.css',
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
                        ],
                        'dm-pipeline-modal' => [
                            'file' => 'inc/core/admin/pages/pipelines/assets/js/pipeline-modal.js',
                            'deps' => ['jquery', 'dm-core-modal'],
                            'in_footer' => true,
                            'localize' => [
                                'object' => 'dmPipelineModal',
                                'data' => [
                                    'ajax_url' => admin_url('admin-ajax.php'),
                                    'admin_post_url' => admin_url('admin-post.php'),
                                    'oauth_nonces' => [
                                        'twitter' => wp_create_nonce('dm_twitter_oauth_init_nonce'),
                                        'googlesheets' => wp_create_nonce('dm_googlesheets_oauth_init_nonce'),
                                        'reddit' => wp_create_nonce('dm_reddit_oauth_init_nonce'),
                                        'facebook' => wp_create_nonce('dm_facebook_oauth_init_nonce'),
                                        'threads' => wp_create_nonce('dm_threads_oauth_init_nonce')
                                    ],
                                    'disconnect_nonce' => wp_create_nonce('dm_disconnect_account'),
                                    'test_connection_nonce' => wp_create_nonce('dm_test_connection'),
                                    'strings' => [
                                        'connecting' => __('Connecting...', 'data-machine'),
                                        'disconnecting' => __('Disconnecting...', 'data-machine'),
                                        'testing' => __('Testing...', 'data-machine'),
                                        'saving' => __('Saving...', 'data-machine'),
                                        'confirmDisconnect' => __('Are you sure you want to disconnect this account? You will need to reconnect to use this handler.', 'data-machine')
                                    ]
                                ]
                            ]
                        ],
                        'ai-http-provider-manager' => [
                            'file' => 'lib/ai-http-client/assets/js/provider-manager.js',
                            'deps' => ['jquery'],
                            'in_footer' => true
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
        // Get context from $_POST directly (like templates access other data)
        $context_raw = wp_unslash($_POST['context'] ?? '{}');
        $context = json_decode($context_raw, true);
        
        switch ($template) {
            case 'step-selection':
                // Dual-Mode Step Discovery Pattern
                // DISCOVERY MODE: apply_filters('dm_get_steps', []) - Returns ALL registered step types
                $all_steps = apply_filters('dm_get_steps', []);
                
                // Debug logging using logger service
                $logger = apply_filters('dm_get_logger', null);
                if ($logger) {
                    $logger->debug('Step discovery returned for modal rendering.', [
                        'step_count' => count($all_steps),
                        'step_types' => array_keys($all_steps)
                    ]);
                }
                
                return apply_filters('dm_render_template', '', 'modal/step-selection-cards', array_merge($context, [
                    'all_steps' => $all_steps
                ]));
                
            case 'handler-selection':
                $step_type = $context['step_type'] ?? 'unknown';
                $pipeline_id = $context['pipeline_id'] ?? null;
                $flow_id = $context['flow_id'] ?? null;
                
                // Get available handlers using parameter-based filter discovery
                $available_handlers = apply_filters('dm_get_handlers', null, $step_type);
                
                if (empty($available_handlers)) {
                    return '';
                }
                
                return apply_filters('dm_render_template', '', 'modal/handler-selection-cards', [
                    'step_type' => $step_type,
                    'handlers' => $available_handlers,
                    'pipeline_id' => $pipeline_id,
                    'flow_id' => $flow_id
                ]);
                
            case 'confirm-delete':
                $delete_type = $context['delete_type'] ?? 'step'; // Default to step for backward compatibility
                $pipeline_id = $context['pipeline_id'] ?? null;
                $step_type = $context['step_type'] ?? 'unknown';
                $step_position = $context['step_position'] ?? 'unknown';
                $pipeline_name = $context['pipeline_name'] ?? __('Unknown Pipeline', 'data-machine');
                
                $affected_flows = [];
                $affected_jobs = [];
                
                if ($pipeline_id && is_numeric($pipeline_id)) {
                    $db_flows = apply_filters('dm_get_database_service', null, 'flows');
                    if ($db_flows) {
                        $affected_flows = $db_flows->get_flows_for_pipeline((int)$pipeline_id);
                    }
                    
                    // For pipeline deletion, also get affected jobs count
                    if ($delete_type === 'pipeline') {
                        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
                        if ($db_jobs) {
                            $affected_jobs = $db_jobs->get_jobs_for_pipeline((int)$pipeline_id);
                        }
                    }
                }
                
                // Enhance context with affected flows/jobs data and deletion information
                $enhanced_context = array_merge($context, [
                    'delete_type' => $delete_type,
                    'step_label' => ucfirst(str_replace('_', ' ', $step_type)),
                    'step_position' => $step_position,
                    'pipeline_name' => $pipeline_name,
                    'affected_flows' => $affected_flows,
                    'affected_jobs' => $affected_jobs
                ]);
                
                // Render using core modal template with enhanced context
                $template_path = DATA_MACHINE_PATH . 'inc/core/admin/modal/templates/confirm-delete.php';
                if (file_exists($template_path)) {
                    extract($enhanced_context);
                    ob_start();
                    include $template_path;
                    return ob_get_clean();
                }
                
                return '<div class="dm-error">Delete confirmation template not found</div>';
                
                
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
                
                // No configuration modal needed - component handles its own messaging if required
                return '';
        }
        
        return $content;
    }, 10, 2);
}

// Load Pipeline Scheduler component filters
$scheduler_filters_path = __DIR__ . '/scheduler/PipelineSchedulerFilters.php';
if (file_exists($scheduler_filters_path)) {
    require_once $scheduler_filters_path;
}

// Auto-register when file loads - achieving complete self-containment
dm_register_pipelines_admin_page_filters();