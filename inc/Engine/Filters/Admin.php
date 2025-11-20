<?php
/**
 * Admin filter registration and discovery hub.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register admin interface filters for Data Machine.
 *
 * Registers filters for admin page discovery, menu management, and UI components.
 *
 * @since 0.1.0
 */
function datamachine_register_admin_filters() {


    add_filter('datamachine_admin_pages', function($pages) {
        return $pages;
    }, 5, 1);
    
    


    // Template rendering with dynamic discovery
    add_filter('datamachine_render_template', function($content, $template_name, $data = []) {
        // Template discovery and rendering
        // Dynamic discovery of all registered admin pages and their template directories
        $all_pages = apply_filters('datamachine_admin_pages', []);
        
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

        return '';
    }, 10, 3);
    
    // WordPress-native admin hook registration  
    add_action('admin_menu', 'datamachine_register_admin_menu');
    add_action('admin_enqueue_scripts', 'datamachine_enqueue_admin_assets');
}



/**
 * Register admin menu and pages for Data Machine.
 *
 * Creates WordPress admin menu structure and registers pages based on enabled settings.
 * Handles Engine Mode restrictions and page ordering.
 *
 * @since 0.1.0
 */
function datamachine_register_admin_menu() {
    // Get enabled admin pages based on settings (includes Engine Mode check)
    $registered_pages = datamachine_get_enabled_admin_pages();
    
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
        __('Data Machine', 'datamachine'),
        __('Data Machine', 'datamachine'),
        $first_page['capability'] ?? 'manage_options',
        'datamachine-' . $first_slug,
        '', // No callback - main menu is just container
        'dashicons-database-view',
        30
    );
    
    // Store hook suffix and page config for first page
    datamachine_store_hook_suffix($first_slug, $main_menu_hook);
    datamachine_store_page_config($first_slug, $first_page);
    
    // Add first page as submenu with its proper title
    $first_submenu_hook = add_submenu_page(
        'datamachine-' . $first_slug,
        $first_page['page_title'] ?? $first_page['menu_title'] ?? ucfirst($first_slug),
        $first_page['menu_title'] ?? ucfirst($first_slug),
        $first_page['capability'] ?? 'manage_options',
        'datamachine-' . $first_slug,
        function() use ($first_page, $first_slug) {
            datamachine_render_admin_page_content($first_page, $first_slug);
        }
    );
    
    // Add remaining pages as submenus (already sorted)
    $remaining_pages = array_slice($registered_pages, 1, null, true);
    foreach ($remaining_pages as $slug => $page_config) {
        $hook_suffix = add_submenu_page(
            'datamachine-' . $first_slug,
            $page_config['page_title'] ?? $page_config['menu_title'] ?? ucfirst($slug),
            $page_config['menu_title'] ?? ucfirst($slug),
            $page_config['capability'] ?? 'manage_options',
            'datamachine-' . $slug,
            function() use ($page_config, $slug) {
                datamachine_render_admin_page_content($page_config, $slug);
            }
        );
        
        // Store hook suffixes and page config for asset loading
        datamachine_store_hook_suffix($slug, $hook_suffix);
        datamachine_store_page_config($slug, $page_config);
    }
}

// Settings component registration
add_action('init', function() {
    // Load Settings component
    require_once DATAMACHINE_PATH . 'inc/Core/Admin/Settings/SettingsFilters.php';
}, 1);

/**
 * Get Data Machine settings with defaults.
 */


/**
 * Get enabled admin pages based on settings.
 */
function datamachine_get_enabled_admin_pages() {
    $settings = get_option('datamachine_settings', []);

    if ($settings['engine_mode']) {
        return [];
    }

    $all_pages = apply_filters('datamachine_admin_pages', []);

    if (empty($settings['enabled_pages'])) {
        return $all_pages;
    }

    $enabled_keys = array_keys(array_filter($settings['enabled_pages']));
    return array_intersect_key($all_pages, array_flip($enabled_keys));
}

/**
 * Get enabled general AI tools (non-handler-specific).
 */
function datamachine_get_enabled_global_tools() {
    $settings = get_option('datamachine_settings', []);
    $all_tools = apply_filters('chubes_ai_tools', []);

    $global_tools = array_filter($all_tools, function($tool_config) {
        return !isset($tool_config['handler']);
    });

    if (empty($settings['enabled_tools'])) {
        return $global_tools;
    }

    return array_intersect_key($global_tools, array_filter($settings['enabled_tools']));
}

function datamachine_store_hook_suffix($page_slug, $hook_suffix) {
    $page_hook_suffixes = get_option('datamachine_page_hook_suffixes', []);
    $page_hook_suffixes[$page_slug] = $hook_suffix;
    update_option('datamachine_page_hook_suffixes', $page_hook_suffixes);
}

/**
 * Store page configuration for unified rendering.
 *
 * @param string $page_slug Page slug
 * @param array $page_config Page configuration
 */
function datamachine_store_page_config($page_slug, $page_config) {
    $page_configs = get_option('datamachine_page_configs', []);
    $page_configs[$page_slug] = $page_config;
    update_option('datamachine_page_configs', $page_configs);
}

/**
 * Render admin page content using unified configuration.
 *
 * @param array $page_config Page configuration
 * @param string $page_slug Page slug
 */
function datamachine_render_admin_page_content($page_config, $page_slug) {
    // Special handling for logs page to use Logs class render_content method
    if ($page_slug === 'logs') {
        // Use Logs class to render content properly
        $logs_instance = new \DataMachine\Core\Admin\Pages\Logs\Logs();
        $logs_instance->render_content();
        return;
    }

    // Direct template rendering using standardized template name pattern for other pages
    $content = apply_filters('datamachine_render_template', '', "page/{$page_slug}-page", [
        'page_slug' => $page_slug,
        'page_config' => $page_config
    ]);

    if (!empty($content)) {
        echo wp_kses($content, datamachine_allowed_html());
    } else {
        // Default empty state
        echo '<div class="wrap"><h1>' . esc_html($page_config['page_title'] ?? ucfirst($page_slug)) . '</h1>';
        echo '<p>' . esc_html__('Page content not configured.', 'datamachine') . '</p></div>';
    }
}

/**
 * Get allowed HTML for admin template content
 *
 * Returns comprehensive allowed HTML array for WordPress admin templates
 * including form elements and data attributes needed for admin interfaces.
 *
 * @return array Allowed HTML tags and attributes
 */
function datamachine_allowed_html(): array {
    // Start with WordPress post allowed HTML as base
    $allowed_html = wp_kses_allowed_html('post');

    // Add essential admin form elements
    $allowed_html['form'] = [
        'action' => [],
        'method' => [],
        'class' => [],
        'id' => [],
        'enctype' => [],
        'novalidate' => [],
    ];

    $allowed_html['input'] = [
        'type' => [],
        'name' => [],
        'value' => [],
        'class' => [],
        'id' => [],
        'checked' => [],
        'disabled' => [],
        'readonly' => [],
        'placeholder' => [],
        'required' => [],
        'min' => [],
        'max' => [],
        'step' => [],
        'data-*' => true,
    ];

    $allowed_html['select'] = [
        'name' => [],
        'class' => [],
        'id' => [],
        'multiple' => [],
        'required' => [],
        'disabled' => [],
        'data-*' => true,
    ];

    $allowed_html['option'] = [
        'value' => [],
        'selected' => [],
        'disabled' => [],
    ];

    $allowed_html['textarea'] = [
        'name' => [],
        'class' => [],
        'id' => [],
        'rows' => [],
        'cols' => [],
        'placeholder' => [],
        'required' => [],
        'readonly' => [],
        'disabled' => [],
        'data-*' => true,
    ];

    $allowed_html['button'] = [
        'type' => [],
        'class' => [],
        'id' => [],
        'disabled' => [],
        'name' => [],
        'value' => [],
        'data-*' => true,
    ];

    $allowed_html['label'] = [
        'for' => [],
        'class' => [],
        'id' => [],
    ];

    // Add fieldset and legend for form grouping
    $allowed_html['fieldset'] = [
        'class' => [],
        'id' => [],
        'disabled' => [],
    ];

    $allowed_html['legend'] = [
        'class' => [],
        'id' => [],
    ];

    return $allowed_html;
}

function datamachine_enqueue_admin_assets( $hook_suffix ) {
    $page_hook_suffixes = get_option('datamachine_page_hook_suffixes', []);
    
    // Find matching page slug for this hook suffix
    $current_page_slug = null;
    foreach ($page_hook_suffixes as $slug => $stored_hook) {
        if ($stored_hook === $hook_suffix) {
            $current_page_slug = $slug;
            break;
        }
    }
    
    if (!$current_page_slug) {
        return;
    }
    
    $page_configs = get_option('datamachine_page_configs', []);
    
    // Get page assets from unified configuration
    $page_config = $page_configs[$current_page_slug] ?? [];
    $page_assets = $page_config['assets'] ?? [];
    
    
    if (!empty($page_assets['css']) || !empty($page_assets['js'])) {
        datamachine_enqueue_page_assets($page_assets, $current_page_slug);
    }
}

function datamachine_enqueue_page_assets($assets, $page_slug) {
    $plugin_base_path = DATAMACHINE_PATH;
    $plugin_base_url = DATAMACHINE_URL;
    $version = DATAMACHINE_VERSION;
    
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
                $js_config['deps'] ?? [],
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

/**
 * Global system prompt injection moved to modular directive system
 * 
 * All AI directive handling is now centralized in modular directive classes:
 * GlobalSystemPromptDirective, PipelineSystemPromptDirective, ToolDefinitionsDirective, 
 * and SiteContextDirective.
 */