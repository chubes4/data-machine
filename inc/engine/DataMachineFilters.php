<?php
/**
 * Data Machine Engine - Pure Orchestration Layer
 *
 * TRUE ENGINE AGNOSTICISM: Pure orchestration services with zero business logic.
 * Business logic components self-register via their own *Filters.php files.
 * 
 * Engine Bootstrap Functions (Pure Orchestration Only):
 * - dm_register_direct_service_filters(): Core orchestration services
 * - dm_register_database_service_system(): Parameter-based database filter hooks  
 * - dm_register_wpdb_service_filter(): WordPress database access
 * - dm_register_context_retrieval_service(): DataPacket context orchestration
 * - dm_register_universal_handler_system(): Handler registration filter hooks
 * - dm_register_utility_filters(): Utility and modal filter hooks  
 * - dm_register_step_auto_discovery_system(): Step registration filter hooks
 * - dm_register_datapacket_creation_system(): DataPacket creation filter hooks
 * 
 * Business Logic Separation:
 * - Jobs/ProcessedItems logic → Core database components
 * - AI processing logic → AI step components
 * - Admin logic → Admin components
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
 * Override: add_filter('dm_get_{service_name}', function($service) { return new CustomService(); }, 20);
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
 * Pure parameter-based system with no legacy filter patterns.
 * 
 * Usage: $db_service = apply_filters('dm_get_database_service', null, 'jobs');
 * Usage: $db_service = apply_filters('dm_get_database_service', null, 'pipelines');
 * Usage: $db_service = apply_filters('dm_get_database_service', null, 'flows');
 * Usage: $db_service = apply_filters('dm_get_database_service', null, 'processed_items');
 * Usage: $db_service = apply_filters('dm_get_database_service', null, 'remote_locations');
 * 
 * External Plugin Example:
 * // Add custom database table
 * add_filter('dm_get_database_service', function($service, $type) {
 *     if ($type === 'custom_table') {
 *         return new MyPlugin\Database\CustomTable();
 *     }
 *     return $service;
 * }, 10, 2);
 * 
 * // Override core database service
 * add_filter('dm_get_database_service', function($service, $type) {
 *     if ($type === 'jobs') {
 *         return new MyPlugin\Database\EnhancedJobs();
 *     }
 *     return $service;
 * }, 20, 2);
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
 * Usage Examples:
 * - Add custom service: add_filter('dm_get_database_service', function($service, $type) {...}, 10, 2);
 * - Override core service: add_filter('dm_get_database_service', function($service, $type) {...}, 20, 2);
 * - Wrap existing service: add_filter('dm_get_database_service', function($service, $type) {...}, 30, 2);
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
 * Override: add_filter('dm_get_wpdb_service', function($wpdb) { return $custom_wpdb; }, 20);
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
 * Advanced: apply_filters('dm_get_context', null, $job_id, 'input_only');
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
                            
                            // Dynamic pattern matching - no hardcoded step types
                            $filter_matches = apply_filters('dm_context_filter_matches', false, $filter_type, $source_type, $data_packet);
                            
                            if ($filter_matches) {
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
 * Core Usage:
 * $handlers = apply_filters('dm_get_handlers', null, 'input');
 * $handlers = apply_filters('dm_get_handlers', null, 'output');
 * 
 * Component Self-Registration Pattern (via *Filters.php files):
 * add_filter('dm_get_handlers', function($handlers, $type) {
 *     if ($type === 'input') {
 *         $handlers['my_handler'] = [
 *             'class' => \MyPlugin\Handlers\MyHandler::class,
 *             'label' => __('My Handler', 'textdomain'),
 *             'description' => __('My custom handler description', 'textdomain')
 *         ];
 *     }
 *     return $handlers;
 * }, 10, 2);
 * 
 * Handler Settings System (pure filter-based, 2-parameter pattern):
 * $settings = apply_filters('dm_get_handler_settings', null, 'twitter');
 * 
 * Settings Self-Registration Pattern (via *Filters.php files):
 * add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
 *     if ($handler_slug === 'twitter') {
 *         return new TwitterSettings();
 *     }
 *     return $settings;
 * }, 10, 2);
 * 
 * Authentication Self-Registration Pattern (via *Filters.php files):
 * add_filter('dm_get_auth', function($auth, $handler_slug) {
 *     if ($handler_slug === 'twitter') {
 *         return new TwitterAuth();
 *     }
 *     return $auth;
 * }, 10, 2);
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
        // No legacy filter calls - complete architectural purity
        
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
    }, 10, 2);
    
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
    // Usage: $auth = apply_filters('dm_get_auth', null, 'wordpress');
    // External Plugin Example:
    // add_filter('dm_get_auth', function($auth, $handler_slug) {
    //     if ($handler_slug === 'instagram') {
    //         return new MyPlugin\Handlers\Instagram\InstagramAuth();
    //     }
    //     return $auth;
    // }, 10, 2);
    add_filter('dm_get_auth', function($auth, $handler_slug) {
        if ($auth !== null) {
            return $auth; // External override provided
        }
        
        // Core returns null - auth components self-register via this same filter
        // This enables pure parameter-based auto-linking with zero hardcoding
        return null;
    }, 5, 2);
}

/**
 * Register utility filters for external handlers.
 * 
 * Provides pure parameter-based filter hooks for modal content, field rendering,
 * item identification, and context filtering. These filters support external
 * plugin integration and maintain architectural consistency.
 * 
 * @since 0.1.0
 */
function dm_register_utility_filters() {
    
    /**
     * Parameter-based admin page system for architectural consistency.
     * 
     * Follows same pattern as handlers, database services, auth, and all other services.
     * Eliminates collection-based registration for pure parameter-based architecture.
     * 
     * Core Usage:
     * - Page Discovery: $page_config = apply_filters('dm_get_admin_page', null, 'jobs');
     * - Page Discovery: $page_config = apply_filters('dm_get_admin_page', null, 'pipelines');
     * 
     * Component Self-Registration Pattern (via *Filters.php files):
     * add_filter('dm_get_admin_page', function($config, $page_slug) {
     *     if ($page_slug === 'jobs') {
     *         return [
     *             'page_title' => __('Jobs', 'data-machine'),
     *             'menu_title' => __('Jobs', 'data-machine'),
     *             'capability' => 'manage_options',
     *             'position' => 20
     *         ];
     *     }
     *     return $config;
     * }, 10, 2);
     * 
     * External Plugin Integration:
     * add_filter('dm_get_admin_page', function($config, $page_slug) {
     *     if ($page_slug === 'analytics') {
     *         return [
     *             'page_title' => 'Analytics Dashboard',
     *             'menu_title' => 'Analytics',
     *             'capability' => 'manage_options',
     *             'position' => 35
     *         ];
     *     }
     *     return $config;
     * }, 10, 2);
     */
    add_filter('dm_get_admin_page', function($config, $page_slug) {
        if ($config !== null) {
            return $config; // Component self-registration provided
        }
        
        // Pure parameter-based system - pages self-register via this same filter
        // No hardcoded page lists - complete architectural consistency
        return null;
    }, 5, 2);
    
    /**
     * Admin page discovery helper for AdminMenuAssets.
     * 
     * Returns all available admin pages by checking known page slugs.
     * Maintains backward compatibility during transition period.
     * 
     * Core Usage: $all_pages = apply_filters('dm_discover_admin_pages', []);
     */
    add_filter('dm_discover_admin_pages', function($pages) {
        // Known core admin page slugs
        $known_slugs = ['jobs', 'pipelines', 'logs'];
        
        foreach ($known_slugs as $slug) {
            $page_config = apply_filters('dm_get_admin_page', null, $slug);
            if ($page_config !== null) {
                $pages[$slug] = $page_config;
            }
        }
        
        // External plugins can add their page slugs via this same filter
        // add_filter('dm_discover_admin_pages', function($pages) {
        //     $custom_config = apply_filters('dm_get_admin_page', null, 'analytics');
        //     if ($custom_config) $pages['analytics'] = $custom_config;
        //     return $pages;
        // }, 20);
        
        return $pages;
    }, 10);
    
    /**
     * Parameter-based page asset discovery system.
     * 
     * Allows pages to declare their required assets for dynamic loading.
     * Replaces hardcoded asset enqueuing with filter-based auto-discovery.
     * 
     * Usage: $assets = apply_filters('dm_get_page_assets', null, $page_slug);
     * 
     * External Plugin Integration:
     * add_filter('dm_get_page_assets', function($assets, $page_slug) {
     *     if ($page_slug === 'analytics') {
     *         return [
     *             'css' => [
     *                 'analytics-admin' => [
     *                     'file' => 'assets/css/analytics-admin.css',
     *                     'deps' => ['dm-admin-core'],
     *                     'media' => 'all'
     *                 ]
     *             ],
     *             'js' => [
     *                 'analytics-admin' => [
     *                     'file' => 'assets/js/analytics-admin.js',
     *                     'deps' => ['jquery', 'wp-util'],
     *                     'in_footer' => true,
     *                     'localize' => [
     *                         'object' => 'analyticsAjax',
     *                         'data' => ['ajax_url' => admin_url('admin-ajax.php')]
     *                     ]
     *                 ]
     *             ]
     *         ];
     *     }
     *     return $assets;
     * }, 10, 2);
     */
    add_filter('dm_get_page_assets', function($assets, $page_slug) {
        if ($assets !== null) {
            return $assets; // External override provided
        }
        
        // No hardcoded core assets - pages register their own assets via filters
        return null;
    }, 10, 2);
    
    // Common configuration parsing removed - handlers manage their own config parsing
    // This maintains proper separation of concerns and handler autonomy
    
    // Duplicate checking removed - handlers should directly use ProcessedItemsManager service
    // Usage: $manager = apply_filters('dm_get_processed_items_manager', null);
    
    
    // Search term filtering removed - handlers should implement their own filtering logic
    // This maintains handler autonomy and avoids over-engineered abstractions
    
    // Timeframe filtering removed - handlers should implement their own time-based logic
    // This avoids over-engineered abstractions and maintains handler control
    
    /**
     * Register pure parameter-based identifier generation system.
     * 
     * Eliminates hardcoded switches in ProcessedItemsManager by allowing handlers
     * to register their identifier extraction logic via filters.
     * 
     * Core Usage (handlers self-register):
     * add_filter('dm_get_item_identifier', function($identifier, $source_type, $raw_data) {
     *     if ($source_type === 'twitter' && $identifier === null) {
     *         return $raw_data['id_str'] ?? $raw_data['id'] ?? null;
     *     }
     *     return $identifier;
     * }, 10, 3);
     * 
     * Engine Usage: $identifier = apply_filters('dm_get_item_identifier', null, $source_type, $raw_data);
     */
    add_filter('dm_get_item_identifier', function($identifier, $source_type, $raw_data) {
        // Pure parameter-based system - handlers register their extraction logic
        // Core returns null to allow fallback detection in ProcessedItemsManager
        return $identifier;
    }, 5, 3);
    
    /**
     * Register pure parameter-based modal content system.
     * 
     * Eliminates hardcoded modal type switches by allowing components to register
     * their modal content generation via filters.
     * 
     * Core Usage (components self-register):
     * add_filter('dm_get_modal_content', function($content, $modal_type, $context) {
     *     if ($modal_type === 'custom_config' && $content === null) {
     *         return $this->generate_custom_modal_content($context);
     *     }
     *     return $content;
     * }, 10, 3);
     * 
     * Engine Usage: $content = apply_filters('dm_get_modal_content', null, $modal_type, $context);
     */
    add_filter('dm_get_modal_content', function($content, $modal_type, $context) {
        // Pure parameter-based system - modal types register their content generation logic
        // Core returns null to allow legacy fallback in Modal.php
        return $content;
    }, 5, 3);
    
    /**
     * Register pure parameter-based modal save system.
     * 
     * Eliminates hardcoded modal save switches by allowing components to register
     * their configuration save logic via filters.
     * 
     * Core Usage (components self-register):
     * add_filter('dm_save_modal_config', function($result, $modal_type, $context, $config_data) {
     *     if ($modal_type === 'custom_config' && $result === null) {
     *         return $this->save_custom_configuration($context, $config_data);
     *     }
     *     return $result;
     * }, 10, 4);
     * 
     * Engine Usage: $result = apply_filters('dm_save_modal_config', null, $modal_type, $context, $config_data);
     */
    add_filter('dm_save_modal_config', function($result, $modal_type, $context, $config_data) {
        // Pure parameter-based system - modal types register their save logic
        // Core returns null to allow legacy fallback in Modal.php
        return $result;
    }, 5, 4);
    
    /**
     * Register pure parameter-based field rendering system.
     * 
     * Eliminates hardcoded field type switches by allowing custom field renderers
     * to register their HTML generation via filters.
     * 
     * Core Usage (field types self-register):
     * add_filter('dm_render_field', function($html, $field_type, $field_config, $field_value, $field_key) {
     *     if ($field_type === 'color_picker' && $html === null) {
     *         return $this->render_color_picker_field($field_config, $field_value, $field_key);
     *     }
     *     return $html;
     * }, 10, 5);
     * 
     * Engine Usage: $html = apply_filters('dm_render_field', null, $field_type, $field_config, $field_value, $field_key);
     */
    add_filter('dm_render_field', function($html, $field_type, $field_config, $field_value, $field_key) {
        // Pure parameter-based system - field types register their rendering logic
        // Core returns null to allow legacy fallback in Modal.php
        return $html;
    }, 5, 5);
    
    /**
     * Register context filter matching for completely dynamic context filtering.
     * 
     * Engine provides pure filter hook with no hardcoded assumptions.
     * Components register their own filtering logic as needed.
     * 
     * Usage: add_filter('dm_context_filter_matches', function($matches, $filter_type, $source_type, $data_packet) {
     *     if ($filter_type === 'my_custom_filter' && $source_type === 'my_source') {
     *         return true;
     *     }
     *     return $matches;
     * }, 10, 4);
     */
    add_filter('dm_context_filter_matches', function($matches, $filter_type, $source_type, $data_packet) {
        // Pure filter hook - no core implementation, completely external
        // Components define their own filter logic with zero engine assumptions
        return $matches;
    }, 5, 4);
    
    // Admin notices removed - components use WordPress add_action('admin_notices') directly
    
}

/**
 * Register parameter-based step self-registration system.
 * 
 * ARCHITECTURAL COMPLIANCE: Provides pure filter hook for step self-registration.
 * Steps MUST register in their own class files via *Filters.php following the
 * modular "plugins within plugins" architecture pattern.
 * 
 * Core Steps Self-Registration Pattern (via InputStepFilters.php, etc.):
 * add_filter('dm_get_steps', function($step_config, $step_type) {
 *     if ($step_type === 'input') {
 *         return [
 *             'label' => __('Input', 'data-machine'),
 *             'description' => __('Collect data from external sources', 'data-machine'),
 *             'class' => 'DataMachine\\Core\\Steps\\Input\\InputStep'
 *         ];
 *     }
 *     return $step_config;
 * }, 10, 2);
 * 
 * External Plugin Step Registration:
 * add_filter('dm_get_steps', function($step_config, $step_type) {
 *     if ($step_type === 'custom_transform') {
 *         return [
 *             'label' => __('Custom Transform', 'my-plugin'),
 *             'description' => __('Custom data transformation', 'my-plugin'),
 *             'class' => 'MyPlugin\\Steps\\CustomTransform'
 *         ];
 *     }
 *     return $step_config;
 * }, 10, 2);
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
    add_filter('dm_get_steps', function($step_config, $step_type) {
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
 * Engine Usage:
 * $datapacket = apply_filters('dm_create_datapacket', null, $source_data, $source_type, $context);
 * 
 * Component Self-Registration Pattern (via *Filters.php files):
 * add_filter('dm_create_datapacket', function($datapacket, $source_data, $source_type, $context) {
 *     if ($source_type === 'files') {
 *         return FilesDataPacket::create($source_data, $context);
 *     }
 *     return $datapacket;
 * }, 10, 4);
 * 
 * External Plugin Integration:
 * add_filter('dm_create_datapacket', function($datapacket, $source_data, $source_type, $context) {
 *     if ($source_type === 'shopify_orders') {
 *         return DataPacket::fromShopifyOrders($source_data, $context);
 *     }
 *     return $datapacket;
 * }, 10, 4);
 * 
 * Key Benefits:
 * - Universal: ANY component can create DataPackets using same filter
 * - Engine-Agnostic: Zero hardcoded source types in engine
 * - Component Responsibility: Each component registers its own conversion
 * - External Extensibility: Plugins add new source types automatically
 * - Graceful Failure: Unknown source types return null
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
