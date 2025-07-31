<?php
/**
 * WordPress Input Handler Component Filter Registration
 * 
 * Revolutionary "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as WordPress Input Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Input\WordPress
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Input\WordPress;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all WordPress Input Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers WordPress Input Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_wordpress_input_filters() {
    
    // Handler registration - WordPress declares itself as input handler
    add_filter('dm_get_handlers', function($handlers, $type) {
        if ($type === 'input') {
            $handlers['wordpress'] = [
                'class' => WordPress::class,
                'label' => __('WordPress', 'data-machine'),
                'description' => __('Source content from WordPress posts and pages', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);
    
    // Authentication registration - parameter-matched to 'wordpress' handler
    add_filter('dm_get_auth', function($auth, $handler_slug) {
        if ($handler_slug === 'wordpress') {
            return new WordPressAuth();
        }
        return $auth;
    }, 10, 2);
    
    // Settings registration - parameter-matched to 'wordpress' handler
    add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
        if ($handler_slug === 'wordpress') {
            return new WordPressSettings();
        }
        return $settings;
    }, 10, 2);
    
    // DataPacket conversion registration - parameter-based self-registration
    add_filter('dm_create_datapacket', function($datapacket, $source_data, $source_type, $context) {
        if ($source_type === 'wordpress') {
            return \DataMachine\Engine\DataPacket::fromWordPress($source_data, $context);
        }
        return $datapacket;
    }, 10, 4);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_input_filters();