<?php
/**
 * Pipelines Admin Page Registration
 * 
 * Self-contained admin page registration following filter-based discovery architecture.
 * Registers page, assets, and modal integration via dm_admin_pages filter.
 * 
 * @package DataMachine\Core\Admin\Pages\Pipelines
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Pipelines admin page components
 * 
 * Self-registration pattern using filter-based discovery.
 * Engine discovers page capabilities through dm_admin_pages filter.
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
                        'file' => 'inc/Core/Admin/Modal/assets/css/core-modal.css',
                        'deps' => [],
                        'media' => 'all'
                    ],
                    'dm-pipelines-page' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/css/pipelines-page.css',
                        'deps' => ['dm-core-modal'],
                        'media' => 'all'
                    ],
                    'dm-pipeline-status' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/css/pipeline-status.css',
                        'deps' => ['dm-pipelines-page'],
                        'media' => 'all'
                    ],
                    'dm-pipelines-modal' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/css/pipelines-modal.css',
                        'deps' => ['dm-core-modal', 'dm-pipelines-page', 'dm-pipeline-status'],
                        'media' => 'all'
                    ],
                    'ai-http-components' => [
                        'file' => 'vendor/chubes4/ai-http-client/assets/css/components.css',
                        'deps' => [],
                        'media' => 'all'
                    ],
                    'dm-import-export' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/css/import-export.css',
                        'deps' => [],
                        'media' => 'all'
                    ]
                ],
                'js' => [
                    'dm-core-modal' => [
                        'file' => 'inc/Core/Admin/Modal/assets/js/core-modal.js',
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
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/js/pipeline-auth.js',
                        'deps' => [],
                        'in_footer' => true
                    ],
                    'dm-pipeline-status' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/js/pipeline-status.js',
                        'deps' => ['jquery'],
                        'in_footer' => true
                    ],
                    'dm-pipeline-cards-ui' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/js/pipeline-cards-ui.js',
                        'deps' => ['jquery', 'jquery-ui-sortable'],
                        'in_footer' => true
                    ],
                    'dm-pipelines-page' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/js/pipelines-page.js',
                        'deps' => ['jquery', 'dm-pipeline-status', 'dm-pipeline-cards-ui'],
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
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/js/pipeline-builder.js',
                        'deps' => ['jquery', 'dm-pipelines-page', 'dm-pipeline-status'],
                        'in_footer' => true
                    ],
                    'dm-flow-builder' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/js/flow-builder.js',
                        'deps' => ['jquery', 'dm-pipelines-page', 'dm-pipeline-status'],
                        'in_footer' => true
                    ],
                    'dm-pipelines-modal' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/js/pipelines-modal.js',
                        'deps' => ['jquery', 'dm-core-modal'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dmPipelineModal',
                            'data' => [
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'admin_post_url' => admin_url('admin-post.php'),
                                'dm_ajax_nonce' => wp_create_nonce('dm_ajax_actions'),
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
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/js/file-uploads.js',
                        'deps' => ['jquery', 'dm-pipelines-modal'],
                        'in_footer' => true
                    ],
                    'ai-http-provider-manager' => [
                        'file' => 'vendor/chubes4/ai-http-client/assets/js/provider-manager.js',
                        'deps' => ['jquery'],
                        'in_footer' => true
                    ],
                    'dm-import-export' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/js/import-export.js',
                        'deps' => ['jquery', 'dm-core-modal'],
                        'in_footer' => true
                    ],
                    'dm-pipeline-auto-save' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/js/pipeline-auto-save.js',
                        'deps' => ['jquery'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dmPipelineAutoSave',
                            'data' => [
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'dm_ajax_nonce' => wp_create_nonce('dm_ajax_actions'),
                                // Status strings removed for silent auto-save
                            ]
                        ]
                    ],
                    // Tool configuration moved to Settings page for better UX
                ]
            ]
        ];
        return $pages;
    });
    
    // Register authentication AJAX handlers
    \DataMachine\Core\Admin\Pages\Pipelines\Ajax\PipelineAuthAjax::register();
    
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

        $modals['pipeline-templates'] = [
            'template' => 'modal/pipeline-templates',
            'title' => __('Choose a Pipeline Template', 'data-machine')
        ];

        $modals['handler-selection'] = [
            'template' => 'modal/handler-selection-cards',
            'title' => __('Select Handler', 'data-machine')
        ];
        
        $modals['configure-step'] = [
            'template' => 'modal/configure-step', // Extensible - steps can register their own templates
            'title' => __('Configure Step', 'data-machine')
        ];
        
        // Tool configuration modal moved to Settings page for better UX
        
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

        // Handler authentication modal
        $modals['modal/handler-auth-form'] = [
            'template' => 'modal/handler-auth-form',
            'title' => __('Handler Authentication', 'data-machine')
        ];

        return $modals;
    });
}

// Auto-register when file loads - achieving complete self-containment
dm_register_pipelines_admin_page_filters();