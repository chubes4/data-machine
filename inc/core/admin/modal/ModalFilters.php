<?php
/**
 * Modal System Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as the Modal System's pure infrastructure - it provides ZERO
 * hardcoded component knowledge. Individual components register their own modal
 * capabilities in their own *Filters.php files following true modular architecture.
 * 
 * The modal system is a Universal HTML Popup Component that serves any component's
 * self-generated content via the dm_get_modal_content filter.
 * 
 * @package DataMachine
 * @subpackage Core\Admin\Modal
 * @since 0.1.0
 */

namespace DataMachine\Core\Admin\Modal;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Modal System infrastructure filters
 * 
 * Pure infrastructure implementation with ZERO component knowledge.
 * Components register their own modal capabilities in their own *Filters.php files.
 * 
 * This achieves true "plugins within plugins" architecture where the modal system
 * provides only the infrastructure and components provide their own modal content.
 * 
 * @since 0.1.0
 */
function dm_register_modal_system_filters() {
    
    // Register core modal assets globally for any page that needs modals
    add_filter('dm_get_page_assets', function($assets, $page_slug) {
        // Pages that use the universal modal system
        $modal_pages = ['pipelines', 'jobs', 'logs', 'settings'];
        
        if (in_array($page_slug, $modal_pages)) {
            // Add core modal assets with high priority (load before page-specific assets)
            $assets['css']['dm-core-modal'] = [
                'file' => 'inc/core/admin/modal/assets/css/core-modal.css',
                'deps' => [],
                'media' => 'all'
            ];
            
            $assets['js']['dm-core-modal'] = [
                'file' => 'inc/core/admin/modal/assets/js/core-modal.js',
                'deps' => ['jquery'],
                'in_footer' => true,
                'localize' => [
                    'object' => 'dmCoreModal',
                    'data' => [
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'get_modal_content_nonce' => wp_create_nonce('dm_get_modal_content'),
                        'strings' => [
                            'loading' => __('Loading...', 'data-machine'),
                            'error' => __('Error', 'data-machine'),
                            'close' => __('Close', 'data-machine')
                        ]
                    ]
                ]
            ];
        }
        
        return $assets;
    }, 5, 2); // Priority 5 = loads before page-specific assets (priority 10)
    
    // Include modal template on pages that use the universal modal system
    add_action('admin_footer', function() {
        $current_screen = get_current_screen();
        if (!$current_screen) return;
        
        // Only include modal template on Data Machine admin pages that use modals
        $modal_pages = ['data-machine_page_dm-pipelines', 'data-machine_page_dm-jobs', 'data-machine_page_dm-logs', 'data-machine_page_dm-settings'];
        
        if (in_array($current_screen->id, $modal_pages)) {
            include __DIR__ . '/ModalTemplate.php';
        }
    });
    
    // Register universal AJAX handler for all modal content requests
    // Uses pure filter-based system with template parameter matching
    $modal_ajax_handler = new ModalAjax();
    
    // Pure infrastructure - NO component-specific logic
    // Individual components will register their own modal content generators
    // in their own *Filters.php files using the dm_get_modal_content filter
    
    // Examples of how components will register themselves:
    //
    // TwitterFilters.php:
    // add_filter('dm_get_modal_content', function($content, $template) {
    //     if ($template === 'twitter_handler_config') {
    //         return $this->generate_twitter_modal_content();
    //     }
    //     return $content;
    // }, 10, 2);
    //
    // AIStepFilters.php:
    // add_filter('dm_get_modal_content', function($content, $template) {
    //     if ($template === 'ai_step_config') {
    //         return $this->generate_ai_modal_content();
    //     }
    //     return $content;
    // }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_modal_system_filters();