<?php
/**
 * Core Handler Registry
 * 
 * Implements pure direct filter registration system for complete independence between
 * input/output handlers and pipeline step management.
 * 
 * Direct Filter System:
 * - dm_register_input_handlers: Independent input handler registration
 * - dm_register_output_handlers: Independent output handler registration
 * 
 * External Plugin Example:
 * add_filter('dm_register_input_handlers', function($handlers) {
 *     $handlers['custom_input'] = [
 *         'class' => 'MyPlugin\CustomInputHandler',
 *         'label' => 'Custom Input Source'
 *     ];
 *     return $handlers;
 * });
 * 
 * add_filter('dm_register_output_handlers', function($handlers) {
 *     $handlers['custom_output'] = [
 *         'class' => 'MyPlugin\CustomOutputHandler', 
 *         'label' => 'Custom Output Destination'
 *     ];
 *     return $handlers;
 * });
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes
 * @since      0.1.0
 */

namespace DataMachine\Core;

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
                'wordpress' => 'DataMachine\\Core\\Handlers\\Input\\WordPress',
                'rss' => 'DataMachine\\Handlers\\Input\\Rss',
                'reddit' => 'DataMachine\\Handlers\\Input\\Reddit',
            ],
            'output' => [
                'wordpress' => 'DataMachine\\Core\\Handlers\\Output\\WordPress',
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
     * Register core input handlers via direct filter.
     * 
     * @param array $input_handlers Existing input handlers array from filter
     * @return array Updated input handlers array with core handlers
     */
    public static function register_core_input_handlers(array $input_handlers): array {
        $input_handlers['files'] = [
            'class' => 'DataMachine\\Handlers\\Input\\Files',
            'label' => __('File Upload', 'data-machine')
        ];
        $input_handlers['wordpress'] = [
            'class' => 'DataMachine\\Core\\Handlers\\Input\\WordPress',
            'label' => __('WordPress', 'data-machine')
        ];
        $input_handlers['rss'] = [
            'class' => 'DataMachine\\Handlers\\Input\\Rss',
            'label' => 'RSS Feed'
        ];
        $input_handlers['reddit'] = [
            'class' => 'DataMachine\\Handlers\\Input\\Reddit',
            'label' => 'Reddit Subreddit'
        ];
        
        return $input_handlers;
    }

    /**
     * Register core output handlers via direct filter.
     * 
     * @param array $output_handlers Existing output handlers array from filter
     * @return array Updated output handlers array with core handlers
     */
    public static function register_core_output_handlers(array $output_handlers): array {
        $output_handlers['wordpress'] = [
            'class' => 'DataMachine\\Core\\Handlers\\Output\\WordPress',
            'label' => __('WordPress', 'data-machine')
        ];
        $output_handlers['twitter'] = [
            'class' => 'DataMachine\\Handlers\\Output\\Twitter',
            'label' => __('Post to Twitter', 'data-machine')
        ];
        $output_handlers['facebook'] = [
            'class' => 'DataMachine\\Handlers\\Output\\Facebook',
            'label' => __('Facebook', 'data-machine')
        ];
        $output_handlers['threads'] = [
            'class' => 'DataMachine\\Handlers\\Output\\Threads',
            'label' => __('Threads', 'data-machine')
        ];
        $output_handlers['bluesky'] = [
            'class' => 'DataMachine\\Handlers\\Output\\Bluesky',
            'label' => __('Post to Bluesky', 'data-machine')
        ];
        
        return $output_handlers;
    }


    /**
     * Initialize the core handler registration system.
     * Called during plugin bootstrap to register hooks.
     */
    public static function init(): void {
        // Register core handlers using direct filter system for complete independence
        add_filter('dm_register_input_handlers', [self::class, 'register_core_input_handlers'], 5);
        add_filter('dm_register_output_handlers', [self::class, 'register_core_output_handlers'], 5);
        
        
        // Register core handler settings fields
        add_filter('dm_handler_settings_fields', [self::class, 'register_core_settings_fields'], 10, 4);
        
        // No template registration needed - forms are generated programmatically from field definitions
    }
}