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
     */
    public function handle_get_template()
    {
        // WordPress-native security: capability check + nonce verification
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed: insufficient permissions.', 'data-machine')]);
            return;
        }
        
        // Verify nonce with standard 'dm_pipeline_ajax' action
        $nonce = wp_unslash($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'dm_pipeline_ajax')) {
            wp_send_json_error(['message' => __('Security check failed: invalid nonce.', 'data-machine')]);
            return;
        }
        
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
     */
    public function handle_get_flow_step_card()
    {
        // WordPress-native security: capability check + nonce verification
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed: insufficient permissions.', 'data-machine')]);
            return;
        }
        
        // Verify nonce with standard 'dm_pipeline_ajax' action
        $nonce = wp_unslash($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'dm_pipeline_ajax')) {
            wp_send_json_error(['message' => __('Security check failed: invalid nonce.', 'data-machine')]);
            return;
        }
        
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
     */
    public function handle_get_flow_config()
    {
        // WordPress-native security: capability check + nonce verification
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed: insufficient permissions.', 'data-machine')]);
            return;
        }
        
        // Verify nonce with standard 'dm_pipeline_ajax' action
        $nonce = wp_unslash($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'dm_pipeline_ajax')) {
            wp_send_json_error(['message' => __('Security check failed: invalid nonce.', 'data-machine')]);
            return;
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
     * Handle step configuration save action
     */
    public function handle_configure_step_action()
    {
        // WordPress-native security: capability check + nonce verification
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed: insufficient permissions.', 'data-machine')]);
            return;
        }
        
        // Verify nonce with standard 'dm_pipeline_ajax' action
        $nonce = wp_unslash($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'dm_pipeline_ajax')) {
            wp_send_json_error(['message' => __('Security check failed: invalid nonce.', 'data-machine')]);
            return;
        }
        
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
            
            // Get AI HTTP Client options manager for step-aware configuration
            if (class_exists('AI_HTTP_Options_Manager')) {
                try {
                    $options_manager = new \AI_HTTP_Options_Manager('data-machine', 'llm');
                    
                    // Get form data using step-aware field names (no fallbacks)
                    $form_data = [];
                    
                    // Convert pipeline_step_id to step_id for AI HTTP Client interface boundary
                    // AI HTTP Client expects step_id for field names and configuration storage
                    $step_id = $pipeline_step_id;
                    
                    // Field names are prefixed with step_id for AI HTTP Client step-aware configuration
                    $provider_field = "ai_step_{$step_id}_provider";
                    $api_key_field = 'ai_api_key';  // API key uses generic name
                    $model_field = "ai_step_{$step_id}_model";
                    $temperature_field = "ai_step_{$step_id}_temperature";
                    $system_prompt_field = "ai_step_{$step_id}_system_prompt";
                    
                    // Log received field names for debugging
                    do_action('dm_log', 'debug', 'AI step configuration field mapping', [
                        'pipeline_step_id' => $pipeline_step_id,
                        'step_id' => $step_id,
                        'expected_fields' => [
                            'provider' => $provider_field,
                            'api_key' => $api_key_field,
                            'model' => $model_field,
                            'temperature' => $temperature_field
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
                        $step_config_data['model'] = sanitize_text_field(wp_unslash($_POST[$model_field]));
                    }
                    if (isset($_POST[$temperature_field])) {
                        $step_config_data['temperature'] = floatval($_POST[$temperature_field]);
                    }
                    if (isset($_POST[$system_prompt_field])) {
                        $step_config_data['system_prompt'] = sanitize_textarea_field(wp_unslash($_POST[$system_prompt_field]));
                    }
                    if (isset($_POST['system_prompt'])) {
                        $step_config_data['system_prompt'] = sanitize_textarea_field(wp_unslash($_POST['system_prompt']));
                    }
                    
                    // CRITICAL FIX: Save API key to shared storage SEPARATELY
                    $api_key_success = true;
                    if (!empty($api_key) && !empty($provider)) {
                        $api_key_success = $options_manager->set_api_key($provider, $api_key);
                        do_action('dm_log', 'debug', 'API key save attempt', [
                            'provider' => $provider,
                            'api_key_length' => strlen($api_key),
                            'save_success' => $api_key_success
                        ]);
                    }
                    
                    // Save step-specific configuration (without API key)
                    do_action('dm_log', 'debug', 'Before step config save', [
                        'pipeline_step_id' => $pipeline_step_id,
                        'step_id' => $step_id,
                        'config_data' => $step_config_data,
                        'options_manager_configured' => $options_manager->is_configured()
                    ]);
                    
                    $step_config_success = $options_manager->save_step_configuration($step_id, $step_config_data);
                    
                    do_action('dm_log', 'debug', 'Step config save attempt', [
                        'pipeline_step_id' => $pipeline_step_id,
                        'step_id' => $step_id,
                        'config_keys' => array_keys($step_config_data),
                        'save_success' => $step_config_success,
                        'option_name' => 'ai_http_client_step_config_data-machine_llm'
                    ]);
                    
                    $success = $api_key_success && $step_config_success;
                    
                    if ($success) {
                        wp_send_json_success([
                            'message' => __('AI step configuration saved successfully', 'data-machine'),
                            'pipeline_step_id' => $pipeline_step_id,
                            'debug_info' => [
                                'api_key_saved' => $api_key_success,
                                'step_config_saved' => $step_config_success,
                                'provider' => $provider
                            ]
                        ]);
                    } else {
                        $error_details = [];
                        if (!$api_key_success) {
                            $error_details[] = 'API key save failed';
                        }
                        if (!$step_config_success) {
                            $error_details[] = 'Step configuration save failed';
                        }
                        
                        wp_send_json_error([
                            'message' => __('Failed to save AI step configuration', 'data-machine'),
                            'details' => implode(', ', $error_details),
                            'debug_info' => [
                                'api_key_success' => $api_key_success,
                                'step_config_success' => $step_config_success,
                                'provider' => $provider,
                                'has_api_key' => !empty($api_key),
                                'api_key_length' => !empty($api_key) ? strlen($api_key) : 0
                            ]
                        ]);
                    }
                    
                } catch (Exception $e) {
                    do_action('dm_log', 'error', 'AI step configuration save error: ' . $e->getMessage());
                    wp_send_json_error(['message' => __('Error saving AI configuration', 'data-machine')]);
                }
            } else {
                wp_send_json_error(['message' => __('AI HTTP Client library not available', 'data-machine')]);
            }
        } else {
            // Handle other step types in the future
            wp_send_json_error(['message' => sprintf(__('Configuration for %s steps is not yet implemented', 'data-machine'), $step_type)]);
        }
    }

    /**
     * Handle add location action for remote locations manager
     */
    public function handle_add_location_action()
    {
        // WordPress-native security: capability check + nonce verification
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed: insufficient permissions.', 'data-machine')]);
            return;
        }
        
        // Verify nonce with standard 'dm_pipeline_ajax' action
        $nonce = wp_unslash($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'dm_pipeline_ajax')) {
            wp_send_json_error(['message' => __('Security check failed: invalid nonce.', 'data-machine')]);
            return;
        }
        
        // Get context data from AJAX request
        $context = $_POST['context'] ?? [];
        
        $handler_slug = sanitize_text_field($context['handler_slug'] ?? '');
        
        if (empty($handler_slug)) {
            wp_send_json_error(['message' => __('Handler slug is required', 'data-machine')]);
        }
        
        // Collect form data for location configuration
        $location_data = [];
        
        // Get standard location fields
        if (isset($_POST['location_name'])) {
            $location_data['location_name'] = sanitize_text_field(wp_unslash($_POST['location_name']));
        }
        if (isset($_POST['location_url'])) {
            $location_data['location_url'] = esc_url_raw(wp_unslash($_POST['location_url']));
        }
        if (isset($_POST['location_username'])) {
            $location_data['location_username'] = sanitize_text_field(wp_unslash($_POST['location_username']));
        }
        if (isset($_POST['location_password'])) {
            $location_data['location_password'] = sanitize_text_field(wp_unslash($_POST['location_password']));
        }
        
        // Validate required fields
        if (empty($location_data['location_name']) || empty($location_data['location_url'])) {
            wp_send_json_error(['message' => __('Location name and URL are required', 'data-machine')]);
        }
        
        // Get remote locations database service
        $all_databases = apply_filters('dm_db', []);
        $db_remote_locations = $all_databases['remote_locations'] ?? null;
        if (!$db_remote_locations) {
            wp_send_json_error(['message' => __('Remote locations database service unavailable', 'data-machine')]);
        }
        
        // Save the remote location
        $location_id = $db_remote_locations->create_location([
            'handler_slug' => $handler_slug,
            'location_name' => $location_data['location_name'],
            'location_config' => wp_json_encode($location_data)
        ]);
        
        if (!$location_id) {
            wp_send_json_error(['message' => __('Failed to save remote location', 'data-machine')]);
        }
        
        // Log the creation
        do_action('dm_log', 'debug', "Created remote location '{$location_data['location_name']}' for handler '{$handler_slug}' (ID: {$location_id})");
        
        wp_send_json_success([
            'message' => sprintf(__('Remote location "%s" saved successfully', 'data-machine'), $location_data['location_name']),
            'location_id' => $location_id,
            'location_name' => $location_data['location_name'],
            'handler_slug' => $handler_slug
        ]);
    }

    /**
     * Handle add handler action with proper update vs replace logic
     */
    public function handle_add_handler_action()
    {
        // WordPress-native security: capability check + nonce verification
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed: insufficient permissions.', 'data-machine')]);
            return;
        }
        
        // Verify nonce with standard 'dm_pipeline_ajax' action
        $nonce = wp_unslash($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'dm_pipeline_ajax')) {
            wp_send_json_error(['message' => __('Security check failed: invalid nonce.', 'data-machine')]);
            return;
        }
        
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
        
        wp_send_json_success([
            'message' => sprintf(__('Handler "%s" added to flow successfully', 'data-machine'), $handler_info['label'] ?? $handler_slug),
            'handler_slug' => $handler_slug,
            'flow_step_id' => $flow_step_id,
            'action_type' => 'added'
        ]);
    }



    /**
     * Handle AJAX file upload with handler context support
     */
    public function handle_upload_file()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_upload_file')) {
            wp_send_json_error(['message' => __('Security check failed.', 'data-machine')]);
            return;
        }
        
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
            $repository = new \DataMachine\Core\Handlers\Fetch\Files\FilesRepository();
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
     * Handle AJAX request to get files with processing status for a specific handler
     */
    public function handle_get_handler_files()
    {
        // Security handled by dm_ajax_route system
        
        // Use the flow_step_id provided by the frontend
        $flow_step_id = sanitize_text_field($_POST['flow_step_id'] ?? '');
        
        if (empty($flow_step_id)) {
            wp_send_json_error(['message' => __('Missing flow step ID from request.', 'data-machine')]);
            return;
        }
        
        try {
            // Get files repository service
            $repository = new \DataMachine\Core\Handlers\Fetch\Files\FilesRepository();
            if (!$repository) {
                wp_send_json_error(['message' => __('File repository service not available.', 'data-machine')]);
                return;
            }
            
            // Get all files for this flow step
            $files = $repository->get_all_files($flow_step_id);
            
            // Get processed items service to check processing status
            $all_databases = apply_filters('dm_db', []);
            $processed_items_service = $all_databases['processed_items'] ?? null;
            if (!$processed_items_service) {
                wp_send_json_error(['message' => __('Processed items service not available.', 'data-machine')]);
                return;
            }
            
            // Enhance files with processing status
            $files_with_status = [];
            
            foreach ($files as $file) {
                $is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, 'files', $file['path']);
                
                $files_with_status[] = [
                    'filename' => $file['filename'],
                    'size' => $file['size'],
                    'size_formatted' => size_format($file['size']),
                    'modified' => $file['modified'],
                    'modified_formatted' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $file['modified']),
                    'is_processed' => $is_processed,
                    'status' => $is_processed ? __('Processed', 'data-machine') : __('Pending', 'data-machine'),
                    'path' => $file['path']
                ];
            }
            
            // Generate HTML using template system
            $html_rows = apply_filters('dm_render_template', '', 'modal/file-status-rows', [
                'files' => $files_with_status
            ]);
            
            wp_send_json_success([
                'html' => $html_rows,
                'total_files' => count($files_with_status),
                'pending_files' => count(array_filter($files_with_status, fn($f) => !$f['is_processed']))
            ]);
            
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Failed to get handler files.', [
                'error' => $e->getMessage(),
                'flow_step_id' => $flow_step_id
            ]);
            
            wp_send_json_error(['message' => __('Failed to retrieve files.', 'data-machine')]);
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