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
                    'dm-pipeline-shared' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/js/pipeline-shared.js',
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
                        'deps' => ['jquery', 'jquery-ui-sortable', 'dm-pipeline-shared'],
                        'in_footer' => true
                    ],
                    'dm-flow-builder' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/js/flow-builder.js',
                        'deps' => ['jquery', 'dm-pipeline-shared'],
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
            ]
        ];
        return $pages;
    });
    
    // AJAX handler registration - Route to specialized page or modal handlers
    add_action('wp_ajax_dm_pipeline_ajax', function() {
        $action = sanitize_text_field(wp_unslash($_POST['pipeline_action'] ?? $_POST['operation'] ?? ''));
        
        // Define modal actions (UI/template operations)
        $modal_actions = [
            'get_modal', 'get_template', 'get_flow_step_card', 'get_flow_config',
            'configure-step-action', 'add-location-action', 'add-handler-action'
        ];
        
        // Route to appropriate specialized handler
        if (in_array($action, $modal_actions)) {
            $ajax_handler = new PipelineModalAjax();
            $ajax_handler->handle_pipeline_modal_ajax();
        } else {
            // All other actions are page/business logic operations
            $ajax_handler = new PipelinePageAjax();
            $ajax_handler->handle_pipeline_page_ajax();
        }
    });
    
    // Handler settings AJAX endpoint - handles "Add Handler to Flow" form submissions
    add_action('wp_ajax_dm_save_handler_settings', function() {
        dm_handle_save_handler_settings();
    });
    
    // Universal modal AJAX integration - no component-specific handlers needed
    // All modal content routed through unified ModalAjax.php endpoint
    
    // Modal content filter registration - Individual filters following architectural consistency
    
    // Step Selection Modal - Self-registering modal content
    add_filter('dm_get_modal', function($content, $template) {
        // Return early if content already provided by another component
        if ($content !== null) {
            return $content;
        }
        
        if ($template !== 'step-selection') {
            return $content;
        }
        
        // Get context from $_POST directly - jQuery auto-parses JSON data attributes
        $context = $_POST['context'] ?? [];
        
        // Dual-Mode Step Discovery Pattern
        // DISCOVERY MODE: apply_filters('dm_get_steps', []) - Returns ALL registered step types
        $all_steps = apply_filters('dm_get_steps', []);
        
        // Sort steps by position property for logical UI ordering
        uasort($all_steps, function($a, $b) {
            $pos_a = $a['position'] ?? 999;
            $pos_b = $b['position'] ?? 999;
            return $pos_a <=> $pos_b;
        });
        
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
    }, 10, 2);
    
    // Handler Selection Modal - Self-registering modal content
    add_filter('dm_get_modal', function($content, $template) {
        // Return early if content already provided by another component
        if ($content !== null) {
            return $content;
        }
        
        if ($template !== 'handler-selection') {
            return $content;
        }
        
        // Get context from $_POST directly - handle both array and JSON string formats
        $context = $_POST['context'] ?? [];
        if (is_string($context)) {
            $context = json_decode($context, true) ?: [];
        }
        
        $step_type = $context['step_type'] ?? 'unknown';
        $pipeline_id = $context['pipeline_id'] ?? null;
        $flow_id = $context['flow_id'] ?? null;
        
        // Enhanced debugging for modal context generation
        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            $logger->debug('Handler selection modal context', [
                'template' => $template,
                'step_type' => $step_type,
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id,
                'context_keys' => array_keys($context),
                'post_keys' => array_keys($_POST)
            ]);
        }
        
        // Get available handlers using pure discovery
        $all_handlers = apply_filters('dm_get_handlers', []);
        $available_handlers = array_filter($all_handlers, function($handler) use ($step_type) {
            return ($handler['type'] ?? '') === $step_type;
        });
        
        if (empty($available_handlers)) {
            $logger && $logger->warning('No handlers found for step type', ['step_type' => $step_type]);
            return '';
        }
        
        $logger && $logger->debug('Handler selection rendering', [
            'handler_count' => count($available_handlers),
            'handler_slugs' => array_keys($available_handlers)
        ]);
        
        return apply_filters('dm_render_template', '', 'modal/handler-selection-cards', [
            'step_type' => $step_type,
            'handlers' => $available_handlers,
            'pipeline_id' => $pipeline_id,
            'flow_id' => $flow_id
        ]);
    }, 10, 2);
    
    
    // Configure Step Modal - Self-registering modal content
    add_filter('dm_get_modal', function($content, $template) {
        // Return early if content already provided by another component
        if ($content !== null) {
            return $content;
        }
        
        if ($template !== 'configure-step') {
            return $content;
        }
        
        // Get context from $_POST directly - jQuery auto-parses JSON data attributes
        $context = $_POST['context'] ?? [];
        
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
    }, 10, 2);
    
    // Confirm Delete Modal - Self-registering modal content with flow data enrichment
    add_filter('dm_get_modal', function($content, $template) {
        // Return early if content already provided by another component
        if ($content !== null) {
            return $content;
        }
        
        if ($template !== 'confirm-delete') {
            return $content;
        }
        
        // Get context from $_POST directly - jQuery auto-parses JSON data attributes
        $context = $_POST['context'] ?? [];
        if (is_string($context)) {
            $context = json_decode($context, true) ?: [];
        }
        
        $delete_type = $context['delete_type'] ?? 'step';
        $pipeline_id = $context['pipeline_id'] ?? null;
        $flow_id = $context['flow_id'] ?? null;
        $step_id = $context['step_id'] ?? null;
        
        // Enhanced logging for modal context
        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            $logger->debug('Confirm delete modal context', [
                'delete_type' => $delete_type,
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id,
                'step_id' => $step_id
            ]);
        }
        
        // Enrich context with flow data based on deletion type
        $affected_flows = [];
        $affected_jobs = [];
        
        if ($delete_type === 'pipeline' && $pipeline_id) {
            // Get all flows for this pipeline
            $all_databases = apply_filters('dm_get_database_services', []);
            $db_flows = $all_databases['flows'] ?? null;
            if ($db_flows) {
                $flows = $db_flows->get_flows_for_pipeline($pipeline_id);
                foreach ($flows as $flow) {
                    $affected_flows[] = [
                        'name' => $flow['flow_name'],
                        'created_at' => $flow['created_at']
                    ];
                }
            }
            
            // Get jobs count for this pipeline
            $db_jobs = $all_databases['jobs'] ?? null;
            if ($db_jobs) {
                $jobs = $db_jobs->get_jobs_for_pipeline($pipeline_id);
                $affected_jobs = $jobs; // Count will be used in template
            }
            
        } elseif ($delete_type === 'step' && $pipeline_id) {
            // For step deletion, get flows for pipeline to show impact
            $all_databases = apply_filters('dm_get_database_services', []);
            $db_flows = $all_databases['flows'] ?? null;
            if ($db_flows) {
                $flows = $db_flows->get_flows_for_pipeline($pipeline_id);
                foreach ($flows as $flow) {
                    $affected_flows[] = [
                        'name' => $flow['flow_name'],
                        'created_at' => $flow['created_at']
                    ];
                }
            }
        }
        // For flow deletion, no additional flows needed (flows don't have sub-flows)
        
        // Log the enriched data
        if ($logger) {
            $logger->debug('Delete modal flow enrichment', [
                'affected_flows_count' => count($affected_flows),
                'affected_jobs_count' => count($affected_jobs)
            ]);
        }
        
        // Merge enriched data with context for template
        $enriched_context = array_merge($context, [
            'affected_flows' => $affected_flows,
            'affected_jobs' => $affected_jobs
        ]);
        
        return apply_filters('dm_render_template', '', 'modal/confirm-delete', $enriched_context);
    }, 10, 2);
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
    $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
    $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
    
    $logger && $logger->debug('Handler settings extracted parameters', [
        'handler_slug' => $handler_slug,
        'step_type' => $step_type,
        'flow_id' => $flow_id,
        'pipeline_id' => $pipeline_id
    ]);
    
    if (empty($handler_slug) || empty($step_type)) {
        $error_details = [
            'handler_slug_empty' => empty($handler_slug),
            'step_type_empty' => empty($step_type),
            'post_keys' => array_keys($_POST)
        ];
        
        $logger && $logger->error('Handler slug and step type validation failed', $error_details);
        
        wp_send_json_error([
            'message' => __('Handler slug and step type are required.', 'data-machine'),
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
    
    // Get settings class to process form data
    $settings_instance = apply_filters('dm_get_handler_settings', null, $handler_slug);
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
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
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
        
        // Find or create step configuration
        $step_key = $step_type;
        if (!isset($flow_config['steps'][$step_key])) {
            $flow_config['steps'][$step_key] = [
                'step_type' => $step_type,
                'handlers' => []
            ];
        }
        
        // Initialize handlers array if it doesn't exist
        if (!isset($flow_config['steps'][$step_key]['handlers'])) {
            $flow_config['steps'][$step_key]['handlers'] = [];
        }
        
        // Check if handler already exists
        $handler_exists = isset($flow_config['steps'][$step_key]['handlers'][$handler_slug]);
        
        // UPDATE existing handler settings OR ADD new handler (no replacement)
        $flow_config['steps'][$step_key]['handlers'][$handler_slug] = [
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