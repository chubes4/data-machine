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
    add_action('wp_ajax_dm_pipeline_ajax', function() {
        $action = sanitize_text_field(wp_unslash($_POST['pipeline_action'] ?? $_POST['operation'] ?? ''));
        
        // Get admin page configuration with AJAX handlers
        $all_pages = apply_filters('dm_get_admin_pages', []);
        $pipeline_page = $all_pages['pipelines'] ?? null;
        $ajax_handlers = $pipeline_page['ajax_handlers'] ?? [];
        
        // Define modal actions (UI/template operations)
        $modal_actions = [
            'get_modal', 'get_template', 'get_flow_step_card', 'get_flow_config',
            'configure-step-action', 'add-location-action', 'add-handler-action'
        ];
        
        // Route using discovered handlers
        $modal_handler = $ajax_handlers['modal'] ?? null;
        $page_handler = $ajax_handlers['page'] ?? null;
        
        if (in_array($action, $modal_actions) && $modal_handler) {
            $modal_handler->handle_pipeline_modal_ajax();
        } else if ($page_handler) {
            $page_handler->handle_pipeline_page_ajax();
        } else {
            wp_send_json_error(['message' => __('AJAX handler not found', 'data-machine')]);
        }
    });
    
    // Handler settings AJAX endpoint - handles "Add Handler to Flow" form submissions
    add_action('wp_ajax_dm_save_handler_settings', function() {
        dm_handle_save_handler_settings();
    });
    
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
        
        // Generic handler settings modal (redirects to specific handlers)
        $modals['handler-settings'] = [
            'template' => 'modal/handler-settings-form',
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
    $logger = apply_filters('dm_get_logger', null);
    if ($logger) {
        $logger->debug('Save handler settings request received', [
            'post_keys' => array_keys($_POST),
            'post_data' => array_intersect_key($_POST, array_flip(['handler_slug', 'step_type', 'flow_id', 'pipeline_id', 'action'])),
            'has_nonce' => isset($_POST['handler_settings_nonce']),
            'user_can_manage' => current_user_can('manage_options')
        ]);
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['handler_settings_nonce'] ?? '', 'dm_save_handler_settings')) {
        $logger && $logger->error('Handler settings nonce verification failed');
        wp_send_json_error(['message' => __('Security check failed.', 'data-machine')]);
        return;
    }
    
    // Verify user capabilities
    if (!current_user_can('manage_options')) {
        $logger && $logger->error('Handler settings insufficient permissions');
        wp_send_json_error(['message' => __('Insufficient permissions.', 'data-machine')]);
        return;
    }
    
    // Get form data
    $handler_slug = sanitize_text_field(wp_unslash($_POST['handler_slug'] ?? ''));
    $step_type = sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));
    $step_id = sanitize_text_field(wp_unslash($_POST['step_id'] ?? ''));
    $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
    $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
    
    $logger && $logger->debug('Handler settings extracted parameters', [
        'handler_slug' => $handler_slug,
        'step_type' => $step_type,
        'step_id' => $step_id,
        'flow_id' => $flow_id,
        'pipeline_id' => $pipeline_id
    ]);
    
    if (empty($handler_slug) || empty($step_type) || empty($step_id)) {
        $error_details = [
            'handler_slug_empty' => empty($handler_slug),
            'step_type_empty' => empty($step_type),
            'step_id_empty' => empty($step_id),
            'post_keys' => array_keys($_POST)
        ];
        
        $logger && $logger->error('Handler slug, step type, and step ID validation failed', $error_details);
        
        wp_send_json_error([
            'message' => __('Handler slug, step type, and step ID are required.', 'data-machine'),
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
        
        // Parse current flow configuration
        $flow_config_raw = $flow['flow_config'] ?? '{}';
        $flow_config = is_string($flow_config_raw) ? json_decode($flow_config_raw, true) : $flow_config_raw;
        $flow_config = $flow_config ?: [];
        
        // Initialize step configuration if it doesn't exist
        if (!isset($flow_config['steps'])) {
            $flow_config['steps'] = [];
        }
        
        // Find or create step configuration using step_id
        if (!isset($flow_config['steps'][$step_id])) {
            $flow_config['steps'][$step_id] = [
                'step_type' => $step_type,
                'handlers' => []
            ];
        }
        
        // Initialize handlers array if it doesn't exist
        if (!isset($flow_config['steps'][$step_id]['handlers'])) {
            $flow_config['steps'][$step_id]['handlers'] = [];
        }
        
        // Check if handler already exists
        $handler_exists = isset($flow_config['steps'][$step_id]['handlers'][$handler_slug]);
        
        // UPDATE existing handler settings OR ADD new handler (no replacement)
        $flow_config['steps'][$step_id]['handlers'][$handler_slug] = [
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
        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            $action_type = $handler_exists ? 'updated' : 'added';
            $logger->debug("Handler '{$handler_slug}' {$action_type} for step '{$step_type}' in flow {$flow_id}");
        }
        
        $action_message = $handler_exists 
            ? sprintf(__('Handler "%s" settings updated successfully', 'data-machine'), $handler_config['label'] ?? $handler_slug)
            : sprintf(__('Handler "%s" added to flow successfully', 'data-machine'), $handler_config['label'] ?? $handler_slug);
        
        wp_send_json_success([
            'message' => $action_message,
            'handler_slug' => $handler_slug,
            'step_type' => $step_type,
            'flow_id' => $flow_id,
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

// Auto-register when file loads - achieving complete self-containment
dm_register_pipelines_admin_page_filters();