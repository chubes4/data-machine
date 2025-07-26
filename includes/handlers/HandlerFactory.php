<?php
/**
 * Handler Factory implementation using PSR-4 autoloading and service locator pattern.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/module-config
 * @since      NEXT_VERSION
 */

namespace DataMachine\Handlers;

use DataMachine\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class HandlerFactory {

    /**
     * Constructor using pure filter-based architecture.
     * All services accessed via filters for maximum extensibility.
     */
    public function __construct() {
        // Parameter-less constructor - all services accessed via filters
    }

    /**
     * Create a handler instance based on type and slug.
     * Uses PSR-4 autoloading and service locator pattern.
     *
     * @param string $handler_type The type of handler ('input' or 'output').
     * @param string $handler_slug The slug of the handler.
     * @return object|WP_Error The handler instance or WP_Error on failure.
     */
    public function create_handler(string $handler_type, string $handler_slug) {
        try {
            // Get handler info from registration system
            $logger = apply_filters('dm_get_service', null, 'logger');
            
            if ($handler_type === 'input') {
                $handler_info = Constants::get_input_handler($handler_slug);
            } elseif ($handler_type === 'output') {
                $handler_info = Constants::get_output_handler($handler_slug);
            } else {
                if ($logger) {
                    $logger->error('Invalid handler type requested.', ['type' => $handler_type, 'slug' => $handler_slug]);
                }
                return new \WP_Error('invalid_handler_type', 'Invalid handler type specified.', ['type' => $handler_type]);
            }

            if (!$handler_info || !isset($handler_info['class'])) {
                if ($logger) {
                    $logger->error('Handler class not found in registry.', ['type' => $handler_type, 'slug' => $handler_slug]);
                }
                return new \WP_Error('handler_not_found', 'Handler class not found in registry.', ['type' => $handler_type, 'slug' => $handler_slug]);
            }

            $class_name = $handler_info['class'];

            // Ensure the class exists (PSR-4 autoloading)
            if (!class_exists($class_name)) {
                if ($logger) {
                    $logger->error('Handler class not found - check namespace and autoloader.', ['type' => $handler_type, 'slug' => $handler_slug, 'class' => $class_name]);
                }
                return new \WP_Error('handler_class_not_found', 'Handler class not found - check namespace and autoloader.', ['class' => $class_name]);
            }

            // Create handler with pure filter-based instantiation
            // Handlers use filter-based service access internally via apply_filters('dm_get_service', ...)
            if ($logger) {
                $logger->debug('Creating handler with filter-based services.', [
                    'type' => $handler_type, 
                    'slug' => $handler_slug, 
                    'class' => $class_name
                ]);
            }
            
            // Pure filter-based architecture: handlers access services themselves
            return new $class_name();
            
        } catch (\Exception $e) {
            if ($logger) {
                $logger->error('Exception during handler creation.', [
                    'type' => $handler_type, 
                    'slug' => $handler_slug, 
                    'class' => $class_name ?? 'unknown',
                    'exception_message' => $e->getMessage()
                ]);
            }
            return new \WP_Error('handler_creation_exception', 'An exception occurred during handler creation: ' . $e->getMessage());
        }
    }

    /**
     * Get all registered input handlers.
     * 
     * @return array Array of input handlers from filter system.
     */
    public function get_input_handlers(): array {
        return Constants::get_input_handlers();
    }

    /**
     * Get all registered output handlers.
     * 
     * @return array Array of output handlers from filter system.
     */
    public function get_output_handlers(): array {
        return Constants::get_output_handlers();
    }

} 