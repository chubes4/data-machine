<?php
/**
 * Data Machine Engine - Pure Orchestration Layer
 *
 * TRUE ENGINE AGNOSTICISM: Pure orchestration services with zero business logic.
 * Business logic components self-register via their own *Filters.php files.
 * 
 * Engine Bootstrap Functions (Pure Backend Processing Only):
 * - dm_register_direct_service_filters(): Core orchestration services
 * - dm_register_database_service_system(): Parameter-based database filter hooks  
 * - dm_register_wpdb_service_filter(): WordPress database access
 * - dm_register_context_retrieval_service(): DataPacket context orchestration
 * - dm_register_universal_handler_system(): Handler registration filter hooks
 * - dm_register_utility_filters(): Backend utility filter hooks only
 * - dm_register_step_auto_discovery_system(): Step registration filter hooks
 * - dm_register_datapacket_creation_system(): DataPacket creation filter hooks
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
 * - dm_get_constants: System constants and configuration
 * - dm_get_http_service: Generic HTTP utility service
 * - dm_get_pipeline_context: Pipeline sequence management
 * 
 * Business logic services (moved to core components):
 * - Logger → Admin component
 * - JobStatusManager → Jobs component  
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
    
    add_filter('dm_get_constants', function($service) {
        if ($service !== null) {
            return $service;
        }
        return new \DataMachine\Engine\Constants();
    }, 10);
    
    add_filter('dm_get_http_service', function($service) {
        if ($service !== null) {
            return $service;
        }
        return new \DataMachine\Engine\HttpService();
    }, 10);
    
    add_filter('dm_get_pipeline_context', function($service) {
        if ($service !== null) {
            return $service;
        }
        return new \DataMachine\Engine\PipelineContext();
    }, 10);
    
    add_filter('dm_get_action_scheduler', function($service) {
        if ($service !== null) {
            return $service;
        }
        return new \DataMachine\Engine\ActionSchedulerService();
    }, 10);
    
    // Note: Business logic services moved to appropriate core component *Filters.php files:
    // - Logger → Admin component
    // - FluidContextBridge → AI step component  
    // - JobStatusManager → Jobs component
    // - JobCreator → Jobs component
    // - ProcessedItemsManager → ProcessedItems component
}

/**
 * Register parameter-based database service system.
 * 
 * Provides a unified filter for all database services using type parameters.
 * Pure parameter-based system enforcing architectural consistency.
 * 
 * Usage: $db_service = apply_filters('dm_get_database_service', null, 'jobs');
 *
 * @since NEXT_VERSION
 */
function dm_register_database_service_system() {
    // Pure filter-based discovery - no hardcoded services
    // Core database services self-register via individual filters
    dm_register_core_database_services();
}

/**
 * Register core database services via pure filter-based self-registration.
 * 
 * ARCHITECTURAL COMPLIANCE: Each service registers individually via filters,
 * following the "plugins within plugins" architecture. External plugins can
 * override or extend services using standard WordPress filter patterns.
 * 
 * Usage: add_filter('dm_get_database_service', function($service, $type) {...}, 10, 2);
 * 
 * @since NEXT_VERSION
 */
function dm_register_core_database_services() {
    // ARCHITECTURAL COMPLIANCE COMPLETE - Database services self-register via *Filters.php files
    // Bootstrap provides pure filter hook - components add their own registration logic
    // Required component *Filters.php files:
    // - JobsFilters.php
    // - PipelinesFilters.php  
    // - FlowsFilters.php
    // - ProcessedItemsFilters.php
    // - RemoteLocationsFilters.php
    
    // Pure filter hook - components self-register
    add_filter('dm_get_database_service', function($service, $type) {
        // Components self-register via this same filter with higher priority
        return $service;
    }, 5, 2);
}

/**
 * Register WPDB service filter for WordPress database access.
 * 
 * Provides filter-based access to WordPress global $wpdb object,
 * maintaining architectural consistency across all database components.
 * Enables external plugins to override database connection if needed.
 * 
 * Usage: $wpdb = apply_filters('dm_get_wpdb_service', null);
 * 
 * @since NEXT_VERSION
 */
function dm_register_wpdb_service_filter() {
    add_filter('dm_get_wpdb_service', function($wpdb_service) {
        if ($wpdb_service !== null) {
            return $wpdb_service; // External override provided
        }
        
        global $wpdb;
        return $wpdb;
    }, 10);
}

/**
 * Register context retrieval service for pipeline DataPackets.
 * 
 * PURE ENGINE SERVICE: Context retrieval is a core orchestration function
 * that belongs in the engine layer as it deals with DataPacket orchestration.
 * 
 * Usage: $context = apply_filters('dm_get_context', null, $job_id);
 * 
 * @since 0.1.0
 */
function dm_register_context_retrieval_service() {
    // Add context retrieval filter for pipeline DataPackets
    // Returns all DataPackets from the current pipeline job as an array
    add_filter('dm_get_context', function($context, $job_id, $filter_type = 'all') {
        if ($context !== null) {
            return $context; // External override provided
        }
        
        if (empty($job_id)) {
            return [];
        }
        
        // Get Jobs database service
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
        if (!$db_jobs) {
            return [];
        }
        
        // Retrieve all step data for the job
        $step_data = $db_jobs->get_job_step_data($job_id);
        if (empty($step_data)) {
            return [];
        }
        
        $context_packets = [];
        
        // Convert stored JSON step data back to DataPacket objects
        foreach ($step_data as $step_name => $step_data_array) {
            if (!is_array($step_data_array) || empty($step_data_array['packets'])) {
                continue;
            }
            
            // Each step can have multiple DataPackets
            foreach ($step_data_array['packets'] as $packet_data) {
                if (is_array($packet_data)) {
                    try {
                        $data_packet = \DataMachine\Engine\DataPacket::fromArray($packet_data);
                        
                        // Apply filter type using packet metadata (completely engine agnostic)
                        if ($filter_type === 'all') {
                            $context_packets[] = $data_packet;
                        } else {
                            // Use source_type from packet metadata for dynamic filtering
                            $source_type = $data_packet->metadata['source_type'] ?? 'unknown';
                            
                            // Direct source type matching - simplified without unused filter
                            if ($filter_type === $source_type) {
                                $context_packets[] = $data_packet;
                            }
                        }
                        
                    } catch (\Exception $e) {
                        // Log conversion error but continue processing other packets
                        $logger = apply_filters('dm_get_logger', null);
                        if ($logger) {
                            $logger->warning('Failed to convert step data to DataPacket', [
                                'job_id' => $job_id,
                                'step_name' => $step_name,
                                'error' => $e->getMessage(),
                                'context' => 'dm_get_context_filter'
                            ]);
                        }
                    }
                }
            }
        }
        
        // Debug logging in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $logger = apply_filters('dm_get_logger', null);
            if ($logger) {
                $logger->debug('Context retrieved for job', [
                    'job_id' => $job_id,
                    'filter_type' => $filter_type,
                    'packet_count' => count($context_packets),
                    'steps_processed' => array_keys($step_data),
                    'context' => 'dm_get_context_filter'
                ]);
            }
        }
        
        return $context_packets;
    }, 10, 3);
}

/**
 * Register universal handler system via dm_get_handlers filter.
 * 
 * Provides pure filter-based handler registration with parameter-based discovery.
 * Components self-register via *Filters.php files following "plugins within plugins" architecture.
 * 
 * Usage: $handlers = apply_filters('dm_get_handlers', null, 'input');
 * 
 * @since NEXT_VERSION
 */
function dm_register_universal_handler_system() {
    add_filter('dm_get_handlers', function($handlers, $type) {
        if ($handlers !== null) {
            return $handlers; // External override provided
        }
        
        $handlers = [];
        
        // Pure parameter-based system - handlers self-register via this same filter
        // Complete architectural purity - single filter pattern throughout
        
        // Debug logging in development mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->debug('Universal handlers initialized for type', [
                'type' => $type,
                'handler_count' => count($handlers),
                'context' => 'handler_registration'
            ]);
        }
        
        return $handlers;
    }, 50, 2); // Priority 50 ensures individual handlers register first at priority 10
    
    // Parameter-based handler settings system (2-parameter pattern for consistency)
    // ARCHITECTURAL EXPECTATION: Components self-register via *Filters.php files
    // Bootstrap provides pure filter hook - components add their own registration logic
    add_filter('dm_get_handler_settings', function($service, $handler_key) {
        if ($service !== null) {
            return $service; // Component self-registration provided
        }
        
        // ARCHITECTURAL COMPLIANCE COMPLETE - Pure filter hook only
        // Components self-register via *Filters.php files following established patterns
        //
        // Bootstrap provides only pure filter hook - components add their own logic
        
        return null; // Components self-register via filters
    }, 10, 2);
    
    // Parameter-based authentication system - auto-links to handlers via matching parameters
    // Usage: $auth = apply_filters('dm_get_auth', null, 'twitter');
    add_filter('dm_get_auth', function($auth, $handler_slug) {
        if ($auth !== null) {
            // Auto-register hooks if auth service has register_hooks method
            if (method_exists($auth, 'register_hooks')) {
                static $registered_hooks = [];
                $auth_class = get_class($auth);
                
                // Only register hooks once per auth class
                if (!isset($registered_hooks[$auth_class])) {
                    $auth->register_hooks();
                    $registered_hooks[$auth_class] = true;
                }
            }
            return $auth; // External override provided
        }
        
        // Core returns null - auth components self-register via this same filter
        // This enables pure parameter-based auto-linking with zero hardcoding
        return null;
    }, 5, 2);
}

/**
 * Register backend utility filters for data processing.
 * 
 * BACKEND-ONLY FILTERS: Provides pure parameter-based filter hooks for backend
 * content processing operations. Admin/UI filters have been moved to AdminFilters.php
 * to maintain clear architectural separation between engine and admin layers.
 * 
 * @since 0.1.0
 */
function dm_register_utility_filters() {
    
    
    // Bridge system removed - AdminMenuAssets now uses direct parameter-based discovery
    // This eliminates architectural confusion and aligns with component-owned registration
    
    
    // Common configuration parsing removed - handlers manage their own config parsing
    // This maintains proper separation of concerns and handler autonomy
    
    // Duplicate checking removed - handlers should directly use ProcessedItemsManager service
    // Usage: $manager = apply_filters('dm_get_processed_items_manager', null);
    
    
    // Search term filtering removed - handlers should implement their own filtering logic
    // This maintains handler autonomy and avoids over-engineered abstractions
    
    // Timeframe filtering removed - handlers should implement their own time-based logic
    // This avoids over-engineered abstractions and maintains handler control
    
    
    
    
    
    
    // Admin notices removed - components use WordPress add_action('admin_notices') directly
    
}

/**
 * Register parameter-based step self-registration system.
 * 
 * ARCHITECTURAL COMPLIANCE: Provides pure filter hook for step self-registration.
 * Steps MUST register in their own class files via *Filters.php following the
 * modular "plugins within plugins" architecture pattern.
 * 
 * Usage: $step_config = apply_filters('dm_get_steps', null, 'input');
 * 
 * @since NEXT_VERSION
 */
function dm_register_step_auto_discovery_system() {
    
    /**
     * Parameter-based step registration filter.
     * 
     * Pure filter hook with no core implementation. Steps self-register 
     * in their own class files following modular architecture.
     * 
     * @param mixed $step_config Existing step configuration (null if not found)
     * @param string $step_type Step type to register ('input', 'ai', 'output', etc.)
     * @return array|null Step configuration array or null if not found
     */
    add_filter('dm_get_steps', function($step_config, $step_type = null) {
        // Pure filter hook - steps self-register in their own class files
        // No centralized registration - follows modular architecture
        return $step_config;
    }, 5, 2);
}

/**
 * Register universal DataPacket creation system.
 * 
 * UNIVERSAL SYSTEM: Pure parameter-based DataPacket conversion system
 * with zero engine hardcoding. Components self-register conversion logic via 
 * parameter matching, enabling universal extensibility.
 * 
 * Usage: $datapacket = apply_filters('dm_create_datapacket', null, $source_data, $source_type, $context);
 * 
 * @since NEXT_VERSION
 */
function dm_register_datapacket_creation_system() {
    
    /**
     * Universal DataPacket creation filter - Pure parameter-based discovery.
     * 
     * Engine provides filter hook only - components self-register conversion logic.
     * No hardcoded source types, no switch statements, maximum extensibility.
     * 
     * @param DataPacket|null $datapacket Current DataPacket (null if none created)
     * @param array $source_data Raw data from component (handler output, AI response, etc.)
     * @param string $source_type Component identifier (files, rss, reddit, ai, custom_api, etc.)
     * @param array $context Additional context for conversion (job_id, step info, etc.)
     * @return DataPacket|null DataPacket instance or null if no converter available
     */
    add_filter('dm_create_datapacket', function($datapacket, $source_data, $source_type, $context) {
        // Pure filter hook - no core implementation
        // Components self-register via parameter matching in their *Filters.php files
        return $datapacket;
    }, 5, 4);
}
