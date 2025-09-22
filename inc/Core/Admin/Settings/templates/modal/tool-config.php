<?php
/**
 * Tool Configuration Modal Template
 *
 * Provides configuration interface for AI tools that require setup.
 * Each tool has its own configuration section with appropriate fields.
 * 
 * Migrated from Pipelines to Settings page for better UX and logical organization.
 *
 * @package DataMachine\Core\Admin\Settings\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

// Context: $tool_id passed from modal trigger
$tool_id = $tool_id ?? '';

if (empty($tool_id)) {
    echo '<div class="dm-error">';
    echo '<h4>' . esc_html__('Configuration Error', 'data-machine') . '</h4>';
    echo '<p>' . esc_html__('No tool ID specified for configuration.', 'data-machine') . '</p>';
    echo '</div>';
    return;
}

// Get tool configuration
$tool_config = apply_filters('dm_get_tool_config', [], $tool_id);

?>
<div class="dm-tool-config-modal-content">
    <?php
    // Get all registered tools
    $all_tools = apply_filters('ai_tools', []);

    if (isset($all_tools[$tool_id])) {
        $tool_class = $all_tools[$tool_id]['class'];

        // Check if the tool has configuration fields
        if (class_exists($tool_class) && method_exists($tool_class, 'get_config_fields')) {
            $config_fields = call_user_func([$tool_class, 'get_config_fields']);

            if (!empty($config_fields)) {
                // Generate dynamic configuration UI
                ?>
                <div class="dm-tool-config-container">
                    <div class="dm-tool-config-header">
                        <!-- translators: %s: tool name -->
                        <h3><?php echo esc_html(sprintf(__('Configure %s', 'data-machine'), ucwords(str_replace('_', ' ', $tool_id)))); ?></h3>
                    </div>

                    <form id="dm-<?php echo esc_attr($tool_id); ?>-config-form" data-tool-id="<?php echo esc_attr($tool_id); ?>">
                        <table class="form-table">
                            <tbody>
                                <?php foreach ($config_fields as $field_name => $field_config): ?>
                                    <tr class="form-field">
                                        <th scope="row">
                                            <label for="<?php echo esc_attr($tool_id . '_' . $field_name); ?>"><?php echo esc_html($field_config['label']); ?></label>
                                        </th>
                                        <td>
                                            <?php
                                            $field_type = $field_config['type'] ?? 'text';
                                            $field_value = $tool_config[$field_name] ?? '';
                                            $field_placeholder = $field_config['placeholder'] ?? '';
                                            $field_required = !empty($field_config['required']);
                                            ?>

                                            <input type="<?php echo esc_attr($field_type); ?>"
                                                   id="<?php echo esc_attr($tool_id . '_' . $field_name); ?>"
                                                   name="<?php echo esc_attr($field_name); ?>"
                                                   value="<?php echo esc_attr($field_value); ?>"
                                                   class="regular-text"
                                                   placeholder="<?php echo esc_attr($field_placeholder); ?>"
                                                   <?php echo $field_required ? 'required' : ''; ?> />

                                            <?php if (!empty($field_config['description'])): ?>
                                                <p class="description">
                                                    <?php echo esc_html($field_config['description']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                </div>
                <?php
            } else {
                // Tool exists but has no configuration fields - show no configuration needed message
                ?>
                <div class="dm-tool-config-container">
                    <div class="dm-tool-config-header">
                        <!-- translators: %s: tool name -->
                        <h3><?php echo esc_html(sprintf(__('Configure %s', 'data-machine'), ucwords(str_replace('_', ' ', $tool_id)))); ?></h3>
                        <p><?php esc_html_e('This tool does not require any configuration.', 'data-machine'); ?></p>
                    </div>
                </div>
                <?php
            }
        } else {
            // Tool exists but no config fields method - show no configuration available
            ?>
            <div class="dm-tool-config-container">
                <div class="dm-tool-config-header">
                    <!-- translators: %s: tool name -->
                    <h3><?php echo esc_html(sprintf(__('Configure %s', 'data-machine'), ucwords(str_replace('_', ' ', $tool_id)))); ?></h3>
                    <p><?php esc_html_e('This tool does not require any configuration.', 'data-machine'); ?></p>
                </div>
            </div>
            <?php
        }
    } else {
        // Tool not found
        echo '<div class="dm-error">';
        echo '<h4>' . esc_html__('Unknown Tool', 'data-machine') . '</h4>';
        /* translators: %s: tool identifier */
        echo '<p>' . esc_html(sprintf(__('Configuration for tool "%s" is not available.', 'data-machine'), esc_html($tool_id))) . '</p>';
        echo '</div>';
    }
    ?>
    
    <!-- Save Actions -->
    <div class="dm-tool-config-actions">
        <button type="button" class="button button-secondary dm-modal-close">
            <?php esc_html_e('Cancel', 'data-machine'); ?>
        </button>
        <button type="button" class="button button-primary dm-modal-close" 
                data-template="tool-config-save"
                data-context='<?php echo esc_attr(wp_json_encode(['tool_id' => $tool_id])); ?>'>
            <?php esc_html_e('Save Configuration', 'data-machine'); ?>
        </button>
    </div>
    
    <!-- Settings page context - no navigation back to pipeline needed -->
    <div class="dm-settings-tool-notice">
        <p class="description">
            <?php esc_html_e('Once configured, this tool will be available for use in all AI steps across all pipelines.', 'data-machine'); ?>
        </p>
    </div>
</div>