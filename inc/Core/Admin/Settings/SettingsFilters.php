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

function datamachine_register_settings_admin_page_filters() {
    add_action('admin_menu', 'datamachine_register_settings_page');
    add_action('admin_init', 'datamachine_register_settings');
    add_action('admin_enqueue_scripts', 'datamachine_enqueue_settings_assets');

    add_filter('datamachine_render_template', function($content, $template_name, $data = []) {
        $settings_template_path = DATAMACHINE_PATH . 'inc/Core/Admin/Settings/templates/' . $template_name . '.php';
        if (file_exists($settings_template_path)) {
            ob_start();
            extract($data);
            include $settings_template_path;
            return ob_get_clean();
        }
        return $content;
    }, 15, 3);
    
    add_filter('datamachine_admin_assets', function($assets, $context) {
        if ($context === 'settings') {
            $settings = get_option('datamachine_settings', []);

            $assets['css'] = [
                'datamachine-core-modal' => [
                    'src' => '../Modal/assets/css/core-modal.css',
                    'deps' => []
                ],
                'datamachine-settings-page' => [
                    'src' => 'assets/css/settings-page.css',
                    'deps' => ['datamachine-core-modal']
                ]
            ];
            $assets['js'] = [
                'datamachine-settings-page' => [
                    'src' => 'assets/js/settings-page.js',
                    'deps' => ['wp-api-fetch'],
                    'localize' => [
                        'object' => 'datamachineSettings',
                        'data' => [
                            'strings' => [
                                'saving' => __('Saving...', 'datamachine'),
                                'clearing' => __('Clearing...', 'datamachine')
                            ]
                        ]
                    ]
                ],
                'datamachine-agent-tab' => [
                    'src' => 'assets/js/agent-tab.js',
                    'deps' => ['wp-api-fetch'],
                    'localize' => [
                        'object' => 'datamachineAgentTab',
                        'data' => [
                            'savedProvider' => $settings['default_provider'] ?? '',
                            'savedModel' => $settings['default_model'] ?? '',
                            'strings' => [
                                'selectProviderFirst' => __('Select provider first...', 'datamachine'),
                                'selectModel' => __('Select Model...', 'datamachine')
                            ]
                        ]
                    ]
                ]
            ];
        }
        return $assets;
    }, 10, 2);
}

function datamachine_register_settings_page() {
    $hook = add_options_page(
        __('Data Machine Settings', 'datamachine'),
        __('Data Machine', 'datamachine'),
        'manage_options',
        'datamachine-settings',
        'datamachine_render_settings_page_template'
    );
    
    datamachine_store_settings_hook_suffix($hook);
}

function datamachine_register_settings() {
    register_setting('datamachine_settings', 'datamachine_settings', [
        'sanitize_callback' => 'datamachine_sanitize_settings'
    ]);
}

function datamachine_render_settings_page_template() {
    $content = apply_filters('datamachine_render_template', '', 'page/settings-page', [
        'page_title' => __('Data Machine Settings', 'datamachine')
    ]);

    echo wp_kses($content, datamachine_allowed_html());
}

function datamachine_enqueue_settings_assets($hook) {
    $settings_hook = datamachine_get_settings_hook_suffix();
    if ($hook !== $settings_hook) {
        return;
    }
    
    $assets = apply_filters('datamachine_admin_assets', [], 'settings');
    
    foreach ($assets['css'] ?? [] as $handle => $css_config) {
        $css_url = DATAMACHINE_URL . 'inc/Core/Admin/Settings/' . $css_config['src'];

        // Use file modification time for cache busting in development
        $css_file_path = DATAMACHINE_PATH . 'inc/Core/Admin/Settings/' . $css_config['src'];
        $css_version = file_exists($css_file_path) ? filemtime($css_file_path) : DATAMACHINE_VERSION;
        
        wp_enqueue_style(
            $handle,
            $css_url,
            $css_config['deps'] ?? [],
            $css_version,
            $css_config['media'] ?? 'all'
        );
    }
    
    foreach ($assets['js'] ?? [] as $handle => $js_config) {
        $js_url = DATAMACHINE_URL . 'inc/Core/Admin/Settings/' . $js_config['src'];

        // Use file modification time for cache busting in development
        $js_file_path = DATAMACHINE_PATH . 'inc/Core/Admin/Settings/' . $js_config['src'];
        $js_version = file_exists($js_file_path) ? filemtime($js_file_path) : DATAMACHINE_VERSION;
        
        wp_enqueue_script(
            $handle,
            $js_url,
            $js_config['deps'] ?? [],
            $js_version,
            $js_config['in_footer'] ?? true
        );
        
        if ($js_config['localize'] ?? false) {
            wp_localize_script($handle, $js_config['localize']['object'], $js_config['localize']['data']);
        }
    }
}

function datamachine_sanitize_settings($input) {
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

    // Default AI provider and model
    $sanitized['default_provider'] = '';
    if (isset($input['default_provider'])) {
        $sanitized['default_provider'] = sanitize_text_field($input['default_provider']);
    }

    $sanitized['default_model'] = '';
    if (isset($input['default_model'])) {
        $sanitized['default_model'] = sanitize_text_field($input['default_model']);
    }

    // Handle AI provider API keys
    if (isset($input['ai_provider_keys']) && is_array($input['ai_provider_keys'])) {
        $provider_keys = [];
        foreach ($input['ai_provider_keys'] as $provider => $key) {
            $provider_keys[sanitize_key($provider)] = sanitize_text_field($key);
        }

        // Save to AI HTTP Client library's storage
        if (!empty($provider_keys)) {
            $all_keys = apply_filters('ai_provider_api_keys', null);
            $updated_keys = array_merge($all_keys, $provider_keys);
            apply_filters('ai_provider_api_keys', $updated_keys);
        }
    }

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
            $excluded = apply_filters('datamachine_wordpress_system_taxonomies', []);
            foreach ($wp_input['enabled_taxonomies'] as $taxonomy => $value) {
                if (in_array($taxonomy, $valid_taxonomies) && $value) {
                    if (!in_array($taxonomy, $excluded)) {
                        $sanitized['wordpress_settings']['enabled_taxonomies'][$taxonomy] = 1;
                    }
                }
            }
        }
        
        $sanitized['wordpress_settings']['default_author_id'] = 0;
        if (isset($wp_input['default_author_id'])) {
            $author_id = absint($wp_input['default_author_id']);
            $display_name = apply_filters('datamachine_wordpress_user_display_name', null, $author_id);
            if ($display_name !== null) {
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

        // Tri-state override behavior for global toggles:
        // - Only store the key when the checkbox is checked (true) to indicate an override.
        // - When unchecked, omit the key entirely so pipeline-level settings remain in control.
        if (!empty($wp_input['default_include_source'])) {
            $sanitized['wordpress_settings']['default_include_source'] = true;
        }

        if (!empty($wp_input['default_enable_images'])) {
            $sanitized['wordpress_settings']['default_enable_images'] = true;
        }
    }
    
    return $sanitized;
}

add_filter('datamachine_enabled_settings', function($fields, $handler_slug, $step_type, $context) {
    if (!in_array($handler_slug, ['wordpress_posts', 'wordpress_publish'])) {
        return $fields;
    }
    
    $all_settings = get_option('datamachine_settings', []);
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

add_filter('datamachine_apply_global_defaults', function($current_settings, $handler_slug, $step_type) {
    if (!in_array($handler_slug, ['wordpress_posts', 'wordpress_publish'])) {
        return $current_settings;
    }
    
    $all_settings = get_option('datamachine_settings', []);
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

function datamachine_store_settings_hook_suffix($hook) {
    update_option('datamachine_settings_hook_suffix', $hook);
}

function datamachine_get_settings_hook_suffix() {
    return get_option('datamachine_settings_hook_suffix', '');
}

datamachine_register_settings_admin_page_filters();