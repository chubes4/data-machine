<?php
/**
 * Pipelines Admin Page Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * Unified admin page architecture with embedded asset configuration and modal integration.
 * Demonstrates complete self-containment through direct dm_admin_pages registration
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
    
    // Pure discovery mode - matches actual system usage
    add_filter('dm_admin_pages', function($pages) {
        $pages['pipelines'] = [
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
                    'dm-pipelines-page' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/css/pipelines-page.css',
                        'deps' => ['dm-core-modal'],
                        'media' => 'all'
                    ],
                    'dm-pipelines-modal' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/css/pipelines-modal.css',
                        'deps' => ['dm-core-modal', 'dm-pipelines-page'],
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
                    'dm-pipelines-page' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/js/pipelines-page.js',
                        'deps' => ['jquery'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dmPipelineBuilder',
                            'data' => [
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'pipeline_ajax_nonce' => wp_create_nonce('dm_pipeline_ajax'),
                                'ai_http_nonce' => wp_create_nonce('ai_http_nonce'),
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
                                    'atLeastOneStep' => __('At least one step is required', 'data-machine'),
                                    'noFlows' => __('0 flows', 'data-machine'),
                                    'noFlowsMessage' => __('No flows configured for this pipeline.', 'data-machine'),
                                    'configureHandlers' => __('Configure handlers for each step above', 'data-machine')
                                ]
                            ]
                        ]
                    ],
                    'dm-pipeline-builder' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/js/pipeline-builder.js',
                        'deps' => ['jquery', 'jquery-ui-sortable', 'dm-pipelines-page'],
                        'in_footer' => true
                    ],
                    'dm-flow-builder' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/js/flow-builder.js',
                        'deps' => ['jquery', 'dm-pipelines-page'],
                        'in_footer' => true
                    ],
                    'dm-pipelines-modal' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/js/pipelines-modal.js',
                        'deps' => ['jquery', 'dm-core-modal'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dmPipelineModal',
                            'data' => [
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'admin_post_url' => admin_url('admin-post.php'),
                                'pipeline_ajax_nonce' => wp_create_nonce('dm_pipeline_ajax'),
                                'oauth_nonces' => [
                                    'twitter' => wp_create_nonce('dm_twitter_oauth_init_nonce'),
                                    'googlesheets' => wp_create_nonce('dm_googlesheets_oauth_init_nonce'),
                                    'reddit' => wp_create_nonce('dm_reddit_oauth_init_nonce'),
                                    'facebook' => wp_create_nonce('dm_facebook_oauth_init_nonce'),
                                    'threads' => wp_create_nonce('dm_threads_oauth_init_nonce')
                                ],
                                'disconnect_nonce' => wp_create_nonce('dm_disconnect_account'),
                                'test_connection_nonce' => wp_create_nonce('dm_test_connection'),
                                'get_files_nonce' => wp_create_nonce('dm_get_handler_files'),
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
                    'dm-file-uploads' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/js/file-uploads.js',
                        'deps' => ['jquery', 'dm-pipelines-modal'],
                        'in_footer' => true
                    ],
                    'ai-http-provider-manager' => [
                        'file' => 'lib/ai-http-client/assets/js/provider-manager.js',
                        'deps' => ['jquery'],
                        'in_footer' => true
                    ]
                ]
            ],
            'ajax_handlers' => [
                'modal' => new PipelineModalAjax(),
                'page' => new PipelinePageAjax()
            ]
        ];
        return $pages;
    });
    
    // AJAX handler registration - Universal routing via dm_ajax_route action hook
    // Eliminates 132 lines of repetitive handler discovery and routing code
    
    // Page actions (business logic operations) - using universal AJAX routing
    add_action('wp_ajax_dm_add_step', fn() => do_action('dm_ajax_route', 'dm_add_step', 'page'));
    add_action('wp_ajax_dm_create_pipeline', fn() => do_action('dm_ajax_route', 'dm_create_pipeline', 'page'));
    add_action('wp_ajax_dm_add_flow', fn() => do_action('dm_ajax_route', 'dm_add_flow', 'page'));
    add_action('wp_ajax_dm_save_flow_schedule', fn() => do_action('dm_ajax_route', 'dm_save_flow_schedule', 'page'));
    add_action('wp_ajax_dm_run_flow_now', fn() => do_action('dm_ajax_route', 'dm_run_flow_now', 'page'));
    
    // Central deletion endpoints - delegate to dm_delete action hook
    add_action('wp_ajax_dm_delete_step', fn() => do_action('dm_ajax_route', 'dm_delete_step', 'page'));
    add_action('wp_ajax_dm_delete_pipeline', fn() => do_action('dm_ajax_route', 'dm_delete_pipeline', 'page')); 
    add_action('wp_ajax_dm_delete_flow', fn() => do_action('dm_ajax_route', 'dm_delete_flow', 'page'));
    
    // Modal actions (UI/template operations) - using universal AJAX routing
    add_action('wp_ajax_dm_get_template', fn() => do_action('dm_ajax_route', 'dm_get_template', 'modal'));
    add_action('wp_ajax_dm_get_flow_step_card', fn() => do_action('dm_ajax_route', 'dm_get_flow_step_card', 'modal'));
    add_action('wp_ajax_dm_get_flow_config', fn() => do_action('dm_ajax_route', 'dm_get_flow_config', 'modal'));
    add_action('wp_ajax_dm_configure_step_action', fn() => do_action('dm_ajax_route', 'dm_configure_step_action', 'modal'));
    add_action('wp_ajax_dm_add_location_action', fn() => do_action('dm_ajax_route', 'dm_add_location_action', 'modal'));
    add_action('wp_ajax_dm_add_handler_action', fn() => do_action('dm_ajax_route', 'dm_add_handler_action', 'modal'));
    
    // Handler settings AJAX endpoint - handles "Add Handler to Flow" form submissions
    add_action('wp_ajax_dm_save_handler_settings', function() {
        dm_handle_save_handler_settings();
    });
    
    
    // Pipeline auto-save hook moved to DataMachineActions.php for architectural consistency
    
    // Universal modal AJAX integration - no component-specific handlers needed
    // All modal content routed through unified ModalAjax.php endpoint
    
    // Modal registration - Two-layer architecture: metadata only, content via dm_render_template
    add_filter('dm_modals', function($modals) {
        // Static pipeline modals - metadata only, content generated during AJAX via dm_render_template
        $modals['step-selection'] = [
            'template' => 'modal/step-selection-cards',
            'title' => __('Select Step Type', 'data-machine')
        ];
        
        $modals['handler-selection'] = [
            'template' => 'modal/handler-selection-cards',
            'title' => __('Select Handler', 'data-machine')
        ];
        
        $modals['configure-step'] = [
            'template' => 'modal/configure-step', // Extensible - steps can register their own templates
            'title' => __('Configure Step', 'data-machine')
        ];
        
        $modals['confirm-delete'] = [
            'template' => 'modal/confirm-delete',
            'title' => __('Confirm Delete', 'data-machine')
        ];
        
        // Flow scheduling modal
        $modals['flow-schedule'] = [
            'template' => 'modal/flow-schedule',
            'title' => __('Schedule Flow', 'data-machine')
        ];
        
        // Handler-specific settings modals - direct template access
        // WordPress handlers require input/output distinction
        $modals['handler-settings'] = [
            'dynamic_template' => true, // Flag for dynamic template resolution
            'title' => __('Handler Settings', 'data-machine')
        ];
        
        return $modals;
    });
    
    // Template requirements registration - use centralized dm_template_requirements system
    add_filter('dm_template_requirements', function($requirements) {
        // Pipeline template context requirements - moved from inline filter logic
        $pipeline_requirements = [
            // Modal templates
            'modal/configure-step' => [
                'required' => ['step_type', 'pipeline_id', 'pipeline_step_id'],
                'optional' => ['flow_id'],
                'auto_generate' => ['flow_step_id' => '{pipeline_step_id}_{flow_id}']
            ],
            'modal/handler-selection-cards' => [
                'required' => ['step_type', 'pipeline_id', 'flow_step_id']
            ],
            // Handler-specific settings templates - minimal requirements with pure discovery
            'modal/handler-settings/files' => [
                'required' => ['handler_slug']
            ],
            'modal/handler-settings/rss' => [
                'required' => ['handler_slug']
            ],
            'modal/handler-settings/reddit' => [
                'required' => ['handler_slug']
            ],
            'modal/handler-settings/googlesheets_fetch' => [
                'required' => ['handler_slug']
            ],
            'modal/handler-settings/googlesheets_publish' => [
                'required' => ['handler_slug']
            ],
            'modal/handler-settings/wordpress_fetch' => [
                'required' => ['handler_slug']
            ],
            'modal/handler-settings/wordpress_publish' => [
                'required' => ['handler_slug']
            ],
            'modal/handler-settings/twitter' => [
                'required' => ['handler_slug']
            ],
            'modal/handler-settings/facebook' => [
                'required' => ['handler_slug']
            ],
            'modal/handler-settings/threads' => [
                'required' => ['handler_slug']
            ],
            'modal/handler-settings/bluesky' => [
                'required' => ['handler_slug']
            ],
            'modal/step-selection-cards' => [
                'required' => ['pipeline_id']
            ],
            'modal/flow-schedule' => [
                'required' => ['flow_id']
            ],
            
            // Page templates
            'page/pipeline-step-card' => [
                'required' => ['pipeline_id', 'step'],
                'extract_from_step' => ['pipeline_step_id', 'step_type']
            ],
            'page/flow-step-card' => [
                'required' => ['flow_id', 'step', 'flow_config'],
                'extract_from_step' => ['pipeline_step_id', 'step_type'],
                'auto_generate' => [
                    'flow_step_id' => '{step.pipeline_step_id}_{flow_id}'
                ]
            ],
            'page/flow-instance-card' => [
                'required' => ['flow'],
                'extract_from_flow' => ['flow_id', 'pipeline_id', 'flow_name']
            ],
            'page/pipeline-card' => [
                'required' => ['pipeline'],
                'extract_from_pipeline' => ['pipeline_id', 'pipeline_name', 'step_configuration']
            ]
        ];
        
        // Merge with existing requirements from other components
        return array_merge($requirements, $pipeline_requirements);
    }, 10, 1);
}

/**
 * Handle AJAX request to save handler settings and add handler to flow
 * 
 * This endpoint handles the "Add Handler to Flow" button submissions from handler settings modals.
 * According to architecture, handlers don't need to be configured before being added to flows.
 */
function dm_handle_save_handler_settings() {
    
    // Enhanced debugging for save handler process
    do_action('dm_log', 'debug', 'Save handler settings request received', [
        'post_keys' => array_keys($_POST),
        'post_data' => array_intersect_key($_POST, array_flip(['handler_slug', 'step_type', 'flow_id', 'pipeline_id', 'action'])),
        'has_nonce' => isset($_POST['handler_settings_nonce']),
        'user_can_manage' => current_user_can('manage_options')
    ]);
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['handler_settings_nonce'] ?? '', 'dm_save_handler_settings')) {
        do_action('dm_log', 'error', 'Handler settings nonce verification failed');
        wp_send_json_error(['message' => __('Security check failed.', 'data-machine')]);
        return;
    }
    
    // Verify user capabilities
    if (!current_user_can('manage_options')) {
        do_action('dm_log', 'error', 'Handler settings insufficient permissions');
        wp_send_json_error(['message' => __('Insufficient permissions.', 'data-machine')]);
        return;
    }
    
    // Get form data
    $handler_slug = sanitize_text_field(wp_unslash($_POST['handler_slug'] ?? ''));
    $step_type = sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));
    $flow_step_id = sanitize_text_field(wp_unslash($_POST['flow_step_id'] ?? ''));
    $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
    
    // Extract flow_id and pipeline_step_id from flow_step_id using universal filter
    $parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);
    $flow_id = $parts['flow_id'] ?? null;
    $pipeline_step_id = $parts['pipeline_step_id'] ?? null;
    
    do_action('dm_log', 'debug', 'Handler settings extracted parameters', [
        'handler_slug' => $handler_slug,
        'step_type' => $step_type,
        'flow_step_id' => $flow_step_id,
        'pipeline_step_id' => $pipeline_step_id,
        'flow_id' => $flow_id,
        'pipeline_id' => $pipeline_id
    ]);
    
    if (empty($handler_slug) || empty($step_type) || empty($flow_step_id)) {
        $error_details = [
            'handler_slug_empty' => empty($handler_slug),
            'step_type_empty' => empty($step_type),
            'flow_step_id_empty' => empty($flow_step_id),
            'post_keys' => array_keys($_POST)
        ];
        
        do_action('dm_log', 'error', 'Handler slug, step type, and flow step ID validation failed', $error_details);
        
        wp_send_json_error([
            'message' => __('Handler slug, step type, and flow step ID are required.', 'data-machine'),
            'debug_info' => $error_details
        ]);
        return;
    }
    
    // Get handler configuration via pure discovery
    $all_handlers = apply_filters('dm_handlers', []);
    $handlers = array_filter($all_handlers, function($handler) use ($step_type) {
        return ($handler['type'] ?? '') === $step_type;
    });
    
    if (!isset($handlers[$handler_slug])) {
        wp_send_json_error(['message' => __('Invalid handler for this step type.', 'data-machine')]);
        return;
    }
    
    $handler_info = $handlers[$handler_slug];
    
    // Get settings class to process form data using pure discovery
    $all_settings = apply_filters('dm_handler_settings', []);
    $handler_settings = $all_settings[$handler_slug] ?? null;
    $saved_handler_settings = [];
    
    // If handler has settings, sanitize the form data
    if ($handler_settings && method_exists($handler_settings, 'sanitize')) {
        $raw_settings = [];
        
        // Extract form fields (skip WordPress and system fields)
        foreach ($_POST as $key => $value) {
            if (!in_array($key, ['action', 'handler_settings_nonce', '_wp_http_referer', 'handler_slug', 'step_type', 'flow_id', 'pipeline_id'])) {
                $raw_settings[$key] = $value;
            }
        }
        
        $saved_handler_settings = $handler_settings->sanitize($raw_settings);
    }
    
    // For flow context, add handler to flow configuration using centralized action
    if ($flow_id > 0) {
        // Use centralized flow handler management action (no return value capture)
        do_action('dm_update_flow_handler', $flow_step_id, $handler_slug, $saved_handler_settings);
        
        // Auto-save pipeline after handler settings change
        if ($pipeline_id > 0) {
            do_action('dm_auto_save', $pipeline_id);
        }
        
        // Determine action type for response (simple check - handler exists if settings were previously saved)
        $action_type = !empty($saved_handler_settings) ? 'updated' : 'added';
        $action_message = ($action_type === 'updated')
            ? sprintf(__('Handler "%s" settings updated successfully', 'data-machine'), $handler_info['label'] ?? $handler_slug)
            : sprintf(__('Handler "%s" added to flow successfully', 'data-machine'), $handler_info['label'] ?? $handler_slug);
        
        wp_send_json_success([
            'message' => $action_message,
            'handler_slug' => $handler_slug,
            'step_type' => $step_type,
            'flow_step_id' => $flow_step_id,
            'flow_id' => $flow_id,
            'pipeline_id' => $pipeline_id,
            'handler_config' => $handler_info,
            'handler_settings' => $saved_handler_settings,
            'action_type' => $action_type
        ]);
        
    } else {
        // For pipeline context (template), just confirm the handler is valid
        wp_send_json_success([
            'message' => sprintf(__('Handler "%s" configuration saved.', 'data-machine'), $handler_info['label'] ?? $handler_slug),
            'handler_slug' => $handler_slug,
            'step_type' => $step_type,
            'pipeline_id' => $pipeline_id,
            'handler_config' => $handler_info,
            'handler_settings' => $saved_handler_settings
        ]);
    }
}

// Auto-register when file loads - achieving complete self-containment
dm_register_pipelines_admin_page_filters();