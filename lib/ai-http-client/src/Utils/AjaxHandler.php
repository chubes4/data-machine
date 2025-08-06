<?php
/**
 * AI HTTP Client - AJAX Handler
 * 
 * Provides WordPress AJAX endpoints for dynamic component interactions.
 * Handles provider settings and model fetching.
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Ajax_Handler {
    
    /**
     * Save provider settings via AJAX
     * Handles both global and step-aware configurations
     */
    public static function save_settings() {
        // Security verification
        if (!check_ajax_referer('ai_http_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'ai-http-client')]);
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'ai-http-client')]);
            return;
        }
        
        // Get plugin context (required)
        $plugin_context = sanitize_text_field(wp_unslash($_POST['plugin_context'] ?? ''));
        if (empty($plugin_context)) {
            wp_send_json_error(['message' => __('Plugin context is required', 'ai-http-client')]);
            return;
        }
        
        // Get step ID (optional - for step-aware configuration)
        $step_id = sanitize_text_field(wp_unslash($_POST['step_id'] ?? ''));
        
        try {
            $options_manager = new AI_HTTP_Options_Manager($plugin_context, 'llm');
            
            // Collect form data
            $form_data = [];
            
            // Provider selection
            if (isset($_POST['ai_provider']) || isset($_POST['ai_step_' . $step_id . '_provider'])) {
                $provider_field = !empty($step_id) ? 'ai_step_' . $step_id . '_provider' : 'ai_provider';
                $form_data['provider'] = sanitize_text_field(wp_unslash($_POST[$provider_field] ?? ''));
            }
            
            // API key (always generic field name)
            if (isset($_POST['ai_api_key'])) {
                $form_data['api_key'] = sanitize_text_field(wp_unslash($_POST['ai_api_key']));
            }
            
            // Model selection
            if (isset($_POST['ai_model']) || isset($_POST['ai_step_' . $step_id . '_model'])) {
                $model_field = !empty($step_id) ? 'ai_step_' . $step_id . '_model' : 'ai_model';
                $form_data['model'] = sanitize_text_field(wp_unslash($_POST[$model_field] ?? ''));
            }
            
            // Temperature
            if (isset($_POST['ai_temperature']) || isset($_POST['ai_step_' . $step_id . '_temperature'])) {
                $temp_field = !empty($step_id) ? 'ai_step_' . $step_id . '_temperature' : 'ai_temperature';
                $form_data['temperature'] = floatval($_POST[$temp_field] ?? 0.7);
            }
            
            // System prompt
            if (isset($_POST['ai_system_prompt']) || isset($_POST['ai_step_' . $step_id . '_system_prompt'])) {
                $prompt_field = !empty($step_id) ? 'ai_step_' . $step_id . '_system_prompt' : 'ai_system_prompt';
                $form_data['system_prompt'] = sanitize_textarea_field(wp_unslash($_POST[$prompt_field] ?? ''));
            }
            
            // Save configuration
            if (!empty($step_id)) {
                // Step-aware save
                $success = $options_manager->save_step_configuration($step_id, $form_data);
            } else {
                // Global save - save provider selection and settings
                if (!empty($form_data['provider'])) {
                    $options_manager->set_selected_provider($form_data['provider']);
                }
                
                // Save provider-specific settings
                if (!empty($form_data['provider'])) {
                    unset($form_data['provider']); // Don't include provider in settings
                    $success = $options_manager->save_provider_settings($form_data['provider'] ?? 'openai', $form_data);
                } else {
                    $success = true;
                }
            }
            
            if ($success) {
                wp_send_json_success([
                    'message' => __('Settings saved successfully', 'ai-http-client'),
                    'step_id' => $step_id
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to save settings', 'ai-http-client')]);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Load provider settings via AJAX
     * Supports both global and step-aware configurations
     */
    public static function load_provider_settings() {
        // Security verification
        if (!check_ajax_referer('ai_http_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'ai-http-client')]);
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'ai-http-client')]);
            return;
        }
        
        // Get required parameters
        $plugin_context = sanitize_text_field(wp_unslash($_POST['plugin_context'] ?? ''));
        $provider = sanitize_text_field(wp_unslash($_POST['provider'] ?? ''));
        $step_id = sanitize_text_field(wp_unslash($_POST['step_id'] ?? ''));
        
        if (empty($plugin_context)) {
            wp_send_json_error(['message' => __('Plugin context is required', 'ai-http-client')]);
            return;
        }
        
        try {
            $options_manager = new AI_HTTP_Options_Manager($plugin_context, 'llm');
            
            if (!empty($step_id)) {
                // Step-aware configuration
                $step_config = $options_manager->get_step_configuration($step_id);
                $selected_provider = $step_config['provider'] ?? null;
                
                // Get provider settings with step context
                $settings = $options_manager->get_provider_settings_with_step($selected_provider, $step_id);
                $settings['provider'] = $selected_provider;
                $settings = array_merge($settings, $step_config);
                
            } else {
                // Global configuration
                $all_settings = $options_manager->get_all_providers();
                $selected_provider = $all_settings['selected_provider'] ?? null;
                
                $settings = $options_manager->get_provider_settings($provider ?: $selected_provider);
                $settings['provider'] = $selected_provider;
            }
            
            wp_send_json_success($settings);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Get available models for a provider via AJAX
     */
    public static function get_models() {
        // Security verification
        if (!check_ajax_referer('ai_http_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'ai-http-client')]);
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'ai-http-client')]);
            return;
        }
        
        // Get required parameters
        $plugin_context = sanitize_text_field(wp_unslash($_POST['plugin_context'] ?? ''));
        $provider = sanitize_text_field(wp_unslash($_POST['provider'] ?? ''));
        
        if (empty($plugin_context) || empty($provider)) {
            wp_send_json_error(['message' => __('Plugin context and provider are required', 'ai-http-client')]);
            return;
        }
        
        try {
            $client = new AI_HTTP_Client([
                'plugin_context' => $plugin_context,
                'ai_type' => 'llm'
            ]);
            
            $models = $client->get_models($provider);
            
            if (is_wp_error($models)) {
                wp_send_json_error(['message' => $models->get_error_message()]);
            } else {
                wp_send_json_success($models);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
}