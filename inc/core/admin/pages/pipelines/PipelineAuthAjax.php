<?php
/**
 * Pipeline Authentication AJAX Handler
 *
 * Centralized authentication and authorization AJAX operations for pipelines.
 * Handles OAuth connections, disconnections, configuration saves, and tool configuration.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelineAuthAjax
{
    /**
     * Register all pipeline authentication AJAX handlers.
     */
    public static function register() {
        $instance = new self();
        
        // OAuth and authentication AJAX actions
        add_action('wp_ajax_dm_disconnect_account', [$instance, 'handle_disconnect_account']);
        add_action('wp_ajax_dm_check_oauth_status', [$instance, 'handle_check_oauth_status']);
        add_action('wp_ajax_dm_save_auth_config', [$instance, 'handle_save_auth_config']);
        // Note: dm_save_tool_config moved to SettingsPageAjax for better UX
    }

    /**
     * Handle account disconnection for OAuth handlers
     */
    public function handle_disconnect_account()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        $handler_slug = sanitize_text_field(wp_unslash($_POST['handler_slug'] ?? ''));
        
        if (empty($handler_slug)) {
            wp_send_json_error(['message' => __('Handler slug is required', 'data-machine')]);
        }

        // Validate handler exists and supports authentication
        $all_auth = apply_filters('dm_auth_providers', []);
        $auth_instance = $all_auth[$handler_slug] ?? null;
        
        if (!$auth_instance) {
            wp_send_json_error(['message' => __('Authentication provider not found', 'data-machine')]);
        }

        // Clear OAuth credentials using dm_oauth filter
        $cleared = apply_filters('dm_oauth', false, 'clear', $handler_slug);
        
        if ($cleared) {
            do_action('dm_log', 'debug', 'Account disconnected successfully', [
                'handler_slug' => $handler_slug
            ]);
            
            wp_send_json_success([
                'message' => sprintf(__('%s account disconnected successfully', 'data-machine'), ucfirst($handler_slug))
            ]);
        } else {
            do_action('dm_log', 'error', 'Failed to disconnect account', [
                'handler_slug' => $handler_slug
            ]);
            
            wp_send_json_error(['message' => __('Failed to disconnect account', 'data-machine')]);
        }
    }

    /**
     * Check OAuth authentication status for polling
     */
    public function handle_check_oauth_status()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }
        $handler_slug = sanitize_text_field(wp_unslash($_POST['handler_slug'] ?? ''));
        
        if (empty($handler_slug)) {
            wp_send_json_error(['message' => __('Handler slug is required', 'data-machine')]);
        }

        // Get auth provider instance
        $all_auth = apply_filters('dm_auth_providers', []);
        $auth_instance = $all_auth[$handler_slug] ?? null;
        
        if (!$auth_instance) {
            wp_send_json_error(['message' => __('Authentication provider not found', 'data-machine')]);
        }

        // Check authentication status
        $is_authenticated = $auth_instance->is_authenticated();
        
        if ($is_authenticated) {
            // Get account details for success response
            $account_details = null;
            if (method_exists($auth_instance, 'get_account_details')) {
                $account_details = $auth_instance->get_account_details();
            }
            
            wp_send_json_success([
                'authenticated' => true,
                'account_details' => $account_details,
                'handler_slug' => $handler_slug
            ]);
        } else {
            // Check for recent OAuth errors stored in transients
            $error_transient = get_transient('dm_oauth_error_' . $handler_slug);
            $success_transient = get_transient('dm_oauth_success_' . $handler_slug);
            
            if ($error_transient) {
                // Clear the error transient since we're handling it
                delete_transient('dm_oauth_error_' . $handler_slug);
                
                wp_send_json_success([
                    'authenticated' => false,
                    'error' => true,
                    'error_code' => 'oauth_failed',
                    'error_message' => $error_transient,
                    'handler_slug' => $handler_slug
                ]);
            } elseif ($success_transient) {
                // Clear the success transient and re-check auth status
                delete_transient('dm_oauth_success_' . $handler_slug);
                
                // Force re-check authentication status as success transient might indicate completion
                $is_authenticated = $auth_instance->is_authenticated();
                
                if ($is_authenticated) {
                    $account_details = null;
                    if (method_exists($auth_instance, 'get_account_details')) {
                        $account_details = $auth_instance->get_account_details();
                    }
                    
                    wp_send_json_success([
                        'authenticated' => true,
                        'account_details' => $account_details,
                        'handler_slug' => $handler_slug
                    ]);
                }
            }
            
            // Still not authenticated, continue polling
            wp_send_json_success([
                'authenticated' => false,
                'error' => false,
                'handler_slug' => $handler_slug
            ]);
        }
    }

    /**
     * Handle authentication configuration form submissions
     */
    public function handle_save_auth_config()
    {
        // Security verification
        if (!check_ajax_referer('dm_ajax_actions', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $handler_slug = sanitize_text_field(wp_unslash($_POST['handler_slug'] ?? ''));
        if (empty($handler_slug)) {
            wp_send_json_error(['message' => __('Handler slug is required', 'data-machine')]);
        }

        // Get auth provider instance to validate fields
        $all_auth = apply_filters('dm_auth_providers', []);
        $auth_instance = $all_auth[$handler_slug] ?? null;
        if (!$auth_instance || !method_exists($auth_instance, 'get_config_fields')) {
            wp_send_json_error(['message' => __('Auth provider not found or invalid', 'data-machine')]);
        }

        // Get field definitions for validation
        $config_fields = $auth_instance->get_config_fields();
        $config_data = [];

        // Get existing configuration to handle unchanged saves
        $existing_config = apply_filters('dm_oauth', [], 'get_config', $handler_slug);

        // Validate and sanitize each field
        foreach ($config_fields as $field_name => $field_config) {
            $value = sanitize_text_field(wp_unslash($_POST[$field_name] ?? ''));
            
            // Check required fields only if no existing config and value is empty
            if (($field_config['required'] ?? false) && empty($value) && empty($existing_config[$field_name] ?? '')) {
                wp_send_json_error(['message' => sprintf(__('%s is required', 'data-machine'), $field_config['label'])]);
            }
            
            // Use existing value if form value is empty (handles unchanged saves)
            if (empty($value) && !empty($existing_config[$field_name] ?? '')) {
                $value = $existing_config[$field_name];
            }
            
            $config_data[$field_name] = $value;
        }

        // Save configuration using dm_oauth filter
        $saved = apply_filters('dm_oauth', null, 'store_config', $handler_slug, $config_data);
        
        if ($saved) {
            wp_send_json_success(['message' => __('Configuration saved successfully', 'data-machine')]);
        } else {
            wp_send_json_error(['message' => __('Failed to save configuration', 'data-machine')]);
        }
    }

    // Tool configuration methods moved to SettingsPageAjax for better UX
}