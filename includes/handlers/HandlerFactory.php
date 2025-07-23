<?php
/**
 * Handler Factory implementation using Dependency Injection.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/module-config
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Ensure required classes are loaded (especially if this file is included early)
require_once DATA_MACHINE_PATH . 'includes/handlers/class-handler-registry.php';
require_once DATA_MACHINE_PATH . 'includes/helpers/class-data-machine-logger.php';
require_once DATA_MACHINE_PATH . 'includes/database/class-database-processed-items.php';
require_once DATA_MACHINE_PATH . 'includes/helpers/class-data-machine-encryption-helper.php';
require_once DATA_MACHINE_PATH . 'admin/oauth/class-data-machine-oauth-twitter.php';
require_once DATA_MACHINE_PATH . 'admin/oauth/class-data-machine-oauth-reddit.php';
require_once DATA_MACHINE_PATH . 'includes/database/class-database-remote-locations.php';
require_once DATA_MACHINE_PATH . 'includes/database/class-database-modules.php';
require_once DATA_MACHINE_PATH . 'includes/database/class-database-projects.php';

class Dependency_Injection_Handler_Factory {

    private $handler_registry;
    private $logger;
    private $db_processed_items;
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
        Data_Machine_Handler_Registry $handler_registry,
        ?Data_Machine_Logger $logger, // Logger is optional
        Data_Machine_Database_Processed_Items $db_processed_items,
        Data_Machine_Encryption_Helper $encryption_helper,
        Data_Machine_OAuth_Twitter $oauth_twitter,
        Data_Machine_OAuth_Reddit $oauth_reddit,
        Data_Machine_OAuth_Threads $oauth_threads, // Added
        Data_Machine_OAuth_Facebook $oauth_facebook, // Added
        Data_Machine_Database_Remote_Locations $db_remote_locations,
        Data_Machine_Database_Modules $db_modules,
        Data_Machine_Database_Projects $db_projects,
        Data_Machine_Handler_HTTP_Service $handler_http_service
    ) {
        $this->handler_registry = $handler_registry;
        $this->logger = $logger;
        $this->db_processed_items = $db_processed_items;
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
                return new WP_Error('invalid_handler_type', 'Invalid handler type specified.', ['type' => $handler_type]);
            }

            if (!$handler_info || !isset($handler_info['class'])) {
                if ($this->logger) $this->logger->error('Handler class not found in registry.', ['type' => $handler_type, 'slug' => $handler_slug]);
                return new WP_Error('handler_not_found', 'Handler class not found in registry.', ['type' => $handler_type, 'slug' => $handler_slug]);
            }

            $class_name = $handler_info['class'];

            // Ensure the class file is loaded (Registry should handle this, but double-check)
            if (!class_exists($class_name)) {
                 // Attempt to load based on convention if registry didn't
                 $expected_path = DATA_MACHINE_PATH . "includes/{$handler_type}/class-data-machine-{$handler_type}-" . str_replace('_', '-', $handler_slug) . '.php';
                 if (file_exists($expected_path)) {
                      require_once $expected_path;
                 }

                 // If still not found, log and return error
                 if (!class_exists($class_name)) {
                    if ($this->logger) $this->logger->error('Handler class file could not be loaded.', ['type' => $handler_type, 'slug' => $handler_slug, 'class' => $class_name, 'path' => $expected_path]);
                    return new WP_Error('handler_class_not_loadable', 'Handler class file could not be loaded.', ['class' => $class_name]);
                 }
            }

            // Instantiate based on the specific handler slug/class
            switch ($handler_slug) {
                // --- Input Handlers ---
                case 'files':
                    return new Data_Machine_Input_Files($this->db_modules, $this->db_projects, $this->db_processed_items, $this->logger);
                case 'airdrop_rest_api':
                    return new Data_Machine_Input_Airdrop_Rest_Api($this->db_modules, $this->db_projects, $this->db_processed_items, $this->db_remote_locations, $this->handler_http_service, $this->logger);
                case 'public_rest_api':
                    return new Data_Machine_Input_Public_Rest_Api($this->db_modules, $this->db_projects, $this->db_processed_items, $this->handler_http_service, $this->logger);
                case 'reddit':
                    return new Data_Machine_Input_Reddit($this->db_modules, $this->db_projects, $this->db_processed_items, $this->oauth_reddit, $this->handler_http_service, $this->logger);
                case 'rss':
                    return new Data_Machine_Input_Rss($this->db_modules, $this->db_projects, $this->db_processed_items, $this->handler_http_service, $this->logger);

                // --- Output Handlers ---
                case 'publish_remote':
                    return new Data_Machine_Output_Publish_Remote($this->db_remote_locations, $this->handler_http_service, $this->logger, $this->db_processed_items);
                case 'bluesky':
                    return new Data_Machine_Output_Bluesky($this->encryption_helper, $this->handler_http_service, $this->logger);
                case 'twitter':
                    return new Data_Machine_Output_Twitter($this->oauth_twitter, $this->logger);
                case 'data_export':
                    return new Data_Machine_Output_Data_Export(); // No dependencies
                case 'publish_local':
                     return new Data_Machine_Output_Publish_Local($this->db_processed_items, $this->logger);
                case 'threads':
                     // Ensure the class file is loaded (should be handled by registry/autoload)
                     if (!class_exists('Data_Machine_Output_Threads')) require_once DATA_MACHINE_PATH . 'includes/handlers/output/class-data-machine-output-threads.php';
                     return new Data_Machine_Output_Threads($this->handler_http_service, $this->logger);
                case 'facebook':
                     // Ensure the class file is loaded (should be handled by registry/autoload)
                     if (!class_exists('Data_Machine_Output_Facebook')) require_once DATA_MACHINE_PATH . 'includes/handlers/output/class-data-machine-output-facebook.php';
                     return new Data_Machine_Output_Facebook($this->handler_http_service, $this->logger);

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
                             return new WP_Error('unhandled_handler_with_args', 'Cannot instantiate handler: Unknown slug with constructor requiring arguments.', ['slug' => $handler_slug]);
                        }
                    } else {
                        // Should have been caught earlier, but final check
                        return new WP_Error('handler_class_still_not_found', 'Handler class not found.', ['class' => $class_name]);
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
            return new WP_Error('handler_creation_exception', 'An exception occurred during handler creation: ' . $e->getMessage());
        } catch (\Error $e) { // Catch fatal errors too
             if ($this->logger) $this->logger->critical('Fatal error during handler creation.', [
                 'type' => $handler_type, 
                 'slug' => $handler_slug, 
                 'class' => $class_name, 
                 'error_message' => $e->getMessage(),
                 'error_trace' => $e->getTraceAsString() 
             ]);
             return new WP_Error('handler_creation_error', 'A critical error occurred during handler creation: ' . $e->getMessage());
        }
    }

} 