<?php
/**
 * Data Machine Service Filters Registration
 *
 * Centralizes filter-based service registration with lazy loading and static caching.
 * External plugins can override any service via filter priority.
 *
 * @package DataMachine
 * @since 0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register direct service filters for core Data Machine services.
 * 
 * Provides filter-based service access with lazy loading and static caching.
 * External plugins can override any service using filter priority.
 * 
 * Key services:
 * - dm_get_logger: Logging throughout the codebase  
 * - dm_get_ai_http_client: AI integration and processing
 * - dm_get_orchestrator: Core pipeline processing
 * - dm_get_fluid_context_bridge: Enhanced AI context management
 * - dm_get_pipeline_context: Pipeline flow context and step management
 * - dm_get_context: Access to all DataPackets from pipeline jobs
 * 
 * Database services use parameter-based system: apply_filters('dm_get_database_service', null, 'type')
 * Handler auth classes are instantiated directly by handlers (internal implementation details)
 * 
 * Usage: $service = apply_filters('dm_get_{service_name}', null);
 * Override: add_filter('dm_get_{service_name}', function($service) { return new CustomService(); }, 20);
 *
 * @since 0.1.0
 */
function dm_register_direct_service_filters() {
    // Static cache for lazy-loaded services
    static $service_cache = [];
    
    add_filter('dm_get_logger', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['logger'])) {
            $service_cache['logger'] = new \DataMachine\Admin\Logger();
        }
        
        return $service_cache['logger'];
    }, 10);
    
    
    add_filter('dm_get_ai_http_client', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['ai_http_client'])) {
            $service_cache['ai_http_client'] = new \AI_HTTP_Client(['plugin_context' => 'data-machine', 'ai_type' => 'llm']);
        }
        
        return $service_cache['ai_http_client'];
    }, 10);
    
    add_filter('dm_get_fluid_context_bridge', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['fluid_context_bridge'])) {
            $service_cache['fluid_context_bridge'] = new \DataMachine\Core\Steps\AI\FluidContextBridge();
        }
        
        return $service_cache['fluid_context_bridge'];
    }, 10);
    
    add_filter('dm_get_orchestrator', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['orchestrator'])) {
            $service_cache['orchestrator'] = new \DataMachine\Engine\ProcessingOrchestrator();
        }
        
        return $service_cache['orchestrator'];
    }, 10);
    
    add_filter('dm_get_constants', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['constants'])) {
            $service_cache['constants'] = new \DataMachine\Engine\Constants();
        }
        
        return $service_cache['constants'];
    }, 10);
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
    add_filter('dm_get_database_service', function($service, $type) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        // Direct instantiation - no caching complexity
        switch ($type) {
            case 'jobs':
                return new \DataMachine\Core\Database\Jobs\Jobs();
            case 'pipelines':
                return new \DataMachine\Core\Database\Pipelines\Pipelines();
            case 'flows':
                return new \DataMachine\Core\Database\Flows\Flows();
            case 'processed_items':
                return new \DataMachine\Core\Database\ProcessedItems\ProcessedItems();
            case 'remote_locations':
                // TODO: Fix RemoteLocations class loading issue
                return new \DataMachine\Core\Database\RemoteLocations\RemoteLocations();
                // throw new Exception("RemoteLocations database service temporarily disabled due to class loading issue");
            default:
                throw new Exception("Unknown database service type: {$type}");
        }
    }, 10, 2);
}


/**
 * Continue registering remaining core services.
 * 
 * Registers business logic services, admin components, and utility services:
 * - Pipeline Context: Step sequence and position management
 * - Context Retrieval: Access to all DataPackets from pipeline jobs
 * - Job Status Manager: Job state transitions and tracking  
 * - AI Response Parser: AI content processing and formatting
 * - Import/Export Handler: Project configuration management
 * - Admin interfaces: Modal system, list tables, AJAX handlers
 * 
 * All services follow filter-based access with lazy loading and static caching.
 * External plugins can override any service via filter priority.
 * 
 * @since 0.1.0
 */
function dm_register_remaining_core_services() {
    // Static cache for lazy-loaded services
    static $service_cache = [];
    
    
    add_filter('dm_get_http_service', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['http_service'])) {
            $service_cache['http_service'] = new \DataMachine\Engine\HttpService();
        }
        
        return $service_cache['http_service'];
    }, 10);
    
    add_filter('dm_get_encryption_helper', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['encryption_helper'])) {
            $service_cache['encryption_helper'] = new \DataMachine\Admin\EncryptionHelper();
        }
        
        return $service_cache['encryption_helper'];
    }, 10);
    
    add_filter('dm_get_prompt_builder', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['prompt_builder'])) {
            $service_cache['prompt_builder'] = new \DataMachine\Core\Steps\AI\PromptBuilder();
            $service_cache['prompt_builder']->register_all_sections();
        }
        
        return $service_cache['prompt_builder'];
    }, 10);
    
    // Scheduler functionality migrated to Action Scheduler integration
    
    // Project prompts filter - WordPress-native filter system
    // Returns project prompts for given project_id
    // Usage: $prompts = apply_filters('dm_get_project_prompt', null, $project_id);
    add_filter('dm_get_project_prompt', function($prompts, $project_id) {
        if ($prompts !== null) {
            return $prompts; // External override provided
        }
        
        $db_projects = apply_filters('dm_get_database_service', null, 'projects');
        if (!$db_projects) {
            return [];
        }
        
        $project = $db_projects->get_project($project_id);
        if (!$project || empty($project->pipeline_configuration)) {
            return [];
        }
        
        // Parse pipeline configuration to extract prompts from AI steps
        $pipeline_config = json_decode($project->pipeline_configuration, true);
        if (!is_array($pipeline_config)) {
            return [];
        }
        
        $step_prompts = [];
        foreach ($pipeline_config as $position => $step_config) {
            if (isset($step_config['type']) && $step_config['type'] === 'ai' && !empty($step_config['prompt'])) {
                $step_prompts[$position] = $step_config['prompt'];
            }
        }
        
        return $step_prompts;
    }, 10, 2);
    
    add_filter('dm_get_admin_page', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['admin_page'])) {
            $service_cache['admin_page'] = new \DataMachine\Admin\AdminPage();
        }
        
        return $service_cache['admin_page'];
    }, 10);
    
    add_filter('dm_get_job_status_manager', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['job_status_manager'])) {
            $service_cache['job_status_manager'] = new \DataMachine\Engine\JobStatusManager();
        }
        
        return $service_cache['job_status_manager'];
    }, 10);
    
    add_filter('dm_get_job_creator', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['job_creator'])) {
            $service_cache['job_creator'] = new \DataMachine\Engine\JobCreator();
        }
        
        return $service_cache['job_creator'];
    }, 10);
    
    add_filter('dm_get_processed_items_manager', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['processed_items_manager'])) {
            $service_cache['processed_items_manager'] = new \DataMachine\Engine\ProcessedItemsManager();
        }
        
        return $service_cache['processed_items_manager'];
    }, 10);
    
    // ImportExport handler removed - functionality migrated to other handlers
    
    // Universal AJAX handler system - parameter-based access
    add_filter('dm_get_ajax_handler', function($service, $type) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        $cache_key = "ajax_handler_{$type}";
        if (!isset($service_cache[$cache_key])) {
            $service_cache[$cache_key] = null;
            
            switch ($type) {
                case 'remote_locations':
                    // RemoteLocations functionality migrated - no longer needed
                    break;
                case 'pipeline_management':
                    // PipelineManagement AJAX functionality migrated - no longer needed
                    break;
                case 'project_management':
                    // ProjectManagement AJAX functionality migrated - no longer needed  
                    break;
                default:
                    // For future extensibility - external plugins can override via this same filter
                    $service_cache[$cache_key] = null;
                    break;
            }
        }
        
        return $service_cache[$cache_key];
    }, 10, 2);
    
    
    // admin menu assets access filter
    add_filter('dm_get_admin_menu_assets', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['admin_menu_assets'])) {
            $service_cache['admin_menu_assets'] = new \DataMachine\Admin\AdminMenuAssets();
        }
        
        return $service_cache['admin_menu_assets'];
    }, 10);
    
    
    // remote location service access filter
    add_filter('dm_get_remote_location_service', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['remote_location_service'])) {
            $service_cache['remote_location_service'] = new \DataMachine\Core\Admin\Pages\RemoteLocations\RemoteLocationService();
        }
        
        return $service_cache['remote_location_service'];
    }, 10);
    
    // Add AI Response Parser service filter
    add_filter('dm_get_ai_response_parser', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['ai_response_parser'])) {
            $service_cache['ai_response_parser'] = new \DataMachine\Core\Steps\AI\AiResponseParser();
        }
        
        return $service_cache['ai_response_parser'];
    }, 10);
    
    // Add AI step configuration filter
    // Returns AI configuration for specific project/step position
    // Usage: $config = apply_filters('dm_get_ai_step_config', null, $project_id, $step_position);
    add_filter('dm_get_ai_step_config', function($config, $project_id, $step_position) {
        if ($config !== null) {
            return $config; // External override provided
        }
        
        // Modern WordPress options storage with standardized key pattern
        $ai_option_key = "dm_ai_step_config_{$project_id}_{$step_position}";
        $step_ai_config = get_option($ai_option_key, [
            'provider' => '',
            'model' => '',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'enabled' => true
        ]);
        
        return $step_ai_config;
    }, 10, 3);
    
    // Add AI step configuration save filter
    // Allows external plugins to override AI configuration storage
    // Usage: $result = apply_filters('dm_save_ai_step_config', null, $project_id, $step_position, $config);
    add_filter('dm_save_ai_step_config', function($result, $project_id, $step_position, $config) {
        if ($result !== null) {
            return $result; // External override provided
        }
        
        // Modern WordPress options storage with direct save
        $ai_option_key = "dm_ai_step_config_{$project_id}_{$step_position}";
        return update_option($ai_option_key, $config);
    }, 10, 4);
    
    // Handler auth classes are now instantiated directly by handlers
    // This simplifies the architecture since auth is tightly coupled to handlers
    
    // Add List Table service filter for admin interfaces
    add_filter('dm_get_remote_locations_list_table', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['remote_locations_list_table'])) {
            $service_cache['remote_locations_list_table'] = new \DataMachine\Core\Admin\Pages\RemoteLocations\ListTable();
        }
        
        return $service_cache['remote_locations_list_table'];
    }, 10);
    
    // Add Modal service filter for universal modal system
    add_filter('dm_get_modal_service', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['modal_service'])) {
            $service_cache['modal_service'] = new \DataMachine\Core\Admin\Modal\Modal();
        }
        
        return $service_cache['modal_service'];
    }, 10);
    
    // Add Pipeline Context service filter
    add_filter('dm_get_pipeline_context', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['pipeline_context'])) {
            $service_cache['pipeline_context'] = new \DataMachine\Engine\PipelineContext();
        }
        
        return $service_cache['pipeline_context'];
    }, 10);
    
    // Add context retrieval filter for pipeline DataPackets
    // Returns all DataPackets from the current pipeline job as an array
    // Usage: $context = apply_filters('dm_get_context', null, $job_id);
    // Future: Support parameter-based access: apply_filters('dm_get_context', null, $job_id, 'input_only');
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
                        
                        // Apply filter type for future granular control
                        if ($filter_type === 'all') {
                            $context_packets[] = $data_packet;
                        } elseif ($filter_type === 'input_only' && strpos($step_name, 'input') !== false) {
                            $context_packets[] = $data_packet;
                        } elseif ($filter_type === 'ai_only' && strpos($step_name, 'ai') !== false) {
                            $context_packets[] = $data_packet;
                        }
                        // Additional filter types can be added here as needed
                        
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
 * Provides a clean, single filter for all handler registration with type parameters.
 * Pure parameter-based system with no backward compatibility layers.
 * 
 * Usage: $handlers = apply_filters('dm_get_handlers', null, 'input');
 * Usage: $handlers = apply_filters('dm_get_handlers', null, 'output');
 * 
 * External Plugin Registration (Minimal Pattern):
 * add_filter('dm_get_handlers', function($handlers, $type) {
 *     if ($type === 'input') {
 *         $handlers['my_handler'] = [
 *             'has_auth' => true,
 *             'label' => __('My Handler', 'textdomain')
 *             // Class auto-discovered from registration context
 *             // Settings class auto-discovered: HandlerClass → HandlerClassSettings
 *         ];
 *     }
 *     return $handlers;
 * }, 10, 2);
 * 
 * External Plugin Registration (Legacy Pattern - Still Supported):
 * add_filter('dm_get_handlers', function($handlers, $type) {
 *     if ($type === 'input') {
 *         $handlers['my_handler'] = [
 *             'class' => 'MyPlugin\\Handler\\Class',  // Optional - auto-discovered if not provided
 *             'label' => __('My Handler', 'textdomain'),
 *             'settings_class' => 'MyPlugin\\Handler\\Settings'  // Optional - auto-discovered if not provided
 *         ];
 *     }
 *     return $handlers;
 * }, 10, 2);
 * 
 * Handler Settings System with Auto-Discovery:
 * $settings = apply_filters('dm_get_handler_settings', null, 'output', 'twitter');
 * 
 * Settings Resolution Order:
 * 1. Explicit 'settings_class' parameter (backward compatibility)
 * 2. Auto-discovery: HandlerClass → HandlerClassSettings
 * 3. Return null if neither found
 * 
 * Examples:
 * - 'MyPlugin\\TwitterHandler' → 'MyPlugin\\TwitterHandlerSettings'
 * - 'DataMachine\\Core\\Handlers\\Output\\Twitter\\Twitter' → 'DataMachine\\Core\\Handlers\\Output\\Twitter\\TwitterSettings'
 * 
 * External Plugin Settings Override:
 * add_filter('dm_get_handler_settings', function($service, $type, $key) {
 *     if ($type === 'output' && $key === 'twitter') {
 *         return new MyPlugin\EnhancedTwitterSettings();
 *     }
 *     return $service;
 * }, 20, 3);
 * 
 * @since NEXT_VERSION
 */
function dm_register_universal_handler_system() {
    // Static cache for lazy-loaded handlers by type
    static $handler_cache = [];
    
    add_filter('dm_get_handlers', function($handlers, $type) use (&$handler_cache) {
        if ($handlers !== null) {
            return $handlers; // External override provided
        }
        
        if (!isset($handler_cache[$type])) {
            $handler_cache[$type] = [];
            
            // Pure parameter-based system - handlers self-register via this same filter
            // No legacy filter calls - complete architectural purity
            
            // Debug logging in development mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $logger = apply_filters('dm_get_logger', null);
                $logger && $logger->debug('Universal handlers initialized for type', [
                    'type' => $type,
                    'handler_count' => count($handler_cache[$type]),
                    'context' => 'handler_registration'
                ]);
            }
        }
        
        return $handler_cache[$type];
    }, 10, 2);
    
    // Parameter-based handler settings system with auto-discovery
    add_filter('dm_get_handler_settings', function($service, $handler_type, $handler_key) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        // Get handler configuration
        $handlers = apply_filters('dm_get_handlers', null, $handler_type);
        $handler_config = $handlers[$handler_key] ?? null;
        
        if (!$handler_config) {
            return null; // Handler not found
        }
        
        // Primary: Use explicit settings_class if provided (backward compatibility)
        if (!empty($handler_config['settings_class'])) {
            $settings_class = $handler_config['settings_class'];
            if (class_exists($settings_class)) {
                return new $settings_class();
            }
        }
        
        // Auto-discovery: Settings class using naming conventions
        // This works with minimal registrations without explicit class parameters
        
        // Method 1: If handler class is explicitly provided, derive settings from it
        if (!empty($handler_config['class'])) {
            $handler_class = $handler_config['class'];
            
            // Extract base class name from full class path
            $class_parts = explode('\\', $handler_class);
            $base_class_name = end($class_parts);
            
            // Construct auto-discovered settings class name
            $auto_settings_class = str_replace($base_class_name, $base_class_name . 'Settings', $handler_class);
            
            if (class_exists($auto_settings_class)) {
                // Debug logging for successful auto-discovery
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $logger = apply_filters('dm_get_logger', null);
                    $logger && $logger->debug('Auto-discovered settings class from explicit class', [
                        'handler_type' => $handler_type,
                        'handler_key' => $handler_key,
                        'handler_class' => $handler_class,
                        'auto_settings_class' => $auto_settings_class,
                        'context' => 'settings_auto_discovery'
                    ]);
                }
                
                return new $auto_settings_class();
            }
        }
        
        // Method 2: For minimal registrations, use naming convention auto-discovery
        // Handler name: 'twitter' -> Settings: 'DataMachine\\Core\\Handlers\\Output\\Twitter\\TwitterSettings'
        $expected_settings_class = sprintf(
            'DataMachine\\Core\\Handlers\\%s\\%s\\%sSettings',
            ucfirst($handler_type),
            ucfirst($handler_key),
            ucfirst($handler_key)
        );
        
        if (class_exists($expected_settings_class)) {
            // Debug logging for successful naming convention auto-discovery
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $logger = apply_filters('dm_get_logger', null);
                $logger && $logger->debug('Auto-discovered settings class via naming convention', [
                    'handler_type' => $handler_type,
                    'handler_key' => $handler_key,
                    'expected_settings_class' => $expected_settings_class,
                    'context' => 'settings_auto_discovery'
                ]);
            }
            
            return new $expected_settings_class();
        }
        
        return null; // No settings available
    }, 10, 3);
    
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
 * Provides configuration parsing and item filtering utilities
 * to eliminate the need for external handlers to handle complex logic.
 * 
 * Key filters:
 * - dm_parse_common_config: Parse standard configuration fields
 * - dm_check_if_processed: Check for duplicate items
 * - dm_filter_by_search_terms: Content filtering by keywords
 * - dm_filter_by_timeframe: Time-based content filtering
 * 
 * @since 0.1.0
 */
function dm_register_utility_filters() {
    
    /**
     * Initialize admin page registry for pure self-registration pattern.
     * 
     * Admin pages register themselves using this filter, eliminating any hardcoded
     * page lists and following the same pure parameter-based architecture as handlers.
     * 
     * Core Usage: 
     * - Pages: add_filter('dm_register_admin_pages', function($pages) { $pages['slug'] = $config; return $pages; });
     * - Discovery: $all_pages = apply_filters('dm_register_admin_pages', []);
     * 
     * External Plugin Integration:
     * add_filter('dm_register_admin_pages', function($pages) {
     *     $pages['analytics'] = [
     *         'page_title' => 'Analytics Dashboard',
     *         'menu_title' => 'Analytics', 
     *         'capability' => 'manage_options',
     *         'callback' => [$this, 'render_analytics_page']
     *     ];
     *     return $pages;
     * }, 10);
     */
    add_filter('dm_register_admin_pages', function($pages) {
        // Initialize empty registry - pages add themselves to this
        return is_array($pages) ? $pages : [];
    }, 5);
    
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
    
    /**
     * Parse common configuration fields.
     * 
     * @param array|null $config Existing parsed config
     * @param array $source_config Raw handler configuration
     * @return array Parsed common configuration values
     */
    add_filter('dm_parse_common_config', function($config, $source_config) {
        if ($config !== null) {
            return $config; // External override provided
        }
        
        $parsed_config = [
            'process_limit' => max(1, absint($source_config['item_count'] ?? 1)),
            'timeframe_limit' => $source_config['timeframe_limit'] ?? 'all_time',
            'search_term' => trim($source_config['search'] ?? '')
        ];
        
        // Parse search keywords if search term provided
        $parsed_config['search_keywords'] = [];
        if (!empty($parsed_config['search_term'])) {
            $keywords = array_map('trim', explode(',', $parsed_config['search_term']));
            $parsed_config['search_keywords'] = array_filter($keywords, function($k) { 
                return !empty($k); 
            });
        }
        
        // Calculate timeframe cutoff timestamp
        $parsed_config['cutoff_timestamp'] = null;
        if ($parsed_config['timeframe_limit'] !== 'all_time') {
            $interval_map = [
                '24_hours' => '-24 hours',
                '72_hours' => '-72 hours',
                '7_days'   => '-7 days',
                '30_days'  => '-30 days'
            ];
            
            if (isset($interval_map[$parsed_config['timeframe_limit']])) {
                $parsed_config['cutoff_timestamp'] = strtotime(
                    $interval_map[$parsed_config['timeframe_limit']], 
                    current_time('timestamp', true)
                );
            }
        }
        
        $logger = apply_filters('dm_get_logger', null);
        $logger && $logger->info('Filter: Parsed common configuration.', [
            'process_limit' => $parsed_config['process_limit'],
            'timeframe_limit' => $parsed_config['timeframe_limit'],
            'search_keywords_count' => count($parsed_config['search_keywords']),
            'cutoff_timestamp' => $parsed_config['cutoff_timestamp']
        ]);
        
        return $parsed_config;
        
    }, 10, 2);
    
    /**
     * Check if an item has already been processed.
     * 
     * @param bool $is_processed Current processing status
     * @param int $project_id Project ID (updated from module_id)
     * @param string $source_type Handler source type
     * @param string $item_identifier Unique item identifier
     * @return bool True if already processed
     */
    add_filter('dm_check_if_processed', function($is_processed, $project_id, $source_type, $item_identifier) {
        if ($is_processed !== false) {
            return $is_processed;
        }
        
        $processed_items_manager = apply_filters('dm_get_processed_items_manager', null);
        if (!$processed_items_manager) {
            return false;
        }
        
        return $processed_items_manager->is_item_processed($project_id, $source_type, $item_identifier);
        
    }, 10, 4);
    
    
    /**
     * Perform content filtering by search terms.
     * 
     * @param bool|null $matches Current match status
     * @param string $content Content to search (title + body text)
     * @param array $keywords Array of keywords to search for
     * @return bool True if content matches search criteria (or no keywords)
     */
    add_filter('dm_filter_by_search_terms', function($matches, $content, $keywords) {
        if ($matches !== null) {
            return $matches;
        }
        
        if (empty($keywords)) {
            return true;
        }
        
        foreach ($keywords as $keyword) {
            if (mb_stripos($content, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
        
    }, 10, 3);
    
    /**
     * Filter to perform timeframe filtering.
     * 
     * Eliminates need for external handlers to implement time-based filtering.
     * Compares item timestamps against cutoff values.
     * 
     * @param bool|null $passes Current filter status
     * @param int|null $cutoff_timestamp Cutoff timestamp or null for no limit
     * @param int $item_timestamp Item's timestamp
     * @return bool True if item should be included
     */
    add_filter('dm_filter_by_timeframe', function($passes, $cutoff_timestamp, $item_timestamp) {
        if ($passes !== null) {
            return $passes; // External override provided
        }
        
        if ($cutoff_timestamp === null) {
            return true; // No time limit
        }
        
        if ($item_timestamp === false || $item_timestamp < $cutoff_timestamp) {
            return false; // Item is too old
        }
        
        return true; // Item passes time filter
        
    }, 10, 3);
    
    /**
     * Enhanced handler class auto-discovery utility.
     * 
     * Automatically discovers handler class names based on naming conventions,
     * reducing registration boilerplate. Supports multiple fallback patterns
     * for maximum compatibility with external plugins.
     * 
     * @param string|null $class_name Existing class name (null if not found)
     * @param string $handler_name Handler name/key (e.g., 'twitter', 'reddit')
     * @param string $handler_type Handler type ('input' or 'output')
     * @return string|null Discovered class name or null if not found
     */
    add_filter('dm_auto_discover_handler_class', function($class_name, $handler_name, $handler_type) {
        // External override provided - respect it
        if ($class_name !== null) {
            return $class_name;
        }
        
        // Get handler registration information for explicit class
        $handlers = apply_filters('dm_get_handlers', null, $handler_type);
        if (isset($handlers[$handler_name]['class']) && class_exists($handlers[$handler_name]['class'])) {
            return $handlers[$handler_name]['class'];
        }
        
        // Auto-discovery: Primary naming convention
        // Handler: 'twitter' -> 'DataMachine\Core\Handlers\Output\Twitter\Twitter'
        $primary_class = sprintf(
            'DataMachine\\Core\\Handlers\\%s\\%s\\%s',
            ucfirst($handler_type),
            ucfirst($handler_name),
            ucfirst($handler_name)
        );
        
        if (class_exists($primary_class)) {
            return $primary_class;
        }
        
        // Fallback patterns for external plugins and alternate structures
        $fallback_patterns = [
            sprintf('DataMachine\\Handlers\\%s\\%s', ucfirst($handler_type), ucfirst($handler_name)),
            sprintf('DataMachine\\%s\\%s', ucfirst($handler_type), ucfirst($handler_name)),
            sprintf('%s\\%s', ucfirst($handler_type), ucfirst($handler_name)),
            ucfirst($handler_name), // Simple class name
            sprintf('%sHandler', ucfirst($handler_name)), // Common pattern
            sprintf('%s%s', ucfirst($handler_name), ucfirst($handler_type)) // TwitterOutput, etc.
        ];
        
        foreach ($fallback_patterns as $pattern) {
            if (class_exists($pattern)) {
                return $pattern;
            }
        }
        
        return null;
    }, 10, 3);
}

/**
 * Register parameter-based step auto-discovery system.
 * 
 * Enables pipeline steps to be auto-discovered and registered through
 * the same pure filter-based pattern as handlers (parameter-based) and admin pages (collection-based).
 * 
 * External plugins can register custom step types via:
 * add_filter('dm_get_steps', function($step_config, $step_type) {
 *     if ($step_type === 'custom_step') {
 *         return [
 *             'label' => __('Custom Step', 'my-plugin'),
 *             'has_handlers' => true,
 *             'description' => __('Custom processing step', 'my-plugin'),
 *             'class' => 'MyPlugin\\Steps\\CustomStep'
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
     * Enables auto-discovery of pipeline step types through parameter-based
     * filter system for maximum extensibility.
     * 
     * @param mixed $step_config Existing step configuration (null if not found)
     * @param string $step_type Step type to register ('input', 'ai', 'output', etc.)
     * @return array|null Step configuration array or null if not found
     */
    add_filter('dm_get_steps', function($step_config, $step_type) {
        // External override provided - respect it
        if ($step_config !== null) {
            return $step_config;
        }
        
        // No configuration found - allow external plugins to provide it
        return null;
    }, 5, 2);
}
