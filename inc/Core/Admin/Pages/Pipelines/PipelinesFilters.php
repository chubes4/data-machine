<?php
/**
 * Pipelines Admin Page Registration
 * 
 * Self-contained admin page registration following filter-based discovery architecture.
 * Registers page, assets, and modal integration via datamachine_admin_pages filter.
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
 * Engine discovers page capabilities through datamachine_admin_pages filter.
 */
function datamachine_register_pipelines_admin_page_filters() {
    
    // Pure discovery mode - matches actual system usage
    add_filter('datamachine_admin_pages', function($pages) {
        $pages['pipelines'] = [
            'page_title' => __('Pipelines', 'datamachine'),
            'menu_title' => __('Pipelines', 'datamachine'),
            'capability' => 'manage_options',
            'position' => 10,
            'templates' => __DIR__ . '/templates/',
            'assets' => [
                'css' => [
                    'wp-components' => [
                        'file' => null, // Use WordPress core version
                        'deps' => [],
                        'media' => 'all'
                    ],
                    'datamachine-core-modal' => [
                        'file' => 'inc/Core/Admin/Modal/assets/css/core-modal.css',
                        'deps' => ['wp-components'],
                        'media' => 'all'
                    ],
                    'datamachine-pipelines-page' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/css/pipelines-page.css',
                        'deps' => ['datamachine-core-modal'],
                        'media' => 'all'
                    ],
                    'datamachine-pipelines-modal' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/css/pipelines-modal.css',
                        'deps' => ['datamachine-core-modal', 'datamachine-pipelines-page'],
                        'media' => 'all'
                    ],
                    'ai-http-components' => [
                        'file' => 'vendor/chubes4/ai-http-client/assets/css/components.css',
                        'deps' => [],
                        'media' => 'all'
                    ],
                    'datamachine-import-export' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/css/import-export.css',
                        'deps' => [],
                        'media' => 'all'
                    ]
                ],
                'js' => [
                    // React bundle (only script needed)
                    'datamachine-pipelines-react' => [
                        'file' => 'inc/Core/Admin/Pages/Pipelines/assets/build/pipelines-react.js',
                        'deps' => ['wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'wp-data', 'wp-dom-ready', 'wp-notices'],
                        'in_footer' => true,
                        'localize' => [
                            'object' => 'dataMachineConfig',
                            'data' => [
                                'restNamespace' => 'datamachine/v1',
                                'restNonce' => wp_create_nonce('wp_rest'),
                                // stepTypes: Loaded via REST API in PipelineContext
                                // handlers: Loaded via REST API in PipelineContext
                                // stepSettings: Dead code - removed
                                // aiProviders: Loaded via REST API in ConfigureStepModal
                                // aiTools: Loaded via REST API in AIToolsSelector
                                // handlerSettings: Lazy-loaded via REST API in HandlerSettingsModal
                            ]
                        ]
                    ]
                ]
            ]
        ];
        return $pages;
    });

    // Pipeline auto-save hook moved to DataMachineActions.php for architectural consistency

    // Pipelines page uses React modals - no AJAX modal loading needed
    // These modal registrations are for the legacy template-based system (not actively used)

    // Modal registration - Two-layer architecture: metadata only, content via datamachine_render_template
    add_filter('datamachine_modals', function($modals) {
        // Legacy pipeline modals - metadata only (Pipelines page uses React instead)
        $modals['step-selection'] = [
            'template' => 'modal/step-selection-cards',
            'title' => __('Select Step Type', 'datamachine')
        ];

        $modals['handler-selection'] = [
            'template' => 'modal/handler-selection-cards',
            'title' => __('Select Handler', 'datamachine')
        ];
        
        $modals['configure-step'] = [
            'template' => 'modal/configure-step', // Extensible - steps can register their own templates
            'title' => __('Configure Step', 'datamachine')
        ];
        
        // Tool configuration modal moved to Settings page for better UX
        
        $modals['confirm-delete'] = [
            'template' => 'modal/confirm-delete',
            'title' => __('Confirm Delete', 'datamachine')
        ];
        
        // Flow scheduling modal
        $modals['flow-schedule'] = [
            'template' => 'modal/flow-schedule',
            'title' => __('Schedule Flow', 'datamachine')
        ];
        
        // Handler-specific settings modals - direct template access
        // WordPress handlers require input/output distinction
        $modals['handler-settings'] = [
            'dynamic_template' => true, // Flag for dynamic template resolution
            'title' => __('Handler Settings', 'datamachine')
        ];

        // Handler authentication modal
        $modals['modal/handler-auth-form'] = [
            'template' => 'modal/handler-auth-form',
            'title' => __('Handler Authentication', 'datamachine')
        ];

        return $modals;
    });
}

/**
 * Get AI providers formatted for React
 *
 * @return array AI providers with models
 */
function datamachine_get_ai_providers_for_react() {
    // Get AI providers from HTTP client library
    $http_providers = apply_filters('ai_http_providers', []);

    $providers = [];
    foreach ($http_providers as $key => $provider_data) {
        $providers[$key] = [
            'label' => $provider_data['label'] ?? ucfirst($key),
            'models' => $provider_data['models'] ?? []
        ];
    }

    return $providers;
}

/**
 * Get AI tools formatted for React
 *
 * @return array AI tools with configuration status
 */
function datamachine_get_ai_tools_for_react() {
    // Get all available tools
    $all_tools = apply_filters('ai_tools', []);

    // Filter to only general tools (no handler property)
    $general_tools = array_filter($all_tools, function($tool_def) {
        return !isset($tool_def['handler']);
    });

    $tools = [];
    foreach ($general_tools as $tool_id => $tool_def) {
        $tools[$tool_id] = [
            'label' => $tool_def['label'] ?? ucfirst(str_replace('_', ' ', $tool_id)),
            'description' => $tool_def['description'] ?? '',
            'configured' => apply_filters('datamachine_tool_configured', false, $tool_id)
        ];
    }

    return $tools;
}

// Auto-register when file loads - achieving complete self-containment
datamachine_register_pipelines_admin_page_filters();