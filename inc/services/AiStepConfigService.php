<?php

namespace DataMachine\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Step Configuration Service
 * 
 * Handles step-specific AI configuration management with provider/model settings per step.
 * Enables different providers/models per AI step in pipeline (e.g., GPT-4 → Claude → Gemini workflows).
 * 
 * @package    Data_Machine
 * @subpackage Data_Machine/Services
 * @since      NEXT_VERSION
 */
class AiStepConfigService {

    /**
     * Constructor.
     * Uses filter-based service access for dependencies.
     */
    public function __construct() {
        // Pure filter-based architecture - no constructor dependencies
    }

    /**
     * Get AI configuration for a specific pipeline step.
     * 
     * @param int $project_id Project ID
     * @param int $step_position Step position in pipeline (0-based)
     * @return array AI configuration with provider/model settings
     */
    public function get_step_ai_config(int $project_id, int $step_position): array {
        $option_key = "dm_ai_step_config_{$project_id}_{$step_position}";
        $config = get_option($option_key, []);
        
        // Provide sensible defaults if no configuration exists
        if (empty($config)) {
            $config = [
                'provider' => '',
                'model' => '',
                'temperature' => 0.7,
                'max_tokens' => 2000,
                'enabled' => true
            ];
        }
        
        return $config;
    }

    /**
     * Save AI configuration for a specific pipeline step.
     * 
     * @param int $project_id Project ID
     * @param int $step_position Step position in pipeline (0-based)
     * @param array $config AI configuration data
     * @return bool True on success, false on failure
     */
    public function save_step_ai_config(int $project_id, int $step_position, array $config): bool {
        $option_key = "dm_ai_step_config_{$project_id}_{$step_position}";
        
        // Sanitize configuration data
        $sanitized_config = [
            'provider' => sanitize_text_field($config['provider'] ?? ''),
            'model' => sanitize_text_field($config['model'] ?? ''),
            'temperature' => floatval($config['temperature'] ?? 0.7),
            'max_tokens' => intval($config['max_tokens'] ?? 2000),
            'enabled' => (bool)($config['enabled'] ?? true)
        ];
        
        // Validate temperature range
        $sanitized_config['temperature'] = max(0, min(2, $sanitized_config['temperature']));
        
        // Validate max_tokens range
        $sanitized_config['max_tokens'] = max(1, min(100000, $sanitized_config['max_tokens']));
        
        return update_option($option_key, $sanitized_config);
    }

    /**
     * Delete AI configuration for a specific pipeline step.
     * 
     * @param int $project_id Project ID
     * @param int $step_position Step position in pipeline (0-based)
     * @return bool True on success, false on failure
     */
    public function delete_step_ai_config(int $project_id, int $step_position): bool {
        $option_key = "dm_ai_step_config_{$project_id}_{$step_position}";
        return delete_option($option_key);
    }

    /**
     * Get all AI configurations for a project's pipeline steps.
     * 
     * @param int $project_id Project ID
     * @return array Array of step configurations indexed by step position
     */
    public function get_project_ai_configs(int $project_id): array {
        global $wpdb;
        
        $option_prefix = "dm_ai_step_config_{$project_id}_";
        $option_like = $wpdb->esc_like($option_prefix) . '%';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            $option_like
        ));
        
        $configs = [];
        foreach ($results as $result) {
            // Extract step position from option name
            $step_position = str_replace($option_prefix, '', $result->option_name);
            if (is_numeric($step_position)) {
                $configs[intval($step_position)] = maybe_unserialize($result->option_value);
            }
        }
        
        return $configs;
    }

    /**
     * Delete all AI configurations for a project (cleanup when project is deleted).
     * 
     * @param int $project_id Project ID
     * @return bool True on success, false on failure
     */
    public function delete_project_ai_configs(int $project_id): bool {
        global $wpdb;
        
        $option_prefix = "dm_ai_step_config_{$project_id}_";
        $option_like = $wpdb->esc_like($option_prefix) . '%';
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $option_like
        ));
        
        return $deleted !== false;
    }

    /**
     * Get available AI providers from ai-http-client.
     * 
     * @return array Available providers with their models
     */
    public function get_available_providers(): array {
        $ai_http_client = apply_filters('dm_get_ai_http_client', null);
        
        if (!$ai_http_client || !method_exists($ai_http_client, 'get_available_providers')) {
            // Fallback with common providers if ai-http-client doesn't support this method
            return [
                'openai' => [
                    'label' => 'OpenAI',
                    'models' => ['gpt-4', 'gpt-4-turbo', 'gpt-3.5-turbo']
                ],
                'anthropic' => [
                    'label' => 'Anthropic',
                    'models' => ['claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku']
                ],
                'google' => [
                    'label' => 'Google',
                    'models' => ['gemini-pro', 'gemini-pro-vision']
                ]
            ];
        }
        
        return $ai_http_client->get_available_providers();
    }

    /**
     * Render AI configuration form for ProviderManagerComponent integration.
     * 
     * @param int $project_id Project ID
     * @param int $step_position Step position in pipeline (0-based)
     * @param string $step_id Unique step identifier
     * @return string HTML content for the configuration form
     */
    public function render_step_ai_config_form(int $project_id, int $step_position, string $step_id): string {
        $ai_http_client = apply_filters('dm_get_ai_http_client', null);
        $current_config = $this->get_step_ai_config($project_id, $step_position);
        
        ob_start();
        ?>
        <div class="dm-ai-step-config" data-project-id="<?php echo esc_attr($project_id); ?>" data-step-position="<?php echo esc_attr($step_position); ?>" data-step-id="<?php echo esc_attr($step_id); ?>">
            
            <div class="dm-ai-config-header" style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e4e7;">
                <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: #1e1e1e;">
                    <?php 
                    /* translators: %s: step position number */
                    echo esc_html(sprintf(__('AI Configuration - Step %d', 'data-machine'), $step_position + 1)); 
                    ?>
                </h3>
                <p style="margin: 0; color: #646970; font-size: 14px;">
                    <?php esc_html_e('Configure the AI provider and model for this specific step. Each step can use different providers to create powerful multi-model workflows.', 'data-machine'); ?>
                </p>
            </div>

            <?php if ($ai_http_client && method_exists($ai_http_client, 'render_provider_manager')): ?>
                
                <!-- AI HTTP Client ProviderManagerComponent Integration -->
                <div class="dm-provider-manager-wrapper">
                    <?php
                    // Render the ProviderManagerComponent with step-specific context
                    $component_config = [
                        'context' => 'ai_step',
                        'step_id' => $step_id,
                        'project_id' => $project_id,
                        'step_position' => $step_position,
                        'current_config' => $current_config,
                        'show_global_fallback' => true,
                        'enable_step_specific' => true
                    ];
                    
                    echo wp_kses_post($ai_http_client->render_provider_manager($component_config));
                    ?>
                </div>

            <?php else: ?>
                
                <!-- Fallback form if ProviderManagerComponent not available -->
                <div class="dm-ai-config-fallback" style="border: 1px solid #e2e4e7; border-radius: 6px; padding: 20px; background: #f8f9fa;">
                    <div class="notice notice-warning inline" style="margin: 0 0 20px 0;">
                        <p><?php esc_html_e('AI HTTP Client ProviderManagerComponent not available. Using fallback configuration.', 'data-machine'); ?></p>
                    </div>
                    
                    <table class="form-table" style="margin: 0;">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="dm-ai-provider-<?php echo esc_attr($step_id); ?>">
                                        <?php esc_html_e('AI Provider', 'data-machine'); ?>
                                    </label>
                                </th>
                                <td>
                                    <select id="dm-ai-provider-<?php echo esc_attr($step_id); ?>" name="provider" class="regular-text">
                                        <option value=""><?php esc_html_e('Use Global Default', 'data-machine'); ?></option>
                                        <option value="openai" <?php selected($current_config['provider'], 'openai'); ?>>OpenAI</option>
                                        <option value="anthropic" <?php selected($current_config['provider'], 'anthropic'); ?>>Anthropic</option>
                                        <option value="google" <?php selected($current_config['provider'], 'google'); ?>>Google</option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('Select the AI provider for this step. Leave empty to use global default.', 'data-machine'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="dm-ai-model-<?php echo esc_attr($step_id); ?>">
                                        <?php esc_html_e('AI Model', 'data-machine'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="dm-ai-model-<?php echo esc_attr($step_id); ?>" 
                                           name="model" 
                                           value="<?php echo esc_attr($current_config['model']); ?>" 
                                           class="regular-text" 
                                           placeholder="<?php esc_attr_e('e.g., gpt-4, claude-3-opus, gemini-pro', 'data-machine'); ?>">
                                    <p class="description">
                                        <?php esc_html_e('Specify the model name for the selected provider.', 'data-machine'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="dm-ai-temperature-<?php echo esc_attr($step_id); ?>">
                                        <?php esc_html_e('Temperature', 'data-machine'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="dm-ai-temperature-<?php echo esc_attr($step_id); ?>" 
                                           name="temperature" 
                                           value="<?php echo esc_attr($current_config['temperature']); ?>" 
                                           min="0" 
                                           max="2" 
                                           step="0.1" 
                                           class="small-text">
                                    <p class="description">
                                        <?php esc_html_e('Controls randomness (0 = deterministic, 2 = very creative). Default: 0.7', 'data-machine'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="dm-ai-max-tokens-<?php echo esc_attr($step_id); ?>">
                                        <?php esc_html_e('Max Tokens', 'data-machine'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="dm-ai-max-tokens-<?php echo esc_attr($step_id); ?>" 
                                           name="max_tokens" 
                                           value="<?php echo esc_attr($current_config['max_tokens']); ?>" 
                                           min="1" 
                                           max="100000" 
                                           class="regular-text">
                                    <p class="description">
                                        <?php esc_html_e('Maximum tokens in the response. Higher values allow longer responses.', 'data-machine'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

            <!-- Step Status -->
            <div class="dm-step-status" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e4e7;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 500;">
                    <input type="checkbox" 
                           name="enabled" 
                           value="1" 
                           <?php checked($current_config['enabled'], true); ?>
                           style="margin: 0;">
                    <?php esc_html_e('Enable AI processing for this step', 'data-machine'); ?>
                </label>
                <p class="description" style="margin: 6px 0 0 0; color: #646970; font-size: 13px;">
                    <?php esc_html_e('Uncheck to skip AI processing and pass data through unchanged.', 'data-machine'); ?>
                </p>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle AJAX save for AI step configuration.
     * 
     * @param array $config_data Configuration data from AJAX request
     * @param int $project_id Project ID
     * @param int $step_position Step position
     * @return array Response array with success/error status
     */
    public function handle_ajax_save(array $config_data, int $project_id, int $step_position): array {
        try {
            $result = $this->save_step_ai_config($project_id, $step_position, $config_data);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => __('AI configuration saved successfully.', 'data-machine'),
                    'data' => $this->get_step_ai_config($project_id, $step_position)
                ];
            } else {
                return [
                    'success' => false,
                    'message' => __('Failed to save AI configuration.', 'data-machine')
                ];
            }
        } catch (\Exception $e) {
            $logger = apply_filters('dm_get_logger', null);
            if ($logger) {
                $logger->error('AI Step Config: Save failed', [
                    'project_id' => $project_id,
                    'step_position' => $step_position,
                    'error' => $e->getMessage()
                ]);
            }
            
            return [
                'success' => false,
                'message' => __('Error saving AI configuration: ', 'data-machine') . $e->getMessage()
            ];
        }
    }
}