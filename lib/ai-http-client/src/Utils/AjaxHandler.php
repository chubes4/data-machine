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
            // Collect form data
            $form_data = [];
            $provider = null;
            $api_key = null;
            
            // Provider selection
            if (isset($_POST['ai_provider']) || isset($_POST['ai_step_' . $step_id . '_provider'])) {
                $provider_field = !empty($step_id) ? 'ai_step_' . $step_id . '_provider' : 'ai_provider';
                $provider = sanitize_text_field(wp_unslash($_POST[$provider_field] ?? ''));
                $form_data['provider'] = $provider;
            }
            
            // API key (always generic field name)
            if (isset($_POST['ai_api_key'])) {
                $api_key = sanitize_text_field(wp_unslash($_POST['ai_api_key']));
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
            
            // Save configuration using actions
            if (!empty($step_id)) {
                // Step-aware save
                do_action('save_ai_config', [
                    'type' => 'step_config',
                    'step_id' => $step_id,
                    'data' => $form_data
                ]);
                $success = true; // Actions don't return values
            } else {
                // Global save - save provider selection and settings
                if (!empty($provider)) {
                    do_action('save_ai_config', [
                        'type' => 'selected_provider',
                        'provider' => $provider
                    ]);
                }
                
                // Save API key separately if provided
                if (!empty($api_key) && !empty($provider)) {
                    do_action('save_ai_config', [
                        'type' => 'api_key',
                        'provider' => $provider,
                        'api_key' => $api_key
                    ]);
                }
                
                // Save provider-specific settings (without provider and API key)
                if (!empty($provider)) {
                    unset($form_data['provider']); // Don't include provider in settings
                    do_action('save_ai_config', [
                        'type' => 'provider_settings',
                        'provider' => $provider,
                        'data' => $form_data
                    ]);
                }
                $success = true; // Actions don't return values
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
            if (!empty($step_id)) {
                // Step-aware configuration using ai_config filter
                $step_config = apply_filters('ai_config', $step_id);
                $selected_provider = $step_config['provider'] ?? null;
                
                // Get global provider settings using ai_config filter
                $all_providers_config = apply_filters('ai_config', null);
                $provider_settings = isset($all_providers_config[$selected_provider]) ? $all_providers_config[$selected_provider] : [];
                
                $settings = array_merge($provider_settings, $step_config);
                $settings['provider'] = $selected_provider;
                
            } else {
                // Global configuration using ai_config filter
                $all_settings = apply_filters('ai_config', null);
                $selected_provider = $all_settings['selected_provider'] ?? null;
                
                $provider_to_load = $provider ?: $selected_provider;
                $settings = isset($all_settings[$provider_to_load]) ? $all_settings[$provider_to_load] : [];
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
            // Use ai_models filter for direct model fetching
            $models = apply_filters('ai_models', $provider);
            
            if (empty($models)) {
                wp_send_json_error(['message' => 'No API key configured for ' . $provider . '. Enter API key to load models.']);
                return;
            }
            
            wp_send_json_success($models);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
}