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
    
    // ========================================================================
    // ADMIN PAGE SYSTEM
    // ========================================================================
    
    /**
     * Admin page discovery filter
     */
    add_filter('dm_admin_pages', function($pages) {
        // Components self-register via this same filter with higher priority
        return $pages;
    }, 5, 1);
    
    // ========================================================================
    // MODAL SYSTEM
    // ========================================================================
    
    /**
     * Modal content discovery filter
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
        
        // Fallback: Search core modal templates directory
        // Handle modal/ prefix in template names correctly
        if (strpos($template_name, 'modal/') === 0) {
            $modal_template_name = substr($template_name, 6); // Remove 'modal/' prefix
            $core_modal_template_path = DATA_MACHINE_PATH . 'inc/core/admin/modal/templates/' . $modal_template_name . '.php';
        } else {
            $core_modal_template_path = DATA_MACHINE_PATH . 'inc/core/admin/modal/templates/' . $template_name . '.php';
        }
        
        if (file_exists($core_modal_template_path)) {
            ob_start();
            extract($data); // Extract data array to make variables available in template scope
            include $core_modal_template_path;
            return ob_get_clean();
        }
        
        // Return empty string when template truly not found
        return '';
    }, 10, 3);
    
    // WordPress-native admin hook registration  
    add_action('admin_menu', 'dm_register_admin_menu');
    add_action('admin_menu', 'dm_register_settings_page');
    add_action('admin_init', 'dm_register_settings');
    add_action('admin_enqueue_scripts', 'dm_enqueue_admin_assets');
}


// ========================================================================
// ID EXTRACTION UTILITIES
// ========================================================================

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

// ========================================================================
// ADMIN MENU FUNCTIONS
// ========================================================================

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

/**
 * Register Data Machine settings page under WordPress Settings menu
 */
function dm_register_settings_page() {
    $hook = add_options_page(
        __('Data Machine Settings', 'data-machine'),
        __('Data Machine', 'data-machine'),
        'manage_options',
        'data-machine-settings',
        'dm_render_settings_page'
    );
    
    // Add settings page styles
    add_action("admin_print_styles-{$hook}", 'dm_enqueue_settings_page_styles');
}

/**
 * Register Data Machine settings with WordPress Settings API
 */
function dm_register_settings() {
    register_setting('data_machine_settings', 'data_machine_settings', [
        'sanitize_callback' => 'dm_sanitize_settings'
    ]);
    
    add_settings_section(
        'dm_admin_control',
        __('Admin Interface Control', 'data-machine'),
        'dm_admin_control_section_callback',
        'data-machine-settings'
    );
    
    add_settings_field(
        'engine_mode',
        __('Engine Mode', 'data-machine'),
        'dm_engine_mode_field_callback',
        'data-machine-settings',
        'dm_admin_control'
    );
    
    add_settings_field(
        'enabled_pages',
        __('Admin Pages', 'data-machine'),
        'dm_enabled_pages_field_callback',
        'data-machine-settings',
        'dm_admin_control'
    );
    
    add_settings_field(
        'enabled_tools',
        __('General Tools', 'data-machine'),
        'dm_enabled_tools_field_callback',
        'data-machine-settings',
        'dm_admin_control'
    );
    
    add_settings_field(
        'global_system_prompt',
        __('Global System Prompt', 'data-machine'),
        'dm_global_system_prompt_field_callback',
        'data-machine-settings',
        'dm_admin_control'
    );
}

/**
 * Get Data Machine settings with defaults
 */
function dm_get_data_machine_settings() {
    $defaults = [
        'engine_mode' => false,
        'enabled_pages' => [], // Empty array means all pages enabled by default
        'enabled_tools' => [],  // Empty array means all tools enabled by default
        'global_system_prompt' => '' // Empty string means no global prompt by default
    ];
    
    $settings = get_option('data_machine_settings', $defaults);
    return wp_parse_args($settings, $defaults);
}

/**
 * Get enabled admin pages based on settings
 */
function dm_get_enabled_admin_pages() {
    $settings = dm_get_data_machine_settings();
    
    // Engine mode - no admin pages
    if ($settings['engine_mode']) {
        return [];
    }
    
    // Get all discovered admin pages
    $all_pages = apply_filters('dm_admin_pages', []);
    
    // If enabled_pages is empty, all pages are enabled (default behavior)
    if (empty($settings['enabled_pages'])) {
        return $all_pages;
    }
    
    // Filter pages based on enabled settings
    $enabled_pages = [];
    foreach ($all_pages as $slug => $page_config) {
        if (!empty($settings['enabled_pages'][$slug])) {
            $enabled_pages[$slug] = $page_config;
        }
    }
    
    return $enabled_pages;
}

/**
 * Get enabled general AI tools based on settings
 */
function dm_get_enabled_general_tools() {
    $settings = dm_get_data_machine_settings();
    
    // Engine mode - no tools available
    if ($settings['engine_mode']) {
        return [];
    }
    
    // Get all registered tools
    $all_tools = apply_filters('ai_tools', []);
    
    // Filter to only general tools (no handler property)
    $general_tools = [];
    foreach ($all_tools as $tool_name => $tool_config) {
        if (!isset($tool_config['handler'])) {
            $general_tools[$tool_name] = $tool_config;
        }
    }
    
    // If enabled_tools is empty, all general tools are enabled (default behavior)
    if (empty($settings['enabled_tools'])) {
        return $general_tools;
    }
    
    // Filter tools based on enabled settings
    $enabled_tools = [];
    foreach ($general_tools as $tool_name => $tool_config) {
        if (!empty($settings['enabled_tools'][$tool_name])) {
            $enabled_tools[$tool_name] = $tool_config;
        }
    }
    
    return $enabled_tools;
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
    
    // No fallback - unified system only
    
    if (!empty($page_assets['css']) || !empty($page_assets['js'])) {
        dm_enqueue_page_assets($page_assets, $current_page_slug);
    }
}

/**
 * Enqueue page-specific assets
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

// ========================================================================
// SETTINGS PAGE CALLBACKS
// ========================================================================

/**
 * Render Data Machine settings page
 */
function dm_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Data Machine Settings', 'data-machine'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('data_machine_settings');
            do_settings_sections('data-machine-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Admin control section callback
 */
function dm_admin_control_section_callback() {
    echo '<p>' . esc_html__('Control which Data Machine admin interfaces are available. Engine Mode disables all admin pages while preserving core functionality.', 'data-machine') . '</p>';
}

/**
 * Engine mode field callback
 */
function dm_engine_mode_field_callback() {
    $settings = dm_get_data_machine_settings();
    $engine_mode = $settings['engine_mode'];
    ?>
    <fieldset>
        <label>
            <input type="checkbox" name="data_machine_settings[engine_mode]" value="1" <?php checked($engine_mode, true); ?>>
            <?php esc_html_e('Enable Engine Mode', 'data-machine'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Disables all Data Machine admin pages. The plugin engine remains fully functional for programmatic use.', 'data-machine'); ?>
        </p>
    </fieldset>
    <?php
}

/**
 * Enabled pages field callback
 */
function dm_enabled_pages_field_callback() {
    $settings = dm_get_data_machine_settings();
    $enabled_pages = $settings['enabled_pages'];
    $engine_mode = $settings['engine_mode'];
    
    // Get all available admin pages
    $all_pages = apply_filters('dm_admin_pages', []);
    
    if (empty($all_pages)) {
        echo '<p>' . esc_html__('No admin pages are currently registered.', 'data-machine') . '</p>';
        return;
    }
    
    $disabled_attr = $engine_mode ? 'disabled' : '';
    ?>
    <fieldset <?php echo $disabled_attr; ?>>
        <?php foreach ($all_pages as $slug => $page_config): ?>
            <?php
            $page_title = $page_config['menu_title'] ?? $page_config['page_title'] ?? ucfirst($slug);
            $is_enabled = empty($enabled_pages) || !empty($enabled_pages[$slug]); // Default to enabled
            ?>
            <label style="display: block; margin-bottom: 8px;">
                <input type="checkbox" 
                       name="data_machine_settings[enabled_pages][<?php echo esc_attr($slug); ?>]" 
                       value="1" 
                       <?php checked($is_enabled, true); ?>
                       <?php echo $disabled_attr; ?>>
                <?php echo esc_html($page_title); ?>
            </label>
        <?php endforeach; ?>
        <?php if ($engine_mode): ?>
            <p class="description">
                <?php esc_html_e('Individual page controls are disabled when Engine Mode is active.', 'data-machine'); ?>
            </p>
        <?php else: ?>
            <p class="description">
                <?php esc_html_e('Select which admin pages to show in the Data Machine menu. Unchecked pages will be hidden but data remains intact.', 'data-machine'); ?>
            </p>
        <?php endif; ?>
    </fieldset>
    <?php
}

/**
 * Enabled tools field callback
 */
function dm_enabled_tools_field_callback() {
    $settings = dm_get_data_machine_settings();
    $enabled_tools = $settings['enabled_tools'];
    $engine_mode = $settings['engine_mode'];
    
    // Get all available general tools
    $all_tools = apply_filters('ai_tools', []);
    $general_tools = [];
    foreach ($all_tools as $tool_name => $tool_config) {
        if (!isset($tool_config['handler'])) {
            $general_tools[$tool_name] = $tool_config;
        }
    }
    
    if (empty($general_tools)) {
        echo '<p>' . esc_html__('No general tools are currently registered.', 'data-machine') . '</p>';
        return;
    }
    
    $disabled_attr = $engine_mode ? 'disabled' : '';
    ?>
    <fieldset <?php echo $disabled_attr; ?>>
        <?php foreach ($general_tools as $tool_name => $tool_config): ?>
            <?php
            $tool_description = $tool_config['description'] ?? '';
            $is_enabled = empty($enabled_tools) || !empty($enabled_tools[$tool_name]); // Default to enabled
            ?>
            <label style="display: block; margin-bottom: 8px;">
                <input type="checkbox" 
                       name="data_machine_settings[enabled_tools][<?php echo esc_attr($tool_name); ?>]" 
                       value="1" 
                       <?php checked($is_enabled, true); ?>
                       <?php echo $disabled_attr; ?>>
                <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $tool_name))); ?></strong>
                <?php if ($tool_description): ?>
                    - <?php echo esc_html($tool_description); ?>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
        <?php if ($engine_mode): ?>
            <p class="description">
                <?php esc_html_e('Tool controls are disabled when Engine Mode is active.', 'data-machine'); ?>
            </p>
        <?php else: ?>
            <p class="description">
                <?php esc_html_e('Select which general AI tools are available for use in AI steps. Unchecked tools will not appear in AI step configuration.', 'data-machine'); ?>
            </p>
        <?php endif; ?>
    </fieldset>
    <?php
}

/**
 * Global system prompt field callback
 */
function dm_global_system_prompt_field_callback() {
    $settings = dm_get_data_machine_settings();
    $global_prompt = $settings['global_system_prompt'];
    $engine_mode = $settings['engine_mode'];
    
    $disabled_attr = $engine_mode ? 'disabled' : '';
    ?>
    <fieldset <?php echo $disabled_attr; ?>>
        <textarea name="data_machine_settings[global_system_prompt]" 
                  id="dm_global_system_prompt"
                  rows="6" 
                  cols="70" 
                  class="large-text"
                  placeholder="<?php esc_attr_e('e.g., Write in a friendly, professional tone. Always maintain brand consistency and cite sources when possible.', 'data-machine'); ?>"
                  <?php echo $disabled_attr; ?>><?php echo esc_textarea($global_prompt); ?></textarea>
        
        <?php if ($engine_mode): ?>
            <p class="description">
                <?php esc_html_e('Global system prompt is disabled when Engine Mode is active.', 'data-machine'); ?>
            </p>
        <?php else: ?>
            <p class="description">
                <?php esc_html_e('This prompt will be applied to all AI operations as the first system message, providing consistent brand voice and editorial guidelines across all pipelines.', 'data-machine'); ?>
            </p>
        <?php endif; ?>
    </fieldset>
    <?php
}

/**
 * Sanitize settings
 */
function dm_sanitize_settings($input) {
    $sanitized = [];
    
    // Engine mode
    $sanitized['engine_mode'] = !empty($input['engine_mode']);
    
    // Enabled pages
    $sanitized['enabled_pages'] = [];
    if (!empty($input['enabled_pages']) && is_array($input['enabled_pages'])) {
        foreach ($input['enabled_pages'] as $slug => $value) {
            if (!empty($value)) {
                $sanitized['enabled_pages'][sanitize_key($slug)] = true;
            }
        }
    }
    
    // Enabled tools
    $sanitized['enabled_tools'] = [];
    if (!empty($input['enabled_tools']) && is_array($input['enabled_tools'])) {
        foreach ($input['enabled_tools'] as $tool_name => $value) {
            if (!empty($value)) {
                $sanitized['enabled_tools'][sanitize_key($tool_name)] = true;
            }
        }
    }
    
    // Global system prompt
    $sanitized['global_system_prompt'] = '';
    if (isset($input['global_system_prompt'])) {
        $sanitized['global_system_prompt'] = wp_unslash($input['global_system_prompt']); // Preserve formatting
    }
    
    return $sanitized;
}

/**
 * Enqueue settings page styles
 */
function dm_enqueue_settings_page_styles() {
    ?>
    <style>
    .dm-settings-fieldset {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 12px 16px;
        margin: 8px 0;
    }
    
    .dm-settings-fieldset legend {
        font-weight: 600;
        padding: 0 8px;
    }
    
    .dm-settings-engine-mode {
        background: #fff3cd;
        border-color: #ffeaa7;
    }
    
    .dm-settings-engine-mode.active {
        background: #f8d7da;
        border-color: #f5c6cb;
    }
    
    .dm-settings-pages {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 12px;
        background: #fafafa;
    }
    
    .dm-settings-page-item {
        display: block;
        margin-bottom: 8px;
        padding: 4px 0;
    }
    
    .dm-settings-page-item:last-child {
        margin-bottom: 0;
    }
    
    .dm-engine-mode-notice {
        background: #e7f3ff;
        border-left: 4px solid #0073aa;
        padding: 12px;
        margin: 16px 0;
    }
    </style>
    <?php
}

// ========================================================================
// GLOBAL SYSTEM PROMPT INTEGRATION
// ========================================================================

/**
 * Inject global system prompt into AI requests
 * 
 * Hooks into the AI HTTP Client's ai_request filter to prepend a global
 * system message for consistent brand voice and editorial guidelines.
 */
add_filter('ai_request', function($request, $provider_name, $streaming_callback, $tools) {
    $settings = dm_get_data_machine_settings();
    $global_prompt = trim($settings['global_system_prompt'] ?? '');
    
    // Skip if no global prompt or engine mode is active
    if (empty($global_prompt) || $settings['engine_mode']) {
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