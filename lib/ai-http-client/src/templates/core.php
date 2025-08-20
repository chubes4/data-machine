<?php
/**
 * Core AI Provider Manager Template
 * 
 * Renders the main provider selection, API key input, and model selector components.
 * This is the base template that contains all required components for AI provider setup.
 *
 * Available variables:
 * @var string $unique_id - Unique form identifier
 * @var string $selected_provider - Currently selected provider
 * @var array $provider_config - Provider configuration data
 * @var array $all_config - Complete provider configuration
 */

defined('ABSPATH') || exit;

// Use standard field names - AI HTTP Client no longer stores step-specific configuration
$provider_field = 'ai_provider';
$api_key_field = 'ai_api_key';
$model_field = 'ai_model';

// Get available providers
$all_providers = apply_filters('ai_providers', []);
$llm_providers = array_filter($all_providers, function($provider) {
    return isset($provider['type']) && $provider['type'] === 'llm';
});


// Get current API key value from shared storage using the ai_provider_api_keys filter
$shared_api_keys = apply_filters('ai_provider_api_keys', null);
$current_api_key = (is_array($shared_api_keys) && isset($shared_api_keys[$selected_provider])) ? $shared_api_keys[$selected_provider] : '';

// Get current model value
$selected_model = $provider_config['model'] ?? '';

// Get step context if available for tool configuration navigation
$step_context = $all_config['step_context'] ?? [];
?>

<table id="<?php echo esc_attr($unique_id); ?>" class="form-table ai-http-provider-config">
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
                        data-selected-model="<?php echo esc_attr($selected_model); ?>"
                        class="regular-text">
                    <?php
                    try {
                        // Add API key to provider config for model fetching
                        $provider_config_with_key = $provider_config;
                        $provider_config_with_key['api_key'] = $current_api_key;
                        
                        // Use filter-based model fetching for dynamic model loading
                        $models = apply_filters('ai_models', $selected_provider, $provider_config_with_key);
                        
                        if (empty($models)) {
                            echo '<option value="">' . esc_html__('Enter API key to load models', 'ai-http-client') . '</option>';
                        } else {
                            foreach ($models as $model_id => $model_name) {
                                $selected_attr = ($selected_model === $model_id) ? 'selected' : '';
                                // Ensure model_name is always a string for display
                                $display_name = is_array($model_name) ? 
                                    ($model_name['name'] ?? $model_name['id'] ?? $model_id) : 
                                    (string)$model_name;
                                echo '<option value="' . esc_attr($model_id) . '" ' . $selected_attr . '>';
                                echo esc_html($display_name);
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
    
    <?php
    // Available Tools Section (Data Machine Integration)
    // Only show if we're in Data Machine context with general tools available
    $all_tools = apply_filters('ai_tools', []);
    $general_tools = array_filter($all_tools, function($tool) {
        return !isset($tool['handler']); // General tools don't have a handler property
    });
    
    if (!empty($general_tools)):
        // Get currently enabled tools from AI configuration
        $ai_config = $all_config['ai_config'] ?? [];
        $enabled_tools = $ai_config['enabled_tools'] ?? [];
    ?>
    <tr class="form-field">
        <th scope="row">
            <label><?php esc_html_e('Available Tools', 'data-machine'); ?></label>
        </th>
        <td>
            <fieldset>
                <legend class="screen-reader-text"><?php esc_html_e('Select available tools for this AI step', 'data-machine'); ?></legend>
                
                <?php foreach ($general_tools as $tool_id => $tool_config): ?>
                    <?php
                    $tool_configured = apply_filters('dm_tool_configured', false, $tool_id);
                    $configure_needed = !$tool_configured && !empty($tool_config['requires_config']);
                    $tool_enabled = in_array($tool_id, $enabled_tools) && $tool_configured;
                    ?>
                    <div class="dm-tool-option" style="margin-bottom: 8px;">
                        <label style="display: inline-block; margin-right: 10px;">
                            <input type="checkbox" 
                                   name="enabled_tools[]" 
                                   value="<?php echo esc_attr($tool_id); ?>"
                                   <?php checked($tool_enabled); ?>
                                   <?php disabled($configure_needed); ?> />
                            <span style="margin-left: 4px;"><?php echo esc_html($tool_config['description'] ?? ucfirst($tool_id)); ?></span>
                        </label>
                        
                        <?php if ($configure_needed): ?>
                            <?php
                            // Build context data for tool configuration modal
                            $tool_context = ['tool_id' => $tool_id];
                            if (!empty($step_context)) {
                                $tool_context = array_merge($tool_context, $step_context);
                            }
                            ?>
                            <a href="#" 
                               class="dm-modal-open dm-tool-configure-link" 
                               data-template="tool-config"
                               data-context='<?php echo esc_attr(json_encode($tool_context)); ?>'
                               style="color: #0073aa; text-decoration: none; font-size: 12px;">
                                <?php esc_html_e('Configure', 'data-machine'); ?>
                            </a>
                        <?php elseif ($tool_configured && !$tool_enabled): ?>
                            <span style="color: #00a32a; font-size: 12px;">
                                <?php esc_html_e('✓ Configured', 'data-machine'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <p class="description" style="margin-top: 8px;">
                    <?php esc_html_e('Tools provide additional capabilities like web search for fact-checking. Configure required tools before enabling them.', 'data-machine'); ?>
                </p>
            </fieldset>
        </td>
    </tr>
    <?php endif; ?>
</table>