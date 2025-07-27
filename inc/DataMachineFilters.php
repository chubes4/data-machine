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
 * - dm_get_db_jobs: Pipeline execution and job management
 * - dm_get_ai_http_client: AI integration and processing
 * - dm_get_orchestrator: Core pipeline processing
 * - dm_get_fluid_context_bridge: Enhanced AI context management
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
            $service_cache['logger'] = new \DataMachine\Helpers\Logger();
        }
        
        return $service_cache['logger'];
    }, 10);
    
    add_filter('dm_get_db_jobs', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['db_jobs'])) {
            $service_cache['db_jobs'] = new \DataMachine\Database\Jobs();
        }
        
        return $service_cache['db_jobs'];
    }, 10);
    
    add_filter('dm_get_db_projects', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['db_projects'])) {
            $service_cache['db_projects'] = new \DataMachine\Database\Projects();
        }
        
        return $service_cache['db_projects'];
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
            $service_cache['fluid_context_bridge'] = new \DataMachine\Engine\FluidContextBridge();
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
    
    // database modules access filter
    add_filter('dm_get_db_modules', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['db_modules'])) {
            $service_cache['db_modules'] = new \DataMachine\Database\Modules();
        }
        
        return $service_cache['db_modules'];
    }, 10);
    
    
    
    // database processed items access filter
    add_filter('dm_get_db_processed_items', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['db_processed_items'])) {
            $service_cache['db_processed_items'] = new \DataMachine\Database\ProcessedItems();
        }
        
        return $service_cache['db_processed_items'];
    }, 10);
    
    // HTTP service access filter
    add_filter('dm_get_http_service', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['http_service'])) {
            $service_cache['http_service'] = new \DataMachine\Core\Handlers\HttpService();
        }
        
        return $service_cache['http_service'];
    }, 10);
    
    
    // Twitter Auth access filter (for modular Twitter handler)
    add_filter('dm_get_twitter_auth', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['twitter_auth'])) {
            $service_cache['twitter_auth'] = new \DataMachine\Core\Handlers\Output\Twitter\TwitterAuth();
        }
        
        return $service_cache['twitter_auth'];
    }, 10);
    
    // Threads Auth access filter (for modular Threads handler)
    add_filter('dm_get_threads_auth', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['threads_auth'])) {
            $service_cache['threads_auth'] = new \DataMachine\Core\Handlers\Output\Threads\ThreadsAuth();
        }
        
        return $service_cache['threads_auth'];
    }, 10);
    
    // OAuth Reddit access filter
    add_filter('dm_get_oauth_reddit', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['oauth_reddit'])) {
            $service_cache['oauth_reddit'] = new \DataMachine\Core\Handlers\Input\Reddit\RedditAuth();
        }
        
        return $service_cache['oauth_reddit'];
    }, 10);
    
    
    // Facebook Auth access filter (for modular Facebook handler)
    add_filter('dm_get_facebook_auth', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['facebook_auth'])) {
            $service_cache['facebook_auth'] = new \DataMachine\Core\Handlers\Output\Facebook\FacebookAuth();
        }
        
        return $service_cache['facebook_auth'];
    }, 10);
    
    // prompt builder access filter
    add_filter('dm_get_prompt_builder', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['prompt_builder'])) {
            $service_cache['prompt_builder'] = new \DataMachine\Helpers\PromptBuilder();
            $service_cache['prompt_builder']->register_all_sections();
        }
        
        return $service_cache['prompt_builder'];
    }, 10);
    
    
    // scheduler access filter
    add_filter('dm_get_scheduler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['scheduler'])) {
            $service_cache['scheduler'] = new \DataMachine\Admin\Projects\Scheduler();
        }
        
        return $service_cache['scheduler'];
    }, 10);
    
    // Project prompts filter - WordPress-native filter system
    // Returns project prompts for given project_id
    // Usage: $prompts = apply_filters('dm_get_project_prompt', null, $project_id);
    add_filter('dm_get_project_prompt', function($prompts, $project_id) {
        if ($prompts !== null) {
            return $prompts; // External override provided
        }
        
        $db_projects = apply_filters('dm_get_db_projects', null);
        if (!$db_projects) {
            return [];
        }
        
        $project = $db_projects->get_project($project_id);
        if (!$project || empty($project->step_prompts)) {
            return [];
        }
        
        $step_prompts = json_decode($project->step_prompts, true);
        return is_array($step_prompts) ? $step_prompts : [];
    }, 10, 2);
    
    // Project pipeline config service removed - replaced with direct database access
    // All pipeline configuration now uses dm_get_db_projects filter for direct database operations
    
    // admin page access filter
    add_filter('dm_get_admin_page', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['admin_page'])) {
            $service_cache['admin_page'] = new \DataMachine\Admin\AdminPage();
        }
        
        return $service_cache['admin_page'];
    }, 10);
    
    // AiStepConfigService removed - replaced with direct WordPress database operations
    // All AI step configuration now uses get_option/update_option/delete_option patterns
    // External plugins can still access these options using the dm_ai_step_config_{project_id}_{step_position} format
    
    // job status manager access filter
    add_filter('dm_get_job_status_manager', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['job_status_manager'])) {
            $service_cache['job_status_manager'] = new \DataMachine\Engine\JobStatusManager();
        }
        
        return $service_cache['job_status_manager'];
    }, 10);
    
    // job creator access filter
    add_filter('dm_get_job_creator', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['job_creator'])) {
            $service_cache['job_creator'] = new \DataMachine\Engine\JobCreator();
        }
        
        return $service_cache['job_creator'];
    }, 10);
    
    // processed items manager access filter
    add_filter('dm_get_processed_items_manager', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['processed_items_manager'])) {
            $service_cache['processed_items_manager'] = new \DataMachine\Engine\ProcessedItemsManager();
        }
        
        return $service_cache['processed_items_manager'];
    }, 10);
    
    // Additional service filters for newly converted components
    
    // API auth page access filter
    add_filter('dm_get_api_auth_page', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['api_auth_page'])) {
            $service_cache['api_auth_page'] = new \DataMachine\Admin\OAuth\ApiAuthPage();
        }
        
        return $service_cache['api_auth_page'];
    }, 10);
    
    // Module configuration is now handled through the pipeline system
    
    // import export handler access filter
    add_filter('dm_get_import_export_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['import_export_handler'])) {
            $service_cache['import_export_handler'] = new \DataMachine\Admin\Projects\ImportExport();
        }
        
        return $service_cache['import_export_handler'];
    }, 10);
    
    // Module AJAX operations are now handled through pipeline management
    
    // AJAX handlers access filters
    
    add_filter('dm_get_dashboard_ajax_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['dashboard_ajax_handler'])) {
            $service_cache['dashboard_ajax_handler'] = new \DataMachine\Admin\Projects\ProjectManagementAjax();
        }
        
        return $service_cache['dashboard_ajax_handler'];
    }, 10);
    
    add_filter('dm_get_ajax_scheduler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['ajax_scheduler'])) {
            $service_cache['ajax_scheduler'] = new \DataMachine\Admin\Projects\AjaxScheduler();
        }
        
        return $service_cache['ajax_scheduler'];
    }, 10);
    
    add_filter('dm_get_ajax_auth', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['ajax_auth'])) {
            $service_cache['ajax_auth'] = new \DataMachine\Admin\OAuth\AjaxAuth();
        }
        
        return $service_cache['ajax_auth'];
    }, 10);
    
    add_filter('dm_get_remote_locations_ajax_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['remote_locations_ajax_handler'])) {
            $service_cache['remote_locations_ajax_handler'] = new \DataMachine\Admin\RemoteLocations\RemoteLocationsAjax();
        }
        
        return $service_cache['remote_locations_ajax_handler'];
    }, 10);
    
    // File uploads are now handled through the pipeline system
    
    add_filter('dm_get_pipeline_management_ajax_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['pipeline_management_ajax_handler'])) {
            $service_cache['pipeline_management_ajax_handler'] = new \DataMachine\Admin\Projects\PipelineManagementAjax();
        }
        
        return $service_cache['pipeline_management_ajax_handler'];
    }, 10);
    
    add_filter('dm_get_pipeline_steps_ajax_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['pipeline_steps_ajax_handler'])) {
            $service_cache['pipeline_steps_ajax_handler'] = new \DataMachine\Admin\Projects\ProjectPipelineStepsAjax();
        }
        
        return $service_cache['pipeline_steps_ajax_handler'];
    }, 10);
    
    add_filter('dm_get_modal_config_ajax_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['modal_config_ajax_handler'])) {
            $service_cache['modal_config_ajax_handler'] = new \DataMachine\Admin\Projects\ModalConfigAjax();
        }
        
        return $service_cache['modal_config_ajax_handler'];
    }, 10);
    
    // Settings registration is now handled through pipeline configuration
    
    add_filter('dm_get_data_machine_plugin', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['data_machine_plugin'])) {
            $service_cache['data_machine_plugin'] = new \DataMachine\Core\DataMachine();
        }
        
        return $service_cache['data_machine_plugin'];
    }, 10);
    
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
    
    // database remote locations access filter
    add_filter('dm_get_db_remote_locations', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['db_remote_locations'])) {
            $service_cache['db_remote_locations'] = new \DataMachine\Database\RemoteLocations();
        }
        
        return $service_cache['db_remote_locations'];
    }, 10);
    
    // remote location service access filter
    add_filter('dm_get_remote_location_service', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service;
        }
        
        if (!isset($service_cache['remote_location_service'])) {
            $service_cache['remote_location_service'] = new \DataMachine\Admin\RemoteLocations\RemoteLocationService();
        }
        
        return $service_cache['remote_location_service'];
    }, 10);
    
    // Core admin pages register via same filter as external plugins
    // This validates the extension API by using it exactly as third-party developers would
    add_filter('dm_register_admin_pages', function($pages) {
        $admin_page = apply_filters('dm_get_admin_page', null);
        
        // Main menu page
        $pages[] = [
            'type' => 'menu',
            'page_title' => __('Data Machine', 'data-machine'),
            'menu_title' => __('Data Machine', 'data-machine'),
            'capability' => 'manage_options',
            'menu_slug' => 'dm-project-management',
            'callback' => [$admin_page, 'display_project_management_page'],
            'icon_url' => 'dashicons-database-import',
            'position' => 6
        ];

        // Projects submenu (same slug for clean URLs)
        $pages[] = [
            'type' => 'submenu',
            'parent_slug' => 'dm-project-management',
            'page_title' => __('Projects', 'data-machine'),
            'menu_title' => __('Projects', 'data-machine'),
            'capability' => 'manage_options',
            'menu_slug' => 'dm-project-management',
            'callback' => [$admin_page, 'display_project_management_page']
        ];

        // Remote Locations submenu
        $pages[] = [
            'type' => 'submenu',
            'parent_slug' => 'dm-project-management',
            'page_title' => __('Manage Remote Locations', 'data-machine'),
            'menu_title' => __('Remote Locations', 'data-machine'),
            'capability' => 'manage_options',
            'menu_slug' => 'dm-remote-locations',
            'callback' => [$admin_page, 'display_remote_locations_page']
        ];

        // API / Auth submenu
        $pages[] = [
            'type' => 'submenu',
            'parent_slug' => 'dm-project-management',
            'page_title' => __('API / Auth', 'data-machine'),
            'menu_title' => __('API / Auth', 'data-machine'),
            'capability' => 'manage_options',
            'menu_slug' => 'dm-api-keys',
            'callback' => [$admin_page, 'display_api_keys_page']
        ];

        // Jobs submenu
        $pages[] = [
            'type' => 'submenu',
            'parent_slug' => 'dm-project-management',
            'page_title' => __('Jobs', 'data-machine'),
            'menu_title' => __('Jobs', 'data-machine'),
            'capability' => 'manage_options',
            'menu_slug' => 'dm-jobs',
            'callback' => [$admin_page, 'display_jobs_page']
        ];

        return $pages;
    }, 10); // Standard priority like any external plugin would use
}


/**
 * Register utility filters for external handlers.
 * 
 * Provides validation and configuration parsing filters
 * to eliminate the need for external handlers to handle complex validation logic.
 * 
 * Key filters:
 * - dm_validate_handler_requirements: Validate module and user access
 * - dm_parse_common_config: Parse standard configuration fields
 * - dm_check_if_processed: Check for duplicate items
 * - dm_get_module_with_ownership: Get module with ownership verification
 * 
 * @since 0.1.0
 */
function dm_register_utility_filters() {
    
    /**
     * Validate basic handler requirements.
     * 
     * @param array|null $validation Existing validation result
     * @param object $module Module object
     * @param int $user_id User ID
     * @return array Validation result with module_id and project
     * @throws Exception If validation fails
     */
    add_filter('dm_validate_handler_requirements', function($validation, $module, $user_id) {
        if ($validation !== null) {
            return $validation; // External override provided
        }
        
        $logger = apply_filters('dm_get_logger', null);
        $db_modules = apply_filters('dm_get_db_modules', null);
        $db_projects = apply_filters('dm_get_db_projects', null);
        
        $logger && $logger->info('Filter: Validating handler requirements.', [
            'module_id' => $module->module_id ?? null,
            'user_id' => $user_id
        ]);
        
        // Extract and validate module ID
        $module_id = isset($module->module_id) ? absint($module->module_id) : 0;
        if (empty($module_id)) {
            $logger && $logger->error('Filter: Module ID missing from module object.');
            throw new Exception(esc_html__('Missing module ID.', 'data-machine'));
        }
        
        // Validate user ID
        if (empty($user_id)) {
            $logger && $logger->error('Filter: User ID not provided.', ['module_id' => $module_id]);
            throw new Exception(esc_html__('User ID not provided.', 'data-machine'));
        }
        
        // Validate dependencies
        if (!$db_modules || !$db_projects) {
            $logger && $logger->error('Filter: Required database service not available.', ['module_id' => $module_id]);
            throw new Exception(esc_html__('Required database service not available.', 'data-machine'));
        }
        
        // Get module with ownership check
        $project = apply_filters('dm_get_module_with_ownership', null, $module, $user_id);
        
        return [
            'module_id' => $module_id,
            'project' => $project
        ];
        
    }, 10, 3);
    
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
     * @param int $module_id Module ID
     * @param string $source_type Handler source type
     * @param string $item_identifier Unique item identifier
     * @return bool True if already processed
     */
    add_filter('dm_check_if_processed', function($is_processed, $module_id, $source_type, $item_identifier) {
        if ($is_processed !== false) {
            return $is_processed;
        }
        
        $processed_items_manager = apply_filters('dm_get_processed_items_manager', null);
        if (!$processed_items_manager) {
            return false;
        }
        
        return $processed_items_manager->is_item_processed($module_id, $source_type, $item_identifier);
        
    }, 10, 4);
    
    /**
     * Get module with ownership verification.
     * 
     * @param object|null $project Existing project object
     * @param object $module Module object
     * @param int $user_id User ID
     * @return object Project object with verified ownership
     * @throws Exception If ownership verification fails
     */
    add_filter('dm_get_module_with_ownership', function($project, $module, $user_id) {
        if ($project !== null) {
            return $project; // External override provided
        }
        
        $db_projects = apply_filters('dm_get_db_projects', null);
        if (!$db_projects) {
            throw new Exception(esc_html__('Database projects service not available.', 'data-machine'));
        }
        
        if (!isset($module->project_id)) {
            throw new Exception(esc_html__('Invalid module provided (missing project ID).', 'data-machine'));
        }
        
        $project = $db_projects->get_project($module->project_id, $user_id);
        if (!$project) {
            throw new Exception(esc_html__('Permission denied for this module.', 'data-machine'));
        }
        
        return $project;
        
    }, 10, 3);
    
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
}