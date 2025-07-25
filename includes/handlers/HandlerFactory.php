<?php
/**
 * Handler Factory implementation using Dependency Injection.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/module-config
 * @since      NEXT_VERSION
 */

namespace DataMachine\Handlers;

use DataMachine\Admin\OAuth\{Twitter as OAuthTwitter, Reddit as OAuthReddit, Threads as OAuthThreads, Facebook as OAuthFacebook};
use DataMachine\Database\{Modules, Projects, RemoteLocations};
use DataMachine\Engine\ProcessedItemsManager;
use DataMachine\Handlers\HttpService;
use DataMachine\Helpers\{Logger, EncryptionHelper};
use DataMachine\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class HandlerFactory {
    private $logger;
    private $processed_items_manager;
    private $encryption_helper;
    private $oauth_twitter;
    private $oauth_reddit;
    private $oauth_threads; // Added
    private $oauth_facebook; // Added
    private $db_remote_locations;
    private $db_modules;
    private $db_projects;
    private $handler_http_service;

    /**
     * Constructor. Injects all potential dependencies needed by any handler.
     */
    public function __construct(
        ?Logger $logger, // Logger is optional
        ProcessedItemsManager $processed_items_manager,
        EncryptionHelper $encryption_helper,
        OAuthTwitter $oauth_twitter,
        OAuthReddit $oauth_reddit,
        OAuthThreads $oauth_threads, // Added
        OAuthFacebook $oauth_facebook, // Added
        RemoteLocations $db_remote_locations,
        Modules $db_modules,
        Projects $db_projects,
        HttpService $handler_http_service
    ) {
        $this->logger = $logger;
        $this->processed_items_manager = $processed_items_manager;
        $this->encryption_helper = $encryption_helper;
        $this->oauth_twitter = $oauth_twitter;
        $this->oauth_reddit = $oauth_reddit;
        $this->oauth_threads = $oauth_threads;   // Added
        $this->oauth_facebook = $oauth_facebook; // Added
        $this->db_remote_locations = $db_remote_locations;
        $this->db_modules = $db_modules;
        $this->db_projects = $db_projects;
        $this->handler_http_service = $handler_http_service;
    }

    /**
     * Create a handler instance based on type and slug.
     *
     * @param string $handler_type The type of handler ('input' or 'output').
     * @param string $handler_slug The slug of the handler.
     * @return object|WP_Error The handler instance or WP_Error on failure.
     */
    public function create_handler(string $handler_type, string $handler_slug) {
        $handler_info = null;
        $class_name = null;

        try {
            if ($handler_type === 'input') {
                $handler_info = Constants::get_input_handler($handler_slug);
            } elseif ($handler_type === 'output') {
                $handler_info = Constants::get_output_handler($handler_slug);
            } else {
                 if ($this->logger) $this->logger->error('Invalid handler type requested.', ['type' => $handler_type, 'slug' => $handler_slug]);
                return new \WP_Error('invalid_handler_type', 'Invalid handler type specified.', ['type' => $handler_type]);
            }

            if (!$handler_info || !isset($handler_info['class'])) {
                if ($this->logger) $this->logger->error('Handler class not found in registry.', ['type' => $handler_type, 'slug' => $handler_slug]);
                return new \WP_Error('handler_not_found', 'Handler class not found in registry.', ['type' => $handler_type, 'slug' => $handler_slug]);
            }

            $class_name = $handler_info['class'];

            // Ensure the class exists (should be autoloaded via PSR-4)
            if (!class_exists($class_name)) {
                if ($this->logger) $this->logger->error('Handler class not found - check namespace and autoloader.', ['type' => $handler_type, 'slug' => $handler_slug, 'class' => $class_name]);
                return new \WP_Error('handler_class_not_found', 'Handler class not found - check namespace and autoloader.', ['class' => $class_name]);
            }

            // Dynamic instantiation using reflection-based dependency resolution
            try {
                if ($this->logger) $this->logger->debug('Creating handler via dynamic instantiation.', [
                    'type' => $handler_type, 
                    'slug' => $handler_slug, 
                    'class' => $class_name
                ]);
                
                return $this->instantiate_with_dependencies($class_name);
                
            } catch (\Exception $e) {
                if ($this->logger) $this->logger->error('Failed to instantiate handler dynamically.', [
                    'type' => $handler_type,
                    'slug' => $handler_slug, 
                    'class' => $class_name,
                    'error' => $e->getMessage()
                ]);
                return new \WP_Error('handler_instantiation_failed', 
                    'Failed to create handler: ' . $e->getMessage(), 
                    ['class' => $class_name, 'slug' => $handler_slug]
                );
            }

        } catch (\Exception $e) {
            if ($this->logger) $this->logger->error('Exception during handler creation.', [
                'type' => $handler_type, 
                'slug' => $handler_slug, 
                'class' => $class_name, 
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString() // Optional: for detailed debugging
            ]);
            return new \WP_Error('handler_creation_exception', 'An exception occurred during handler creation: ' . $e->getMessage());
        } catch (\Error $e) { // Catch fatal errors too
             if ($this->logger) $this->logger->critical('Fatal error during handler creation.', [
                 'type' => $handler_type, 
                 'slug' => $handler_slug, 
                 'class' => $class_name, 
                 'error_message' => $e->getMessage(),
                 'error_trace' => $e->getTraceAsString() 
             ]);
             return new \WP_Error('handler_creation_error', 'A critical error occurred during handler creation: ' . $e->getMessage());
        }
    }

    /**
     * Instantiate a handler with automatically resolved dependencies.
     *
     * @param string $class_name Fully qualified class name
     * @return object Handler instance
     * @throws \Exception If required dependencies are missing
     */
    private function instantiate_with_dependencies(string $class_name): object {
        $dependencies = $this->resolve_constructor_dependencies($class_name);
        return new $class_name(...$dependencies);
    }

    /**
     * Resolve constructor dependencies for a handler class using reflection.
     *
     * @param string $class_name Fully qualified class name
     * @return array Array of resolved dependencies in constructor order
     * @throws \Exception If required dependencies cannot be resolved
     */
    private function resolve_constructor_dependencies(string $class_name): array {
        $reflection = new \ReflectionClass($class_name);
        $constructor = $reflection->getConstructor();
        
        if (!$constructor) {
            return [];
        }
        
        $dependencies = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            
            if ($type && !$type->isBuiltin()) {
                $type_name = $type->getName();
                $dependency = $this->get_dependency_by_type($type_name);
                
                if ($dependency === null && !$param->isOptional()) {
                    throw new \Exception("Required dependency {$type_name} not available for {$class_name}");
                }
                
                $dependencies[] = $dependency;
            } else {
                // Handle built-in types or no type hint - use default value if available
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    $dependencies[] = null;
                }
            }
        }
        
        return $dependencies;
    }

    /**
     * Get dependency instance by type name.
     *
     * @param string $type_name Fully qualified class/interface name
     * @return mixed|null Dependency instance or null if not available
     */
    private function get_dependency_by_type(string $type_name): mixed {
        return match($type_name) {
            'DataMachine\Database\Modules' => $this->db_modules,
            'DataMachine\Database\Projects' => $this->db_projects,
            'DataMachine\Database\RemoteLocations' => $this->db_remote_locations,
            'DataMachine\Engine\ProcessedItemsManager' => $this->processed_items_manager,
            'DataMachine\Helpers\Logger' => $this->logger,
            'DataMachine\Helpers\EncryptionHelper' => $this->encryption_helper,
            'DataMachine\Handlers\HttpService' => $this->handler_http_service,
            'DataMachine\Admin\OAuth\Twitter' => $this->oauth_twitter,
            'DataMachine\Admin\OAuth\Reddit' => $this->oauth_reddit,
            'DataMachine\Admin\OAuth\Threads' => $this->oauth_threads,
            'DataMachine\Admin\OAuth\Facebook' => $this->oauth_facebook,
            default => null
        };
    }

    /**
     * Register an external handler for third-party plugin support.
     *
     * @param string $type Handler type ('input' or 'output')
     * @param string $slug Handler slug
     * @param string $class_name Fully qualified class name
     */
    public function register_external_handler(string $type, string $slug, string $class_name): void {
        if ($type === 'input') {
            // Note: This would require enhancing HandlerRegistry with registration methods
            // For now, this is a placeholder for future extensibility
            if ($this->logger) {
                $this->logger->info('External input handler registration requested', [
                    'slug' => $slug, 
                    'class' => $class_name
                ]);
            }
        } elseif ($type === 'output') {
            if ($this->logger) {
                $this->logger->info('External output handler registration requested', [
                    'slug' => $slug, 
                    'class' => $class_name
                ]);
            }
        }
    }

} 