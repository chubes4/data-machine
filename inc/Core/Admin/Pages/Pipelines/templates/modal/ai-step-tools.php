<?php
/**
 * AI Step Tools Modal Template
 *
 * Pure rendering template for AI step tools selection modal content.
 * Handles tool enablement, configuration validation, and selection UI.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

// Expected variables passed to this template:
// $global_enabled_tools - Array of all available tools
// $modal_enabled_tools - Array of tools enabled for this step
// $pipeline_step_id - Pipeline step identifier

// No tools available
if (empty($global_enabled_tools)) {
    return '';
}

?>
<tr class="form-field">
    <th scope="row">
        <label><?php echo esc_html__('Available Tools', 'data-machine'); ?></label>
    </th>
    <td>
        <fieldset>
            <legend class="screen-reader-text"><?php echo esc_html__('Select available tools for this AI step', 'data-machine'); ?></legend>
            
            <?php foreach ($global_enabled_tools as $tool_id => $tool_config): ?>
                <?php
                // Configuration requirements
                $tool_configured = apply_filters('dm_tool_configured', false, $tool_id);
                $requires_config = !empty($tool_config['requires_config']);
                $config_needed = $requires_config && !$tool_configured;
                
                // Modal checkbox state: what user selected for this specific pipeline step
                $tool_modal_enabled = in_array($tool_id, $modal_enabled_tools);
                
                // Simple logic: checkbox checked if tool is in enabled_tools array (period)
                $should_be_checked = $tool_modal_enabled;
                
                // Generate simple tool name from tool_id (e.g., "local_search" -> "Local Search")
                $tool_name = $tool_config['name'] ?? ucwords(str_replace('_', ' ', $tool_id));
                
                // Get tool description for tooltip
                $tool_description = $tool_config['description'] ?? '';
                ?>
                
                <div class="dm-tool-option">
                    <label>
                        <input type="checkbox" 
                               name="enabled_tools[]" 
                               value="<?php echo esc_attr($tool_id); ?>"
                               <?php checked($should_be_checked, true); ?>
                               <?php disabled($config_needed, true); ?> />
                        <span><?php echo esc_html($tool_name); ?></span>
                        
                        <?php if (!empty($tool_description)): ?>
                            <span class="dm-tool-info" data-tooltip="<?php echo esc_attr($tool_description); ?>">ⓘ</span>
                        <?php endif; ?>
                    </label>
                    
                    <?php if ($config_needed): ?>
                        <span class="dm-tool-config-warning">
                            ⚠ <a href="<?php echo esc_url(admin_url('options-general.php?page=data-machine-settings')); ?>" target="_blank">
                                <?php echo esc_html__('Configure in settings', 'data-machine'); ?>
                            </a>
                        </span>
                    <?php endif; ?>
                </div>
                
            <?php endforeach; ?>
            
        </fieldset>
    </td>
</tr>