<?php
/**
 * Settings Admin Page Filter Registration
 * 
 * WordPress Settings API integration for Data Machine configuration.
 * 
 * @package DataMachine\Core\Admin\Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

function dm_register_settings_admin_page_filters() {
    add_action('admin_menu', 'dm_register_settings_page');
    add_action('admin_init', 'dm_register_settings');
    add_action('admin_enqueue_scripts', 'dm_enqueue_settings_assets');
    
    add_filter('dm_modals', function($modals) {
        $modals['tool-config'] = [
            'template' => 'modal/tool-config',
            'title' => __('Configure Tool', 'data-machine')
        ];
        return $modals;
    });
    
    add_filter('dm_render_template', function($content, $template_name, $data = []) {
        $settings_template_path = DATA_MACHINE_PATH . 'inc/Core/Admin/Settings/templates/' . $template_name . '.php';
        if (file_exists($settings_template_path)) {
            ob_start();
            extract($data);
            include $settings_template_path;
            return ob_get_clean();
        }
        return $content;
    }, 15, 3);
    
    add_filter('dm_admin_assets', function($assets, $context) {
        if ($context === 'settings') {
            $assets['css'] = [
                'dm-core-modal' => [
                    'src' => '../Modal/assets/css/core-modal.css',
                    'deps' => []
                ],
                'dm-settings-page' => [
                    'src' => 'assets/css/settings-page.css',
                    'deps' => ['dm-core-modal']
                ]
            ];
            $assets['js'] = [
                'dm-core-modal' => [
                    'src' => '../Modal/assets/js/core-modal.js',
                    'deps' => ['jquery'],
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
                    'src' => 'assets/js/settings-page.js',
                    'deps' => ['jquery', 'dm-core-modal'],
                    'localize' => [
                        'object' => 'dmSettings',
                        'data' => [
                            'ajax_url' => admin_url('admin-ajax.php'),
                            'dm_ajax_nonce' => wp_create_nonce('dm_ajax_actions'),
                            'strings' => [
                                'saving' => __('Saving...', 'data-machine'),
                                'clearing' => __('Clearing...', 'data-machine')
                            ]
                        ]
                    ]
                ]
            ];
        }
        return $assets;
    }, 10, 2);
    
    \DataMachine\Core\Admin\Settings\SettingsPageAjax::register();
}

function dm_register_settings_page() {
    $hook = add_options_page(
        __('Data Machine Settings', 'data-machine'),
        __('Data Machine', 'data-machine'),
        'manage_options',
        'data-machine-settings',
        'dm_render_settings_page_template'
    );
    
    dm_store_settings_hook_suffix($hook);
}

function dm_register_settings() {
    register_setting('data_machine_settings', 'data_machine_settings', [
        'sanitize_callback' => 'dm_sanitize_settings'
    ]);
}

function dm_render_settings_page_template() {
    $content = apply_filters('dm_render_template', '', 'page/settings-page', [
        'page_title' => __('Data Machine Settings', 'data-machine')
    ]);

    echo wp_kses($content, dm_allowed_html());
}

function dm_enqueue_settings_assets($hook) {
    $settings_hook = dm_get_settings_hook_suffix();
    if ($hook !== $settings_hook) {
        return;
    }
    
    $assets = apply_filters('dm_admin_assets', [], 'settings');
    
    foreach ($assets['css'] ?? [] as $handle => $css_config) {
        $css_url = DATA_MACHINE_URL . 'inc/Core/Admin/Settings/' . $css_config['src'];

        // Use file modification time for cache busting in development
        $css_file_path = DATA_MACHINE_PATH . 'inc/Core/Admin/Settings/' . $css_config['src'];
        $css_version = file_exists($css_file_path) ? filemtime($css_file_path) : DATA_MACHINE_VERSION;
        
        wp_enqueue_style(
            $handle,
            $css_url,
            $css_config['deps'] ?? [],
            $css_version,
            $css_config['media'] ?? 'all'
        );
    }
    
    foreach ($assets['js'] ?? [] as $handle => $js_config) {
        $js_url = DATA_MACHINE_URL . 'inc/Core/Admin/Settings/' . $js_config['src'];

        // Use file modification time for cache busting in development
        $js_file_path = DATA_MACHINE_PATH . 'inc/Core/Admin/Settings/' . $js_config['src'];
        $js_version = file_exists($js_file_path) ? filemtime($js_file_path) : DATA_MACHINE_VERSION;
        
        wp_enqueue_script(
            $handle,
            $js_url,
            $js_config['deps'] ?? ['jquery'],
            $js_version,
            $js_config['in_footer'] ?? true
        );
        
        if ($js_config['localize'] ?? false) {
            wp_localize_script($handle, $js_config['localize']['object'], $js_config['localize']['data']);
        }
    }
}

function dm_sanitize_settings($input) {
    $sanitized = [];
    
    $sanitized['engine_mode'] = !empty($input['engine_mode']);
    
    $sanitized['enabled_pages'] = [];
    if (is_array($input['enabled_pages'] ?? [])) {
        foreach ($input['enabled_pages'] as $slug => $value) {
            if ($value) {
                $sanitized['enabled_pages'][sanitize_key($slug)] = true;
            }
        }
    }

    $sanitized['enabled_tools'] = [];
    if (is_array($input['enabled_tools'] ?? [])) {
        foreach ($input['enabled_tools'] as $tool_id => $value) {
            if ($value) {
                $sanitized['enabled_tools'][sanitize_key($tool_id)] = true;
            }
        }
    }

    $sanitized['cleanup_job_data_on_failure'] = !empty($input['cleanup_job_data_on_failure']);
    
    $sanitized['global_system_prompt'] = '';
    if (isset($input['global_system_prompt'])) {
        $sanitized['global_system_prompt'] = wp_unslash($input['global_system_prompt']);
    }
    
    $sanitized['site_context_enabled'] = !empty($input['site_context_enabled']);

    // Sanitize file retention days (1-90 days range)
    $sanitized['file_retention_days'] = 7; // default
    if (isset($input['file_retention_days'])) {
        $retention_days = absint($input['file_retention_days']);
        if ($retention_days >= 1 && $retention_days <= 90) {
            $sanitized['file_retention_days'] = $retention_days;
        }
    }

    $sanitized['wordpress_settings'] = [];
    if (isset($input['wordpress_settings'])) {
        $wp_input = $input['wordpress_settings'];
        
        $sanitized['wordpress_settings']['enabled_post_types'] = [];
        if (is_array($wp_input['enabled_post_types'] ?? [])) {
            $valid_post_types = get_post_types(['public' => true]);
            foreach ($wp_input['enabled_post_types'] as $post_type => $value) {
                if (in_array($post_type, $valid_post_types) && $value) {
                    $sanitized['wordpress_settings']['enabled_post_types'][$post_type] = 1;
                }
            }
        }
        
        $sanitized['wordpress_settings']['enabled_taxonomies'] = [];
        if (is_array($wp_input['enabled_taxonomies'] ?? [])) {
            $valid_taxonomies = get_taxonomies(['public' => true]);
            foreach ($wp_input['enabled_taxonomies'] as $taxonomy => $value) {
                if (in_array($taxonomy, $valid_taxonomies) && $value) {
                    if (!in_array($taxonomy, ['post_format', 'nav_menu', 'link_category'])) {
                        $sanitized['wordpress_settings']['enabled_taxonomies'][$taxonomy] = 1;
                    }
                }
            }
        }
        
        $sanitized['wordpress_settings']['default_author_id'] = 0;
        if (isset($wp_input['default_author_id'])) {
            $author_id = absint($wp_input['default_author_id']);
            if (get_userdata($author_id)) {
                $sanitized['wordpress_settings']['default_author_id'] = $author_id;
            }
        }
        
        $sanitized['wordpress_settings']['default_post_status'] = '';
        if (isset($wp_input['default_post_status'])) {
            $post_status = sanitize_text_field($wp_input['default_post_status']);
            if (get_post_status_object($post_status)) {
                $sanitized['wordpress_settings']['default_post_status'] = $post_status;
            }
        }

        $sanitized['wordpress_settings']['default_include_source'] = !empty($wp_input['default_include_source']);
        $sanitized['wordpress_settings']['default_enable_images'] = !empty($wp_input['default_enable_images']);
    }
    
    return $sanitized;
}

add_filter('dm_enabled_settings', function($fields, $handler_slug, $step_type, $context) {
    if (!in_array($handler_slug, ['wordpress_posts', 'wordpress_publish'])) {
        return $fields;
    }
    
    $all_settings = get_option('data_machine_settings', []);
    $wp_settings = $all_settings['wordpress_settings'] ?? [];
    $enabled_post_types = $wp_settings['enabled_post_types'] ?? [];
    $enabled_taxonomies = $wp_settings['enabled_taxonomies'] ?? [];
    $default_author_id = $wp_settings['default_author_id'] ?? 0;
    $default_post_status = $wp_settings['default_post_status'] ?? '';
    
    foreach ($fields as $field_name => &$field_config) {
        $include_field = true;
        
        if ($field_name === 'post_type' && $enabled_post_types) {
            $filtered_options = [];
            foreach ($field_config['options'] ?? [] as $value => $label) {
                if ($value === 'any' || $enabled_post_types[$value] ?? false) {
                    $filtered_options[$value] = $label;
                }
            }
            $field_config['options'] = $filtered_options;
        }
        
        if (strpos($field_name, 'taxonomy_') === 0 && $enabled_taxonomies) {
            if (preg_match('/^taxonomy_(.+?)(?:_filter|_selection)$/', $field_name, $matches)) {
                $taxonomy_name = $matches[1];
                if (!($enabled_taxonomies[$taxonomy_name] ?? false)) {
                    $include_field = false;
                }
            }
        }
        
        if ($field_name === 'post_author' && $default_author_id) {
            $include_field = false;
        }

        if ($field_name === 'post_status' && $default_post_status) {
            $include_field = false;
        }

        // Global boolean settings - hide fields when globally controlled
        if ($field_name === 'include_source' && isset($wp_settings['default_include_source'])) {
            $include_field = false;
        }

        if ($field_name === 'enable_images' && isset($wp_settings['default_enable_images'])) {
            $include_field = false;
        }
        
        if (!$include_field) {
            unset($fields[$field_name]);
        }
    }
    
    return $fields;
}, 10, 4);

add_filter('dm_apply_global_defaults', function($current_settings, $handler_slug, $step_type) {
    if (!in_array($handler_slug, ['wordpress_posts', 'wordpress_publish'])) {
        return $current_settings;
    }
    
    $all_settings = get_option('data_machine_settings', []);
    $wp_settings = $all_settings['wordpress_settings'] ?? [];
    
    // System defaults ALWAYS override flow step configurations when set
    if ($wp_settings['default_author_id'] ?? false) {
        $current_settings['post_author'] = $wp_settings['default_author_id'];
    }

    if ($wp_settings['default_post_status'] ?? false) {
        $current_settings['post_status'] = $wp_settings['default_post_status'];
    }

    // Global boolean settings - use isset() to check if they're defined (including false values)
    if (isset($wp_settings['default_include_source'])) {
        $current_settings['include_source'] = $wp_settings['default_include_source'];
    }

    if (isset($wp_settings['default_enable_images'])) {
        $current_settings['enable_images'] = $wp_settings['default_enable_images'];
    }

    return $current_settings;
}, 10, 3);

function dm_store_settings_hook_suffix($hook) {
    update_option('dm_settings_hook_suffix', $hook);
}

function dm_get_settings_hook_suffix() {
    return get_option('dm_settings_hook_suffix', '');
}

dm_register_settings_admin_page_filters();