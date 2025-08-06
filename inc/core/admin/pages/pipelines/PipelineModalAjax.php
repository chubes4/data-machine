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
    public function handle_pipeline_modal_ajax()
    {
        // Verify nonce
        if (!check_ajax_referer('dm_pipeline_ajax', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
        }

        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        // Get action from POST data - support both 'pipeline_action' and 'operation' for modal system
        $action = sanitize_text_field(wp_unslash($_POST['pipeline_action'] ?? $_POST['operation'] ?? ''));

        switch ($action) {
            case 'get_modal':
                $this->get_modal();
                break;
            
            case 'get_template':
                $this->get_template();
                break;
            
            case 'get_flow_step_card':
                $this->get_flow_step_card();
                break;
            
            case 'get_flow_config':
                $this->get_flow_config();
                break;
            
            case 'configure-step-action':
                $this->configure_step_action();
                break;
                
            case 'add-location-action':
                $this->add_location_action();
                break;
                
            case 'add-handler-action':
                $this->add_handler_action();
                break;
            
            default:
                wp_send_json_error(['message' => __('Invalid modal action', 'data-machine')]);
        }
    }


    /**
     * Get modal based on template and context
     * Routes to appropriate content generation method
     */
    private function get_modal()
    {
        $template = sanitize_text_field(wp_unslash($_POST['template'] ?? ''));
        $context = $_POST['context'] ?? [];

        // Pure discovery pattern - get all registered modals
        $all_modals = apply_filters('dm_get_modals', []);
        $modal_data = $all_modals[$template] ?? null;
        
        if ($modal_data) {
            $content = $modal_data['content'] ?? '';
            $title = $modal_data['title'] ?? ucfirst(str_replace('-', ' ', $template));
            
            wp_send_json_success([
                'content' => $content,
                'title' => $title
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Modal template "%s" not found', 'data-machine'), $template)
            ]);
        }
    }

    /**
     * Get rendered template with provided data
     * Dedicated endpoint for template rendering to maintain architecture consistency
     */
    private function get_template()
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
        if ($template === 'page/step-card') {
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
    private function get_flow_step_card()
    {
        $step_type = sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));
        $flow_id = sanitize_text_field(wp_unslash($_POST['flow_id'] ?? 'new'));
        
        if (empty($step_type)) {
            wp_send_json_error(['message' => __('Step type is required', 'data-machine')]);
        }

        // Validate step type exists using pure discovery
        $all_steps = apply_filters('dm_get_steps', []);
        $step_config = $all_steps[$step_type] ?? null;
        if (!$step_config) {
            wp_send_json_error(['message' => __('Invalid step type', 'data-machine')]);
        }

        // Check if this is the first flow step by counting existing steps in flows
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_flows = $all_databases['flows'] ?? null;
        $flows = $db_flows ? $db_flows->get_flows_by_pipeline($_POST['pipeline_id'] ?? 0) : [];
        $is_first_step = empty($flows) || empty($flows[0]['flow_config'] ?? []);

        // Prepare data for template
        $template_data = [
            'step' => [
                'step_type' => $step_type,
                'step_config' => []  // Empty config for new steps
            ],
            'flow_config' => [],  // Empty flow config for new steps
            'flow_id' => $flow_id,
            'is_first_step' => $is_first_step  // Template uses this to determine arrow
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
    private function get_flow_config()
    {
        $flow_id = (int) sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));

        if (empty($flow_id)) {
            wp_send_json_error(['message' => __('Flow ID is required.', 'data-machine')]);
            return;
        }

        // Get database service
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_flows = $all_databases['flows'] ?? null;
        if (!$db_flows) {
            wp_send_json_error(['message' => __('Database service unavailable.', 'data-machine')]);
            return;
        }

        // Get flow data
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Flow not found.', 'data-machine')]);
            return;
        }

        // Parse flow configuration
        $flow_config = json_decode($flow['flow_config'] ?? '{}', true) ?: [];

        wp_send_json_success([
            'flow_id' => $flow_id,
            'flow_config' => $flow_config
        ]);
    }

    /**
     * Handle step configuration save action
     */
    private function configure_step_action()
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
        $required_fields = ['step_type', 'pipeline_id', 'step_id'];
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
        $step_id = sanitize_text_field($context['step_id']);
        
        if (empty($step_type)) {
            wp_send_json_error(['message' => __('Step type is required', 'data-machine')]);
        }
        
        // Handle AI step configuration
        if ($step_type === 'ai') {
            // FAIL FAST - require step_id for unique configuration
            if (empty($step_id)) {
                wp_send_json_error([
                    'message' => __('Step ID is required for AI configuration', 'data-machine'),
                    'missing_data' => [
                        'step_id' => empty($step_id),
                        'step_type' => $step_type,
                        'pipeline_id' => $pipeline_id
                    ]
                ]);
            }
            
            // Validate step_id format (should be UUID4)
            if (!wp_is_uuid($step_id)) {
                wp_send_json_error([
                    'message' => __('Step ID format invalid - expected UUID', 'data-machine'),
                    'received' => $step_id
                ]);
            }
            
            // Get AI HTTP Client options manager for step-aware configuration
            if (class_exists('AI_HTTP_Options_Manager')) {
                try {
                    $options_manager = new \AI_HTTP_Options_Manager('data-machine', 'llm');
                    
                    // Get form data using step-aware field names (no fallbacks)
                    $form_data = [];
                    
                    // Field names are prefixed with step_id for step-aware configuration
                    $provider_field = "ai_step_{$step_id}_provider";
                    $api_key_field = 'ai_api_key';  // API key uses generic name
                    $model_field = "ai_step_{$step_id}_model";
                    $temperature_field = "ai_step_{$step_id}_temperature";
                    $system_prompt_field = "ai_step_{$step_id}_system_prompt";
                    
                    // Log received field names for debugging
                    $logger = apply_filters('dm_get_logger', null);
                    $logger?->debug('AI step configuration field mapping', [
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
                    if (isset($_POST[$provider_field])) {
                        $form_data['provider'] = sanitize_text_field(wp_unslash($_POST[$provider_field]));
                    }
                    if (isset($_POST[$api_key_field])) {
                        $form_data['api_key'] = sanitize_text_field(wp_unslash($_POST[$api_key_field]));
                    }
                    if (isset($_POST[$model_field])) {
                        $form_data['model'] = sanitize_text_field(wp_unslash($_POST[$model_field]));
                    }
                    if (isset($_POST[$temperature_field])) {
                        $form_data['temperature'] = floatval($_POST[$temperature_field]);
                    }
                    if (isset($_POST[$system_prompt_field])) {
                        $form_data['system_prompt'] = sanitize_textarea_field(wp_unslash($_POST[$system_prompt_field]));
                    }
                    if (isset($_POST['system_prompt'])) {
                        $form_data['system_prompt'] = sanitize_textarea_field(wp_unslash($_POST['system_prompt']));
                    }
                    
                    // Save step-specific configuration
                    $success = $options_manager->save_step_configuration($step_id, $form_data);
                    
                    if ($success) {
                        wp_send_json_success([
                            'message' => __('AI step configuration saved successfully', 'data-machine'),
                            'step_id' => $step_id
                        ]);
                    } else {
                        wp_send_json_error(['message' => __('Failed to save AI step configuration', 'data-machine')]);
                    }
                    
                } catch (Exception $e) {
                    $logger = apply_filters('dm_get_logger', null);
                    if ($logger) {
                        $logger->error('AI step configuration save error: ' . $e->getMessage());
                    }
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
    private function add_location_action()
    {
        // Get context data from AJAX request - jQuery auto-parses JSON data attributes
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
        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            $logger->debug("Created remote location '{$location_data['location_name']}' for handler '{$handler_slug}' (ID: {$location_id})");
        }
        
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
    private function add_handler_action()
    {
        // Get context data from AJAX request - handle both JSON string and array formats
        $context = $_POST['context'] ?? [];
        
        // If context is a JSON string, decode it
        if (is_string($context)) {
            $context = json_decode($context, true) ?: [];
        }
        
        // Enhanced error logging to debug data flow
        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            $logger->debug('Handler save context received', [
                'context_type' => gettype($_POST['context'] ?? null),
                'context_content' => $context,
                'post_keys' => array_keys($_POST),
                'received_data' => array_intersect_key($_POST, array_flip(['handler_slug', 'step_type', 'flow_id', 'pipeline_id']))
            ]);
        }
        
        // Try to get parameters from context first, then fallback to direct POST parameters
        $handler_slug = sanitize_text_field($context['handler_slug'] ?? $_POST['handler_slug'] ?? '');
        $step_type = sanitize_text_field($context['step_type'] ?? $_POST['step_type'] ?? '');
        $flow_id = (int)sanitize_text_field($context['flow_id'] ?? $_POST['flow_id'] ?? '');
        $pipeline_id = (int)sanitize_text_field($context['pipeline_id'] ?? $_POST['pipeline_id'] ?? '');
        
        if (empty($handler_slug) || empty($step_type)) {
            $error_details = [
                'handler_slug_empty' => empty($handler_slug),
                'step_type_empty' => empty($step_type),
                'context_keys' => array_keys($context),
                'post_keys' => array_keys($_POST)
            ];
            
            $logger && $logger->error('Handler slug and step type validation failed', $error_details);
            
            wp_send_json_error([
                'message' => __('Handler slug and step type are required', 'data-machine'),
                'debug_info' => $error_details
            ]);
        }
        
        // Get handler configuration via pure discovery
        $all_handlers = apply_filters('dm_get_handlers', []);
        $handlers = array_filter($all_handlers, function($handler) use ($step_type) {
            return ($handler['type'] ?? '') === $step_type;
        });
        
        if (!isset($handlers[$handler_slug])) {
            wp_send_json_error(['message' => __('Invalid handler for this step type', 'data-machine')]);
        }
        
        $handler_config = $handlers[$handler_slug];
        
        // Get settings class to process form data using pure discovery
        $all_settings = apply_filters('dm_get_handler_settings', []);
        $settings_instance = $all_settings[$handler_slug] ?? null;
        $handler_settings = [];
        
        // If handler has settings, sanitize the form data
        if ($settings_instance && method_exists($settings_instance, 'sanitize')) {
            $raw_settings = [];
            
            // Extract form fields (skip WordPress and system fields)
            foreach ($_POST as $key => $value) {
                if (!in_array($key, ['action', 'pipeline_action', 'context', 'nonce', '_wp_http_referer'])) {
                    $raw_settings[$key] = $value;
                }
            }
            
            $handler_settings = $settings_instance->sanitize($raw_settings);
        }
        
        // For flow context, update or add handler to flow configuration
        if ($flow_id > 0) {
            $all_databases = apply_filters('dm_get_database_services', []);
        $db_flows = $all_databases['flows'] ?? null;
            if (!$db_flows) {
                wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
            }
            
            // Get current flow
            $flow = $db_flows->get_flow($flow_id);
            if (!$flow) {
                wp_send_json_error(['message' => __('Flow not found', 'data-machine')]);
            }
            
            // Parse current flow configuration
            $flow_config_raw = $flow['flow_config'] ?? '{}';
            $flow_config = is_string($flow_config_raw) ? json_decode($flow_config_raw, true) : $flow_config_raw;
            $flow_config = $flow_config ?: [];
            
            // Initialize step configuration if it doesn't exist
            if (!isset($flow_config['steps'])) {
                $flow_config['steps'] = [];
            }
            
            // Find or create step configuration
            $step_key = $step_type;
            if (!isset($flow_config['steps'][$step_key])) {
                $flow_config['steps'][$step_key] = [
                    'step_type' => $step_type,
                    'handlers' => []
                ];
            }
            
            // Initialize handlers array if it doesn't exist
            if (!isset($flow_config['steps'][$step_key]['handlers'])) {
                $flow_config['steps'][$step_key]['handlers'] = [];
            }
            
            // Check if handler already exists
            $handler_exists = isset($flow_config['steps'][$step_key]['handlers'][$handler_slug]);
            
            // UPDATE existing handler settings OR ADD new handler
            $flow_config['steps'][$step_key]['handlers'][$handler_slug] = [
                'handler_slug' => $handler_slug,
                'settings' => $handler_settings,
                'enabled' => true
            ];
            
            // Update flow with new configuration
            $success = $db_flows->update_flow($flow_id, [
                'flow_config' => wp_json_encode($flow_config)
            ]);
            
            if (!$success) {
                wp_send_json_error(['message' => __('Failed to save handler settings', 'data-machine')]);
            }
            
            // Log the action
            $logger = apply_filters('dm_get_logger', null);
            if ($logger) {
                $action_type = $handler_exists ? 'updated' : 'added';
                $logger->debug("Handler '{$handler_slug}' {$action_type} for step '{$step_type}' in flow {$flow_id}");
            }
            
            $action_message = $handler_exists 
                ? sprintf(__('Handler "%s" settings updated successfully', 'data-machine'), $handler_config['label'] ?? $handler_slug)
                : sprintf(__('Handler "%s" added to flow successfully', 'data-machine'), $handler_config['label'] ?? $handler_slug);
            
            wp_send_json_success([
                'message' => $action_message,
                'handler_slug' => $handler_slug,
                'step_type' => $step_type,
                'flow_id' => $flow_id,
                'handler_config' => $handler_config,
                'handler_settings' => $handler_settings,
                'action_type' => $handler_exists ? 'updated' : 'added'
            ]);
            
        } else {
            // For pipeline context (template), just confirm the handler is valid
            wp_send_json_success([
                'message' => sprintf(__('Handler "%s" configuration saved', 'data-machine'), $handler_config['label'] ?? $handler_slug),
                'handler_slug' => $handler_slug,
                'step_type' => $step_type,
                'pipeline_id' => $pipeline_id,
                'handler_config' => $handler_config,
                'handler_settings' => $handler_settings
            ]);
        }
    }
}