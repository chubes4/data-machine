<?php

namespace DataMachine;

class ServiceRegistry {
    private static $services = [];
    private static $factories = [];
    private static $initialized = false;

    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        self::register_core_services();
        self::setup_service_filter();
        self::$initialized = true;
    }

    private static function register_core_services() {
        // Immediate services (no dependencies)
        self::register('logger', function() {
            return new \DataMachine\Logger();
        });

        self::register('encryption_helper', function() {
            return new \DataMachine\EncryptionHelper();
        });

        self::register('db_projects', function() {
            return new \DataMachine\Database\Projects();
        });

        self::register('db_remote_locations', function() {
            return new \DataMachine\Database\RemoteLocations();
        });

        // Services with dependencies (lazy initialization)
        self::register('action_scheduler', function() {
            $logger = self::get('logger');
            return new \DataMachine\ActionScheduler($logger);
        });

        self::register('memory_guard', function() {
            $logger = self::get('logger');
            return new \DataMachine\MemoryGuard($logger);
        });

        self::register('db_modules', function() {
            $db_projects = self::get('db_projects');
            $logger = self::get('logger');
            return new \DataMachine\Database\Modules($db_projects, $logger);
        });

        self::register('db_jobs', function() {
            $db_projects = self::get('db_projects');
            $logger = self::get('logger');
            return new \DataMachine\Database\Jobs($db_projects, $logger);
        });

        self::register('db_processed_items', function() {
            $logger = self::get('logger');
            return new \DataMachine\Database\ProcessedItems($logger);
        });

        self::register('http_service', function() {
            $logger = self::get('logger');
            return new \DataMachine\HttpService($logger);
        });

        self::register('oauth_twitter', function() {
            $logger = self::get('logger');
            return new \DataMachine\Admin\OAuth\OAuthTwitter($logger);
        });

        self::register('oauth_reddit', function() {
            $logger = self::get('logger');
            return new \DataMachine\Admin\OAuth\OAuthReddit($logger);
        });

        self::register('oauth_threads', function() {
            $threads_client_id = get_option('threads_app_id', '');
            $threads_client_secret = get_option('threads_app_secret', '');
            $logger = self::get('logger');
            return new \DataMachine\Admin\OAuth\OAuthThreads($threads_client_id, $threads_client_secret, $logger);
        });

        self::register('oauth_facebook', function() {
            $facebook_client_id = get_option('facebook_app_id', '');
            $facebook_client_secret = get_option('facebook_app_secret', '');
            $logger = self::get('logger');
            return new \DataMachine\Admin\OAuth\OAuthFacebook($facebook_client_id, $facebook_client_secret, $logger);
        });

        self::register('prompt_builder', function() {
            $prompt_builder = new \DataMachine\Helpers\PromptBuilder();
            $prompt_builder->register_all_sections();
            return $prompt_builder;
        });

        self::register('ai_http_client', function() {
            return new \AI_HTTP_Client(['plugin_context' => 'data-machine', 'ai_type' => 'llm']);
        });

        self::register('job_status_manager', function() {
            $db_jobs = self::get('db_jobs');
            $db_projects = self::get('db_projects');
            $logger = self::get('logger');
            return new \DataMachine\JobStatusManager($db_jobs, $db_projects, $logger);
        });

        self::register('job_creator', function() {
            $db_jobs = self::get('db_jobs');
            $db_modules = self::get('db_modules');
            $db_projects = self::get('db_projects');
            $action_scheduler = self::get('action_scheduler');
            $logger = self::get('logger');
            return new \DataMachine\JobCreator($db_jobs, $db_modules, $db_projects, $action_scheduler, $logger);
        });

        self::register('processed_items_manager', function() {
            $db_processed_items = self::get('db_processed_items');
            $logger = self::get('logger');
            return new \DataMachine\ProcessedItemsManager($db_processed_items, $logger);
        });

        self::register('handler_factory', function() {
            $logger = self::get('logger');
            return new \DataMachine\Handlers\HandlerFactory($logger);
        });

        self::register('pipeline_step_registry', function() {
            return new \DataMachine\Engine\PipelineStepRegistry();
        });

        self::register('scheduler', function() {
            $job_creator = self::get('job_creator');
            $db_projects = self::get('db_projects');
            $db_modules = self::get('db_modules');
            $action_scheduler = self::get('action_scheduler');
            $db_jobs = self::get('db_jobs');
            $logger = self::get('logger');
            return new \DataMachine\Admin\Projects\Scheduler($job_creator, $db_projects, $db_modules, $action_scheduler, $db_jobs, $logger);
        });

        self::register('orchestrator', function() {
            $logger = self::get('logger');
            $action_scheduler = self::get('action_scheduler');
            $db_jobs = self::get('db_jobs');
            return new \DataMachine\Engine\ProcessingOrchestrator($logger, $action_scheduler, $db_jobs);
        });

        self::register('project_prompts_service', function() {
            return new \DataMachine\Helpers\ProjectPromptsService();
        });

        self::register('project_pipeline_config_service', function() {
            return new \DataMachine\Helpers\ProjectPipelineConfigService();
        });
    }

    public static function register($name, $factory) {
        self::$factories[$name] = $factory;
    }

    public static function get($name) {
        // Allow external override via filter
        $override = apply_filters("dm_service_override_{$name}", null);
        if ($override !== null) {
            return $override;
        }

        // Return cached instance if exists
        if (isset(self::$services[$name])) {
            return self::$services[$name];
        }

        // Create and cache new instance
        if (isset(self::$factories[$name])) {
            self::$services[$name] = self::$factories[$name]();
            return self::$services[$name];
        }

        return null;
    }

    private static function setup_service_filter() {
        add_filter('dm_get_service', function($service, $service_name) {
            return self::get($service_name);
        }, 10, 2);
    }
}