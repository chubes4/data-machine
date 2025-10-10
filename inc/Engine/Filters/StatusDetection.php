<?php
/**
 * Status detection system with RED/YELLOW/GREEN priority architecture.
 */

if (!defined('ABSPATH')) {
    exit;
}
function dm_register_status_detection_filters() {

    // RED Status Filters (Critical - Blocks Execution)
    add_filter('dm_detect_status', 'dm_handle_red_pipeline_statuses', 10, 3);
    add_filter('dm_detect_status', 'dm_handle_red_flow_statuses', 10, 3);

    // YELLOW Status Filters (Warning - Suboptimal)
    add_filter('dm_detect_status', 'dm_handle_yellow_pipeline_statuses', 10, 3);
    add_filter('dm_detect_status', 'dm_handle_yellow_flow_statuses', 10, 3);

    // Flow-Specific Status Filters (Optimized for flow-scoped operations)
    add_filter('dm_detect_status', 'dm_handle_flow_step_status', 10, 3);

    // GREEN Status Filters (Success)
    add_filter('dm_detect_status', 'dm_handle_green_statuses', 10, 3);

    // Utility Filters
    add_filter('dm_get_handler_settings_display', 'dm_get_handler_settings_display_data', 10, 2);
}

function dm_handle_red_pipeline_statuses($default_status, $context, $data) {
    if ($context === 'ai_step') {
        $pipeline_step_id = $data['pipeline_step_id'] ?? null;
        if (!$pipeline_step_id) {
            return 'red';
        }

        $ai_config = apply_filters('dm_ai_config', [], $pipeline_step_id);

        if (empty($ai_config)) {
            return 'red';
        }

        $has_provider = !empty($ai_config['selected_provider']) || !empty($ai_config['provider']);
        $has_model = !empty($ai_config['model']);
        $has_prompt = !empty($ai_config['system_prompt']);

        if (!$has_provider || !$has_model || !$has_prompt) {
            return 'red';
        }

        return 'green';
    }

    if ($context === 'pipeline_viability') {
        $pipeline_id = $data['pipeline_id'] ?? null;
        if (!$pipeline_id) {
            return 'red';
        }

        $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        if (empty($pipeline_steps)) {
            return 'red';
        }

        $ordered_steps = [];
        foreach ($pipeline_steps as $step_id => $step_config) {
            $execution_order = $step_config['execution_order'] ?? -1;
            $ordered_steps[$execution_order] = $step_config;
        }
        ksort($ordered_steps);

        $step_types = [];
        foreach ($ordered_steps as $step_config) {
            $step_types[] = $step_config['step_type'] ?? 'unknown';
        }

        if (empty($step_types)) {
            return 'red';
        }

        $last_step_type = end($step_types);
        if ($last_step_type !== 'publish') {
            return 'red';
        }

        if (count($step_types) < 2) {
            return 'red';
        }

        $steps_before_publish = array_slice($step_types, 0, -1);
        $has_ai_step = in_array('ai', $steps_before_publish);

        if (!$has_ai_step) {
            return 'red';
        }

        return 'green';
    }

    return $default_status;
}

function dm_handle_red_flow_statuses($default_status, $context, $data) {
    if ($context === 'handler_auth') {
        $handler_slug = $data['handler_slug'] ?? null;
        if (!$handler_slug) {
            return 'red';
        }

        $all_auth = apply_filters('dm_auth_providers', []);
        $auth_provider = $all_auth[$handler_slug] ?? null;

        if (!$auth_provider) {
            return $default_status;
        }

        if (method_exists($auth_provider, 'is_configured') && $auth_provider->is_configured()) {
            return 'green';
        }

        return 'red';
    }

    if ($context === 'files_status') {
        $flow_step_id = $data['flow_step_id'] ?? null;
        if (!$flow_step_id) {
            return 'red';
        }

        $files_repo = apply_filters('dm_files_repository', [])['files'] ?? null;
        if (!$files_repo) {
            return 'red';
        }

        $files = $files_repo->get_all_files($flow_step_id);

        if (empty($files)) {
            return 'red';
        }

        $unprocessed_count = 0;
        foreach ($files as $file) {
            $file_identifier = $file['path'] ?? $file['filename'] ?? '';
            if ($file_identifier) {
                $is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, 'files', $file_identifier);
                if (!$is_processed) {
                    $unprocessed_count++;
                }
            }
        }

        if ($unprocessed_count === 0) {
            return 'red';
        }

        return 'green';
    }

    return $default_status;
}

function dm_handle_yellow_pipeline_statuses($default_status, $context, $data) {
    if ($context === 'pipeline_step_status') {
        $pipeline_id = $data['pipeline_id'] ?? null;
        $pipeline_step_id = $data['pipeline_step_id'] ?? null;
        $step_type = $data['step_type'] ?? null;

        if (!$pipeline_id || !$pipeline_step_id || !$step_type) {
            return 'red';
        }

        if ($step_type === 'ai') {
            return apply_filters('dm_detect_status', 'green', 'ai_step', [
                'pipeline_step_id' => $pipeline_step_id
            ]);
        }

        $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        if (empty($pipeline_steps)) {
            return 'green';
        }

        foreach ($pipeline_steps as $step_id => $step_config) {
            $pipeline_step_type = $step_config['step_type'] ?? '';
            if ($pipeline_step_type === 'ai') {
                $ai_status = apply_filters('dm_detect_status', 'green', 'ai_step', [
                    'pipeline_step_id' => $step_id
                ]);

                if ($ai_status === 'red') {
                    return 'yellow';
                }
            }
        }

        $viability_status = apply_filters('dm_detect_status', 'green', 'pipeline_viability', [
            'pipeline_id' => $pipeline_id
        ]);

        if ($viability_status === 'red') {
            return 'red';
        }

        return 'green';
    }

    if ($context === 'subsequent_publish_step') {
        $pipeline_step_id = $data['pipeline_step_id'] ?? null;
        if (!$pipeline_step_id) {
            return $default_status;
        }

        $current_step_config = apply_filters('dm_get_pipeline_step_config', [], $pipeline_step_id);
        if (!$current_step_config) {
            return $default_status;
        }

        $current_step_type = $current_step_config['step_type'] ?? '';
        if ($current_step_type !== 'publish') {
            return $default_status;
        }

        $prev_pipeline_step_id = apply_filters('dm_get_previous_pipeline_step_id', null, $pipeline_step_id);
        if (!$prev_pipeline_step_id) {
            return $default_status;
        }

        $prev_step_config = apply_filters('dm_get_pipeline_step_config', [], $prev_pipeline_step_id);
        if (!$prev_step_config) {
            return $default_status;
        }

        $prev_step_type = $prev_step_config['step_type'] ?? '';
        if ($prev_step_type === 'publish') {
            return 'yellow';
        }

        return $default_status;
    }

    return $default_status;
}

function dm_handle_yellow_flow_statuses($default_status, $context, $data) {
    if ($context === 'wordpress_draft') {
        $flow_step_id = $data['flow_step_id'] ?? null;
        if (!$flow_step_id) {
            return 'green';
        }

        $step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
        $handler_settings = $step_config['handler']['settings'] ?? [];

        $post_status = $handler_settings['post_status'] ?? 'publish';

        if ($post_status === 'draft') {
            return 'yellow';
        }

        return 'green';
    }

    return $default_status;
}

/**
 * Flow-scoped status detection optimized for single flow operations.
 */
function dm_handle_flow_step_status($default_status, $context, $data) {
    if ($context !== 'flow_step_status') {
        return $default_status;
    }

    $flow_step_id = $data['flow_step_id'] ?? null;
    $step_config = $data['step_config'] ?? [];
    $step_type = $data['step_type'] ?? '';

    if (!$flow_step_id || !$step_config) {
        return 'red';
    }

    if ($step_type === 'ai') {
        $pipeline_step_id = $step_config['pipeline_step_id'] ?? null;
        if ($pipeline_step_id) {
            return apply_filters('dm_detect_status', 'green', 'ai_step', [
                'pipeline_step_id' => $pipeline_step_id
            ]);
        }
        return 'red';
    }

    $handler_slug = $step_config['handler']['handler_slug'] ?? '';
    if (empty($handler_slug)) {
        return 'red';
    }

    $auth_status = apply_filters('dm_detect_status', 'green', 'handler_auth', [
        'handler_slug' => $handler_slug
    ]);
    if ($auth_status === 'red') {
        return 'red';
    }

    $handler_settings_display = apply_filters('dm_get_handler_settings_display', [], $flow_step_id);
    if (empty($handler_settings_display)) {
        return 'yellow';
    }

    if ($handler_slug === 'wordpress_publish') {
        $draft_status = apply_filters('dm_detect_status', 'green', 'wordpress_draft', [
            'flow_step_id' => $flow_step_id
        ]);
        if ($draft_status === 'yellow') {
            return 'yellow';
        }
    }

    if ($handler_slug === 'files') {
        $files_status = apply_filters('dm_detect_status', 'green', 'files_status', [
            'flow_step_id' => $flow_step_id
        ]);
        if ($files_status === 'red') {
            return 'red';
        }
    }

    return 'green';
}

function dm_handle_green_statuses($default_status, $context, $data) {
    return $default_status;
}

/**
 * Retrieves handler settings for display on flow step cards.
 */
function dm_get_handler_settings_display_data($default, $flow_step_id) {
    if (empty($flow_step_id)) {
        return [];
    }

    $step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
    if (empty($step_config) || !isset($step_config['handler'])) {
        return [];
    }

    $handler_slug = $step_config['handler']['handler_slug'] ?? '';
    $all_settings = $step_config['handler']['settings'] ?? [];
    $current_settings = $all_settings[$handler_slug] ?? [];

    if (!empty($handler_slug)) {
        $all_handler_settings = apply_filters('dm_handler_settings', [], $handler_slug);
        $handler_settings_class = $all_handler_settings[$handler_slug] ?? null;

        if ($handler_settings_class && method_exists($handler_settings_class, 'get_fields')) {
            $fields_for_defaults = $handler_settings_class::get_fields();
            foreach ($fields_for_defaults as $field_name => $field_config) {
                if (!isset($current_settings[$field_name]) && isset($field_config['default'])) {
                    $current_settings[$field_name] = $field_config['default'];
                }
            }
        }
    }

    if (empty($handler_slug) || empty($current_settings)) {
        return [];
    }

    $step_type = $step_config['step_type'] ?? '';
    $current_settings = apply_filters('dm_apply_global_defaults', $current_settings, $handler_slug, $step_type);

    $all_settings = apply_filters('dm_handler_settings', [], $handler_slug);
    $handler_settings = $all_settings[$handler_slug] ?? null;

    if (!$handler_settings) {
        return [];
    }

    $fields = [];
    if (method_exists($handler_settings, 'get_fields')) {
        $fields = $handler_settings::get_fields();
    }

    $settings_display = [];

    if ($handler_slug === 'wordpress_publish') {
        $essential_wordpress_settings = ['post_type', 'post_status', 'post_author'];
        foreach ($essential_wordpress_settings as $essential_key) {
            if (isset($current_settings[$essential_key])) {
                $current_value = $current_settings[$essential_key];
                $field_config = $fields[$essential_key] ?? [];
                $label = $field_config['label'] ?? ucfirst(str_replace('_', ' ', $essential_key));

                $display_value = $current_value;
                if ($essential_key === 'post_author') {
                    $user = get_userdata($current_value);
                    if ($user) {
                        $display_value = $user->display_name;
                    }
                } elseif (isset($field_config['options'][$current_value])) {
                    $display_value = $field_config['options'][$current_value];
                }

                $settings_display[] = [
                    'key' => $essential_key,
                    'label' => $label,
                    'value' => $current_value,
                    'display_value' => $display_value
                ];
            }
        }
    }
    
    foreach ($current_settings as $setting_key => $current_value) {
        if ($handler_slug === 'wordpress_publish' && in_array($setting_key, ['post_type', 'post_status', 'post_author'])) {
            continue;
        }

        if ($handler_slug === 'wordpress_publish' && strpos($setting_key, 'taxonomy_') === 0 && $current_value === 'skip') {
            continue;
        }

        if ($handler_slug === 'facebook') {
            $facebook_internal_fields = ['page_id', 'page_name', 'user_id', 'user_name', 'access_token', 'page_access_token', 'user_access_token', 'token_type', 'authenticated_at', 'token_expires_at', 'target_id'];
            if (in_array($setting_key, $facebook_internal_fields)) {
                continue;
            }
        }

        $field_config = $fields[$setting_key] ?? [];
        $label = $field_config['label'] ?? ucfirst(str_replace('_', ' ', $setting_key));

        $display_value = $current_value;
        if (is_bool($current_value)) {
            $display_value = $current_value ? __('ON', 'data-machine') : __('OFF', 'data-machine');
        } elseif (is_array($current_value)) {
            $display_value = implode(', ', $current_value);
        } elseif ($setting_key === 'subreddit' && $handler_slug === 'reddit') {
            $display_value = 'r/' . $current_value;
            $label = '';
        } elseif (in_array($handler_slug, ['wordpress_posts', 'wordpress_publish'])) {
            if (isset($field_config['options'])) {
                $options = $field_config['options'];
                if (isset($options[$current_value])) {
                    $display_value = $options[$current_value];
                } elseif (strpos($setting_key, 'taxonomy_') === 0 && strpos($setting_key, '_filter') !== false) {
                    if ($current_value == 0) {
                        $display_value = $options[0] ?? __('All', 'data-machine');
                    } else {
                        $taxonomy_name = str_replace(['taxonomy_', '_filter'], '', $setting_key);
                        $term = get_term($current_value, $taxonomy_name);
                        $display_value = (!is_wp_error($term) && $term) ? $term->name : $current_value;
                    }
                }
            } else {
                if ($setting_key === 'post_status' && $current_value === 'any') {
                    $display_value = __('Any', 'data-machine');
                } elseif ($setting_key === 'post_type' && $current_value === 'any') {
                    $display_value = __('Any', 'data-machine');
                } elseif (in_array($setting_key, ['source_url', 'search']) && empty($current_value)) {
                    $display_value = __('N/A', 'data-machine');
                }
            }
        }

        $settings_display[] = [
            'key' => $setting_key,
            'label' => $label,
            'value' => $current_value,
            'display_value' => $display_value
        ];
    }

    return $settings_display;
}
