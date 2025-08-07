<?php
/**
 * Data Machine Engine - Pure Orchestration Layer
 *
 * TRUE ENGINE AGNOSTICISM: Pure orchestration services with zero business logic.
 * Business logic components self-register via their own *Filters.php files.
 * 
 * Engine Bootstrap Functions (Pure Backend Processing Only):
 * - dm_register_direct_service_filters(): Core orchestration services
 * - dm_register_database_service_system(): Pure discovery database service hooks
 * - dm_register_utility_filters(): Pure discovery utility filter hooks
 * 
 * Architectural Separation:
 * - Backend processing logic → Engine components (this file)
 * - Admin/UI logic → Admin components (AdminFilters.php)
 * - Jobs/ProcessedItems logic → Core database components
 * - AI processing logic → AI step components
 * - Handler logic → Handler components
 *
 * @package DataMachine
 * @since 0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register pure engine orchestration services.
 * 
 * PURE ENGINE LAYER: These services are core orchestration utilities that belong
 * in the engine layer. Business logic services are registered in core components.
 * 
 * Engine services registered:
 * - dm_get_ai_http_client: External AI library integration
 * - dm_get_orchestrator: Core pipeline processing orchestration
 * - dm_get_http_service: Generic HTTP utility service
 * 
 * Business logic services (moved to core components):
 * - Logger → Admin component
  
 * - JobCreator → Jobs component
 * - ProcessedItemsManager → ProcessedItems component
 * - AI services → AI step component
 * 
 * Usage: $service = apply_filters('dm_get_{service_name}', null);
 *
 * @since 0.1.0
 */
function dm_register_direct_service_filters() {
    // PURE ENGINE SERVICES - Direct registration for simple orchestration utilities
    // These are core engine components that belong in the orchestration layer
    
    add_filter('dm_get_ai_http_client', function($service) {
        if ($service !== null) {
            return $service;
        }
        return new \AI_HTTP_Client(['plugin_context' => 'data-machine', 'ai_type' => 'llm']);
    }, 10);
    
    add_filter('dm_get_orchestrator', function($service) {
        if ($service !== null) {
            return $service;
        }
        return new \DataMachine\Engine\ProcessingOrchestrator();
    }, 10);
    
    
    add_filter('dm_get_http_service', function($service) {
        if ($service !== null) {
            return $service;
        }
        return new \DataMachine\Engine\HttpService();
    }, 10);
    
    
    add_filter('dm_get_action_scheduler', function($service) {
        if ($service !== null) {
            return $service;
        }
        return new \DataMachine\Engine\ActionSchedulerService();
    }, 10);

}

/**
 * Register pure discovery database service system.
 * 
 * Provides pure discovery access to all database services via collection filtering.
 * Components self-register via *Filters.php files using dm_get_database_services filter.
 * 
 * Usage: $all_databases = apply_filters('dm_get_database_services', []); $db_jobs = $all_databases['jobs'] ?? null;
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
 * ARCHITECTURAL COMPLIANCE: Each service registers via dm_get_database_services filter,
 * following the "plugins within plugins" architecture. External plugins can
 * override or extend services using standard WordPress filter patterns.
 * 
 * Usage: add_filter('dm_get_database_services', function($services) { $services['my_db'] = $instance; return $services; });
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
    
    // Pure discovery filter hook - components self-register via dm_get_database_services
    add_filter('dm_get_database_services', function($services) {
        // Components self-register via this same filter with higher priority
        return $services;
    }, 5, 1);
}




/**
 * Register backend utility filters for data processing.
 * 
 * BACKEND-ONLY FILTERS: Provides pure discovery filter hooks for backend
 * service registration. Admin/UI filters have been moved to AdminFilters.php
 * to maintain clear architectural separation between engine and admin layers.
 * 
 * @since 0.1.0
 */
function dm_register_utility_filters() {
    // Bridge system, configuration parsing, duplicate checking, search/timeframe filtering, 
    // and admin notices all removed - components now use direct WordPress patterns 
    // or dedicated service discovery for proper separation of concerns
    
    // Pure discovery authentication system - consistent with handler discovery patterns
    // Usage: $all_auth = apply_filters('dm_get_auth_providers', []); $twitter_auth = $all_auth['twitter'] ?? null;
    add_filter('dm_get_auth_providers', function($providers) {
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
    
    // Legacy dm_get_auth filter removed - authentication now uses pure discovery via dm_get_auth_providers
    
    // Pure discovery handler settings system (consistent with all other filters)
    // ARCHITECTURAL EXPECTATION: Components self-register via *Filters.php files
    // Bootstrap provides pure filter hook - components add their own registration logic
    add_filter('dm_get_handler_settings', function($all_settings) {
        // ARCHITECTURAL COMPLIANCE COMPLETE - Pure discovery pattern
        // Components self-register via *Filters.php files following established patterns
        //
        // Bootstrap provides only pure filter hook - components add their own logic
        
        return $all_settings; // Components self-register via filters
    }, 10, 1);
    
}


// DataPacket creation system removed - engine uses universal DataPacket constructor
// Input handlers return properly formatted data for direct constructor usage
