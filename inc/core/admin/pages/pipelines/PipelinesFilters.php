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
                    ],
                    'dm-import-export' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/css/import-export.css',
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
                                'dm_ajax_nonce' => wp_create_nonce('dm_ajax_actions'),
                                'strings' => [
                                    'loading' => __('Loading...', 'data-machine'),
                                    'error' => __('Error', 'data-machine'),
                                    'close' => __('Close', 'data-machine')
                                ]
                            ]
                        ]
                    ],
                    'dm-pipeline-auth' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/js/pipeline-auth.js',
                        'deps' => [],
                        'in_footer' => true
                    ],
                    'dm-pipelines-page' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/js/pipelines-page.js',
                        'deps' => ['jquery'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dmPipelineBuilder',
                            'data' => [
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'dm_ajax_nonce' => wp_create_nonce('dm_ajax_actions'),
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
                                'dm_ajax_nonce' => wp_create_nonce('dm_ajax_actions'),
                                'oauth_nonces' => [
                                    'twitter' => wp_create_nonce('dm_twitter_oauth_init_nonce'),
                                    'googlesheets' => wp_create_nonce('dm_googlesheets_oauth_init_nonce'),
                                    'reddit' => wp_create_nonce('dm_reddit_oauth_init_nonce'),
                                    'facebook' => wp_create_nonce('dm_facebook_oauth_init_nonce'),
                                    'threads' => wp_create_nonce('dm_threads_oauth_init_nonce')
                                ],
                                'disconnect_nonce' => wp_create_nonce('dm_disconnect_account'),
                                'get_files_nonce' => wp_create_nonce('dm_get_handler_files'),
                                'strings' => [
                                    'connecting' => __('Connecting...', 'data-machine'),
                                    'disconnecting' => __('Disconnecting...', 'data-machine'),
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
                    ],
                    'dm-import-export' => [
                        'file' => 'inc/core/admin/pages/pipelines/assets/js/import-export.js',
                        'deps' => ['jquery', 'dm-core-modal'],
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
    
    // Import/Export AJAX endpoints
    add_action('wp_ajax_dm_export_pipelines', fn() => do_action('dm_ajax_route', 'dm_export_pipelines', 'page'));
    add_action('wp_ajax_dm_import_pipelines', fn() => do_action('dm_ajax_route', 'dm_import_pipelines', 'page'));
    
    // Modal actions (UI/template operations) - using universal AJAX routing
    add_action('wp_ajax_dm_get_template', fn() => do_action('dm_ajax_route', 'dm_get_template', 'modal'));
    add_action('wp_ajax_dm_get_flow_step_card', fn() => do_action('dm_ajax_route', 'dm_get_flow_step_card', 'modal'));
    add_action('wp_ajax_dm_get_flow_config', fn() => do_action('dm_ajax_route', 'dm_get_flow_config', 'modal'));
    add_action('wp_ajax_dm_configure_step_action', fn() => do_action('dm_ajax_route', 'dm_configure_step_action', 'modal'));
    add_action('wp_ajax_dm_add_location_action', fn() => do_action('dm_ajax_route', 'dm_add_location_action', 'modal'));
    add_action('wp_ajax_dm_add_handler_action', fn() => do_action('dm_ajax_route', 'dm_add_handler_action', 'modal'));
    
    // Handler settings AJAX endpoint - handles "Add Handler to Flow" form submissions
    add_action('wp_ajax_dm_save_handler_settings', fn() => do_action('dm_ajax_route', 'dm_save_handler_settings', 'modal'));
    
    // Account disconnection AJAX endpoint - handles disconnect button clicks
    add_action('wp_ajax_dm_disconnect_account', fn() => do_action('dm_ajax_route', 'dm_disconnect_account', 'modal'));
    
    // OAuth status check AJAX endpoint - handles authentication status polling
    add_action('wp_ajax_dm_check_oauth_status', fn() => do_action('dm_ajax_route', 'dm_check_oauth_status', 'modal'));
    
    // Auth configuration AJAX endpoint - handles auth config form submissions
    add_action('wp_ajax_dm_save_auth_config', function() {
        // Security verification
        if (!check_ajax_referer('dm_ajax_actions', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $handler_slug = sanitize_text_field(wp_unslash($_POST['handler_slug'] ?? ''));
        if (empty($handler_slug)) {
            wp_send_json_error(['message' => __('Handler slug is required', 'data-machine')]);
        }

        // Get auth provider instance to validate fields
        $all_auth = apply_filters('dm_auth_providers', []);
        $auth_instance = $all_auth[$handler_slug] ?? null;
        if (!$auth_instance || !method_exists($auth_instance, 'get_config_fields')) {
            wp_send_json_error(['message' => __('Auth provider not found or invalid', 'data-machine')]);
        }

        // Get field definitions for validation
        $config_fields = $auth_instance->get_config_fields();
        $config_data = [];

        // Validate and sanitize each field
        foreach ($config_fields as $field_name => $field_config) {
            $value = sanitize_text_field(wp_unslash($_POST[$field_name] ?? ''));
            
            // Check required fields
            if (($field_config['required'] ?? false) && empty($value)) {
                wp_send_json_error(['message' => sprintf(__('%s is required', 'data-machine'), $field_config['label'])]);
            }
            
            $config_data[$field_name] = $value;
        }

        // Save configuration using dm_oauth filter
        $saved = apply_filters('dm_oauth', null, 'store_config', $handler_slug, $config_data);
        
        if ($saved) {
            wp_send_json_success(['message' => __('Configuration saved successfully', 'data-machine')]);
        } else {
            wp_send_json_error(['message' => __('Failed to save configuration', 'data-machine')]);
        }
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
}

// Handler settings functionality moved to PipelineModalAjax class to follow dm_ajax_route pattern

// Auto-register when file loads - achieving complete self-containment
dm_register_pipelines_admin_page_filters();