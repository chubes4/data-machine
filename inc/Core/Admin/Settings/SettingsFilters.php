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
                    'deps' => ['wp-api-fetch', 'datamachine-modal-manager'],
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
            $all_keys = apply_filters('chubes_ai_provider_api_keys', null);
            $updated_keys = array_merge($all_keys, $provider_keys);
            apply_filters('chubes_ai_provider_api_keys', $updated_keys);
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

    // Sanitize max turns (1-50 turns range)
    $sanitized['max_turns'] = 12; // default
    if (isset($input['max_turns'])) {
        $max_turns = absint($input['max_turns']);
        if ($max_turns >= 1 && $max_turns <= 50) {
            $sanitized['max_turns'] = $max_turns;
        }
    }

    return $sanitized;
}

function datamachine_store_settings_hook_suffix($hook) {
    update_option('datamachine_settings_hook_suffix', $hook);
}

function datamachine_get_settings_hook_suffix() {
    return get_option('datamachine_settings_hook_suffix', '');
}

datamachine_register_settings_admin_page_filters();