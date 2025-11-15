<?php
/**
 * Agent Tab Template
 *
 * AI controls including tool configuration and system prompts.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$settings = datamachine_get_datamachine_settings();
$engine_mode = $settings['engine_mode'];
$global_prompt = $settings['global_system_prompt'];
$site_context_enabled = $settings['site_context_enabled'];
$default_provider = $settings['default_provider'] ?? '';
$default_model = $settings['default_model'] ?? '';

$disabled_attr = $engine_mode ? 'disabled' : '';

$all_tools = apply_filters('datamachine_global_tools', []);
$configurable_tools = [];
foreach ($all_tools as $tool_name => $tool_config) {
    if (!isset($tool_config['handler']) && ($tool_config['requires_config'] ?? false)) {
        $configurable_tools[$tool_name] = $tool_config;
    }
}
?>

<table class="form-table">
    <tr>
        <th scope="row"><?php esc_html_e('Tool Configuration', 'datamachine'); ?></th>
        <td>
            <?php if ($configurable_tools): ?>
                <div class="datamachine-tool-config-grid">
                    <?php foreach ($configurable_tools as $tool_name => $tool_config): ?>
                        <div class="datamachine-tool-config-item">
                            <h4><?php echo esc_html(ucfirst(str_replace('_', ' ', $tool_name))); ?></h4>
                            <p class="description"><?php echo esc_html($tool_config['description'] ?? ''); ?></p>
                            <?php $is_configured = apply_filters('datamachine_tool_configured', false, $tool_name); ?>
                            <span class="datamachine-config-status <?php echo $is_configured ? 'configured' : 'not-configured'; ?>">
                                <?php echo $is_configured ? esc_html__('Configured', 'datamachine') : esc_html__('Not Configured', 'datamachine'); ?>
                            </span>
                            <?php if (!$engine_mode): ?>
                                <button type="button"
                                        class="button datamachine-open-modal"
                                        data-modal-id="datamachine-modal-tool-config-<?php echo esc_attr($tool_name); ?>">
                                    <?php esc_html_e('Configure', 'datamachine'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($engine_mode): ?>
                    <p class="description">
                        <?php esc_html_e('Tool configuration is disabled when Engine Mode is active.', 'datamachine'); ?>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <p><?php esc_html_e('No configurable tools are currently available.', 'datamachine'); ?></p>
            <?php endif; ?>
        </td>
    </tr>

    <tr>
        <th scope="row"><?php esc_html_e('Global System Prompt', 'datamachine'); ?></th>
        <td>
            <textarea name="datamachine_settings[global_system_prompt]" 
                      rows="8" 
                      cols="70" 
                      class="large-text code"
                      <?php echo esc_attr($disabled_attr); ?>><?php echo esc_textarea($global_prompt); ?></textarea>
            <p class="description">
                <?php esc_html_e('Primary system message that sets the tone and overall behavior for all AI agents. This is the first and most important instruction that influences every AI response in your workflows.', 'datamachine'); ?>
            </p>
            <?php if ($engine_mode): ?>
                <p class="description">
                    <?php esc_html_e('Global system prompt is disabled when Engine Mode is active.', 'datamachine'); ?>
                </p>
            <?php endif; ?>
        </td>
    </tr>

    <tr>
        <th scope="row"><?php esc_html_e('Default AI Provider & Model', 'datamachine'); ?></th>
        <td>
            <div class="datamachine-ai-provider-model-settings">
                <div class="datamachine-provider-field">
                    <label for="default_provider"><?php esc_html_e('Default AI Provider', 'datamachine'); ?></label>
                    <select name="datamachine_settings[default_provider]"
                            id="default_provider"
                            class="regular-text"
                            <?php echo esc_attr($disabled_attr); ?>>
                        <option value=""><?php esc_html_e('Select Provider...', 'datamachine'); ?></option>
                    </select>
                </div>

                <div class="datamachine-model-field">
                    <label for="default_model"><?php esc_html_e('Default AI Model', 'datamachine'); ?></label>
                    <select name="datamachine_settings[default_model]"
                            id="default_model"
                            class="regular-text"
                            <?php echo esc_attr($disabled_attr); ?>>
                        <option value=""><?php esc_html_e('Select provider first...', 'datamachine'); ?></option>
                    </select>
                </div>
            </div>
            <p class="description">
                <?php esc_html_e('Set the default AI provider and model for new AI steps and chat requests. These can be overridden on a per-step or per-request basis.', 'datamachine'); ?>
            </p>
            <?php if ($engine_mode): ?>
                <p class="description">
                    <?php esc_html_e('Default AI provider and model settings are disabled when Engine Mode is active.', 'datamachine'); ?>
                </p>
            <?php endif; ?>
        </td>
    </tr>

    <tr>
        <th scope="row"><?php esc_html_e('Provide site context to agents', 'datamachine'); ?></th>
        <td>
            <fieldset <?php echo esc_attr($disabled_attr); ?>>
                <label for="site_context_enabled">
                    <input type="checkbox" 
                           id="site_context_enabled"
                           name="datamachine_settings[site_context_enabled]" 
                           value="1" 
                           <?php checked($site_context_enabled, true); ?>
                           <?php echo esc_attr($disabled_attr); ?>>
                    <?php esc_html_e('Include WordPress site context in AI requests', 'datamachine'); ?>
                </label>
                <p class="description">
                    <?php esc_html_e('Automatically provides site information (post types, taxonomies, user stats) to AI agents for better context awareness.', 'datamachine'); ?>
                </p>
                <?php if ($engine_mode): ?>
                    <p class="description">
                        <?php esc_html_e('Site context controls are disabled when Engine Mode is active.', 'datamachine'); ?>
                    </p>
                <?php endif; ?>
            </fieldset>
        </td>
    </tr>
</table>
