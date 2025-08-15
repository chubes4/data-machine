<?php
/**
 * Pipeline Modal AJAX Handler
 *
 * Handles modal and template rendering AJAX operations (UI support).
 * Manages modal content generation, template requests, and modal action processing.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelineModalAjax
{
    /**
     * Handle pipeline modal AJAX requests (UI support)
     */
    // Routing wrapper method removed - individual WordPress action hooks call methods directly



    /**
     * Get rendered template with provided data
     * Dedicated endpoint for template rendering to maintain architecture consistency
     * Security handled by dm_ajax_route system
     */
    public function handle_get_template()
    {
        // Remove fallbacks - require explicit data
        if (!isset($_POST['template'])) {
            wp_send_json_error(['message' => __('Template parameter is required', 'data-machine')]);
        }
        if (!isset($_POST['template_data'])) {
            wp_send_json_error(['message' => __('Template data parameter is required', 'data-machine')]);
        }
        
        $template = sanitize_text_field(wp_unslash($_POST['template']));
        $template_data = json_decode(wp_unslash($_POST['template_data']), true);
        
        if (!is_array($template_data)) {
            wp_send_json_error(['message' => __('Invalid template data format', 'data-machine')]);
        }

        if (empty($template)) {
            wp_send_json_error(['message' => __('Template name is required', 'data-machine')]);
        }
        
        // For step-card templates in AJAX context, add sensible defaults for UI rendering
        if ($template === 'page/pipeline-step-card' || $template === 'page/flow-step-card') {
            if (!isset($template_data['is_first_step'])) {
                // AJAX-rendered steps default to showing arrows (safer for dynamic UI)
                $template_data['is_first_step'] = false;
            }
            
            if (!isset($template_data['step']['is_empty'])) {
                // AJAX-rendered steps are typically populated (not empty)
                $template_data['step']['is_empty'] = false;
            }
        }

        // Use universal template rendering system
        $content = apply_filters('dm_render_template', '', $template, $template_data);
        
        if ($content) {
            wp_send_json_success([
                'html' => $content,
                'template' => $template
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Template "%s" not found', 'data-machine'), $template)
            ]);
        }
    }

    /**
     * Get flow step card data for template rendering
     * Security handled by dm_ajax_route system
     */
    public function handle_get_flow_step_card()
    {
        $step_type = sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));
        $flow_id = sanitize_text_field(wp_unslash($_POST['flow_id'] ?? 'new'));
        $pipeline_id = (int) ($_POST['pipeline_id'] ?? 0);
        
        if (empty($step_type)) {
            wp_send_json_error(['message' => __('Step type is required', 'data-machine')]);
        }

        // Validate step type exists using pure discovery
        $all_steps = apply_filters('dm_steps', []);
        $step_config = $all_steps[$step_type] ?? null;
        if (!$step_config) {
            wp_send_json_error(['message' => __('Invalid step type', 'data-machine')]);
        }

        // Check if this is the first flow step by counting existing steps in flows
        $flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
        $is_first_step = empty($flows) || empty($flows[0]['flow_config'] ?? []);

        // Simplified data - let centralized system resolve context requirements
        $template_data = [
            'step' => [
                'step_type' => $step_type,
                'step_config' => []  // Empty config for new steps
            ],
            'flow_id' => $flow_id,
            'pipeline_id' => $pipeline_id,
            'is_first_step' => $is_first_step
        ];

        wp_send_json_success([
            'template_data' => $template_data,
            'step_type' => $step_type,
            'flow_id' => $flow_id
        ]);
    }

    /**
     * Get flow configuration for step card updates
     * Security handled by dm_ajax_route system
     */
    public function handle_get_flow_config()
    {
        $flow_id = (int) sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));

        if (empty($flow_id)) {
            wp_send_json_error(['message' => __('Flow ID is required.', 'data-machine')]);
            return;
        }

        // Get flow configuration using centralized filter
        $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
        
        // Return success even with empty config (normal for new flows)
        wp_send_json_success([
            'flow_id' => $flow_id,
            'flow_config' => $flow_config ?? []
        ]);
    }

    /**
     * Handle step configuration save action
     * Security handled by dm_ajax_route system
     */
    public function handle_configure_step_action()
    {
        // Get context data from AJAX request - no fallbacks
        if (!isset($_POST['context'])) {
            wp_send_json_error(['message' => __('Context data is required', 'data-machine')]);
        }
        
        $context = $_POST['context'] ?? [];
        
        // Handle jQuery's natural JSON string serialization
        if (is_string($context)) {
            $context = json_decode(wp_unslash($context), true);
        }
        
        // Context data should be a native array after JSON decoding
        if (!is_array($context)) {
            wp_send_json_error([
                'message' => __('Invalid context data format - expected array', 'data-machine'),
                'context_type' => gettype($_POST['context'] ?? null),
                'received_context' => $_POST['context'] ?? null
            ]);
        }
        
        // Validate required context fields with enhanced debugging
        $required_fields = ['step_type', 'pipeline_id', 'pipeline_step_id'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (!isset($context[$field]) || empty($context[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            wp_send_json_error([
                'message' => sprintf(__('Required fields missing: %s', 'data-machine'), implode(', ', $missing_fields)),
                'missing_fields' => $missing_fields,
                'received_context' => array_keys($context),
                'context_values' => $context,
                'debug_info' => [
                    'post_keys' => array_keys($_POST),
                    'context_source' => is_string($_POST['context'] ?? null) ? 'json_string' : 'array'
                ]
            ]);
        }
        
        $step_type = sanitize_text_field($context['step_type']);
        $pipeline_id = sanitize_text_field($context['pipeline_id']);
        $pipeline_step_id = sanitize_text_field($context['pipeline_step_id']);
        
        if (empty($step_type)) {
            wp_send_json_error(['message' => __('Step type is required', 'data-machine')]);
        }
        
        // Handle AI step configuration
        if ($step_type === 'ai') {
            // FAIL FAST - require pipeline_step_id for unique configuration
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
            
            // Validate pipeline_step_id format (should be UUID4)
            if (!wp_is_uuid($pipeline_step_id)) {
                wp_send_json_error([
                    'message' => __('Pipeline step ID format invalid - expected UUID', 'data-machine'),
                    'received' => $pipeline_step_id
                ]);
            }
            
            // Save AI HTTP Client step-aware configuration using actions
            try {
                    
                    // Get form data using step-aware field names (no fallbacks)
                    $form_data = [];
                    
                    // AI HTTP Client now uses standard field names - Data Machine handles step-specific storage
                    $provider_field = 'ai_provider';
                    $api_key_field = 'ai_api_key';
                    $model_field = 'ai_model';
                    $system_prompt_field = 'ai_system_prompt';
                    
                    // Log received field names for debugging
                    do_action('dm_log', 'debug', 'AI step configuration field mapping', [
                        'pipeline_step_id' => $pipeline_step_id,
                        'expected_fields' => [
                            'provider' => $provider_field,
                            'api_key' => $api_key_field,
                            'model' => $model_field
                        ],
                        'received_post_keys' => array_keys($_POST)
                    ]);
                    
                    // Extract AI configuration with proper field names
                    $step_config_data = []; // Step config data (no API key)
                    $api_key = null; // API key for shared storage
                    $provider = null; // Provider for API key storage
                    
                    if (isset($_POST[$provider_field])) {
                        $provider = sanitize_text_field(wp_unslash($_POST[$provider_field]));
                        $step_config_data['provider'] = $provider;
                    }
                    if (isset($_POST[$api_key_field])) {
                        $api_key = sanitize_text_field(wp_unslash($_POST[$api_key_field]));
                        // API key goes to shared storage, NOT step config
                    }
                    if (isset($_POST[$model_field])) {
                        $model = sanitize_text_field(wp_unslash($_POST[$model_field]));
                        $step_config_data['model'] = $model;
                        
                        // Also store model per provider for provider switching
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
                    if (isset($_POST[$system_prompt_field])) {
                        $step_config_data['system_prompt'] = sanitize_textarea_field(wp_unslash($_POST[$system_prompt_field]));
                    }
                    if (isset($_POST['system_prompt'])) {
                        $step_config_data['system_prompt'] = sanitize_textarea_field(wp_unslash($_POST['system_prompt']));
                    }
                    
                    // Save API key via unified ai_provider_api_keys filter (replace per-provider option storage)
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
                    
                    // Save step configuration to pipeline database
                    do_action('dm_log', 'debug', 'Before step config save', [
                        'pipeline_step_id' => $pipeline_step_id,
                        'config_data' => $step_config_data
                    ]);
                    
                    // Get current pipeline configuration
                    $all_databases = apply_filters('dm_db', []);
                    $db_pipelines = $all_databases['pipelines'] ?? null;
                    
                    if (!$db_pipelines) {
                        throw new \Exception('Pipeline database service not available');
                    }
                    
                    $pipeline = $db_pipelines->get_pipeline($pipeline_id);
                    if (!$pipeline) {
                        throw new \Exception('Pipeline not found: ' . $pipeline_id);
                    }
                    
                    // Get current step configuration
                    $pipeline_config = is_string($pipeline['pipeline_config']) 
                        ? json_decode($pipeline['pipeline_config'], true) 
                        : ($pipeline['pipeline_config'] ?? []);
                    
                    if (!is_array($pipeline_config)) {
                        $pipeline_config = [];
                    }
                    
                    // Merge with existing step configuration to preserve provider-specific models
                    if (isset($pipeline_config[$pipeline_step_id])) {
                        $existing_config = $pipeline_config[$pipeline_step_id];
                        
                        // Preserve existing provider models
                        if (isset($existing_config['providers']) && isset($step_config_data['providers'])) {
                            $step_config_data['providers'] = array_merge(
                                $existing_config['providers'], 
                                $step_config_data['providers']
                            );
                        } elseif (isset($existing_config['providers']) && !isset($step_config_data['providers'])) {
                            $step_config_data['providers'] = $existing_config['providers'];
                        }
                        
                        // Merge with existing config
                        $pipeline_config[$pipeline_step_id] = array_merge($existing_config, $step_config_data);
                    } else {
                        $pipeline_config[$pipeline_step_id] = $step_config_data;
                    }
                    
                    // Save updated pipeline configuration using dm_auto_save
                    $success = $db_pipelines->update_pipeline($pipeline_id, [
                        'pipeline_config' => json_encode($pipeline_config)
                    ]);
                    
                    if (!$success) {
                        throw new \Exception('Failed to save pipeline step configuration');
                    }
                    
                    // Trigger auto-save for additional processing
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
                    
                } catch (Exception $e) {
                    do_action('dm_log', 'error', 'AI step configuration save error: ' . $e->getMessage());
                    wp_send_json_error(['message' => __('Error saving AI configuration', 'data-machine')]);
                }
        } else {
            // Handle other step types in the future
            wp_send_json_error(['message' => sprintf(__('Configuration for %s steps is not yet implemented', 'data-machine'), $step_type)]);
        }
    }


    /**
     * Handle add handler action with proper update vs replace logic
     * Security handled by dm_ajax_route system
     */
    public function handle_add_handler_action()
    {
        // Pure discovery approach - get only essential data from context
        $context = $_POST['context'] ?? [];
        if (is_string($context)) {
            $context = json_decode($context, true) ?: [];
        }
        
        // Get required context - user clicked on specific flow step
        $handler_slug = sanitize_text_field($context['handler_slug'] ?? '');
        $flow_step_id = sanitize_text_field($context['flow_step_id'] ?? '');
        
        if (empty($handler_slug)) {
            wp_send_json_error(['message' => __('Handler slug is required', 'data-machine')]);
        }
        
        if (empty($flow_step_id)) {
            wp_send_json_error(['message' => __('Flow step ID is required', 'data-machine')]);
        }
        
        // Validate handler exists
        $all_handlers = apply_filters('dm_handlers', []);
        $handler_info = null;
        
        foreach ($all_handlers as $slug => $config) {
            if ($slug === $handler_slug) {
                $handler_info = $config;
                break;
            }
        }
        
        if (!$handler_info) {
            wp_send_json_error(['message' => __('Handler not found', 'data-machine')]);
        }
        
        // Process handler settings from form data
        $saved_handler_settings = $this->process_handler_settings($handler_slug);
        
        // Add handler to the specific flow step user clicked on
        do_action('dm_update_flow_handler', $flow_step_id, $handler_slug, $saved_handler_settings);
        
        // Extract flow_id for JavaScript response
        $parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);
        $flow_id = $parts['flow_id'] ?? null;
        
        wp_send_json_success([
            'message' => sprintf(__('Handler "%s" added to flow successfully', 'data-machine'), $handler_info['label'] ?? $handler_slug),
            'handler_slug' => $handler_slug,
            'flow_step_id' => $flow_step_id,
            'flow_id' => $flow_id,
            'action_type' => 'added'
        ]);
    }



    /**
     * Handle AJAX file upload with handler context support
     */
    public function handle_upload_file()
    {
        
        // Check if file was uploaded
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => __('File upload failed.', 'data-machine')]);
            return;
        }
        
        $file = $_FILES['file'];
        
        // Extract flow_step_id from request for proper file isolation
        // Use the flow_step_id provided by the frontend form
        $flow_step_id = sanitize_text_field($_POST['flow_step_id'] ?? '');
        
        if (empty($flow_step_id)) {
            wp_send_json_error(['message' => __('Missing flow step ID from form data.', 'data-machine')]);
            return;
        }
        
        try {
            // Basic validation - file size and dangerous extensions
            $file_size = filesize($file['tmp_name']);
            if ($file_size === false) {
                throw new \Exception(__('Cannot determine file size.', 'data-machine'));
            }
            
            // 32MB limit
            $max_file_size = 32 * 1024 * 1024;
            if ($file_size > $max_file_size) {
                throw new \Exception(sprintf(
                    __('File too large: %1$s. Maximum allowed size: %2$s', 'data-machine'),
                    size_format($file_size),
                    size_format($max_file_size)
                ));
            }

            // Block dangerous extensions
            $dangerous_extensions = ['php', 'exe', 'bat', 'cmd', 'scr'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $dangerous_extensions)) {
                throw new \Exception(__('File type not allowed for security reasons.', 'data-machine'));
            }
            
            // Use repository to store file with handler context
            $repositories = apply_filters('dm_files_repository', []);
            $repository = $repositories['files'] ?? null;
            if (!$repository) {
                wp_send_json_error(['message' => __('File repository service not available.', 'data-machine')]);
                return;
            }
            
            $stored_path = $repository->store_file($file['tmp_name'], $file['name'], $flow_step_id);
            
            if (!$stored_path) {
                wp_send_json_error(['message' => __('Failed to store file.', 'data-machine')]);
                return;
            }
            
            // Get file info for response
            $file_info = $repository->get_file_info(basename($stored_path));
            
            do_action('dm_log', 'debug', 'File uploaded successfully via AJAX.', [
                'filename' => $file['name'],
                'stored_path' => $stored_path,
                'flow_step_id' => $flow_step_id
            ]);
            
            wp_send_json_success([
                'file_info' => $file_info,
                'message' => sprintf(__('File "%s" uploaded successfully.', 'data-machine'), $file['name'])
            ]);
            
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'File upload failed.', [
                'filename' => $file['name'],
                'error' => $e->getMessage(),
                'flow_step_id' => $flow_step_id
            ]);
            
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }


    /**
     * Handle save handler settings action
     * Security handled by dm_ajax_route system
     */
    public function handle_save_handler_settings()
    {
        // Enhanced debugging for save handler process
        do_action('dm_log', 'debug', 'Save handler settings request received', [
            'post_keys' => array_keys($_POST),
            'post_data' => array_intersect_key($_POST, array_flip(['handler_slug', 'step_type', 'flow_id', 'pipeline_id', 'action'])),
            'has_nonce' => isset($_POST['handler_settings_nonce']),
            'user_can_manage' => current_user_can('manage_options')
        ]);
        
        // Note: nonce verification handled by dm_ajax_route system
        
        // Get and validate required form data
        $handler_slug = sanitize_text_field(wp_unslash($_POST['handler_slug'] ?? ''));
        $step_type = sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));
        $flow_step_id = sanitize_text_field(wp_unslash($_POST['flow_step_id'] ?? ''));
        $pipeline_id = sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        
        if (empty($handler_slug) || empty($step_type) || empty($flow_step_id) || empty($pipeline_id)) {
            wp_send_json_error([
                'message' => __('Required fields are missing.', 'data-machine'),
                'missing_data' => [
                    'handler_slug' => empty($handler_slug),
                    'step_type' => empty($step_type), 
                    'flow_step_id' => empty($flow_step_id),
                    'pipeline_id' => empty($pipeline_id)
                ]
            ]);
            return;
        }
        
        // Validate handler exists
        $all_handlers = apply_filters('dm_handlers', []);
        if (!isset($all_handlers[$handler_slug])) {
            wp_send_json_error(['message' => __('Invalid handler.', 'data-machine')]);
            return;
        }
        
        // Process handler settings using existing method
        $handler_settings = $this->process_handler_settings($handler_slug);
        
        do_action('dm_log', 'debug', 'Handler settings processed', [
            'handler_slug' => $handler_slug,
            'flow_step_id' => $flow_step_id,
            'settings_count' => count($handler_settings)
        ]);
        
        // Save handler settings to flow step
        try {
            do_action('dm_update_flow_handler', $flow_step_id, $handler_slug, $handler_settings);
            
            // Extract flow_id for JavaScript response
            $parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);
            $flow_id = $parts['flow_id'] ?? null;
            
            wp_send_json_success([
                'message' => sprintf(__('Handler "%s" settings saved successfully.', 'data-machine'), $handler_slug),
                'handler_slug' => $handler_slug,
                'flow_step_id' => $flow_step_id,
                'flow_id' => $flow_id
            ]);
            
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Handler settings save failed: ' . $e->getMessage(), [
                'handler_slug' => $handler_slug,
                'flow_step_id' => $flow_step_id
            ]);
            
            wp_send_json_error(['message' => __('Failed to save handler settings.', 'data-machine')]);
        }
    }

    /**
     * Process handler settings from form data
     */
    private function process_handler_settings($handler_slug)
    {
        $all_settings = apply_filters('dm_handler_settings', []);
        $handler_settings = $all_settings[$handler_slug] ?? null;
        
        if (!$handler_settings || !method_exists($handler_settings, 'sanitize')) {
            return [];
        }
        
        $raw_settings = [];
        foreach ($_POST as $key => $value) {
            if (!in_array($key, ['action', 'context', 'nonce', '_wp_http_referer'])) {
                $raw_settings[$key] = $value;
            }
        }
        
        return $handler_settings->sanitize($raw_settings);
    }
}