<?php
/**
 * Data Machine Admin System - Filter Discovery Hub
 *
 * DEVELOPER OVERVIEW: This file serves as the central registry and documentation
 * for ALL admin-related filters in Data Machine. Developers can quickly see
 * how to extend the admin interface and understand the plugin's architecture.
 *
 * ARCHITECTURAL PURPOSE: Separates admin/UI logic from backend processing logic.
 * All frontend rendering, template systems, modal management, and admin page
 * registration filters are centralized here for developer discoverability.
 *
 * EXTENSION PATTERNS: External plugins can extend Data Machine's admin interface
 * by registering to the same filters shown below. All filters use discovery-based
 * architecture with low-priority bootstrap for component self-registration.
 *
 * @package DataMachine
 * @subpackage Engine\Filters
 * @since 0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register all admin-related filters for Data Machine
 * 
 * DEVELOPER OVERVIEW: This function provides bootstrap infrastructure for all
 * admin extension points. Each filter includes usage examples and extension patterns.
 * 
 * FILTER CATEGORIES:
 * - Admin Page System: Page registration and discovery
 * - Modal System: Modal registration and content management
 * - Template System: Template rendering and context resolution
 * - Asset Management: Admin-specific CSS/JS loading
 * 
 * @since 0.1.0
 */
function dm_register_admin_filters() {
    
    // ========================================================================
    // ADMIN PAGE SYSTEM
    // ========================================================================
    
    /**
     * Admin Pages Discovery System
     * 
     * Pure discovery filter where components self-register admin pages.
     * 
     * USAGE:
     * $all_pages = apply_filters('dm_admin_pages', []);
     * $pipelines_page = $all_pages['pipelines'] ?? null;
     * 
     * EXTENSION EXAMPLE:
     * add_filter('dm_admin_pages', function($pages) {
     *     $pages['my_custom_page'] = [
     *         'page_title' => __('My Custom Page', 'my-plugin'),
     *         'menu_title' => __('Custom', 'my-plugin'), 
     *         'capability' => 'manage_options',
     *         'position' => 25,
     *         'templates' => __DIR__ . '/templates/',
     *         'assets' => [
     *             'css' => [...],
     *             'js' => [...]
     *         ],
     *         'ajax_handlers' => [
     *             'modal' => new MyModalAjax(),
     *             'page' => new MyPageAjax()
     *         ]
     *     ];
     *     return $pages;
     * });
     */
    add_filter('dm_admin_pages', function($pages) {
        // Components self-register via this same filter with higher priority
        return $pages;
    }, 5, 1);
    
    // ========================================================================
    // MODAL SYSTEM
    // ========================================================================
    
    /**
     * Modal Content Discovery System
     * 
     * Pure discovery filter where components self-register modal content.
     * 
     * USAGE:
     * $all_modals = apply_filters('dm_modals', []);
     * $step_selection_modal = $all_modals['step-selection'] ?? null;
     * 
     * EXTENSION EXAMPLE:
     * add_filter('dm_modals', function($modals) {
     *     $modals['my-custom-modal'] = [
     *         'template' => 'modal/my-custom-content',
     *         'title' => __('My Custom Modal', 'my-plugin'),
     *         'dynamic_template' => false // or true for handler-specific templates
     *     ];
     *     return $modals;
     * });
     */
    add_filter('dm_modals', function($modals) {
        // Components self-register via this same filter with higher priority
        // Bootstrap provides discovery infrastructure for ModalAjax
        return $modals;
    }, 5, 1);
    
    // ========================================================================
    // TEMPLATE SYSTEM  
    // ========================================================================
    
    
    /**
     * Universal Template Rendering System
     * 
     * Central template rendering filter providing template discovery across all admin pages.
     * 
     * USAGE:
     * $content = apply_filters('dm_render_template', '', 'page/my-template', $data);
     * 
     * PHASES:
     * 1. Template Discovery - Searches all registered admin page template directories
     * 2. Fallback Search - Checks core modal templates directory
     * 
     * EXTENSION: Templates automatically discovered from admin page registrations
     */
    add_filter('dm_render_template', function($content, $template_name, $data = []) {
        // Template discovery and rendering
        // Dynamic discovery of all registered admin pages and their template directories
        $all_pages = apply_filters('dm_admin_pages', []);
        
        foreach ($all_pages as $slug => $page_config) {
            if (!empty($page_config['templates'])) {
                $template_path = $page_config['templates'] . $template_name . '.php';
                if (file_exists($template_path)) {
                    // Extract data variables for template use
                    extract($data);
                    ob_start();
                    include $template_path;
                    return ob_get_clean();
                }
            }
        }
        
        // Fallback: Search core modal templates directory
        // Handle modal/ prefix in template names correctly
        if (strpos($template_name, 'modal/') === 0) {
            $modal_template_name = substr($template_name, 6); // Remove 'modal/' prefix
            $core_modal_template_path = DATA_MACHINE_PATH . 'inc/core/admin/modal/templates/' . $modal_template_name . '.php';
        } else {
            $core_modal_template_path = DATA_MACHINE_PATH . 'inc/core/admin/modal/templates/' . $template_name . '.php';
        }
        
        if (file_exists($core_modal_template_path)) {
            // Extract data variables for template use
            extract($data);
            ob_start();
            include $core_modal_template_path;
            return ob_get_clean();
        }
        
        // Log error and return empty string - no user-facing error display
        do_action('dm_log', 'error', "Template not found: {$template_name}");
        return '';
    }, 10, 3);
    
    // WordPress-native admin hook registration  
    add_action('admin_menu', 'dm_register_admin_menu');
    add_action('admin_enqueue_scripts', 'dm_enqueue_admin_assets');
}


// ========================================================================
// ID EXTRACTION UTILITIES
// ========================================================================

/**
 * Split flow_step_id into individual components
 * 
 * Universal filter for extracting individual IDs from composite flow_step_id.
 * Follows the format: {pipeline_step_id}_{flow_id}
 * 
 * USAGE:
 * $parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);
 * $pipeline_step_id = $parts['pipeline_step_id'];
 * $flow_id = $parts['flow_id'];
 * 
 * @param null $null Not used - filter pattern consistency
 * @param string $flow_step_id Composite ID in format {pipeline_step_id}_{flow_id}
 * @return array|null Array with pipeline_step_id and flow_id, or null if invalid format
 */
add_filter('dm_split_flow_step_id', function($null, $flow_step_id) {
    if (empty($flow_step_id) || !is_string($flow_step_id)) {
        return null;
    }
    
    // Split on last underscore to handle UUIDs with dashes
    $last_underscore_pos = strrpos($flow_step_id, '_');
    if ($last_underscore_pos === false) {
        return null;
    }
    
    $pipeline_step_id = substr($flow_step_id, 0, $last_underscore_pos);
    $flow_id = substr($flow_step_id, $last_underscore_pos + 1);
    
    // Validate flow_id is numeric
    if (!is_numeric($flow_id)) {
        return null;
    }
    
    return [
        'pipeline_step_id' => $pipeline_step_id,
        'flow_id' => (int)$flow_id
    ];
}, 10, 2);

// ========================================================================
// ADMIN MENU FUNCTIONS
// ========================================================================

/**
 * Register admin pages via dynamic discovery system.
 * 
 * Uses consistent discovery pattern matching dm_steps and dm_handlers.
 * All components self-register via filters with zero hardcoded limitations.
 * Pages are ordered by position parameter with alphabetical fallback.
 *
 * @since NEXT_VERSION
 */
function dm_register_admin_menu() {
    // Discovery mode - get all registered admin pages dynamically
    $registered_pages = apply_filters('dm_admin_pages', []);
    
    // Only create Data Machine menu if pages are available
    if (empty($registered_pages)) {
        return;
    }
    
    // Sort pages by position, then alphabetically by menu_title
    uasort($registered_pages, function($a, $b) {
        $pos_a = $a['position'] ?? 50; // Default position 50
        $pos_b = $b['position'] ?? 50;
        
        if ($pos_a === $pos_b) {
            // Same position - sort alphabetically by menu_title
            $title_a = $a['menu_title'] ?? $a['page_title'] ?? '';
            $title_b = $b['menu_title'] ?? $b['page_title'] ?? '';
            return strcasecmp($title_a, $title_b);
        }
        
        return $pos_a <=> $pos_b;
    });
    
    // Use first sorted page as top-level menu
    $first_page = reset($registered_pages);
    $first_slug = key($registered_pages);
    
    $main_menu_hook = add_menu_page(
        __('Data Machine', 'data-machine'),
        __('Data Machine', 'data-machine'),
        $first_page['capability'] ?? 'manage_options',
        'dm-' . $first_slug,
        '', // No callback - main menu is just container
        'dashicons-database-view',
        30
    );
    
    // Store hook suffix and page config for first page
    dm_store_hook_suffix($first_slug, $main_menu_hook);
    dm_store_page_config($first_slug, $first_page);
    
    // Add first page as submenu with its proper title
    $first_submenu_hook = add_submenu_page(
        'dm-' . $first_slug,
        $first_page['page_title'] ?? $first_page['menu_title'] ?? ucfirst($first_slug),
        $first_page['menu_title'] ?? ucfirst($first_slug),
        $first_page['capability'] ?? 'manage_options',
        'dm-' . $first_slug,
        function() use ($first_page, $first_slug) {
            dm_render_admin_page_content($first_page, $first_slug);
        }
    );
    
    // Add remaining pages as submenus (already sorted)
    $remaining_pages = array_slice($registered_pages, 1, null, true);
    foreach ($remaining_pages as $slug => $page_config) {
        $hook_suffix = add_submenu_page(
            'dm-' . $first_slug,
            $page_config['page_title'] ?? $page_config['menu_title'] ?? ucfirst($slug),
            $page_config['menu_title'] ?? ucfirst($slug),
            $page_config['capability'] ?? 'manage_options',
            'dm-' . $slug,
            function() use ($page_config, $slug) {
                dm_render_admin_page_content($page_config, $slug);
            }
        );
        
        // Store hook suffixes and page config for asset loading
        dm_store_hook_suffix($slug, $hook_suffix);
        dm_store_page_config($slug, $page_config);
    }
}

/**
 * Store hook suffix for dynamic asset loading.
 * 
 * Works with any registered page via parameter-based discovery.
 *
 * @param string $page_slug Page slug
 * @param string $hook_suffix WordPress hook suffix
 */
function dm_store_hook_suffix($page_slug, $hook_suffix) {
    $page_hook_suffixes = get_option('dm_page_hook_suffixes', []);
    $page_hook_suffixes[$page_slug] = $hook_suffix;
    update_option('dm_page_hook_suffixes', $page_hook_suffixes);
}

/**
 * Store page configuration for unified rendering.
 *
 * @param string $page_slug Page slug
 * @param array $page_config Page configuration
 */
function dm_store_page_config($page_slug, $page_config) {
    $page_configs = get_option('dm_page_configs', []);
    $page_configs[$page_slug] = $page_config;
    update_option('dm_page_configs', $page_configs);
}

/**
 * Render admin page content using unified configuration.
 *
 * @param array $page_config Page configuration
 * @param string $page_slug Page slug
 */
function dm_render_admin_page_content($page_config, $page_slug) {
    // Direct template rendering using standardized template name pattern
    $content = apply_filters('dm_render_template', '', "page/{$page_slug}-page", [
        'page_slug' => $page_slug,
        'page_config' => $page_config
    ]);
    
    if (!empty($content)) {
        echo $content;
    } else {
        // Default empty state
        echo '<div class="wrap"><h1>' . esc_html($page_config['page_title'] ?? ucfirst($page_slug)) . '</h1>';
        echo '<p>' . esc_html__('Page content not configured.', 'data-machine') . '</p></div>';
    }
}

/**
 * Enqueue admin assets via dynamic page detection.
 * 
 * Uses parameter-based asset discovery aligned with filter-based architecture.
 *
 * @since NEXT_VERSION
 * @param string $hook_suffix The current admin page hook.
 */
function dm_enqueue_admin_assets( $hook_suffix ) {
    $page_hook_suffixes = get_option('dm_page_hook_suffixes', []);
    
    // Find matching page slug for this hook suffix
    $current_page_slug = null;
    foreach ($page_hook_suffixes as $slug => $stored_hook) {
        if ($stored_hook === $hook_suffix) {
            $current_page_slug = $slug;
            break;
        }
    }
    
    if (!$current_page_slug) {
        return; // No registered page for this hook
    }
    
    $page_configs = get_option('dm_page_configs', []);
    
    // Get page assets from unified configuration
    $page_config = $page_configs[$current_page_slug] ?? [];
    $page_assets = $page_config['assets'] ?? [];
    
    // No fallback - unified system only
    
    if (!empty($page_assets['css']) || !empty($page_assets['js'])) {
        dm_enqueue_page_assets($page_assets, $current_page_slug);
    }
}

/**
 * Enqueue assets for a specific page using parameter-based configuration.
 *
 * @param array  $assets Page asset configuration
 * @param string $page_slug Page slug for context
 */
function dm_enqueue_page_assets($assets, $page_slug) {
    $plugin_base_path = DATA_MACHINE_PATH;
    $plugin_base_url = plugins_url('/', 'data-machine/data-machine.php');
    $version = DATA_MACHINE_VERSION;
    
    // Enqueue CSS files
    if (!empty($assets['css'])) {
        foreach ($assets['css'] as $handle => $css_config) {
            $css_path = $plugin_base_path . $css_config['file'];
            $css_url = $plugin_base_url . $css_config['file'];
            $css_version = file_exists($css_path) ? filemtime($css_path) : $version;
            
            wp_enqueue_style(
                $handle,
                $css_url,
                $css_config['deps'] ?? [],
                $css_version,
                $css_config['media'] ?? 'all'
            );
        }
    }
    
    // Enqueue JS files
    if (!empty($assets['js'])) {
        foreach ($assets['js'] as $handle => $js_config) {
            $js_path = $plugin_base_path . $js_config['file'];
            $js_url = $plugin_base_url . $js_config['file'];
            $js_version = file_exists($js_path) ? filemtime($js_path) : $version;
            
            wp_enqueue_script(
                $handle,
                $js_url,
                $js_config['deps'] ?? ['jquery'],
                $js_version,
                $js_config['in_footer'] ?? true
            );
            
            // Add localization if provided
            if (!empty($js_config['localize'])) {
                wp_localize_script($handle, $js_config['localize']['object'], $js_config['localize']['data']);
            }
        }
    }
}