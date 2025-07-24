<?php
/**
 * Handler Factory implementation using Dependency Injection.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/module-config
 * @since      NEXT_VERSION
 */

namespace DataMachine\Handlers;

use DataMachine\Handlers\Input\{Files, AirdropRestApi, PublicRestApi, Reddit, Rss};
use DataMachine\Handlers\Output\{PublishRemote, Bluesky, Twitter, DataExport, PublishLocal, Threads, Facebook};
use DataMachine\Admin\OAuth\{Twitter as OAuthTwitter, Reddit as OAuthReddit, Threads as OAuthThreads, Facebook as OAuthFacebook};
use DataMachine\Database\{Modules, Projects, RemoteLocations};
use DataMachine\Engine\ProcessedItemsManager;
use DataMachine\Helpers\{Logger, EncryptionHelper};

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class HandlerFactory {

    private $handler_registry;
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
        HandlerRegistry $handler_registry,
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
        $this->handler_registry = $handler_registry;
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
                $handler_info = $this->handler_registry->get_input_handler($handler_slug);
            } elseif ($handler_type === 'output') {
                $handler_info = $this->handler_registry->get_output_handler($handler_slug);
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

            // Instantiate based on the specific handler slug/class
            switch ($handler_slug) {
                // --- Input Handlers ---
                case 'files':
                    return new Files($this->db_modules, $this->db_projects, $this->processed_items_manager, $this->logger);
                case 'airdrop_rest_api':
                    return new AirdropRestApi($this->db_modules, $this->db_projects, $this->processed_items_manager, $this->db_remote_locations, $this->handler_http_service, $this->logger);
                case 'public_rest_api':
                    return new PublicRestApi($this->db_modules, $this->db_projects, $this->processed_items_manager, $this->handler_http_service, $this->logger);
                case 'reddit':
                    return new Reddit($this->db_modules, $this->db_projects, $this->processed_items_manager, $this->oauth_reddit, $this->handler_http_service, $this->logger);
                case 'rss':
                    return new Rss($this->db_modules, $this->db_projects, $this->processed_items_manager, $this->handler_http_service, $this->logger);

                // --- Output Handlers ---
                case 'publish_remote':
                    return new PublishRemote($this->db_remote_locations, $this->handler_http_service, $this->logger);
                case 'bluesky':
                    return new Bluesky($this->encryption_helper, $this->handler_http_service, $this->logger);
                case 'twitter':
                    return new Twitter($this->oauth_twitter, $this->logger);
                case 'data_export':
                    return new DataExport(); // No dependencies
                case 'publish_local':
                     return new PublishLocal($this->logger);
                case 'threads':
                     return new Threads($this->handler_http_service, $this->logger);
                case 'facebook':
                     return new Facebook($this->handler_http_service, $this->logger);

                // Default case for unhandled or new handlers
                default:
                    if ($this->logger) $this->logger->warning('Attempting to create handler with unknown slug using generic instantiation (may fail).', ['type' => $handler_type, 'slug' => $handler_slug, 'class' => $class_name]);
                    // Attempt generic instantiation if possible, might fail if constructor needs args
                    // This case should ideally not be hit if all handlers are mapped above.
                    if (class_exists($class_name)) {
                        // Check constructor reflection if needed, but for now, assume no args or log error
                        $reflection = new ReflectionClass($class_name);
                        if ($reflection->getConstructor() === null || $reflection->getConstructor()->getNumberOfRequiredParameters() === 0) {
                            return new $class_name();
                        } else {
                             if ($this->logger) $this->logger->error('Cannot instantiate handler: Unknown slug with constructor requiring arguments.', ['type' => $handler_type, 'slug' => $handler_slug, 'class' => $class_name]);
                             return new \WP_Error('unhandled_handler_with_args', 'Cannot instantiate handler: Unknown slug with constructor requiring arguments.', ['slug' => $handler_slug]);
                        }
                    } else {
                        // Should have been caught earlier, but final check
                        return new \WP_Error('handler_class_still_not_found', 'Handler class not found.', ['class' => $class_name]);
                    }
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

} 