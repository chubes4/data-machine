<?php
/**
 * AI HTTP Client - Action Registration
 * 
 * Registers all AI HTTP Client WordPress actions for write operations and side effects.
 * Actions handle operations with no return values expected.
 *
 * @package AIHttpClient
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Register AI HTTP Client actions
 * 
 * Registers all write operations as WordPress actions
 * 
 * @since 1.2.0
 */
function ai_http_client_register_actions() {
    
    // AI Configuration Save Action - centralized write operations
    // Usage: do_action('save_ai_config', $config_data);
    // Config data format: ['type' => 'provider_settings|step_config|api_key|selected_provider', 'provider' => '...', 'step_id' => '...', 'data' => [...]]
    add_action('save_ai_config', function($config_data) {
        // Validate config data structure
        if (!is_array($config_data) || empty($config_data['type'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AI HTTP Client] save_ai_config action: Invalid config data format');
            }
            return;
        }
        
        // Auto-detect plugin context
        $plugin_context = ai_http_detect_plugin_context();
        if (empty($plugin_context)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AI HTTP Client] save_ai_config action: Could not auto-detect plugin context');
            }
            return;
        }
        
        // Default to LLM type
        $ai_type = 'llm';
        
        try {
            $options_manager = new AI_HTTP_Options_Manager($plugin_context, $ai_type);
            
            // Route to appropriate save operation based on type
            switch ($config_data['type']) {
                case 'provider_settings':
                    if (empty($config_data['provider']) || !isset($config_data['data'])) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('[AI HTTP Client] save_ai_config: provider_settings requires provider and data');
                        }
                        return;
                    }
                    $options_manager->save_provider_settings($config_data['provider'], $config_data['data']);
                    break;
                    
                case 'step_config':
                    if (empty($config_data['step_id']) || !isset($config_data['data'])) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('[AI HTTP Client] save_ai_config: step_config requires step_id and data');
                        }
                        return;
                    }
                    $options_manager->save_step_configuration($config_data['step_id'], $config_data['data']);
                    break;
                    
                case 'api_key':
                    if (empty($config_data['provider']) || !isset($config_data['api_key'])) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('[AI HTTP Client] save_ai_config: api_key requires provider and api_key');
                        }
                        return;
                    }
                    $options_manager->set_api_key($config_data['provider'], $config_data['api_key']);
                    break;
                    
                case 'selected_provider':
                    if (empty($config_data['provider'])) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('[AI HTTP Client] save_ai_config: selected_provider requires provider');
                        }
                        return;
                    }
                    $options_manager->set_selected_provider($config_data['provider']);
                    break;
                    
                case 'delete_provider_settings':
                    if (empty($config_data['provider'])) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('[AI HTTP Client] save_ai_config: delete_provider_settings requires provider');
                        }
                        return;
                    }
                    $options_manager->delete_provider_settings($config_data['provider']);
                    break;
                    
                case 'delete_step_config':
                    if (empty($config_data['step_id'])) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('[AI HTTP Client] save_ai_config: delete_step_config requires step_id');
                        }
                        return;
                    }
                    $options_manager->delete_step_configuration($config_data['step_id']);
                    break;
                    
                case 'reset_all_settings':
                    $options_manager->reset_all_settings();
                    break;
                    
                default:
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("[AI HTTP Client] save_ai_config: Unknown config type '{$config_data['type']}'");
                    }
                    return;
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[AI HTTP Client] save_ai_config: Successfully saved {$config_data['type']} for plugin {$plugin_context}");
            }
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[AI HTTP Client] save_ai_config: Exception - " . $e->getMessage());
            }
        }
    }, 10, 1);
    
}

// Initialize actions on WordPress init
add_action('init', 'ai_http_client_register_actions');