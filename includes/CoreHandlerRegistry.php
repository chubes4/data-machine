<?php
/**
 * Core Handler Auto-Registration System
 * 
 * Automatically registers core handlers via the same dm_register_handlers filter
 * that external plugins use, ensuring true "eating our own dog food" approach.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes
 * @since      0.1.0
 */

namespace DataMachine;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class CoreHandlerRegistry {

    /**
     * Auto-register all core handlers by scanning handler directories.
     * This ensures core handlers use the same registration system as external handlers.
     * 
     * @param array $handlers Existing handlers array from filter
     * @return array Updated handlers array with core handlers registered
     */
    public static function register_core_handlers(array $handlers): array {
        // Input handlers
        $input_handlers = self::discover_handlers('input');
        foreach ($input_handlers as $slug => $handler_data) {
            $handlers['input'][$slug] = $handler_data;
        }

        // Output handlers  
        $output_handlers = self::discover_handlers('output');
        foreach ($output_handlers as $slug => $handler_data) {
            $handlers['output'][$slug] = $handler_data;
        }

        return $handlers;
    }

    /**
     * Auto-register settings fields for core handlers.
     * 
     * @param array $fields Existing fields array from filter
     * @param string $handler_type 'input' or 'output'
     * @param string $handler_slug Handler slug
     * @param array $current_config Current configuration
     * @return array Updated fields array
     */
    public static function register_core_settings_fields(array $fields, string $handler_type, string $handler_slug, array $current_config): array {
        // Only handle core handlers
        if (!self::is_core_handler($handler_type, $handler_slug)) {
            return $fields;
        }

        $handler_class = self::get_handler_class($handler_type, $handler_slug);
        if ($handler_class && method_exists($handler_class, 'get_settings_fields')) {
            try {
                $fields = $handler_class::get_settings_fields($current_config);
            } catch (\Exception $e) {
                // Log error but don't break the system
                error_log("Data Machine: Error getting settings fields for {$handler_type}:{$handler_slug} - " . $e->getMessage());
            }
        }

        return $fields;
    }


    /**
     * Discover handlers by scanning the handler directories.
     * 
     * @param string $type 'input' or 'output'
     * @return array Array of discovered handlers
     */
    private static function discover_handlers(string $type): array {
        $handlers = [];
        $handler_dir = DATA_MACHINE_PATH . "includes/handlers/{$type}/";
        
        if (!is_dir($handler_dir)) {
            return $handlers;
        }

        $files = glob($handler_dir . '*.php');
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            
            // Skip base classes
            if (strpos($filename, 'Base') === 0) {
                continue;
            }

            $class_name = "DataMachine\\Handlers\\" . ucfirst($type) . "\\{$filename}";
            
            // Verify class exists and has required methods
            if (class_exists($class_name) && method_exists($class_name, 'get_label')) {
                $slug = self::class_name_to_slug($filename);
                $handlers[$slug] = [
                    'class' => $class_name,
                    'label' => $class_name::get_label()
                ];
            }
        }

        return $handlers;
    }

    /**
     * Convert PascalCase class name to snake_case slug.
     * 
     * @param string $class_name Class name in PascalCase
     * @return string Slug in snake_case
     */
    private static function class_name_to_slug(string $class_name): string {
        // Convert PascalCase to snake_case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class_name));
    }

    /**
     * Get handler class name from type and slug.
     * 
     * @param string $handler_type 'input' or 'output'
     * @param string $handler_slug Handler slug
     * @return string|null Class name or null if not found
     */
    private static function get_handler_class(string $handler_type, string $handler_slug): ?string {
        // Convert slug back to PascalCase
        $class_name = str_replace('_', '', ucwords($handler_slug, '_'));
        $full_class_name = "DataMachine\\Handlers\\" . ucfirst($handler_type) . "\\{$class_name}";
        
        return class_exists($full_class_name) ? $full_class_name : null;
    }

    /**
     * Check if a handler is a core handler.
     * 
     * @param string $handler_type 'input' or 'output'
     * @param string $handler_slug Handler slug
     * @return bool True if core handler
     */
    private static function is_core_handler(string $handler_type, string $handler_slug): bool {
        $core_handlers = self::discover_handlers($handler_type);
        return isset($core_handlers[$handler_slug]);
    }


    /**
     * Initialize the core handler registry system.
     * Called during plugin bootstrap to register hooks.
     */
    public static function init(): void {
        // Register core handlers via the same filter external plugins use
        add_filter('dm_register_handlers', [self::class, 'register_core_handlers'], 10);
        
        // Register core handler settings fields - this is now the ONLY system needed
        add_filter('dm_handler_settings_fields', [self::class, 'register_core_settings_fields'], 10, 4);
        
        // No template registration needed - forms are generated programmatically from field definitions
    }
}