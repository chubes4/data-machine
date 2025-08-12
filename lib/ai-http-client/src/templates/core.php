<?php
/**
 * Core AI Provider Manager Template
 * 
 * Renders the main provider selection, API key input, and model selector components.
 * This is the base template that contains all required components for AI provider setup.
 *
 * Available variables:
 * @var string $unique_id - Unique form identifier
 * @var string $plugin_context - Plugin context for configuration isolation
 * @var string $selected_provider - Currently selected provider
 * @var array $provider_config - Provider configuration data
 * @var string $step_id - Optional step ID for step-aware field naming
 * @var array $all_config - Complete provider configuration
 */

defined('ABSPATH') || exit;

// Generate step-aware field names
$provider_field = 'ai_provider';
$api_key_field = 'ai_api_key';
$model_field = 'ai_model';

if (!empty($step_id)) {
    $step_key = sanitize_key($step_id);
    $provider_field = 'ai_step_' . $step_key . '_provider';
    $api_key_field = 'ai_step_' . $step_key . '_api_key';
    $model_field = 'ai_step_' . $step_key . '_model';
}

// Get available providers
$all_providers = apply_filters('ai_providers', []);
$llm_providers = array_filter($all_providers, function($provider) {
    return isset($provider['type']) && $provider['type'] === 'llm';
});

// Get current API key value (API keys are merged into each provider's config)
$current_api_key = '';
if (isset($all_config[$selected_provider]['api_key'])) {
    $current_api_key = $all_config[$selected_provider]['api_key'];
}

// Get current model value
$selected_model = $provider_config['model'] ?? '';
?>

<table class="form-table ai-http-provider-config" data-plugin-context="<?php echo esc_attr($plugin_context); ?>">
    <!-- Provider Selector -->
    <tr class="form-field">
        <th scope="row">
            <label for="<?php echo esc_attr($unique_id); ?>_provider"><?php esc_html_e('AI Provider', 'ai-http-client'); ?></label>
        </th>
        <td>
            <select id="<?php echo esc_attr($unique_id); ?>_provider" 
                    name="<?php echo esc_attr($provider_field); ?>" 
                    data-component-id="<?php echo esc_attr($unique_id); ?>" 
                    data-component-type="provider_selector" 
                    class="regular-text">
                <?php foreach ($llm_providers as $provider_key => $provider_info): ?>
                    <option value="<?php echo esc_attr($provider_key); ?>" <?php selected($selected_provider, $provider_key); ?>>
                        <?php echo esc_html($provider_info['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><small class="description"><?php esc_html_e('Select the AI provider to use for requests.', 'ai-http-client'); ?></small>
        </td>
    </tr>

    <!-- API Key Input -->
    <tr class="form-field">
        <th scope="row">
            <label for="<?php echo esc_attr($unique_id); ?>_api_key"><?php esc_html_e('API Key', 'ai-http-client'); ?></label>
        </th>
        <td>
            <input type="password" 
                   id="<?php echo esc_attr($unique_id); ?>_api_key" 
                   name="<?php echo esc_attr($api_key_field); ?>" 
                   value="<?php echo esc_attr($current_api_key); ?>" 
                   data-component-id="<?php echo esc_attr($unique_id); ?>" 
                   data-component-type="api_key_input" 
                   data-provider="<?php echo esc_attr($selected_provider); ?>" 
                   class="regular-text" 
                   placeholder="<?php esc_attr_e('Enter API key', 'ai-http-client'); ?>" />
            <br><small class="description"><?php esc_html_e('Enter your API key for the selected provider.', 'ai-http-client'); ?></small>
        </td>
    </tr>

    <!-- Model Selector -->
    <tr class="form-field">
        <th scope="row">
            <label for="<?php echo esc_attr($unique_id); ?>_model"><?php esc_html_e('Model', 'ai-http-client'); ?></label>
        </th>
        <td>
            <div>
                <select id="<?php echo esc_attr($unique_id); ?>_model" 
                        name="<?php echo esc_attr($model_field); ?>" 
                        data-component-id="<?php echo esc_attr($unique_id); ?>" 
                        data-component-type="model_selector" 
                        data-provider="<?php echo esc_attr($selected_provider); ?>" 
                        class="regular-text">
                    <?php
                    try {
                        // Use unified model fetcher for dynamic model loading
                        $models = AI_HTTP_Unified_Model_Fetcher::fetch_models($selected_provider, $provider_config);
                        
                        if (empty($models)) {
                            echo '<option value="">' . esc_html__('Enter API key to load models', 'ai-http-client') . '</option>';
                        } else {
                            foreach ($models as $model_id => $model_name) {
                                $selected_attr = ($selected_model === $model_id) ? 'selected' : '';
                                echo '<option value="' . esc_attr($model_id) . '" ' . $selected_attr . '>';
                                echo esc_html($model_name);
                                echo '</option>';
                            }
                        }
                    } catch (Exception $e) {
                        echo '<option value="">' . esc_html__('No API key configured', 'ai-http-client') . '</option>';
                    }
                    ?>
                </select>
            </div>
            <br><small class="description"><?php esc_html_e('Select the AI model to use for requests.', 'ai-http-client'); ?></small>
        </td>
    </tr>
</table>