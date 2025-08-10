<?php
/**
 * Data Machine Engine - Pure Orchestration Layer
 *
 * TRUE ENGINE AGNOSTICISM: Pure orchestration services with zero business logic.
 * Business logic components self-register via their own *Filters.php files.
 * 
 * Engine Bootstrap Functions (Pure Backend Processing Only):
 * - dm_register_database_service_system(): Pure discovery database service hooks
 * - dm_register_utility_filters(): Pure discovery utility filter hooks
 * 
 * Architectural Separation:
 * - Core engine components (ProcessingOrchestrator, Logger): Direct instantiation
 * - Extensible services (database, handlers, steps): Filter-based discovery
 * - Backend processing logic → Engine components (this file)
 * - Admin/UI logic → Core admin components (inc/core/admin/)
 * - Database services → Core database components (*Filters.php)
 * - Handler logic → Handler components (*Filters.php)
 *
 * @package DataMachine
 * @since 0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


/**
 * Register pure discovery database service system.
 * 
 * Provides pure discovery access to all database services via collection filtering.
 * Components self-register via *Filters.php files using dm_db filter.
 * 
 * Usage: $all_databases = apply_filters('dm_db', []); $db_jobs = $all_databases['jobs'] ?? null;
 *
 * @since NEXT_VERSION
 */
function dm_register_database_service_system() {
    // Pure filter-based discovery - no hardcoded services
    // Core database services self-register via individual filters
    dm_register_core_database_services();
}

/**
 * Register core database services via pure discovery self-registration.
 * 
 * ARCHITECTURAL COMPLIANCE: Each service registers via dm_db filter,
 * following the "plugins within plugins" architecture. External plugins can
 * override or extend services using standard WordPress filter patterns.
 * 
 * Usage: add_filter('dm_db', function($services) { $services['my_db'] = $instance; return $services; });
 * 
 * @since NEXT_VERSION
 */
function dm_register_core_database_services() {
    // Database services self-register via *Filters.php files
    // Bootstrap provides pure filter hook - components add their own registration logic
    // Required component *Filters.php files:
    // - JobsFilters.php
    // - PipelinesFilters.php  
    // - FlowsFilters.php
    // - ProcessedItemsFilters.php
    // - RemoteLocationsFilters.php
    
    // Pure discovery filter hook - components self-register via dm_db
    add_filter('dm_db', function($services) {
        // Components self-register via this same filter with higher priority
        return $services;
    }, 5, 1);
}




/**
 * Register core filter hooks for data discovery and transformation.
 * 
 * FILTER-ONLY REGISTRATION: Provides pure discovery filter hooks for service
 * registration and data transformation. Action hooks have been moved to 
 * DataMachineActions.php to maintain clear architectural separation.
 * 
 * Filters registered:
 * - dm_auth_providers: Authentication provider discovery
 * - dm_handler_settings: Handler configuration discovery  
 * - dm_handler_directives: AI directive discovery for handlers
 * - dm_scheduler_intervals: Extensible scheduler interval definitions for external plugins
 * 
 * @since 0.1.0
 */
function dm_register_utility_filters() {
    
    // Pure discovery authentication system - consistent with handler discovery patterns
    // Usage: $all_auth = apply_filters('dm_auth_providers', []); $twitter_auth = $all_auth['twitter'] ?? null;
    add_filter('dm_auth_providers', function($providers) {
        // Auto-register hooks for all auth providers
        static $registered_hooks = [];
        
        foreach ($providers as $auth_instance) {
            if (is_object($auth_instance) && method_exists($auth_instance, 'register_hooks')) {
                $auth_class = get_class($auth_instance);
                
                // Only register hooks once per auth class
                if (!isset($registered_hooks[$auth_class])) {
                    $auth_instance->register_hooks();
                    $registered_hooks[$auth_class] = true;
                }
            }
        }
        
        return $providers; // Return collection unchanged - components self-register via this same filter
    }, 5, 1);
    
    // Pure discovery handler settings system - consistent with all other discovery filters
    // Usage: $all_settings = apply_filters('dm_handler_settings', []); $settings = $all_settings[$handler_slug] ?? null;
    add_filter('dm_handler_settings', function($all_settings) {
        // Components self-register via *Filters.php files following established patterns
        // Bootstrap provides only pure filter hook - components add their own logic
        return $all_settings; // Components self-register via filters
    }, 10, 1);
    
    // Pure discovery handler directives system - cross-component AI directive discovery
    // Usage: $all_directives = apply_filters('dm_handler_directives', []); $directive = $all_directives[$handler_slug] ?? '';
    add_filter('dm_handler_directives', '__return_empty_array');
    
    // ProcessedItems checking system - single responsibility filter for duplicate prevention
    // Usage: $is_processed = apply_filters('dm_is_item_processed', false, $flow_id, $source_type, $item_identifier);
    add_filter('dm_is_item_processed', function($default, $flow_id, $source_type, $item_identifier) {
        $all_databases = apply_filters('dm_db', []);
        $processed_items = $all_databases['processed_items'] ?? null;
        
        if (!$processed_items) {
            do_action('dm_log', 'warning', 'ProcessedItems service unavailable for item check', [
                'flow_id' => $flow_id, 
                'source_type' => $source_type,
                'item_identifier' => substr($item_identifier, 0, 50) . '...'
            ]);
            return false;
        }
        
        $is_processed = $processed_items->has_item_been_processed($flow_id, $source_type, $item_identifier);
        
        // Optional debug logging for processed item checks
        do_action('dm_log', 'debug', 'Processed item check via filter', [
            'flow_id' => $flow_id,
            'source_type' => $source_type,
            'identifier' => substr($item_identifier, 0, 50) . '...',
            'is_processed' => $is_processed
        ]);
        
        return $is_processed;
    }, 10, 4);
    
    // Extensible scheduler intervals system - allows external plugins to add custom intervals
    // Usage: $all_intervals = apply_filters('dm_scheduler_intervals', []); $hourly = $all_intervals['hourly'] ?? null;
    add_filter('dm_scheduler_intervals', function($intervals) {
        return [
            'every_5_minutes' => [
                'label' => __('Every 5 Minutes', 'data-machine'),
                'seconds' => 300 // 5 * 60
            ],
            'hourly' => [
                'label' => __('Hourly', 'data-machine'),
                'seconds' => HOUR_IN_SECONDS
            ],
            'every_2_hours' => [
                'label' => __('Every 2 Hours', 'data-machine'),
                'seconds' => HOUR_IN_SECONDS * 2
            ],
            'every_4_hours' => [
                'label' => __('Every 4 Hours', 'data-machine'),
                'seconds' => HOUR_IN_SECONDS * 4
            ],
            'qtrdaily' => [
                'label' => __('Every 6 Hours', 'data-machine'),
                'seconds' => HOUR_IN_SECONDS * 6
            ],
            'twicedaily' => [
                'label' => __('Twice Daily', 'data-machine'),
                'seconds' => HOUR_IN_SECONDS * 12
            ],
            'daily' => [
                'label' => __('Daily', 'data-machine'),
                'seconds' => DAY_IN_SECONDS
            ],
            'weekly' => [
                'label' => __('Weekly', 'data-machine'),
                'seconds' => WEEK_IN_SECONDS
            ]
        ];
    }, 10);
    
    // Flow step ID parsing system - clean separation of parsing logic from execution engine
    // Usage: $parts = apply_filters('dm_split_flow_step_id', [], $flow_step_id); $flow_id = $parts['flow_id'] ?? null;
    add_filter('dm_split_flow_step_id', function($default, $flow_step_id) {
        if (empty($flow_step_id) || !is_string($flow_step_id)) {
            return [];
        }
        
        $parts = explode('_', $flow_step_id);
        if (count($parts) >= 2) {
            // Last part is flow_id, everything before is pipeline_step_id (handles UUID underscores)
            $flow_id = array_pop($parts);
            $pipeline_step_id = implode('_', $parts);
            
            return [
                'pipeline_step_id' => $pipeline_step_id,
                'flow_id' => (int)$flow_id
            ];
        }
        
        return [];
    }, 10, 2);
    
}

/**
 * Register admin extension points for core-to-engine integration.
 * 
 * These filters provide extension points that allow core admin functionality
 * to discover and use engine services following the architectural pattern:
 * Engine = behavior/automation, Core = extensible via filter discovery.
 * 
 * @since 0.1.0
 */
function dm_register_admin_extension_points() {
    // AdminMenuAssets removed - now uses direct instantiation as core engine component
    // AdminMenuAssets is core engine infrastructure, not extensible functionality

    // Step configurations collection filter - infrastructure hook for components
    add_filter('dm_step_settings', function($configs) {
        // Engine provides the filter hook infrastructure
        // Step components self-register their configuration capabilities via this same filter
        return $configs;
    }, 5);
    
    // Universal template rendering filter - discovers templates from admin page registration
    add_filter('dm_render_template', function($content, $template_name, $data = []) {
        // Dynamic discovery of all registered admin pages and their template directories
        $all_pages = apply_filters('dm_admin_pages', []);
        
        foreach ($all_pages as $slug => $page_config) {
            if (!empty($page_config['templates'])) {
                $template_path = $page_config['templates'] . $template_name . '.php';
                if (file_exists($template_path)) {
                    // Extract data variables for template use
                    extract($data);
                    ob_start();
                    include $template_path;
                    return ob_get_clean();
                }
            }
        }
        
        // Fallback: Search core modal templates directory
        // Handle modal/ prefix in template names correctly
        if (strpos($template_name, 'modal/') === 0) {
            $modal_template_name = substr($template_name, 6); // Remove 'modal/' prefix
            $core_modal_template_path = DATA_MACHINE_PATH . 'inc/core/admin/modal/templates/' . $modal_template_name . '.php';
        } else {
            $core_modal_template_path = DATA_MACHINE_PATH . 'inc/core/admin/modal/templates/' . $template_name . '.php';
        }
        
        if (file_exists($core_modal_template_path)) {
            // Extract data variables for template use
            extract($data);
            ob_start();
            include $core_modal_template_path;
            return ob_get_clean();
        }
        
        // Log error and return empty string - no user-facing error display
        do_action('dm_log', 'error', "Template not found: {$template_name}");
        return '';
    }, 10, 3);
}
