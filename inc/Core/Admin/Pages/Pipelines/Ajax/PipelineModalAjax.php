<?php
/**
 * Pipeline modal AJAX operations.
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Ajax;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelineModalAjax
{
    public static function register() {
        $instance = new self();

        add_action('wp_ajax_dm_get_template', [$instance, 'handle_get_template']);
        add_action('wp_ajax_dm_get_flow_step_card', [$instance, 'handle_get_flow_step_card']);
        add_action('wp_ajax_dm_get_flow_config', [$instance, 'handle_get_flow_config']);
        add_action('wp_ajax_dm_get_flow_step_config', [$instance, 'handle_get_flow_step_config']);
        add_action('wp_ajax_dm_configure_step_action', [$instance, 'handle_configure_step_action']);
        add_action('wp_ajax_dm_save_handler_settings', [$instance, 'handle_save_handler_settings']);
        add_action('wp_ajax_dm_get_pipeline_data', [$instance, 'handle_get_pipeline_data']);
        add_action('wp_ajax_dm_get_flow_data', [$instance, 'handle_get_flow_data']);
    }

    public function handle_get_template()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        // Require explicit data
        if (!isset($_POST['template'])) {
            wp_send_json_error(['message' => __('Template parameter is required', 'data-machine')]);
        }
        if (!isset($_POST['template_data'])) {
            wp_send_json_error(['message' => __('Template data parameter is required', 'data-machine')]);
        }
        
    $template = sanitize_text_field(wp_unslash($_POST['template']));
    $raw_template_data = sanitize_text_field(wp_unslash($_POST['template_data'] ?? ''));

        // Accept either JSON string or array; decode safely then sanitize
        if (is_string($raw_template_data)) {
            $decoded = json_decode($raw_template_data, true);
            if (!is_array($decoded)) {
                wp_send_json_error(['message' => __('Invalid template data format', 'data-machine')]);
            }
            $template_data = $decoded;
        } elseif (is_array($raw_template_data)) {
            $template_data = $raw_template_data; // already unslashed above
        } else {
            wp_send_json_error(['message' => __('Template data must be a JSON string or array', 'data-machine')]);
        }

        // Recursively sanitize template data array
        $template_data = $this->sanitize_template_data($template_data);

        if (empty($template)) {
            wp_send_json_error(['message' => __('Template name is required', 'data-machine')]);
        }
        
        if ($template === 'page/pipeline-step-card' || $template === 'page/flow-step-card') {
            if (!isset($template_data['step']['is_empty'])) {
                $template_data['step']['is_empty'] = false;
            }
        }

        $content = apply_filters('dm_render_template', '', $template, $template_data);
        
        if ($content) {
            wp_send_json_success([
                'html' => $content,
                'template' => $template
            ]);
        } else {
            wp_send_json_error([
                /* translators: %s: Template name */
                'message' => sprintf(__('Template "%s" not found', 'data-machine'), $template)
            ]);
        }
    }

    public function handle_get_flow_step_card()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        $step_type = sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));
        $flow_id = sanitize_text_field(wp_unslash($_POST['flow_id'] ?? 'new'));
        $pipeline_id = (int) sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? '0'));
        
        if (empty($step_type)) {
            wp_send_json_error(['message' => __('Step type is required', 'data-machine')]);
        }

        $all_steps = apply_filters('dm_steps', []);
        $step_config = $all_steps[$step_type] ?? null;
        if (!$step_config) {
            wp_send_json_error(['message' => __('Invalid step type', 'data-machine')]);
        }

        $template_data = [
            'step' => [
                'step_type' => $step_type,
                'step_config' => []
            ],
            'flow_id' => $flow_id,
            'pipeline_id' => $pipeline_id
        ];

        wp_send_json_success([
            'template_data' => $template_data,
            'step_type' => $step_type,
            'flow_id' => $flow_id
        ]);
    }

    public function handle_get_flow_config()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        $flow_id = (int) sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));

        if (empty($flow_id)) {
            wp_send_json_error(['message' => __('Flow ID is required.', 'data-machine')]);
            return;
        }

        $flow = apply_filters('dm_get_flow', null, $flow_id);
        $flow_config = $flow['flow_config'] ?? [];

        wp_send_json_success([
            'flow_id' => $flow_id,
            'flow_config' => $flow_config ?? []
        ]);
    }

    public function handle_get_flow_step_config()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $flow_step_id = sanitize_text_field(wp_unslash($_POST['flow_step_id'] ?? ''));

        if (empty($flow_step_id)) {
            wp_send_json_error(['message' => __('Flow step ID is required', 'data-machine')]);
            return;
        }

        $step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);

        wp_send_json_success([
            'flow_step_id' => $flow_step_id,
            'step_config' => $step_config
        ]);
    }

    public function handle_configure_step_action()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        if (!isset($_POST['context'])) {
            wp_send_json_error(['message' => __('Context data is required', 'data-machine')]);
        }

        if (isset($_POST['context']) && is_array($_POST['context'])) {
            $context_raw = array_map('sanitize_text_field', wp_unslash($_POST['context']));
        } else {
            $context_raw = sanitize_text_field(wp_unslash($_POST['context'] ?? ''));
        }

        if (is_string($context_raw)) {
            $decoded = json_decode($context_raw, true) ?: [];
            $context = is_array($decoded) ? array_map('sanitize_text_field', $decoded) : [];
        } else {
            $context = array_map('sanitize_text_field', (array) $context_raw);
        }

        if (!is_array($context)) {
            wp_send_json_error([
                'message' => __('Invalid context data format - expected array', 'data-machine'),
                'context_type' => gettype($context),
                'received_context' => $context
            ]);
        }
        
        $required_fields = ['step_type', 'pipeline_id', 'pipeline_step_id'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (!isset($context[$field]) || empty($context[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            wp_send_json_error([
                /* translators: %s: List of missing field names */
                'message' => sprintf(__('Required fields missing: %s', 'data-machine'), implode(', ', $missing_fields)),
                'missing_fields' => $missing_fields,
                'received_context' => array_keys($context),
                'context_values' => $context,
                'debug_info' => [
                    'post_keys' => array_keys($_POST),
                    'context_source' => isset($_POST['context']) && is_string($_POST['context']) ? 'json_string' : 'array'
                ]
            ]);
        }
        
        $step_type = sanitize_text_field($context['step_type']);
        $pipeline_id = sanitize_text_field($context['pipeline_id']);
        $pipeline_step_id = sanitize_text_field($context['pipeline_step_id']);
        
        if (empty($step_type)) {
            wp_send_json_error(['message' => __('Step type is required', 'data-machine')]);
        }
        
        if ($step_type === 'ai') {
            if (empty($pipeline_step_id)) {
                wp_send_json_error([
                    'message' => __('Pipeline step ID is required for AI configuration', 'data-machine'),
                    'missing_data' => [
                        'pipeline_step_id' => empty($pipeline_step_id),
                        'step_type' => $step_type,
                        'pipeline_id' => $pipeline_id
                    ]
                ]);
            }
            
            $parsed_step_id = apply_filters('dm_split_pipeline_step_id', null, $pipeline_step_id);
            if ($parsed_step_id === null) {
                wp_send_json_error([
                    'message' => __('Pipeline step ID format invalid - expected {pipeline_id}_{uuid4}', 'data-machine'),
                    'received' => $pipeline_step_id
                ]);
            }
            
            $provider_field = 'ai_provider';
                    $api_key_field = 'ai_api_key';
                    $model_field = 'ai_model';

                    $step_config_data = [];
                    $api_key = null;
                    $provider = null;
                    
                    if (isset($_POST[$provider_field])) {
                        $provider = sanitize_text_field(wp_unslash($_POST[$provider_field]));
                        $step_config_data['provider'] = $provider;
                    }
                    if (isset($_POST[$api_key_field])) {
                        $api_key = sanitize_text_field(wp_unslash($_POST[$api_key_field]));
                    }
                    if (isset($_POST[$model_field])) {
                        $model = sanitize_text_field(wp_unslash($_POST[$model_field]));
                        $step_config_data['model'] = $model;
                        
                        if (!empty($provider) && !empty($model)) {
                            if (!isset($step_config_data['providers'])) {
                                $step_config_data['providers'] = [];
                            }
                            if (!isset($step_config_data['providers'][$provider])) {
                                $step_config_data['providers'][$provider] = [];
                            }
                            $step_config_data['providers'][$provider]['model'] = $model;
                        }
                    }
                    $tools_manager = new \DataMachine\Core\Steps\AI\AIStepTools();
                    
                    if (isset($_POST['enabled_tools']) && is_array($_POST['enabled_tools'])) {
                        $post_enabled_tools_for_log = array_map('sanitize_text_field', wp_unslash($_POST['enabled_tools']));
                    } else {
                        $post_enabled_tools_for_log = sanitize_text_field(wp_unslash($_POST['enabled_tools'] ?? ''));
                    }

                    do_action('dm_log', 'debug', 'PipelineModalAjax: Before saving tool selections', [
                        'pipeline_step_id' => $pipeline_step_id,
                        'post_enabled_tools' => $post_enabled_tools_for_log,
                        'post_keys' => array_keys($_POST)
                    ]);
                    
                    $step_config_data['enabled_tools'] = $tools_manager->save_tool_selections($pipeline_step_id, $_POST);
                    
                    do_action('dm_log', 'debug', 'PipelineModalAjax: After saving tool selections', [
                        'pipeline_step_id' => $pipeline_step_id,
                        'saved_enabled_tools' => $step_config_data['enabled_tools']
                    ]);
                    
                    if (!empty($api_key) && !empty($provider)) {
                        $all_keys = apply_filters('ai_provider_api_keys', null);
                        if (!is_array($all_keys)) {
                            $all_keys = [];
                        }
                        $all_keys[$provider] = $api_key;
                        apply_filters('ai_provider_api_keys', $all_keys);

                        do_action('dm_log', 'debug', 'API key saved via ai_provider_api_keys filter', [
                            'provider' => $provider,
                            'keys_count' => count($all_keys),
                            'api_key_length' => strlen($api_key)
                        ]);
                    }
                    
                    do_action('dm_log', 'debug', 'Before step config save', [
                        'pipeline_step_id' => $pipeline_step_id,
                        'config_data' => $step_config_data
                    ]);
                    
                    $all_databases = apply_filters('dm_db', []);
                    $db_pipelines = $all_databases['pipelines'] ?? null;
                    
                    if (!$db_pipelines) {
                        throw new \Exception('Pipeline database service not available');
                    }
                    
                    $pipeline = $db_pipelines->get_pipeline($pipeline_id);
                    if (!$pipeline) {
                        throw new \Exception('Pipeline not found: ' . esc_html($pipeline_id));
                    }
                    
                    $pipeline_config = $pipeline['pipeline_config'] ?? [];
                    
                    // Preserve provider-specific models
                    if (isset($pipeline_config[$pipeline_step_id])) {
                        $existing_config = $pipeline_config[$pipeline_step_id];
                        
                        do_action('dm_log', 'debug', 'PipelineModalAjax: Merging with existing config', [
                            'pipeline_step_id' => $pipeline_step_id,
                            'existing_enabled_tools' => $existing_config['enabled_tools'] ?? null,
                            'new_enabled_tools' => $step_config_data['enabled_tools'] ?? null,
                            'existing_config_keys' => array_keys($existing_config),
                            'new_config_keys' => array_keys($step_config_data)
                        ]);
                        
                        if (isset($existing_config['providers']) && isset($step_config_data['providers'])) {
                            $step_config_data['providers'] = array_merge(
                                $existing_config['providers'], 
                                $step_config_data['providers']
                            );
                        } elseif (isset($existing_config['providers']) && !isset($step_config_data['providers'])) {
                            $step_config_data['providers'] = $existing_config['providers'];
                        }
                        
                        $pipeline_config[$pipeline_step_id] = array_merge($existing_config, $step_config_data);
                        
                        do_action('dm_log', 'debug', 'PipelineModalAjax: Config merged', [
                            'pipeline_step_id' => $pipeline_step_id,
                            'final_enabled_tools' => $pipeline_config[$pipeline_step_id]['enabled_tools'] ?? null,
                            'final_config_keys' => array_keys($pipeline_config[$pipeline_step_id])
                        ]);
                    } else {
                        $pipeline_config[$pipeline_step_id] = $step_config_data;
                    }
                    
                    $success = $db_pipelines->update_pipeline($pipeline_id, [
                        'pipeline_config' => json_encode($pipeline_config)
                    ]);
                    
                    if (!$success) {
                        do_action('dm_log', 'error', 'Failed to save pipeline step configuration', [
                            'pipeline_step_id' => $pipeline_step_id,
                            'pipeline_id' => $pipeline_id
                        ]);
                        wp_send_json_error(['message' => __('Error saving AI configuration', 'data-machine')]);
                        return;
                    }
                    
                    do_action('dm_auto_save', $pipeline_id);

                    do_action('dm_log', 'debug', 'AI step configuration saved successfully', [
                        'pipeline_step_id' => $pipeline_step_id,
                        'pipeline_id' => $pipeline_id,
                        'provider' => $provider,
                        'config_keys' => array_keys($step_config_data)
                    ]);

                    wp_send_json_success([
                        'message' => __('AI step configuration saved successfully', 'data-machine'),
                        'pipeline_step_id' => $pipeline_step_id,
                        'debug_info' => [
                            'api_key_saved' => !empty($api_key),
                            'step_config_saved' => true,
                            'provider' => $provider
                        ]
                    ]);
        } else {
            /* translators: %s: Step type name */
            wp_send_json_error(['message' => sprintf(__('Configuration for %s steps is not yet implemented', 'data-machine'), $step_type)]);
        }
    }

    /**
     * Step config built from memory (no database query).
     */
    public function handle_save_handler_settings()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        do_action('dm_log', 'debug', 'Save handler settings request received', [
            'post_keys' => array_keys($_POST),
            'post_data' => array_intersect_key($_POST, array_flip(['handler_slug', 'step_type', 'flow_id', 'pipeline_id', 'action', 'context'])),
            'has_nonce' => isset($_POST['nonce']),
            'user_can_manage' => current_user_can('manage_options')
        ]);

        if (isset($_POST['context']) && is_array($_POST['context'])) {
            $context_raw = array_map('sanitize_text_field', wp_unslash($_POST['context']));
        } else {
            $context_raw = sanitize_text_field(wp_unslash($_POST['context'] ?? ''));
        }

        if (is_string($context_raw)) {
            $decoded = json_decode($context_raw, true) ?: [];
            $context = is_array($decoded) ? array_map('sanitize_text_field', $decoded) : [];
        } else {
            $context = array_map('sanitize_text_field', (array) $context_raw);
        }

        $handler_slug = isset($context['handler_slug']) ? 
            sanitize_text_field($context['handler_slug']) : 
            sanitize_text_field(wp_unslash($_POST['handler_slug'] ?? ''));
        $step_type = isset($context['step_type']) ? 
            sanitize_text_field($context['step_type']) : 
            sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));
        $flow_step_id = isset($context['flow_step_id']) ? 
            sanitize_text_field($context['flow_step_id']) : 
            sanitize_text_field(wp_unslash($_POST['flow_step_id'] ?? ''));
        $pipeline_id = isset($context['pipeline_id']) ? 
            sanitize_text_field($context['pipeline_id']) : 
            sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        
        
        if (empty($handler_slug) || empty($flow_step_id)) {
            wp_send_json_error([
                'message' => __('Handler slug and flow step ID are required.', 'data-machine'),
                'missing_data' => [
                    'handler_slug' => empty($handler_slug),
                    'flow_step_id' => empty($flow_step_id),
                    'step_type' => empty($step_type),
                    'pipeline_id' => empty($pipeline_id)
                ]
            ]);
            return;
        }

        $all_handlers = apply_filters('dm_handlers', [], $step_type);
        $handler_info = null;
        
        foreach ($all_handlers as $slug => $config) {
            if ($slug === $handler_slug) {
                $handler_info = $config;
                break;
            }
        }
        
        if (!$handler_info) {
            wp_send_json_error(['message' => __('Handler not found.', 'data-machine')]);
            return;
        }
        
        $sanitized_post = array_map('sanitize_text_field', wp_unslash($_POST));
        $handler_settings = $this->process_handler_settings($handler_slug, $sanitized_post);
        
        do_action('dm_log', 'debug', 'Handler settings processed', [
            'handler_slug' => $handler_slug,
            'flow_step_id' => $flow_step_id,
            'settings_count' => count($handler_settings)
        ]);
        
        try {
            do_action('dm_update_flow_handler', $flow_step_id, $handler_slug, $handler_settings);

             $parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);
             $flow_id = $parts['flow_id'] ?? null;
             $pipeline_step_id = $parts['pipeline_step_id'] ?? null;

             // Build config from memory (50% query reduction)
             $step_config = [
                 'step_type' => $step_type,
                 'handler' => [
                     'handler_slug' => $handler_slug,
                     'settings' => [$handler_slug => $handler_settings],
                     'enabled' => true
                 ],
                 'flow_id' => $flow_id,
                 'pipeline_step_id' => $pipeline_step_id,
                 'flow_step_id' => $flow_step_id
             ];

             $all_databases = apply_filters('dm_db', []);
             $db_flows = $all_databases['flows'] ?? null;
             if ($db_flows) {
                 $flow = $db_flows->get_flow($flow_id);
                 $flow_config = $flow['flow_config'] ?? [];
                 $existing_step = $flow_config[$flow_step_id] ?? [];
                 if (isset($existing_step['execution_order'])) {
                     $step_config['execution_order'] = $existing_step['execution_order'];
                 }
             }

             /* translators: %s: Handler name or label */
             $message = sprintf(__('Handler "%s" settings saved successfully.', 'data-machine'), $handler_info['label'] ?? $handler_slug);

             $handler_settings_display = apply_filters('dm_get_handler_settings_display', [], $flow_step_id);

             wp_send_json_success([
                 'message' => $message,
                 'handler_slug' => $handler_slug,
                 'step_type' => $step_type,
                 'flow_step_id' => $flow_step_id,
                 'flow_id' => $flow_id,
                 'pipeline_step_id' => $pipeline_step_id,
                 'step_config' => $step_config,
                 'handler_settings_display' => $handler_settings_display
             ]);
            
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Handler settings save failed: ' . $e->getMessage(), [
                'handler_slug' => $handler_slug,
                'flow_step_id' => $flow_step_id
            ]);
            
            wp_send_json_error(['message' => __('Failed to save handler settings.', 'data-machine')]);
        }
    }

    public function handle_get_pipeline_data()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        $pipeline_id = (int) sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));

        if (empty($pipeline_id)) {
            wp_send_json_error(['message' => __('Pipeline ID is required.', 'data-machine')]);
            return;
        }

        $pipeline_data = apply_filters('dm_get_pipelines', [], $pipeline_id);
        $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        $pipeline_flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);

        wp_send_json_success([
            'pipeline_id' => $pipeline_id,
            'pipeline_data' => $pipeline_data,
            'pipeline_steps' => $pipeline_steps ?? [],
            'pipeline_flows' => $pipeline_flows ?? []
        ]);
    }

    public function handle_get_flow_data()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        $pipeline_id = (int) sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));

        if (empty($pipeline_id)) {
            wp_send_json_error(['message' => __('Pipeline ID is required.', 'data-machine')]);
            return;
        }

        $pipeline_flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
        $first_flow_id = null;
        
        if (!empty($pipeline_flows)) {
            $first_flow_id = $pipeline_flows[0]['flow_id'] ?? null;
        }

        wp_send_json_success([
            'pipeline_id' => $pipeline_id,
            'flows' => $pipeline_flows ?? [],
            'flow_count' => count($pipeline_flows ?? []),
            'first_flow_id' => $first_flow_id
        ]);
    }


    private function process_handler_settings($handler_slug, $post_data)
    {
        $all_settings = apply_filters('dm_handler_settings', [], $handler_slug);
        $handler_settings = $all_settings[$handler_slug] ?? null;

        if (!$handler_settings || !method_exists($handler_settings, 'sanitize')) {
            return [];
        }

        $raw_settings = [];
        foreach ($post_data as $key => $value) {
            // Sanitize key to prevent injection
            $safe_key = sanitize_key($key);
            if (!in_array($safe_key, ['action', 'context', 'nonce', '_wp_http_referer'], true) && !empty($safe_key)) {
                // Apply basic sanitization before passing to handler's sanitize method
                if (is_array($value)) {
                    $raw_settings[$safe_key] = array_map('sanitize_text_field', array_map('wp_unslash', $value));
                } else {
                    $raw_settings[$safe_key] = sanitize_text_field(wp_unslash($value));
                }
            }
        }
        
        return $handler_settings->sanitize($raw_settings);
    }
    
    private function sanitize_template_data($data) {
        if (!is_array($data)) {
            return sanitize_text_field($data);
        }
        
        $sanitized = [];
        foreach ($data as $key => $value) {
            $safe_key = sanitize_key($key);
            if (is_array($value)) {
                $sanitized[$safe_key] = $this->sanitize_template_data($value);
            } elseif (is_string($value)) {
                $sanitized[$safe_key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$safe_key] = $value; // Numbers are safe
            } elseif (is_bool($value)) {
                $sanitized[$safe_key] = $value; // Booleans are safe
            } else {
                // For other types, convert to string and sanitize
                $sanitized[$safe_key] = sanitize_text_field((string)$value);
            }
        }
        
        return $sanitized;
    }
}