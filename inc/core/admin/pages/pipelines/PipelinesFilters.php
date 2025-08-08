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
    
    // Pure discovery mode - matches actual system usage
    add_filter('dm_get_admin_pages', function($pages) {
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
                                'pipeline_auto_save_nonce' => wp_create_nonce('dm_pipeline_auto_save_nonce'),
                                'ai_http_nonce' => wp_create_nonce('ai_http_nonce'),
                                'upload_file_nonce' => wp_create_nonce('dm_upload_file'),
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
                                'oauth_nonces' => [
                                    'twitter' => wp_create_nonce('dm_twitter_oauth_init_nonce'),
                                    'googlesheets' => wp_create_nonce('dm_googlesheets_oauth_init_nonce'),
                                    'reddit' => wp_create_nonce('dm_reddit_oauth_init_nonce'),
                                    'facebook' => wp_create_nonce('dm_facebook_oauth_init_nonce'),
                                    'threads' => wp_create_nonce('dm_threads_oauth_init_nonce')
                                ],
                                'disconnect_nonce' => wp_create_nonce('dm_disconnect_account'),
                                'test_connection_nonce' => wp_create_nonce('dm_test_connection'),
                                'upload_file_nonce' => wp_create_nonce('dm_upload_file'),
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
            ],
            'ajax_handlers' => [
                'modal' => new PipelineModalAjax(),
                'page' => new PipelinePageAjax()
            ]
        ];
        return $pages;
    });
    
    // AJAX handler registration - Route to specialized page or modal handlers
    // Individual WordPress action hooks - replacing central router
    
    // Page actions (business logic operations)
    add_action('wp_ajax_dm_add_step', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $page_handler = $ajax_handlers['page'] ?? null;
        if ($page_handler && method_exists($page_handler, 'handle_add_step')) {
            $page_handler->handle_add_step();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    });
    
    add_action('wp_ajax_dm_delete_step', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $page_handler = $ajax_handlers['page'] ?? null;
        if ($page_handler && method_exists($page_handler, 'handle_delete_step')) {
            $page_handler->handle_delete_step();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    });
    
    add_action('wp_ajax_dm_delete_pipeline', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $page_handler = $ajax_handlers['page'] ?? null;
        if ($page_handler && method_exists($page_handler, 'handle_delete_pipeline')) {
            $page_handler->handle_delete_pipeline();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    });
    
    add_action('wp_ajax_dm_create_pipeline', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $page_handler = $ajax_handlers['page'] ?? null;
        if ($page_handler && method_exists($page_handler, 'handle_create_pipeline')) {
            $page_handler->handle_create_pipeline();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    });
    
    add_action('wp_ajax_dm_add_flow', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $page_handler = $ajax_handlers['page'] ?? null;
        if ($page_handler && method_exists($page_handler, 'handle_add_flow')) {
            $page_handler->handle_add_flow();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    });
    
    add_action('wp_ajax_dm_delete_flow', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $page_handler = $ajax_handlers['page'] ?? null;
        if ($page_handler && method_exists($page_handler, 'handle_delete_flow')) {
            $page_handler->handle_delete_flow();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    });
    
    add_action('wp_ajax_dm_save_flow_schedule', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $page_handler = $ajax_handlers['page'] ?? null;
        if ($page_handler && method_exists($page_handler, 'handle_save_flow_schedule')) {
            $page_handler->handle_save_flow_schedule();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    });
    
    add_action('wp_ajax_dm_run_flow_now', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $page_handler = $ajax_handlers['page'] ?? null;
        if ($page_handler && method_exists($page_handler, 'handle_run_flow_now')) {
            $page_handler->handle_run_flow_now();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    });
    
    // Modal actions (UI/template operations)  
    add_action('wp_ajax_dm_get_template', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $modal_handler = $ajax_handlers['modal'] ?? null;
        if ($modal_handler && method_exists($modal_handler, 'handle_get_template')) {
            $modal_handler->handle_get_template();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    });
    
    add_action('wp_ajax_dm_get_flow_step_card', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $modal_handler = $ajax_handlers['modal'] ?? null;
        if ($modal_handler && method_exists($modal_handler, 'handle_get_flow_step_card')) {
            $modal_handler->handle_get_flow_step_card();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    });
    
    add_action('wp_ajax_dm_get_flow_config', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $modal_handler = $ajax_handlers['modal'] ?? null;
        if ($modal_handler && method_exists($modal_handler, 'handle_get_flow_config')) {
            $modal_handler->handle_get_flow_config();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    });
    
    add_action('wp_ajax_dm_configure_step_action', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $modal_handler = $ajax_handlers['modal'] ?? null;
        if ($modal_handler && method_exists($modal_handler, 'handle_configure_step_action')) {
            $modal_handler->handle_configure_step_action();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    });
    
    add_action('wp_ajax_dm_add_location_action', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $modal_handler = $ajax_handlers['modal'] ?? null;
        if ($modal_handler && method_exists($modal_handler, 'handle_add_location_action')) {
            $modal_handler->handle_add_location_action();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    });
    
    add_action('wp_ajax_dm_add_handler_action', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $modal_handler = $ajax_handlers['modal'] ?? null;
        if ($modal_handler && method_exists($modal_handler, 'handle_add_handler_action')) {
            $modal_handler->handle_add_handler_action();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    });
    
    // Handler settings AJAX endpoint - handles "Add Handler to Flow" form submissions
    add_action('wp_ajax_dm_save_handler_settings', function() {
        dm_handle_save_handler_settings();
    });
    
    // Auto-save AJAX endpoint - routes to PipelinePageAjax for processing
    add_action('wp_ajax_dm_pipeline_auto_save', function() {
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $pipeline_page = $all_pages['pipelines'] ?? null;
        $ajax_handlers = $pipeline_page['ajax_handlers'] ?? [];
        $page_handler = $ajax_handlers['page'] ?? null;
        
        if ($page_handler && method_exists($page_handler, 'handle_auto_save')) {
            $page_handler->handle_auto_save();
        } else {
            wp_send_json_error(['message' => __('Auto-save handler not available', 'data-machine')]);
        }
    });
    
    // Pipeline auto-save hook moved to DataMachineActions.php for architectural consistency
    
    // Universal modal AJAX integration - no component-specific handlers needed
    // All modal content routed through unified ModalAjax.php endpoint
    
    // Modal registration - Two-layer architecture: metadata only, content via dm_render_template
    add_filter('dm_get_modals', function($modals) {
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
    
    // Extract flow_id and pipeline_step_id from flow_step_id for backward compatibility
    $flow_id = null;
    $pipeline_step_id = null;
    if ($flow_step_id && strpos($flow_step_id, '_') !== false) {
        $parts = explode('_', $flow_step_id, 2);
        if (count($parts) === 2) {
            $pipeline_step_id = $parts[0];
            $flow_id = (int)$parts[1];
        }
    }
    
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
    $all_handlers = apply_filters('dm_get_handlers', []);
    $handlers = array_filter($all_handlers, function($handler) use ($step_type) {
        return ($handler['type'] ?? '') === $step_type;
    });
    
    if (!isset($handlers[$handler_slug])) {
        wp_send_json_error(['message' => __('Invalid handler for this step type.', 'data-machine')]);
        return;
    }
    
    $handler_config = $handlers[$handler_slug];
    
    // Get settings class to process form data using pure discovery
    $all_settings = apply_filters('dm_get_handler_settings', []);
    $settings_instance = $all_settings[$handler_slug] ?? null;
    $handler_settings = [];
    
    // If handler has settings, sanitize the form data
    if ($settings_instance && method_exists($settings_instance, 'sanitize')) {
        $raw_settings = [];
        
        // Extract form fields (skip WordPress and system fields)
        foreach ($_POST as $key => $value) {
            if (!in_array($key, ['action', 'handler_settings_nonce', '_wp_http_referer', 'handler_slug', 'step_type', 'flow_id', 'pipeline_id'])) {
                $raw_settings[$key] = $value;
            }
        }
        
        $handler_settings = $settings_instance->sanitize($raw_settings);
    }
    
    // For flow context, add handler to flow configuration
    if ($flow_id > 0) {
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_flows = $all_databases['flows'] ?? null;
        if (!$db_flows) {
            wp_send_json_error(['message' => __('Database service unavailable.', 'data-machine')]);
            return;
        }
        
        // Get current flow
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Flow not found.', 'data-machine')]);
            return;
        }
        
        // Get current flow configuration (already decoded by database layer)
        $flow_config = $flow['flow_config'] ?? [];
        
        // Validate that flow_config is array (database layer should provide arrays)
        if (!is_array($flow_config)) {
            wp_send_json_error(['message' => __('Invalid flow configuration format.', 'data-machine')]);
            return;
        }
        
        // Initialize step configuration if it doesn't exist
        // Use standard flow_config structure: direct flow_step_id key
        if (!isset($flow_config[$flow_step_id])) {
            $flow_config[$flow_step_id] = [
                'flow_step_id' => $flow_step_id,
                'step_type' => $step_type,
                'pipeline_step_id' => $pipeline_step_id,
                'flow_id' => $flow_id,
                'handler' => null
            ];
        }
        
        // Check if handler already exists
        $handler_exists = isset($flow_config[$flow_step_id]['handler']) && 
                         ($flow_config[$flow_step_id]['handler']['handler_slug'] ?? '') === $handler_slug;
        
        // UPDATE existing handler settings OR ADD new handler (single handler per step)
        $flow_config[$flow_step_id]['handler'] = [
            'handler_slug' => $handler_slug,
            'settings' => $handler_settings,
            'enabled' => true
        ];
        
        // Update flow with new configuration
        $success = $db_flows->update_flow($flow_id, [
            'flow_config' => wp_json_encode($flow_config)
        ]);
        
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to add handler to flow.', 'data-machine')]);
            return;
        }
        
        // Log the action
        $action_type = $handler_exists ? 'updated' : 'added';
        do_action('dm_log', 'debug', "Handler '{$handler_slug}' {$action_type} for flow step '{$flow_step_id}' in flow {$flow_id}");
        
        $action_message = $handler_exists 
            ? sprintf(__('Handler "%s" settings updated successfully', 'data-machine'), $handler_config['label'] ?? $handler_slug)
            : sprintf(__('Handler "%s" added to flow successfully', 'data-machine'), $handler_config['label'] ?? $handler_slug);
        
        wp_send_json_success([
            'message' => $action_message,
            'handler_slug' => $handler_slug,
            'step_type' => $step_type,
            'flow_step_id' => $flow_step_id,
            'pipeline_id' => $pipeline_id,
            'handler_config' => $handler_config,
            'handler_settings' => $handler_settings,
            'action_type' => $handler_exists ? 'updated' : 'added'
        ]);
        
    } else {
        // For pipeline context (template), just confirm the handler is valid
        wp_send_json_success([
            'message' => sprintf(__('Handler "%s" configuration saved.', 'data-machine'), $handler_config['label'] ?? $handler_slug),
            'handler_slug' => $handler_slug,
            'step_type' => $step_type,
            'pipeline_id' => $pipeline_id,
            'handler_config' => $handler_config,
            'handler_settings' => $handler_settings
        ]);
    }
}

// Load Pipeline Scheduler component filters
$scheduler_filters_path = __DIR__ . '/scheduler/PipelineSchedulerFilters.php';
if (file_exists($scheduler_filters_path)) {
    require_once $scheduler_filters_path;
}

// Pipeline template context resolution - handles context for all pipeline/flow templates
add_filter('dm_render_template', function($content, $template_name, $data = []) {
    // Pipeline template context requirements
    $pipeline_template_requirements = [
        // Modal templates
        'modal/configure-step' => [
            'required' => ['step_type', 'pipeline_id', 'pipeline_step_id'],
            'optional' => ['flow_id'],
            'auto_generate' => ['flow_step_id' => '{pipeline_step_id}_{flow_id}']
        ],
        'modal/handler-selection-cards' => [
            'required' => ['pipeline_id', 'flow_id', 'step_type']
        ],
        // Handler-specific settings templates - support all handlers with WordPress input/output variants
        'modal/handler-settings/files' => [
            'required' => ['handler_slug', 'step_type'],
            'optional' => ['flow_id', 'pipeline_id', 'pipeline_step_id', 'flow_step_id']
        ],
        'modal/handler-settings/rss' => [
            'required' => ['handler_slug', 'step_type'],
            'optional' => ['flow_id', 'pipeline_id', 'pipeline_step_id', 'flow_step_id']
        ],
        'modal/handler-settings/reddit' => [
            'required' => ['handler_slug', 'step_type'],
            'optional' => ['flow_id', 'pipeline_id', 'pipeline_step_id', 'flow_step_id']
        ],
        'modal/handler-settings/googlesheets_fetch' => [
            'required' => ['handler_slug', 'step_type'],
            'optional' => ['flow_id', 'pipeline_id', 'pipeline_step_id', 'flow_step_id']
        ],
        'modal/handler-settings/googlesheets_publish' => [
            'required' => ['handler_slug', 'step_type'],
            'optional' => ['flow_id', 'pipeline_id', 'pipeline_step_id', 'flow_step_id']
        ],
        'modal/handler-settings/wordpress_fetch' => [
            'required' => ['handler_slug', 'step_type'],
            'optional' => ['flow_id', 'pipeline_id', 'pipeline_step_id', 'flow_step_id']
        ],
        'modal/handler-settings/wordpress_publish' => [
            'required' => ['handler_slug', 'step_type'],
            'optional' => ['flow_id', 'pipeline_id', 'pipeline_step_id', 'flow_step_id']
        ],
        'modal/handler-settings/twitter' => [
            'required' => ['handler_slug', 'step_type'],
            'optional' => ['flow_id', 'pipeline_id', 'pipeline_step_id', 'flow_step_id']
        ],
        'modal/handler-settings/facebook' => [
            'required' => ['handler_slug', 'step_type'],
            'optional' => ['flow_id', 'pipeline_id', 'pipeline_step_id', 'flow_step_id']
        ],
        'modal/handler-settings/threads' => [
            'required' => ['handler_slug', 'step_type'],
            'optional' => ['flow_id', 'pipeline_id', 'pipeline_step_id', 'flow_step_id']
        ],
        'modal/handler-settings/bluesky' => [
            'required' => ['handler_slug', 'step_type'],
            'optional' => ['flow_id', 'pipeline_id', 'pipeline_step_id', 'flow_step_id']
        ],
        'modal/step-selection-cards' => [
            'required' => ['pipeline_id']
        ],
        'modal/flow-schedule' => [
            'required' => ['flow_id']
        ],
        
        // Page templates
        'page/pipeline-step-card' => [
            'required' => ['pipeline_id', 'step', 'is_first_step'],
            'extract_from_step' => ['pipeline_step_id', 'step_type']
        ],
        'page/flow-step-card' => [
            'required' => ['flow_id', 'pipeline_id', 'step', 'flow_config'],
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
    
    // Resolve context if this template has requirements
    if (isset($pipeline_template_requirements[$template_name])) {
        $requirements = $pipeline_template_requirements[$template_name];
        $data = dm_resolve_pipeline_context($requirements, $data);
    }
    
    return $content; // Continue filter chain
}, 5, 3);

/**
 * Resolve pipeline template context based on requirements
 * 
 * @param array $requirements Template context requirements
 * @param array $data Current template data
 * @return array Enhanced data with resolved context
 */
function dm_resolve_pipeline_context($requirements, $data) {
    // Validate required fields
    if (!empty($requirements['required'])) {
        foreach ($requirements['required'] as $field) {
            if (!dm_has_context_field($field, $data)) {
                // Log missing required context
                do_action('dm_log', 'error', "Pipeline template context missing required field: {$field}", [
                    'requirements' => $requirements,
                    'available_data_keys' => array_keys($data)
                ]);
            }
        }
    }
    
    // Auto-generate composite IDs
    if (!empty($requirements['auto_generate'])) {
        foreach ($requirements['auto_generate'] as $target_field => $pattern) {
            $data[$target_field] = dm_generate_id_from_pattern($pattern, $data);
        }
    }
    
    // Extract nested data from objects/arrays
    if (!empty($requirements['extract_from_step'])) {
        $data = dm_extract_step_data($data, $requirements['extract_from_step']);
    }
    
    if (!empty($requirements['extract_from_flow'])) {
        $data = dm_extract_flow_data($data, $requirements['extract_from_flow']);
    }
    
    if (!empty($requirements['extract_from_pipeline'])) {
        $data = dm_extract_pipeline_data($data, $requirements['extract_from_pipeline']);
    }
    
    return $data;
}

/**
 * Check if context field exists and has value
 * 
 * @param string $field Field name to check
 * @param array $data Data array to check
 * @return bool True if field exists and has value
 */
function dm_has_context_field($field, $data) {
    // Handle nested field access (e.g., 'step.step_id')
    if (strpos($field, '.') !== false) {
        $parts = explode('.', $field);
        $current = $data;
        
        foreach ($parts as $part) {
            if (is_array($current) && isset($current[$part])) {
                $current = $current[$part];
            } elseif (is_object($current) && isset($current->$part)) {
                $current = $current->$part;
            } else {
                return false;
            }
        }
        
        return !empty($current);
    }
    
    return isset($data[$field]) && !empty($data[$field]);
}

/**
 * Generate ID from pattern using available data
 * 
 * @param string $pattern Pattern like '{step_id}_{flow_id}'
 * @param array $data Available data
 * @return string Generated ID
 */
function dm_generate_id_from_pattern($pattern, $data) {
    // Replace {field} patterns with actual values
    return preg_replace_callback('/\{([^}]+)\}/', function($matches) use ($data) {
        $field = $matches[1];
        
        // Handle nested field access
        if (strpos($field, '.') !== false) {
            $parts = explode('.', $field);
            $value = $data;
            
            foreach ($parts as $part) {
                if (is_array($value) && isset($value[$part])) {
                    $value = $value[$part];
                } elseif (is_object($value) && isset($value->$part)) {
                    $value = $value->$part;
                } else {
                    return '';
                }
            }
            
            return $value;
        }
        
        return $data[$field] ?? '';
    }, $pattern);
}

/**
 * Extract step-related data
 * 
 * @param array $data Current data
 * @param array $fields Fields to extract from step
 * @return array Enhanced data
 */
function dm_extract_step_data($data, $fields) {
    if (!isset($data['step'])) {
        return $data;
    }
    
    $step = $data['step'];
    
    foreach ($fields as $field) {
        if (is_array($step) && isset($step[$field])) {
            $data[$field] = $step[$field];
        } elseif (is_object($step) && isset($step->$field)) {
            $data[$field] = $step->$field;
        }
    }
    
    return $data;
}

/**
 * Extract flow-related data
 * 
 * @param array $data Current data
 * @param array $fields Fields to extract from flow
 * @return array Enhanced data
 */
function dm_extract_flow_data($data, $fields) {
    if (!isset($data['flow'])) {
        return $data;
    }
    
    $flow = $data['flow'];
    
    foreach ($fields as $field) {
        if (is_array($flow) && isset($flow[$field])) {
            $data[$field] = $flow[$field];
        } elseif (is_object($flow) && isset($flow->$field)) {
            $data[$field] = $flow->$field;
        }
    }
    
    return $data;
}

/**
 * Extract pipeline-related data
 * 
 * @param array $data Current data
 * @param array $fields Fields to extract from pipeline
 * @return array Enhanced data
 */
function dm_extract_pipeline_data($data, $fields) {
    if (!isset($data['pipeline'])) {
        return $data;
    }
    
    $pipeline = $data['pipeline'];
    
    foreach ($fields as $field) {
        if (is_array($pipeline) && isset($pipeline[$field])) {
            $data[$field] = $pipeline[$field];
        } elseif (is_object($pipeline) && isset($pipeline->$field)) {
            $data[$field] = $pipeline->$field;
        }
    }
    
    return $data;
}

/**
 * Central Flow Step ID Generation Utility
 * 
 * Provides consistent flow_step_id generation across all system components.
 * Flow step IDs use the pattern: {pipeline_step_id}_{flow_id}
 * 
 * @since 0.1.0
 */
function dm_register_flow_step_id_utility() {
    add_filter('dm_generate_flow_step_id', function($existing_id, $pipeline_step_id, $flow_id) {
        // Validate required parameters
        if (empty($pipeline_step_id) || empty($flow_id)) {
            do_action('dm_log', 'error', 'Invalid flow step ID generation parameters', [
                'pipeline_step_id' => $pipeline_step_id,
                'flow_id' => $flow_id
            ]);
            return '';
        }
        
        // Generate consistent flow_step_id using established pattern
        return $pipeline_step_id . '_' . $flow_id;
    }, 10, 3);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_pipelines_admin_page_filters();
dm_register_flow_step_id_utility();