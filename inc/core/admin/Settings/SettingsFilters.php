<?php
/**
 * Settings Admin Page Filter Registration
 * 
 * WordPress Settings API integration for Data Machine configuration.
 * 
 * @package DataMachine\Core\Admin\Settings
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Settings Admin Page filters and WordPress Settings API integration.
 *
 * Establishes complete WordPress Settings API integration including:
 * - Settings page registration under WordPress Options menu
 * - Settings fields and sections registration
 * - Tool configuration modal registration
 * - Asset loading for Settings page functionality
 *
 * @since 1.0.0
 */
function dm_register_settings_admin_page_filters() {
    
    // Register settings page using WordPress options page pattern
    add_action('admin_menu', 'dm_register_settings_page');
    add_action('admin_init', 'dm_register_settings');
    add_action('admin_enqueue_scripts', 'dm_enqueue_settings_assets');
    
    
    // Register tool configuration modal for Settings page
    add_filter('dm_modals', function($modals) {
        $modals['tool-config'] = [
            'template' => 'modal/tool-config',
            'title' => __('Configure Tool', 'data-machine')
        ];
        return $modals;
    });
    
    // Register Settings page AJAX handlers
    \DataMachine\Core\Admin\Settings\SettingsPageAjax::register();
}

/**
 * Register Data Machine settings page under WordPress Options menu.
 *
 * Creates 'Data Machine' submenu item with manage_options capability requirement.
 * Stores hook suffix for conditional asset loading.
 */
function dm_register_settings_page() {
    $hook = add_options_page(
        __('Data Machine Settings', 'data-machine'),
        __('Data Machine', 'data-machine'),
        'manage_options',
        'data-machine-settings',
        'dm_render_settings_page_template'
    );
    
    // Store hook for asset loading
    dm_store_settings_hook_suffix($hook);
}

/**
 * Register all Data Machine settings with WordPress Settings API.
 *
 * Configures two main sections:
 * - Admin Interface Control: engine mode, admin pages, tools, system prompt
 * - WordPress Settings: post types, taxonomies, author/status defaults
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
    
    add_settings_field(
        'tool_configuration',
        __('Tool Configuration', 'data-machine'),
        'dm_tool_configuration_field_callback',
        'data-machine-settings',
        'dm_admin_control'
    );
    
    add_settings_section(
        'dm_wordpress_control',
        __('WordPress Settings', 'data-machine'),
        'dm_wordpress_control_section_callback',
        'data-machine-settings'
    );
    
    add_settings_field(
        'wordpress_enabled_post_types',
        __('Enabled Post Types', 'data-machine'),
        'dm_wordpress_enabled_post_types_field_callback',
        'data-machine-settings',
        'dm_wordpress_control'
    );
    
    add_settings_field(
        'wordpress_enabled_taxonomies',
        __('Enabled Taxonomies', 'data-machine'),
        'dm_wordpress_enabled_taxonomies_field_callback',
        'data-machine-settings',
        'dm_wordpress_control'
    );
    
    add_settings_field(
        'wordpress_default_author',
        __('Default Author', 'data-machine'),
        'dm_wordpress_default_author_field_callback',
        'data-machine-settings',
        'dm_wordpress_control'
    );
    
    add_settings_field(
        'wordpress_default_post_status',
        __('Default Post Status', 'data-machine'),
        'dm_wordpress_default_post_status_field_callback',
        'data-machine-settings',
        'dm_wordpress_control'
    );
}

/**
 * Render settings page via template system with fallback.
 *
 * Attempts universal template rendering first, falls back to direct
 * WordPress Settings API rendering if template not found.
 */
function dm_render_settings_page_template() {
    // Use template rendering system for consistency
    $content = apply_filters('dm_render_template', '', 'page/settings-page', [
        'page_title' => __('Data Machine Settings', 'data-machine')
    ]);
    
    if (!empty($content)) {
        echo $content;
    } else {
        // Fallback to direct rendering if template not found
        dm_render_settings_page_fallback();
    }
}

/**
 * Fallback settings page rendering (maintains current functionality)
 */
function dm_render_settings_page_fallback() {
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
 * Store settings page hook suffix for asset loading
 */
function dm_store_settings_hook_suffix($hook) {
    // Store for conditional asset loading
    update_option('dm_settings_hook_suffix', $hook);
}

/**
 * Enqueue settings page assets
 */
function dm_enqueue_settings_assets($hook_suffix) {
    $settings_hook = get_option('dm_settings_hook_suffix');
    
    if ($hook_suffix !== $settings_hook) {
        return; // Not on settings page
    }
    
    // Define Settings assets directly (not via dm_admin_pages since Settings is not a main admin page)
    $assets = [
        'css' => [
            'dm-core-modal' => [
                'file' => 'inc/Core/Admin/Modal/assets/css/core-modal.css',
                'deps' => [],
                'media' => 'all'
            ],
            'dm-settings-page' => [
                'file' => 'inc/Core/Admin/Settings/assets/css/settings-page.css',
                'deps' => ['dm-core-modal'],
                'media' => 'all'
            ]
        ],
        'js' => [
            'dm-core-modal' => [
                'file' => 'inc/Core/Admin/Modal/assets/js/core-modal.js',
                'deps' => ['jquery'],
                'in_footer' => true,
                'localize' => [
                    'object' => 'dmCoreModal',
                    'data' => [
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'dm_ajax_nonce' => wp_create_nonce('dm_ajax_actions'),
                        'strings' => [
                            'loading' => __('Loading...', 'data-machine'),
                            'error' => __('Error', 'data-machine'),
                            'close' => __('Close', 'data-machine')
                        ]
                    ]
                ]
            ],
            'dm-settings-page' => [
                'file' => 'inc/Core/Admin/Settings/assets/js/settings-page.js',
                'deps' => ['jquery', 'dm-core-modal'],
                'in_footer' => true,
                'localize' => [
                    'object' => 'dmSettings',
                    'data' => [
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'dm_ajax_nonce' => wp_create_nonce('dm_ajax_actions'),
                        'strings' => [
                            'saving' => __('Saving...', 'data-machine'),
                            'saved' => __('Settings saved', 'data-machine'),
                            'error' => __('Error saving settings', 'data-machine')
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    // Enqueue assets using existing asset loading system
    dm_enqueue_component_assets($assets, 'settings');
}

/**
 * Enqueue component assets with version management.
 *
 * Loads CSS and JS files with file modification time versioning.
 * Handles localization data injection for JavaScript components.
 *
 * @param array  $assets        Asset configuration array (css/js).
 * @param string $component_slug Component identifier for debugging.
 */
function dm_enqueue_component_assets($assets, $component_slug) {
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

// ========================================================================
// SETTINGS CALLBACKS (Moved from Admin.php)
// ========================================================================

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
    
    // Get all available main admin pages (Settings is not included as it's a WordPress options page)
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
            <label class="dm-settings-page-item">
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
            <label class="dm-settings-page-item">
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
 * Tool configuration field callback
 */
function dm_tool_configuration_field_callback() {
    $settings = dm_get_data_machine_settings();
    $engine_mode = $settings['engine_mode'];
    
    // Get all available general tools that require configuration
    $all_tools = apply_filters('ai_tools', []);
    $configurable_tools = [];
    foreach ($all_tools as $tool_name => $tool_config) {
        if (!isset($tool_config['handler']) && !empty($tool_config['requires_config'])) {
            $configurable_tools[$tool_name] = $tool_config;
        }
    }
    
    if (empty($configurable_tools)) {
        echo '<p>' . esc_html__('No configurable tools are currently available.', 'data-machine') . '</p>';
        return;
    }
    
    $disabled_attr = $engine_mode ? 'disabled' : '';
    ?>
    <fieldset <?php echo $disabled_attr; ?>>
        <div class="dm-tool-configuration-list">
            <?php foreach ($configurable_tools as $tool_name => $tool_config): ?>
                <?php
                $tool_description = $tool_config['description'] ?? '';
                $is_configured = apply_filters('dm_tool_configured', false, $tool_name);
                $status_class = $is_configured ? 'dm-tool-configured' : 'dm-tool-not-configured';
                $status_text = $is_configured ? __('✓ Configured', 'data-machine') : __('⚠ Not Configured', 'data-machine');
                $status_color = $is_configured ? '#00a32a' : '#d63638';
                ?>
                <div class="dm-tool-config-item <?php echo esc_attr($status_class); ?>">
                    <div class="dm-tool-config-header">
                        <div class="dm-tool-info">
                            <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $tool_name))); ?></strong>
                            <?php if ($tool_description): ?>
                                <br>
                                <span class="description"><?php echo esc_html($tool_description); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="dm-tool-actions">
                            <span class="dm-tool-status <?php echo $is_configured ? 'configured' : 'not-configured'; ?>">
                                <?php echo esc_html($status_text); ?>
                            </span>
                            <?php if (!$engine_mode): ?>
                                <button type="button" 
                                        class="button button-secondary dm-modal-open" 
                                        data-template="tool-config"
                                        data-context='{"tool_id":"<?php echo esc_attr($tool_name); ?>"}'>
                                    <?php echo $is_configured ? esc_html__('Reconfigure', 'data-machine') : esc_html__('Configure', 'data-machine'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($engine_mode): ?>
            <p class="description">
                <?php esc_html_e('Tool configuration is disabled when Engine Mode is active.', 'data-machine'); ?>
            </p>
        <?php else: ?>
            <p class="description">
                <?php esc_html_e('Configure AI tools once here, then use them in any pipeline. Tools will be available immediately after configuration.', 'data-machine'); ?>
            </p>
        <?php endif; ?>
    </fieldset>
    <?php
}

/**
 * WordPress control section callback
 */
function dm_wordpress_control_section_callback() {
    echo '<p>' . esc_html__('Control which WordPress post types and taxonomies appear in handler configuration modals. Set global defaults to reduce repetitive configuration.', 'data-machine') . '</p>';
}

/**
 * WordPress enabled post types field callback
 */
function dm_wordpress_enabled_post_types_field_callback() {
    $settings = dm_get_data_machine_settings();
    $wp_settings = $settings['wordpress_settings'] ?? [];
    $enabled_post_types = $wp_settings['enabled_post_types'] ?? [];
    $engine_mode = $settings['engine_mode'];
    
    // Get all available public post types
    $post_types = get_post_types(['public' => true], 'objects');
    
    if (empty($post_types)) {
        echo '<p>' . esc_html__('No public post types are currently available.', 'data-machine') . '</p>';
        return;
    }
    
    $disabled_attr = $engine_mode ? 'disabled' : '';
    ?>
    <fieldset <?php echo $disabled_attr; ?>>
        <?php foreach ($post_types as $post_type): ?>
            <?php
            $is_enabled = empty($enabled_post_types) || !empty($enabled_post_types[$post_type->name]); // Default to enabled
            ?>
            <label class="dm-settings-page-item">
                <input type="checkbox" 
                       name="data_machine_settings[wordpress_settings][enabled_post_types][<?php echo esc_attr($post_type->name); ?>]" 
                       value="1" 
                       <?php checked($is_enabled, true); ?>
                       <?php echo $disabled_attr; ?>>
                <?php echo esc_html($post_type->label); ?>
                <span class="description">(<?php echo esc_html($post_type->name); ?>)</span>
            </label>
        <?php endforeach; ?>
        <p class="description">
            <?php esc_html_e('Unchecked post types will not appear in any WordPress handler configuration modals.', 'data-machine'); ?>
        </p>
        <?php if ($engine_mode): ?>
            <p class="description">
                <?php esc_html_e('Post type controls are disabled when Engine Mode is active.', 'data-machine'); ?>
            </p>
        <?php endif; ?>
    </fieldset>
    <?php
}

/**
 * WordPress enabled taxonomies field callback
 */
function dm_wordpress_enabled_taxonomies_field_callback() {
    $settings = dm_get_data_machine_settings();
    $wp_settings = $settings['wordpress_settings'] ?? [];
    $enabled_taxonomies = $wp_settings['enabled_taxonomies'] ?? [];
    $engine_mode = $settings['engine_mode'];
    
    // Get all available public taxonomies
    $taxonomies = get_taxonomies(['public' => true], 'objects');
    
    // Filter out built-in non-content taxonomies
    $filtered_taxonomies = [];
    foreach ($taxonomies as $taxonomy) {
        if (!in_array($taxonomy->name, ['post_format', 'nav_menu', 'link_category'])) {
            $filtered_taxonomies[] = $taxonomy;
        }
    }
    
    if (empty($filtered_taxonomies)) {
        echo '<p>' . esc_html__('No content taxonomies are currently available.', 'data-machine') . '</p>';
        return;
    }
    
    $disabled_attr = $engine_mode ? 'disabled' : '';
    ?>
    <fieldset <?php echo $disabled_attr; ?>>
        <?php foreach ($filtered_taxonomies as $taxonomy): ?>
            <?php
            $taxonomy_label = $taxonomy->labels->name ?? $taxonomy->label;
            $is_enabled = empty($enabled_taxonomies) || !empty($enabled_taxonomies[$taxonomy->name]); // Default to enabled
            ?>
            <label class="dm-settings-page-item">
                <input type="checkbox" 
                       name="data_machine_settings[wordpress_settings][enabled_taxonomies][<?php echo esc_attr($taxonomy->name); ?>]" 
                       value="1" 
                       <?php checked($is_enabled, true); ?>
                       <?php echo $disabled_attr; ?>>
                <?php echo esc_html($taxonomy_label); ?>
                <span class="description">(<?php echo esc_html($taxonomy->name); ?>)</span>
            </label>
        <?php endforeach; ?>
        <p class="description">
            <?php esc_html_e('Unchecked taxonomies will not appear in any WordPress handler configuration modals.', 'data-machine'); ?>
        </p>
        <?php if ($engine_mode): ?>
            <p class="description">
                <?php esc_html_e('Taxonomy controls are disabled when Engine Mode is active.', 'data-machine'); ?>
            </p>
        <?php endif; ?>
    </fieldset>
    <?php
}

/**
 * WordPress default author field callback
 */
function dm_wordpress_default_author_field_callback() {
    $settings = dm_get_data_machine_settings();
    $wp_settings = $settings['wordpress_settings'] ?? [];
    $default_author_id = $wp_settings['default_author_id'] ?? 0;
    $engine_mode = $settings['engine_mode'];
    
    // Get all available WordPress users
    $users = get_users(['fields' => ['ID', 'display_name', 'user_login']]);
    
    $disabled_attr = $engine_mode ? 'disabled' : '';
    ?>
    <fieldset <?php echo $disabled_attr; ?>>
        <select name="data_machine_settings[wordpress_settings][default_author_id]" <?php echo $disabled_attr; ?>>
            <option value="0" <?php selected($default_author_id, 0); ?>>
                <?php esc_html_e('Show field in modals', 'data-machine'); ?>
            </option>
            <?php foreach ($users as $user): ?>
                <?php
                $display_name = !empty($user->display_name) ? $user->display_name : $user->user_login;
                ?>
                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($default_author_id, $user->ID); ?>>
                    <?php echo esc_html($display_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('When a user is selected, the author field will be hidden from WordPress publish handler modals and this user will be used as the default author.', 'data-machine'); ?>
        </p>
        <?php if ($engine_mode): ?>
            <p class="description">
                <?php esc_html_e('Author controls are disabled when Engine Mode is active.', 'data-machine'); ?>
            </p>
        <?php endif; ?>
    </fieldset>
    <?php
}

/**
 * WordPress default post status field callback
 */
function dm_wordpress_default_post_status_field_callback() {
    $settings = dm_get_data_machine_settings();
    $wp_settings = $settings['wordpress_settings'] ?? [];
    $default_post_status = $wp_settings['default_post_status'] ?? '';
    $engine_mode = $settings['engine_mode'];
    
    $post_statuses = [
        'draft' => __('Draft', 'data-machine'),
        'publish' => __('Publish', 'data-machine'),
        'pending' => __('Pending Review', 'data-machine'),
        'private' => __('Private', 'data-machine'),
    ];
    
    $disabled_attr = $engine_mode ? 'disabled' : '';
    ?>
    <fieldset <?php echo $disabled_attr; ?>>
        <select name="data_machine_settings[wordpress_settings][default_post_status]" <?php echo $disabled_attr; ?>>
            <option value="" <?php selected($default_post_status, ''); ?>>
                <?php esc_html_e('Show field in modals', 'data-machine'); ?>
            </option>
            <?php foreach ($post_statuses as $status => $label): ?>
                <option value="<?php echo esc_attr($status); ?>" <?php selected($default_post_status, $status); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('When a status is selected, the post status field will be hidden from WordPress publish handler modals and this status will be used as the default.', 'data-machine'); ?>
        </p>
        <?php if ($engine_mode): ?>
            <p class="description">
                <?php esc_html_e('Post status controls are disabled when Engine Mode is active.', 'data-machine'); ?>
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
    
    // WordPress settings
    $sanitized['wordpress_settings'] = [];
    if (isset($input['wordpress_settings'])) {
        $wp_input = $input['wordpress_settings'];
        
        // Sanitize enabled post types
        $sanitized['wordpress_settings']['enabled_post_types'] = [];
        if (isset($wp_input['enabled_post_types']) && is_array($wp_input['enabled_post_types'])) {
            $valid_post_types = get_post_types(['public' => true]);
            foreach ($wp_input['enabled_post_types'] as $post_type => $value) {
                if (in_array($post_type, $valid_post_types) && !empty($value)) {
                    $sanitized['wordpress_settings']['enabled_post_types'][$post_type] = 1;
                }
            }
        }
        
        // Sanitize enabled taxonomies
        $sanitized['wordpress_settings']['enabled_taxonomies'] = [];
        if (isset($wp_input['enabled_taxonomies']) && is_array($wp_input['enabled_taxonomies'])) {
            $valid_taxonomies = get_taxonomies(['public' => true]);
            foreach ($wp_input['enabled_taxonomies'] as $taxonomy => $value) {
                if (in_array($taxonomy, $valid_taxonomies) && !empty($value)) {
                    // Skip built-in non-content taxonomies
                    if (!in_array($taxonomy, ['post_format', 'nav_menu', 'link_category'])) {
                        $sanitized['wordpress_settings']['enabled_taxonomies'][$taxonomy] = 1;
                    }
                }
            }
        }
        
        // Sanitize default author ID
        $sanitized['wordpress_settings']['default_author_id'] = 0;
        if (isset($wp_input['default_author_id'])) {
            $author_id = absint($wp_input['default_author_id']);
            if ($author_id > 0) {
                // Validate user exists
                $user = get_user_by('id', $author_id);
                if ($user) {
                    $sanitized['wordpress_settings']['default_author_id'] = $author_id;
                }
            }
        }
        
        // Sanitize default post status
        $sanitized['wordpress_settings']['default_post_status'] = '';
        if (isset($wp_input['default_post_status'])) {
            $post_status = sanitize_text_field($wp_input['default_post_status']);
            $valid_statuses = ['draft', 'publish', 'pending', 'private'];
            if (in_array($post_status, $valid_statuses)) {
                $sanitized['wordpress_settings']['default_post_status'] = $post_status;
            }
        }
    }
    
    return $sanitized;
}

/**
 * Filter handler settings fields based on WordPress global settings.
 *
 * Dynamically modifies WordPress handler configuration modals by:
 * - Filtering post type options to enabled types only
 * - Removing taxonomy fields for disabled taxonomies
 * - Hiding author/status fields when global defaults are set
 *
 * @param array  $fields       Handler settings fields.
 * @param string $handler_slug Handler identifier.
 * @param string $step_type    Step type context.
 * @param array  $context      Additional context data.
 * @return array Filtered settings fields.
 */
add_filter('dm_enabled_settings', function($fields, $handler_slug, $step_type, $context) {
    // Only apply to WordPress handlers
    if (!in_array($handler_slug, ['wordpress_fetch', 'wordpress_publish'])) {
        return $fields;
    }
    
    // Get WordPress global settings (inline to avoid dependencies)
    $all_settings = get_option('data_machine_settings', []);
    $wp_settings = $all_settings['wordpress_settings'] ?? [];
    $enabled_post_types = $wp_settings['enabled_post_types'] ?? [];
    $enabled_taxonomies = $wp_settings['enabled_taxonomies'] ?? [];
    $default_author_id = $wp_settings['default_author_id'] ?? 0;
    $default_post_status = $wp_settings['default_post_status'] ?? '';
    
    $filtered_fields = [];
    
    foreach ($fields as $field_name => $field_config) {
        $include_field = true;
        
        // Filter post type fields
        if ($field_name === 'post_type' && !empty($enabled_post_types)) {
            $filtered_options = [];
            foreach ($field_config['options'] ?? [] as $value => $label) {
                // Always include "any" option for fetch handlers
                if ($value === 'any' || !empty($enabled_post_types[$value])) {
                    $filtered_options[$value] = $label;
                }
            }
            $field_config['options'] = $filtered_options;
        }
        
        // Remove taxonomy fields for disabled taxonomies
        if (strpos($field_name, 'taxonomy_') === 0 && !empty($enabled_taxonomies)) {
            // Extract taxonomy name from field name (handles both _filter and _selection suffixes)
            if (preg_match('/^taxonomy_([^_]+)_(filter|selection)$/', $field_name, $matches)) {
                $taxonomy_name = $matches[1];
                if (empty($enabled_taxonomies[$taxonomy_name])) {
                    $include_field = false;
                }
            }
        }
        
        // Remove author field if default author is set (publish handlers only)
        if ($field_name === 'post_author' && $default_author_id > 0) {
            $include_field = false;
        }
        
        // Remove post status field if default status is set (publish handlers only)
        if ($field_name === 'post_status' && !empty($default_post_status)) {
            $include_field = false;
        }
        
        if ($include_field) {
            $filtered_fields[$field_name] = $field_config;
        }
    }
    
    return $filtered_fields;
}, 10, 4);

/**
 * Apply global WordPress defaults to current handler settings.
 *
 * Automatically injects global default author ID and post status into
 * WordPress publish handler settings when not already configured.
 *
 * @param array  $current_settings Current handler settings.
 * @param string $handler_slug     Handler identifier.
 * @param string $step_type        Step type context.
 * @return array Settings with global defaults applied.
 */
add_filter('dm_apply_global_defaults', function($current_settings, $handler_slug, $step_type) {
    // Only apply to WordPress publish handlers
    if ($handler_slug !== 'wordpress_publish') {
        return $current_settings;
    }
    
    // Get WordPress global settings (inline to avoid dependencies)
    $all_settings = get_option('data_machine_settings', []);
    $wp_settings = $all_settings['wordpress_settings'] ?? [];
    
    // Apply defaults if not already set in current settings
    if (!empty($wp_settings['default_author_id']) && empty($current_settings['post_author'])) {
        $current_settings['post_author'] = $wp_settings['default_author_id'];
    }
    
    if (!empty($wp_settings['default_post_status']) && empty($current_settings['post_status'])) {
        $current_settings['post_status'] = $wp_settings['default_post_status'];
    }
    
    return $current_settings;
}, 10, 3);

// Auto-register when file loads - achieving complete self-containment
dm_register_settings_admin_page_filters();