<?php
/**
 * Pipeline Modal AJAX Handler
 *
 * Handles modal and template rendering AJAX operations (UI support).
 * Manages modal content generation, template requests, and configuration processing.
 * Handles 7 AJAX actions: templates, configuration, data retrieval, and settings.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Ajax;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelineModalAjax
{
    /**
     * Register all pipeline modal AJAX handlers.
     *
     * Self-contained registration pattern following WordPress-native approach.
     * Registers all modal-related AJAX actions this class handles.
     *
     * @since 1.0.0
     */
    public static function register() {
        $instance = new self();
        
        // Modal and template AJAX actions
        add_action('wp_ajax_dm_get_template', [$instance, 'handle_get_template']);
        add_action('wp_ajax_dm_get_flow_step_card', [$instance, 'handle_get_flow_step_card']);
        add_action('wp_ajax_dm_get_flow_config', [$instance, 'handle_get_flow_config']);
        add_action('wp_ajax_dm_configure_step_action', [$instance, 'handle_configure_step_action']);
        add_action('wp_ajax_dm_save_handler_settings', [$instance, 'handle_save_handler_settings']);
        add_action('wp_ajax_dm_get_pipeline_data', [$instance, 'handle_get_pipeline_data']);
        add_action('wp_ajax_dm_get_flow_data', [$instance, 'handle_get_flow_data']);
    }

    /**
     * Core AJAX handler methods for pipeline modal operations.
     * 
     * Provides UI support functionality for modal rendering, step configuration,
     * and handler settings management within the pipeline editor interface.
     */



    /**
     * Render template with provided data via AJAX.
     *
     * Universal template rendering endpoint that processes template requests
     * and injects contextual data for pipeline step cards and modal content.
     * Includes AJAX-specific defaults for UI rendering consistency.
     */
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
        $raw_template_data = sanitize_textarea_field(wp_unslash($_POST['template_data'] ?? ''));
        
        // Validate JSON structure before decoding
        if (!is_string($raw_template_data)) {
            wp_send_json_error(['message' => __('Template data must be a JSON string', 'data-machine')]);
        }
        
        $template_data = json_decode($raw_template_data, true);
        
        if (!is_array($template_data)) {
            wp_send_json_error(['message' => __('Invalid template data format', 'data-machine')]);
        }
        
        // Recursively sanitize template data array
        $template_data = $this->sanitize_template_data($template_data);

        if (empty($template)) {
            wp_send_json_error(['message' => __('Template name is required', 'data-machine')]);
        }
        
        // For step-card templates in AJAX context, add sensible defaults for UI rendering
        if ($template === 'page/pipeline-step-card' || $template === 'page/flow-step-card') {
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
                /* translators: %s: Template name */
                'message' => sprintf(__('Template "%s" not found', 'data-machine'), $template)
            ]);
        }
    }

    /**
     * Generate flow step card template data for AJAX rendering.
     *
     * Validates step type against registered steps and constructs template data
     * for new step cards including flow context and first-step detection logic.
     */
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
     */
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

        // Get flow configuration using centralized filter
        $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
        
        // Return success even with empty config (normal for new flows)
        wp_send_json_success([
            'flow_id' => $flow_id,
            'flow_config' => $flow_config ?? []
        ]);
    }

    /**
     * Process and save AI step configuration data.
     *
     * Handles AI HTTP Client integration by:
     * - Extracting step-specific configuration from form data
     * - Saving API keys to unified ai_provider_api_keys storage
     * - Storing step configuration in pipeline database with provider-specific model persistence
     * - Validating UUID4 format for pipeline_step_id
     */
    public function handle_configure_step_action()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        // Get context data from AJAX request
        if (!isset($_POST['context'])) {
            wp_send_json_error(['message' => __('Context data is required', 'data-machine')]);
        }

        $context_raw = wp_unslash($_POST['context'] ?? []); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $context = is_string($context_raw)
            ? array_map('sanitize_text_field', json_decode($context_raw, true) ?: [])
            : array_map('sanitize_text_field', $context_raw);

        // Context data should be a native array after sanitization
        if (!is_array($context)) {
            wp_send_json_error([
                'message' => __('Invalid context data format - expected array', 'data-machine'),
                'context_type' => gettype($context),
                'received_context' => $context
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
                /* translators: %s: List of missing field names */
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
                    
                    // Get form data using step-aware field names
                    $form_data = [];
                    
                    // AI HTTP Client now uses standard field names - Data Machine handles step-specific storage
                    $provider_field = 'ai_provider';
                    $api_key_field = 'ai_api_key';
                    $model_field = 'ai_model';
                    
                    
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
                    // Process enabled tools using centralized tools management
                    require_once dirname(__DIR__, 3) . '/Steps/AI/AIStepTools.php';
                    $tools_manager = new \DataMachine\Core\Steps\AI\AIStepTools();
                    
                    do_action('dm_log', 'debug', 'PipelineModalAjax: Before saving tool selections', [
                        'pipeline_step_id' => $pipeline_step_id,
                        'post_enabled_tools' => array_map('sanitize_text_field', wp_unslash($_POST['enabled_tools'] ?? [])),
                        'post_keys' => array_keys($_POST)
                    ]);
                    
                    $step_config_data['enabled_tools'] = $tools_manager->save_tool_selections($pipeline_step_id, $_POST);
                    
                    do_action('dm_log', 'debug', 'PipelineModalAjax: After saving tool selections', [
                        'pipeline_step_id' => $pipeline_step_id,
                        'saved_enabled_tools' => $step_config_data['enabled_tools']
                    ]);
                    
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
                    $pipeline_config = $pipeline['pipeline_config'] ?? [];
                    
                    // Merge with existing step configuration to preserve provider-specific models
                    if (isset($pipeline_config[$pipeline_step_id])) {
                        $existing_config = $pipeline_config[$pipeline_step_id];
                        
                        do_action('dm_log', 'debug', 'PipelineModalAjax: Merging with existing config', [
                            'pipeline_step_id' => $pipeline_step_id,
                            'existing_enabled_tools' => $existing_config['enabled_tools'] ?? null,
                            'new_enabled_tools' => $step_config_data['enabled_tools'] ?? null,
                            'existing_config_keys' => array_keys($existing_config),
                            'new_config_keys' => array_keys($step_config_data)
                        ]);
                        
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
                        
                        do_action('dm_log', 'debug', 'PipelineModalAjax: Config merged', [
                            'pipeline_step_id' => $pipeline_step_id,
                            'final_enabled_tools' => $pipeline_config[$pipeline_step_id]['enabled_tools'] ?? null,
                            'final_config_keys' => array_keys($pipeline_config[$pipeline_step_id])
                        ]);
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
            /* translators: %s: Step type name */
            wp_send_json_error(['message' => sprintf(__('Configuration for %s steps is not yet implemented', 'data-machine'), $step_type)]);
        }
    }





    /**
     * Save handler-specific settings to flow step configuration.
     *
     * Unified method that handles both adding new handlers and updating existing handler settings.
     * Processes form data through handler's sanitize() method and updates flow step configuration 
     * via dm_update_flow_handler action. Provides immediate flow configuration refresh for UI consistency.
     */
    public function handle_save_handler_settings()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        
        // Enhanced debugging for save handler process
        do_action('dm_log', 'debug', 'Save handler settings request received', [
            'post_keys' => array_keys($_POST),
            'post_data' => array_intersect_key($_POST, array_flip(['handler_slug', 'step_type', 'flow_id', 'pipeline_id', 'action', 'context'])),
            'has_nonce' => isset($_POST['handler_settings_nonce']),
            'user_can_manage' => current_user_can('manage_options')
        ]);

        // Handle both context-based (add handler) and direct form data (save settings) scenarios
        $context_raw = wp_unslash($_POST['context'] ?? []); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $context = is_string($context_raw)
            ? array_map('sanitize_text_field', json_decode($context_raw, true) ?: [])
            : array_map('sanitize_text_field', $context_raw);
        
        // Extract data from context if available (add handler scenario), otherwise from direct form fields
        // Note: $context is already unslashed and sanitized above, so only unslash direct $_POST values
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
        
        // Determine if this is an "add" or "update" operation
        $action_type = !empty($context) ? 'added' : 'updated';
        
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
            wp_send_json_error(['message' => __('Handler not found.', 'data-machine')]);
            return;
        }
        
        // Process handler settings using existing method
        $sanitized_post = array_map('sanitize_text_field', wp_unslash($_POST));
        $handler_settings = $this->process_handler_settings($handler_slug, $sanitized_post);
        
        do_action('dm_log', 'debug', 'Handler settings processed', [
            'handler_slug' => $handler_slug,
            'flow_step_id' => $flow_step_id,
            'action_type' => $action_type,
            'settings_count' => count($handler_settings)
        ]);
        
        // Save handler settings to flow step
        try {
            do_action('dm_update_flow_handler', $flow_step_id, $handler_slug, $handler_settings);

            // Extract flow_id for JavaScript response
            $parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);
            $flow_id = $parts['flow_id'] ?? null;

            // Get updated flow configuration for immediate UI update
            $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
            
            // Prepare success message based on action type
            $message = ($action_type === 'added')
                /* translators: %s: Handler name or label */
                ? sprintf(__('Handler "%s" added to flow successfully', 'data-machine'), $handler_info['label'] ?? $handler_slug)
                /* translators: %s: Handler name or label */
                : sprintf(__('Handler "%s" settings saved successfully.', 'data-machine'), $handler_slug);
            
            wp_send_json_success([
                'message' => $message,
                'handler_slug' => $handler_slug,
                'step_type' => $step_type,  // Include step_type for UI updates
                'flow_step_id' => $flow_step_id,
                'flow_id' => $flow_id,
                'flow_config' => $flow_config,
                'action_type' => $action_type
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
     * Get complete pipeline data using filters
     */
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

        // Get pipeline data using filters - reliable thanks to dm_auto_save
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

    /**
     * Get flow data for validation and operations
     */
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

        // Get flow data using filters
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


    /**
     * Extract and sanitize handler settings from POST data.
     *
     * Uses handler's sanitize() method for proper data validation while
     * filtering out WordPress-specific form fields (nonce, action, etc.).
     * Returns sanitized settings array ready for storage.
     *
     * @param string $handler_slug Handler identifier for settings lookup.
     * @param array $post_data Sanitized POST data array.
     * @return array Sanitized handler settings.
     */
    private function process_handler_settings($handler_slug, $post_data)
    {
        $all_settings = apply_filters('dm_handler_settings', []);
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
    
    /**
     * Recursively sanitize template data array to prevent XSS
     * 
     * @param array $data Array to sanitize
     * @return array Sanitized array
     */
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