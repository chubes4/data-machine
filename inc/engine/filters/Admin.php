<?php
/**
 * Admin filter registration and discovery hub
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register admin filters - discovery system for pages, modals, templates
 */
function dm_register_admin_filters() {
    
    
    /**
     * Admin page discovery filter
     */
    add_filter('dm_admin_pages', function($pages) {
        // Components self-register via this same filter with higher priority
        return $pages;
    }, 5, 1);
    
    
    /**
     * Modal content discovery filter
     */
    add_filter('dm_modals', function($modals) {
        // Components self-register via this same filter with higher priority
        // Bootstrap provides discovery infrastructure for ModalAjax
        return $modals;
    }, 5, 1);
    
    
    
    /**
     * Template rendering with dynamic discovery across registered admin pages
     */
    add_filter('dm_render_template', function($content, $template_name, $data = []) {
        // Template discovery and rendering
        // Dynamic discovery of all registered admin pages and their template directories
        $all_pages = apply_filters('dm_admin_pages', []);
        
        foreach ($all_pages as $slug => $page_config) {
            if (!empty($page_config['templates'])) {
                $template_path = $page_config['templates'] . $template_name . '.php';
                if (file_exists($template_path)) {
                    ob_start();
                    extract($data); // Extract data array to make variables available in template scope
                    include $template_path;
                    return ob_get_clean();
                }
            }
        }
        
        // Handle core modal templates
        if (strpos($template_name, 'modal/') === 0) {
            $modal_template_name = substr($template_name, 6);
            $core_modal_template_path = DATA_MACHINE_PATH . 'inc/Core/Admin/Modal/templates/' . $modal_template_name . '.php';
        } else {
            $core_modal_template_path = DATA_MACHINE_PATH . 'inc/Core/Admin/Modal/templates/' . $template_name . '.php';
        }
        
        if (file_exists($core_modal_template_path)) {
            ob_start();
            extract($data);
            include $core_modal_template_path;
            return ob_get_clean();
        }
        
        return '';
    }, 10, 3);
    
    // WordPress-native admin hook registration  
    add_action('admin_menu', 'dm_register_admin_menu');
    add_action('admin_enqueue_scripts', 'dm_enqueue_admin_assets');
}



/**
 * Split composite flow_step_id: {pipeline_step_id}_{flow_id}
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


/**
 * Register admin menu via filter-based page discovery
 */
function dm_register_admin_menu() {
    // Get enabled admin pages based on settings (includes Engine Mode check)
    $registered_pages = dm_get_enabled_admin_pages();
    
    // Only create Data Machine menu if pages are available and not in Engine Mode
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

// Settings component registration
add_action('init', function() {
    // Load Settings component
    require_once DATA_MACHINE_PATH . 'inc/Core/Admin/Settings/SettingsFilters.php';
}, 1);

/**
 * Get Data Machine settings with defaults
 */
function dm_get_data_machine_settings() {
    return get_option('data_machine_settings', [
        'engine_mode' => false,
        'enabled_pages' => [],
        'enabled_tools' => [],
        'global_system_prompt' => '',
        'site_context_enabled' => true,
        'cleanup_job_data_on_failure' => true,
        'wordpress_settings' => []
    ]);
}

/**
 * Get enabled admin pages based on settings
 */
function dm_get_enabled_admin_pages() {
    $settings = dm_get_data_machine_settings();
    
    if ($settings['engine_mode']) {
        return [];
    }
    
    $all_pages = apply_filters('dm_admin_pages', []);
    
    if (empty($settings['enabled_pages'])) {
        return $all_pages;
    }
    
    return array_intersect_key($all_pages, array_filter($settings['enabled_pages']));
}

/**
 * Get enabled general AI tools based on settings
 */
function dm_get_enabled_general_tools() {
    $settings = dm_get_data_machine_settings();
    $all_tools = apply_filters('ai_tools', []);
    
    $general_tools = array_filter($all_tools, function($tool_config) {
        return !isset($tool_config['handler']);
    });
    
    if (empty($settings['enabled_tools'])) {
        return $general_tools;
    }
    
    return array_intersect_key($general_tools, array_filter($settings['enabled_tools']));
}

/**
 * Store hook suffix for asset loading
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
 * Enqueue assets via dynamic page detection
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
    
    
    if (!empty($page_assets['css']) || !empty($page_assets['js'])) {
        dm_enqueue_page_assets($page_assets, $current_page_slug);
    }
}

/**
 * Enqueue page-specific assets
 */
function dm_enqueue_page_assets($assets, $page_slug) {
    $plugin_base_path = DATA_MACHINE_PATH;
    $plugin_base_url = DATA_MACHINE_URL;
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


// Settings page rendering and callbacks moved to /inc/Core/Admin/Settings/SettingsFilters.php






// Settings page styles moved to /inc/Core/Admin/Settings/assets/css/settings-page.css


/**
 * Inject global system prompt into AI requests
 * 
 * Hooks into the AI HTTP Client's ai_request filter to prepend a global
 * system message for consistent brand voice and editorial guidelines.
 */
add_filter('ai_request', function($request, $provider_name, $streaming_callback, $tools) {
    $settings = dm_get_data_machine_settings();
    $global_prompt = trim($settings['global_system_prompt'] ?? '');
    
    // Skip if no global prompt 
    if (empty($global_prompt)) {
        return $request;
    }
    
    // Inject global system message as separate entry
    if (isset($request['messages']) && is_array($request['messages'])) {
        array_unshift($request['messages'], [
            'role' => 'system',
            'content' => $global_prompt
        ]);
    }
    
    return $request;
}, 5, 4); // Priority 5 to run before AI HTTP Client's processing