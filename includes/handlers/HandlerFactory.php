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
use DataMachine\Helpers\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class HandlerFactory {

    /**
     * Simple constructor with no manual dependency injection.
     * Handlers get dependencies via service locator when needed.
     */
    public function __construct() {
        // No manual dependency injection needed
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
        global $data_machine_container;
        
        try {
            // Get handler info from registration system
            if ($handler_type === 'input') {
                $handler_info = Constants::get_input_handler($handler_slug);
            } elseif ($handler_type === 'output') {
                $handler_info = Constants::get_output_handler($handler_slug);
            } else {
                $this->log_error('Invalid handler type requested.', ['type' => $handler_type, 'slug' => $handler_slug]);
                return new \WP_Error('invalid_handler_type', 'Invalid handler type specified.', ['type' => $handler_type]);
            }

            if (!$handler_info || !isset($handler_info['class'])) {
                $this->log_error('Handler class not found in registry.', ['type' => $handler_type, 'slug' => $handler_slug]);
                return new \WP_Error('handler_not_found', 'Handler class not found in registry.', ['type' => $handler_type, 'slug' => $handler_slug]);
            }

            $class_name = $handler_info['class'];

            // Ensure the class exists (PSR-4 autoloading)
            if (!class_exists($class_name)) {
                $this->log_error('Handler class not found - check namespace and autoloader.', ['type' => $handler_type, 'slug' => $handler_slug, 'class' => $class_name]);
                return new \WP_Error('handler_class_not_found', 'Handler class not found - check namespace and autoloader.', ['class' => $class_name]);
            }

            // Simple instantiation - handlers get dependencies via service locator or defaults
            $this->log_debug('Creating handler via PSR-4 autoloading.', [
                'type' => $handler_type, 
                'slug' => $handler_slug, 
                'class' => $class_name
            ]);
            
            return new $class_name();
            
        } catch (\Exception $e) {
            $this->log_error('Exception during handler creation.', [
                'type' => $handler_type, 
                'slug' => $handler_slug, 
                'class' => $class_name ?? 'unknown',
                'exception_message' => $e->getMessage()
            ]);
            return new \WP_Error('handler_creation_exception', 'An exception occurred during handler creation: ' . $e->getMessage());
        }
    }

    /**
     * Helper method for logging errors.
     */
    private function log_error(string $message, array $context = []): void {
        global $data_machine_container;
        if (isset($data_machine_container['logger'])) {
            $data_machine_container['logger']->error($message, $context);
        } elseif (WP_DEBUG) {
            error_log('Data Machine HandlerFactory Error: ' . $message . ' ' . wp_json_encode($context));
        }
    }

    /**
     * Helper method for logging debug messages.
     */
    private function log_debug(string $message, array $context = []): void {
        global $data_machine_container;
        if (isset($data_machine_container['logger'])) {
            $data_machine_container['logger']->debug($message, $context);
        } elseif (WP_DEBUG) {
            error_log('Data Machine HandlerFactory Debug: ' . $message . ' ' . wp_json_encode($context));
        }
    }

    /**
     * Register an external handler for third-party plugin support.
     * Note: External handlers should use the dm_register_handlers filter instead.
     *
     * @param string $type Handler type ('input' or 'output')
     * @param string $slug Handler slug
     * @param string $class_name Fully qualified class name
     */
    public function register_external_handler(string $type, string $slug, string $class_name): void {
        $this->log_debug('External handler registration requested (use dm_register_handlers filter instead)', [
            'type' => $type,
            'slug' => $slug, 
            'class' => $class_name
        ]);
    }

} 