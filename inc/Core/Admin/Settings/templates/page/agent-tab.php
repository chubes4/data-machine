<?php
/**
 * Agent Tab Template
 * 
 * AI controls including tool configuration and system prompts.
 */

$settings = dm_get_data_machine_settings();
$engine_mode = $settings['engine_mode'];
$global_prompt = $settings['global_system_prompt'];
$site_context_enabled = $settings['site_context_enabled'];

$disabled_attr = $engine_mode ? 'disabled' : '';

$all_tools = apply_filters('ai_tools', []);
$configurable_tools = [];
foreach ($all_tools as $tool_name => $tool_config) {
    if (!isset($tool_config['handler']) && ($tool_config['requires_config'] ?? false)) {
        $configurable_tools[$tool_name] = $tool_config;
    }
}
?>

<table class="form-table">
    <tr>
        <th scope="row"><?php esc_html_e('Tool Configuration', 'data-machine'); ?></th>
        <td>
            <?php if ($configurable_tools): ?>
                <div class="dm-tool-config-grid">
                    <?php foreach ($configurable_tools as $tool_name => $tool_config): ?>
                        <div class="dm-tool-config-item">
                            <h4><?php echo esc_html(ucfirst(str_replace('_', ' ', $tool_name))); ?></h4>
                            <p class="description"><?php echo esc_html($tool_config['description'] ?? ''); ?></p>
                            <?php $is_configured = apply_filters('dm_tool_configured', false, $tool_name); ?>
                            <span class="dm-config-status <?php echo $is_configured ? 'configured' : 'not-configured'; ?>">
                                <?php echo $is_configured ? esc_html__('Configured', 'data-machine') : esc_html__('Not Configured', 'data-machine'); ?>
                            </span>
                            <?php if (!$engine_mode): ?>
                                <button type="button" 
                                        class="button dm-modal-open" 
                                        data-template="tool-config"
                                        data-context='<?php echo esc_attr(wp_json_encode(['tool_id' => $tool_name])); ?>'>
                                    <?php esc_html_e('Configure', 'data-machine'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($engine_mode): ?>
                    <p class="description">
                        <?php esc_html_e('Tool configuration is disabled when Engine Mode is active.', 'data-machine'); ?>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <p><?php esc_html_e('No configurable tools are currently available.', 'data-machine'); ?></p>
            <?php endif; ?>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><?php esc_html_e('Global System Prompt', 'data-machine'); ?></th>
        <td>
            <textarea name="data_machine_settings[global_system_prompt]" 
                      rows="8" 
                      cols="70" 
                      class="large-text code"
                      <?php echo esc_attr($disabled_attr); ?>><?php echo esc_textarea($global_prompt); ?></textarea>
            <p class="description">
                <?php esc_html_e('Primary system message that sets the tone and overall behavior for all AI agents. This is the first and most important instruction that influences every AI response in your workflows.', 'data-machine'); ?>
            </p>
            <?php if ($engine_mode): ?>
                <p class="description">
                    <?php esc_html_e('Global system prompt is disabled when Engine Mode is active.', 'data-machine'); ?>
                </p>
            <?php endif; ?>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><?php esc_html_e('Provide site context to agents', 'data-machine'); ?></th>
        <td>
            <fieldset <?php echo esc_attr($disabled_attr); ?>>
                <label for="site_context_enabled">
                    <input type="checkbox" 
                           id="site_context_enabled"
                           name="data_machine_settings[site_context_enabled]" 
                           value="1" 
                           <?php checked($site_context_enabled, true); ?>
                           <?php echo esc_attr($disabled_attr); ?>>
                    <?php esc_html_e('Include WordPress site context in AI requests', 'data-machine'); ?>
                </label>
                <p class="description">
                    <?php esc_html_e('Automatically provides site information (post types, taxonomies, user stats) to AI agents for better context awareness.', 'data-machine'); ?>
                </p>
                <?php if ($engine_mode): ?>
                    <p class="description">
                        <?php esc_html_e('Site context controls are disabled when Engine Mode is active.', 'data-machine'); ?>
                    </p>
                <?php endif; ?>
            </fieldset>
        </td>
    </tr>
</table>