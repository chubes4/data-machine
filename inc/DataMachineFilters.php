<?php
/**
 * Data Machine Service Filters Registration
 *
 * This file contains the ultra-direct service filter registration system that provides
 * the most efficient access pattern possible for critical Data Machine services.
 * 
 * Centralizes all filter-based service registration in one organized location,
 * enabling external plugins to override any service via filter priority while
 * maintaining lazy loading and static caching for optimal performance.
 *
 * @package DataMachine
 * @since 0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register ultra-direct service filters for most heavily used core services.
 * 
 * This provides the most direct access pattern possible for critical services:
 * - dm_get_logger: Most used service (40+ references throughout codebase)
 * - dm_get_db_jobs: Critical for pipeline execution and job management
 * - dm_get_db_projects: Core project management database operations
 * - dm_get_ai_http_client: AI integration and processing
 * - dm_get_fluid_context_bridge: New fluid architecture for enhanced AI context
 * - dm_get_orchestrator: Core pipeline processing orchestration
 * 
 * Features:
 * - External override capability via filter priority
 * - Lazy loading with static caching for performance
 * - Clean dependency resolution without registry lookup
 * - WordPress-native patterns throughout
 * - Zero constructor dependencies
 * 
 * Usage Examples:
 * $logger = apply_filters('dm_get_logger', null);
 * $db_jobs = apply_filters('dm_get_db_jobs', null);
 * $ai_client = apply_filters('dm_get_ai_http_client', null);
 * $orchestrator = apply_filters('dm_get_orchestrator', null);
 * $fluid_bridge = apply_filters('dm_get_fluid_context_bridge', null);
 * 
 * External Override Examples:
 * add_filter('dm_get_logger', function($service) { return new CustomLogger(); }, 20);
 * add_filter('dm_get_ai_http_client', function($service) { return new CustomAIClient(); }, 15);
 * 
 * Integration Benefits:
 * - Pure filter-based architecture throughout entire codebase
 * - Ultra-fast direct access to all services without lookup overhead
 * - Complete external override capabilities via filter priority
 * - 100% WordPress-native dependency management patterns
 * - Parameter-less constructors for all service classes
 * - Zero ServiceRegistry dependencies or mixed patterns
 *
 * @since 0.1.0
 */
function dm_register_direct_service_filters() {
    // Static cache for lazy-loaded services
    static $service_cache = [];
    
    // Ultra-direct logger access filter
    add_filter('dm_get_logger', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['logger'])) {
            $service_cache['logger'] = new \DataMachine\Helpers\Logger();
        }
        
        return $service_cache['logger'];
    }, 10);
    
    // Ultra-direct database jobs access filter
    add_filter('dm_get_db_jobs', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['db_jobs'])) {
            // Parameter-less constructor - pure filter-based architecture
            $service_cache['db_jobs'] = new \DataMachine\Database\Jobs();
        }
        
        return $service_cache['db_jobs'];
    }, 10);
    
    // Ultra-direct database projects access filter
    add_filter('dm_get_db_projects', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['db_projects'])) {
            // Parameter-less constructor - pure filter-based architecture
            $service_cache['db_projects'] = new \DataMachine\Database\Projects();
        }
        
        return $service_cache['db_projects'];
    }, 10);
    
    // Ultra-direct AI HTTP client access filter
    add_filter('dm_get_ai_http_client', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['ai_http_client'])) {
            $service_cache['ai_http_client'] = new \AI_HTTP_Client(['plugin_context' => 'data-machine', 'ai_type' => 'llm']);
        }
        
        return $service_cache['ai_http_client'];
    }, 10);
    
    // Ultra-direct fluid context bridge access filter
    add_filter('dm_get_fluid_context_bridge', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['fluid_context_bridge'])) {
            // Parameter-less constructor - pure filter-based architecture
            $service_cache['fluid_context_bridge'] = new \DataMachine\Engine\FluidContextBridge();
        }
        
        return $service_cache['fluid_context_bridge'];
    }, 10);
    
    // Ultra-direct processing orchestrator access filter
    add_filter('dm_get_orchestrator', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['orchestrator'])) {
            $service_cache['orchestrator'] = new \DataMachine\Engine\ProcessingOrchestrator();
        }
        
        return $service_cache['orchestrator'];
    }, 10);
    
    // Ultra-direct database modules access filter
    add_filter('dm_get_db_modules', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['db_modules'])) {
            // Parameter-less constructor - pure filter-based architecture
            $service_cache['db_modules'] = new \DataMachine\Database\Modules();
        }
        
        return $service_cache['db_modules'];
    }, 10);
    
    
    
    // Ultra-direct database processed items access filter
    add_filter('dm_get_db_processed_items', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['db_processed_items'])) {
            $service_cache['db_processed_items'] = new \DataMachine\Database\ProcessedItems();
        }
        
        return $service_cache['db_processed_items'];
    }, 10);
    
    // Ultra-direct HTTP service access filter
    add_filter('dm_get_http_service', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['http_service'])) {
            $service_cache['http_service'] = new \DataMachine\Core\Handlers\HttpService();
        }
        
        return $service_cache['http_service'];
    }, 10);
    
    // Ultra-direct OAuth Twitter access filter
    add_filter('dm_get_oauth_twitter', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['oauth_twitter'])) {
            $service_cache['oauth_twitter'] = new \DataMachine\Admin\OAuth\Twitter();
        }
        
        return $service_cache['oauth_twitter'];
    }, 10);
    
    // Ultra-direct OAuth Reddit access filter
    add_filter('dm_get_oauth_reddit', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['oauth_reddit'])) {
            $service_cache['oauth_reddit'] = new \DataMachine\Admin\OAuth\Reddit();
        }
        
        return $service_cache['oauth_reddit'];
    }, 10);
    
    // Ultra-direct OAuth Threads access filter
    add_filter('dm_get_oauth_threads', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['oauth_threads'])) {
            $service_cache['oauth_threads'] = new \DataMachine\Admin\OAuth\Threads();
        }
        
        return $service_cache['oauth_threads'];
    }, 10);
    
    // Ultra-direct OAuth Facebook access filter
    add_filter('dm_get_oauth_facebook', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['oauth_facebook'])) {
            $service_cache['oauth_facebook'] = new \DataMachine\Admin\OAuth\Facebook();
        }
        
        return $service_cache['oauth_facebook'];
    }, 10);
    
    // Ultra-direct prompt builder access filter
    add_filter('dm_get_prompt_builder', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['prompt_builder'])) {
            $service_cache['prompt_builder'] = new \DataMachine\Helpers\PromptBuilder();
            $service_cache['prompt_builder']->register_all_sections();
        }
        
        return $service_cache['prompt_builder'];
    }, 10);
    
    // Ultra-direct pipeline step registry access filter
    add_filter('dm_get_pipeline_step_registry', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['pipeline_step_registry'])) {
            $service_cache['pipeline_step_registry'] = new \DataMachine\Engine\PipelineStepRegistry();
        }
        
        return $service_cache['pipeline_step_registry'];
    }, 10);
    
    // Ultra-direct scheduler access filter
    add_filter('dm_get_scheduler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['scheduler'])) {
            $service_cache['scheduler'] = new \DataMachine\Admin\Projects\Scheduler();
        }
        
        return $service_cache['scheduler'];
    }, 10);
    
    // Ultra-direct project prompts service access filter
    add_filter('dm_get_project_prompts_service', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['project_prompts_service'])) {
            $service_cache['project_prompts_service'] = new \DataMachine\Helpers\ProjectPromptsService();
        }
        
        return $service_cache['project_prompts_service'];
    }, 10);
    
    // Ultra-direct project pipeline config service access filter
    add_filter('dm_get_project_pipeline_config_service', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['project_pipeline_config_service'])) {
            $service_cache['project_pipeline_config_service'] = new \DataMachine\Services\ProjectPipelineConfigService();
        }
        
        return $service_cache['project_pipeline_config_service'];
    }, 10);
    
    // Ultra-direct admin page access filter
    add_filter('dm_get_admin_page', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['admin_page'])) {
            $service_cache['admin_page'] = new \DataMachine\Admin\AdminPage();
        }
        
        return $service_cache['admin_page'];
    }, 10);
    
    // Ultra-direct AI step config service access filter
    add_filter('dm_get_ai_step_config_service', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['ai_step_config_service'])) {
            $service_cache['ai_step_config_service'] = new \DataMachine\Services\AiStepConfigService();
        }
        
        return $service_cache['ai_step_config_service'];
    }, 10);
    
    // Ultra-direct job status manager access filter
    add_filter('dm_get_job_status_manager', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['job_status_manager'])) {
            $service_cache['job_status_manager'] = new \DataMachine\Engine\JobStatusManager();
        }
        
        return $service_cache['job_status_manager'];
    }, 10);
    
    // Ultra-direct job creator access filter
    add_filter('dm_get_job_creator', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['job_creator'])) {
            $service_cache['job_creator'] = new \DataMachine\Engine\JobCreator();
        }
        
        return $service_cache['job_creator'];
    }, 10);
    
    // Ultra-direct processed items manager access filter
    add_filter('dm_get_processed_items_manager', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['processed_items_manager'])) {
            $service_cache['processed_items_manager'] = new \DataMachine\Engine\ProcessedItemsManager();
        }
        
        return $service_cache['processed_items_manager'];
    }, 10);
    
    // Additional service filters for newly converted components
    
    // Ultra-direct API auth page access filter
    add_filter('dm_get_api_auth_page', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['api_auth_page'])) {
            $service_cache['api_auth_page'] = new \DataMachine\Admin\OAuth\ApiAuthPage();
        }
        
        return $service_cache['api_auth_page'];
    }, 10);
    
    // Ultra-direct module config handler access filter
    add_filter('dm_get_module_config_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['module_config_handler'])) {
            $service_cache['module_config_handler'] = new \DataMachine\Admin\ModuleConfig\ModuleConfigHandler();
        }
        
        return $service_cache['module_config_handler'];
    }, 10);
    
    // Ultra-direct import export handler access filter
    add_filter('dm_get_import_export_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['import_export_handler'])) {
            $service_cache['import_export_handler'] = new \DataMachine\Admin\Projects\ImportExport();
        }
        
        return $service_cache['import_export_handler'];
    }, 10);
    
    // Ultra-direct AJAX handlers access filters
    add_filter('dm_get_module_ajax_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['module_ajax_handler'])) {
            $service_cache['module_ajax_handler'] = new \DataMachine\Admin\ModuleConfig\Ajax\ModuleConfigAjax();
        }
        
        return $service_cache['module_ajax_handler'];
    }, 10);
    
    add_filter('dm_get_dashboard_ajax_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['dashboard_ajax_handler'])) {
            $service_cache['dashboard_ajax_handler'] = new \DataMachine\Admin\Projects\ProjectManagementAjax();
        }
        
        return $service_cache['dashboard_ajax_handler'];
    }, 10);
    
    add_filter('dm_get_ajax_scheduler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['ajax_scheduler'])) {
            $service_cache['ajax_scheduler'] = new \DataMachine\Admin\Projects\AjaxScheduler();
        }
        
        return $service_cache['ajax_scheduler'];
    }, 10);
    
    add_filter('dm_get_ajax_auth', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['ajax_auth'])) {
            $service_cache['ajax_auth'] = new \DataMachine\Admin\OAuth\AjaxAuth();
        }
        
        return $service_cache['ajax_auth'];
    }, 10);
    
    add_filter('dm_get_remote_locations_ajax_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['remote_locations_ajax_handler'])) {
            $service_cache['remote_locations_ajax_handler'] = new \DataMachine\Admin\ModuleConfig\Ajax\RemoteLocationsAjax();
        }
        
        return $service_cache['remote_locations_ajax_handler'];
    }, 10);
    
    add_filter('dm_get_file_upload_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['file_upload_handler'])) {
            $service_cache['file_upload_handler'] = new \DataMachine\Admin\Projects\FileUploadHandler();
        }
        
        return $service_cache['file_upload_handler'];
    }, 10);
    
    add_filter('dm_get_pipeline_management_ajax_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['pipeline_management_ajax_handler'])) {
            $service_cache['pipeline_management_ajax_handler'] = new \DataMachine\Admin\Projects\PipelineManagementAjax();
        }
        
        return $service_cache['pipeline_management_ajax_handler'];
    }, 10);
    
    add_filter('dm_get_pipeline_steps_ajax_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['pipeline_steps_ajax_handler'])) {
            $service_cache['pipeline_steps_ajax_handler'] = new \DataMachine\Admin\Projects\ProjectPipelineStepsAjax();
        }
        
        return $service_cache['pipeline_steps_ajax_handler'];
    }, 10);
    
    add_filter('dm_get_modal_config_ajax_handler', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['modal_config_ajax_handler'])) {
            $service_cache['modal_config_ajax_handler'] = new \DataMachine\Admin\Projects\ModalConfigAjax();
        }
        
        return $service_cache['modal_config_ajax_handler'];
    }, 10);
    
    add_filter('dm_get_register_settings', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['register_settings'])) {
            $service_cache['register_settings'] = new \DataMachine\Admin\ModuleConfig\RegisterSettings();
        }
        
        return $service_cache['register_settings'];
    }, 10);
    
    add_filter('dm_get_data_machine_plugin', function($service) use (&$service_cache) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if (!isset($service_cache['data_machine_plugin'])) {
            $service_cache['data_machine_plugin'] = new \DataMachine\Core\DataMachine();
        }
        
        return $service_cache['data_machine_plugin'];
    }, 10);
}