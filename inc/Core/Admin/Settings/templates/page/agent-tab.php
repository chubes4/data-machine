<?php
/**
 * Agent Tab Template
 *
 * AI controls including tool configuration and system prompts.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$datamachine_settings = \DataMachine\Core\PluginSettings::all();
$datamachine_engine_mode = $datamachine_settings['engine_mode'] ?? false;
$datamachine_global_prompt = $datamachine_settings['global_system_prompt'] ?? '';
$datamachine_site_context_enabled = $datamachine_settings['site_context_enabled'] ?? false;
$datamachine_default_provider = $datamachine_settings['default_provider'] ?? '';
$datamachine_default_model = $datamachine_settings['default_model'] ?? '';
$datamachine_enabled_tools = $datamachine_settings['enabled_tools'] ?? [];
$datamachine_max_turns = $datamachine_settings['max_turns'] ?? 12;

$datamachine_disabled_attr = $datamachine_engine_mode ? 'disabled' : '';

$datamachine_tool_manager = new \DataMachine\Engine\AI\Tools\ToolManager();
$datamachine_global_tools = $datamachine_tool_manager->get_global_tools();

// Pre-populate enabled_tools with all configured tools (opt-out pattern)
if (empty($datamachine_enabled_tools)) {
    $datamachine_opt_out_defaults = $datamachine_tool_manager->get_opt_out_defaults();
    foreach ($datamachine_opt_out_defaults as $datamachine_tool_id) {
        $datamachine_enabled_tools[$datamachine_tool_id] = true;
    }
}
?>

<table class="form-table">
    <tr>
        <th scope="row"><?php esc_html_e('Tool Configuration', 'datamachine'); ?></th>
        <td>
            <?php if ($datamachine_global_tools): ?>
                <div class="datamachine-tool-config-grid">
                    <?php foreach ($datamachine_global_tools as $datamachine_tool_name => $datamachine_tool_config): ?>
                        <?php
                        $datamachine_is_configured = $datamachine_tool_manager->is_tool_configured($datamachine_tool_name);
                        $datamachine_is_enabled = isset($datamachine_enabled_tools[$datamachine_tool_name]);
                        $datamachine_requires_config = $datamachine_tool_manager->requires_configuration($datamachine_tool_name);
                        $datamachine_tool_label = $datamachine_tool_config['label'] ?? ucfirst(str_replace('_', ' ', $datamachine_tool_name));
                        ?>
                        <div class="datamachine-tool-config-item">
                            <h4><?php echo esc_html($datamachine_tool_label); ?></h4>
                            <p class="description"><?php echo esc_html($datamachine_tool_config['description'] ?? ''); ?></p>
                            <div class="datamachine-tool-controls">
                                <span class="datamachine-config-status <?php echo $datamachine_is_configured ? 'configured' : 'not-configured'; ?>">
                                    <?php echo $datamachine_is_configured ? esc_html__('Configured', 'datamachine') : esc_html__('Not Configured', 'datamachine'); ?>
                                </span>

                                <?php if (!$datamachine_engine_mode): ?>
                                    <?php if ($datamachine_is_configured): ?>
                                        <!-- Show toggle for configured tools -->
                                        <label class="datamachine-tool-enabled-toggle">
                                            <input type="checkbox"
                                                   name="datamachine_settings[enabled_tools][<?php echo esc_attr($datamachine_tool_name); ?>]"
                                                   value="1"
                                                   <?php checked($datamachine_is_enabled, true); ?>>
                                            <?php esc_html_e('Enable for agents', 'datamachine'); ?>
                                        </label>
                                    <?php else: ?>
                                        <!-- Show disabled checkbox for unconfigured tools -->
                                        <label class="datamachine-tool-enabled-toggle datamachine-tool-disabled">
                                            <input type="checkbox" disabled>
                                            <span class="description"><?php esc_html_e('Configure to enable', 'datamachine'); ?></span>
                                        </label>
                                    <?php endif; ?>

                                    <?php if ($datamachine_requires_config): ?>
                                        <!-- Only show Configure button for tools that need configuration -->
                                        <button type="button"
                                                class="button datamachine-open-modal"
                                                data-modal-id="datamachine-modal-tool-config-<?php echo esc_attr($datamachine_tool_name); ?>">
                                            <?php esc_html_e('Configure', 'datamachine'); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($datamachine_engine_mode): ?>
                    <p class="description">
                        <?php esc_html_e('Tool configuration is disabled when Engine Mode is active.', 'datamachine'); ?>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <p><?php esc_html_e('No global tools are currently available.', 'datamachine'); ?></p>
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
                      <?php echo esc_attr($datamachine_disabled_attr); ?>><?php echo esc_textarea($datamachine_global_prompt); ?></textarea>
            <p class="description">
                <?php esc_html_e('Primary system message that sets the tone and overall behavior for all AI agents. This is the first and most important instruction that influences every AI response in your workflows.', 'datamachine'); ?>
            </p>
            <?php if ($datamachine_engine_mode): ?>
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
                            <?php echo esc_attr($datamachine_disabled_attr); ?>>
                        <option value=""><?php esc_html_e('Select Provider...', 'datamachine'); ?></option>
                    </select>
                </div>

                <div class="datamachine-model-field">
                    <label for="default_model"><?php esc_html_e('Default AI Model', 'datamachine'); ?></label>
                    <select name="datamachine_settings[default_model]"
                            id="default_model"
                            class="regular-text"
                            <?php echo esc_attr($datamachine_disabled_attr); ?>>
                        <option value=""><?php esc_html_e('Select provider first...', 'datamachine'); ?></option>
                    </select>
                </div>
            </div>
            <p class="description">
                <?php esc_html_e('Set the default AI provider and model for new AI steps and chat requests. These can be overridden on a per-step or per-request basis.', 'datamachine'); ?>
            </p>
            <?php if ($datamachine_engine_mode): ?>
                <p class="description">
                    <?php esc_html_e('Default AI provider and model settings are disabled when Engine Mode is active.', 'datamachine'); ?>
                </p>
            <?php endif; ?>
        </td>
    </tr>

    <tr>
        <th scope="row"><?php esc_html_e('Provide site context to agents', 'datamachine'); ?></th>
        <td>
            <fieldset <?php echo esc_attr($datamachine_disabled_attr); ?>>
                <label for="site_context_enabled">
                    <input type="checkbox"
                           id="site_context_enabled"
                           name="datamachine_settings[site_context_enabled]"
                           value="1"
                           <?php checked($datamachine_site_context_enabled, true); ?>
                           <?php echo esc_attr($datamachine_disabled_attr); ?>>
                    <?php esc_html_e('Include WordPress site context in AI requests', 'datamachine'); ?>
                </label>
                <p class="description">
                    <?php esc_html_e('Automatically provides site information (post types, taxonomies, user stats) to AI agents for better context awareness.', 'datamachine'); ?>
                </p>
                <?php if ($datamachine_engine_mode): ?>
                    <p class="description">
                        <?php esc_html_e('Site context controls are disabled when Engine Mode is active.', 'datamachine'); ?>
                    </p>
                <?php endif; ?>
            </fieldset>
        </td>
    </tr>

    <tr>
        <th scope="row"><?php esc_html_e('Maximum conversation turns', 'datamachine'); ?></th>
        <td>
            <input type="number"
                   name="datamachine_settings[max_turns]"
                   value="<?php echo esc_attr($datamachine_max_turns); ?>"
                   min="1"
                   max="50"
                   class="small-text"
                   <?php echo esc_attr($datamachine_disabled_attr); ?>>
            <p class="description">
                <?php esc_html_e('Maximum number of conversation turns allowed for AI agents (1-50). Applies to both pipeline and chat conversations.', 'datamachine'); ?>
            </p>
            <?php if ($datamachine_engine_mode): ?>
                <p class="description">
                    <?php esc_html_e('Maximum turns setting is disabled when Engine Mode is active.', 'datamachine'); ?>
                </p>
            <?php endif; ?>
        </td>
    </tr>
</table>
