<?php
/**
 * Core Handler Settings Field Registry
 * 
 * Provides settings field registration for core handlers via dm_handler_settings_fields filter.
 * Core handler registration is now done explicitly via bootstrap filter system.
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
     * Auto-register settings fields for core handlers.
     * 
     * @param array $fields Existing fields array from filter
     * @param string $handler_type 'input' or 'output'
     * @param string $handler_slug Handler slug
     * @param array $current_config Current configuration
     * @return array Updated fields array
     */
    public static function register_core_settings_fields(array $fields, string $handler_type, string $handler_slug, array $current_config): array {
        // Define core handler mappings
        $core_handlers = [
            'input' => [
                'files' => 'DataMachine\\Handlers\\Input\\Files',
                'local_word_press' => 'DataMachine\\Handlers\\Input\\LocalWordPress',
                'airdrop_rest_api' => 'DataMachine\\Handlers\\Input\\AirdropRestApi',
                'public_rest_api' => 'DataMachine\\Handlers\\Input\\PublicRestApi',
                'rss' => 'DataMachine\\Handlers\\Input\\Rss',
                'reddit' => 'DataMachine\\Handlers\\Input\\Reddit',
            ],
            'output' => [
                'publish_local' => 'DataMachine\\Handlers\\Output\\PublishLocal',
                'publish_remote' => 'DataMachine\\Handlers\\Output\\PublishRemote',
                'twitter' => 'DataMachine\\Handlers\\Output\\Twitter',
                'facebook' => 'DataMachine\\Handlers\\Output\\Facebook',
                'threads' => 'DataMachine\\Handlers\\Output\\Threads',
                'bluesky' => 'DataMachine\\Handlers\\Output\\Bluesky',
            ]
        ];

        // Only handle core handlers
        if (!isset($core_handlers[$handler_type][$handler_slug])) {
            return $fields;
        }

        $handler_class = $core_handlers[$handler_type][$handler_slug];
        if (class_exists($handler_class) && method_exists($handler_class, 'get_settings_fields')) {
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
     * Initialize the core handler settings field registration system.
     * Called during plugin bootstrap to register hooks.
     * Note: Handler registration is now done explicitly via bootstrap filter.
     */
    public static function init(): void {
        // Register core handler settings fields
        add_filter('dm_handler_settings_fields', [self::class, 'register_core_settings_fields'], 10, 4);
        
        // No template registration needed - forms are generated programmatically from field definitions
    }
}