<?php
/**
 * Settings Page Template with Tabbed Interface
 *
 * Tabbed settings interface with Admin, Agent, and WordPress sections.
 * Uses WordPress native nav-tab-wrapper pattern for consistency.
 *
 * @package DataMachine\Core\Admin\Settings\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

// Get active tab from URL parameter or default to admin
$active_tab = 'admin';
if (isset($_GET['tab'])) {
    $active_tab = sanitize_key($_GET['tab']);
}
$valid_tabs = ['admin', 'agent', 'wordpress'];
if (!in_array($active_tab, $valid_tabs)) {
    $active_tab = 'admin';
}

?>
<div class="wrap datamachine-settings-page">
    <h1><?php echo esc_html($page_title ?? __('Data Machine Settings', 'data-machine')); ?></h1>
    
    <!-- Tab Navigation -->
    <h2 class="nav-tab-wrapper datamachine-nav-tab-wrapper">
        <a href="?page=data-machine-settings&tab=admin" 
           class="nav-tab <?php echo $active_tab === 'admin' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Admin', 'data-machine'); ?>
        </a>
        <a href="?page=data-machine-settings&tab=agent" 
           class="nav-tab <?php echo $active_tab === 'agent' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Agent', 'data-machine'); ?>
        </a>
        <a href="?page=data-machine-settings&tab=wordpress" 
           class="nav-tab <?php echo $active_tab === 'wordpress' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('WordPress', 'data-machine'); ?>
        </a>
    </h2>
    
    <!-- Tab Content -->
    <form method="post" action="options.php" class="datamachine-settings-form">
        <?php settings_fields('data_machine_settings'); ?>
        
        <div id="datamachine-tab-admin" class="datamachine-tab-content <?php echo $active_tab === 'admin' ? 'active' : ''; ?>">
            <?php echo wp_kses(apply_filters('datamachine_render_template', '', 'page/admin-tab'), datamachine_allowed_html()); ?>
        </div>
        
        <div id="datamachine-tab-agent" class="datamachine-tab-content <?php echo $active_tab === 'agent' ? 'active' : ''; ?>">
            <?php echo wp_kses(apply_filters('datamachine_render_template', '', 'page/agent-tab'), datamachine_allowed_html()); ?>
        </div>
        
        <div id="datamachine-tab-wordpress" class="datamachine-tab-content <?php echo $active_tab === 'wordpress' ? 'active' : ''; ?>">
            <?php echo wp_kses(apply_filters('datamachine_render_template', '', 'page/wordpress-tab'), datamachine_allowed_html()); ?>
        </div>
        
        <div class="datamachine-submit-container">
            <?php submit_button(); ?>
        </div>
    </form>

    <!-- Pre-rendered Tool Config Modals (no AJAX loading) -->
    <?php
    // Get all configurable tools
    $all_tools = apply_filters('ai_tools', []);
    $configurable_tools = [];
    foreach ($all_tools as $tool_name => $tool_config) {
        if (!isset($tool_config['handler']) && ($tool_config['requires_config'] ?? false)) {
            $configurable_tools[$tool_name] = $tool_config;
        }
    }

    // Pre-render modal for each configurable tool
    foreach ($configurable_tools as $tool_id => $tool_info):
        $tool_config = apply_filters('datamachine_get_tool_config', [], $tool_id);
        ?>
        <div id="datamachine-modal-tool-config-<?php echo esc_attr($tool_id); ?>"
             class="datamachine-modal"
             aria-hidden="true"
             style="display: none;">
            <div class="datamachine-modal-overlay"></div>
            <div class="datamachine-modal-container">
                <div class="datamachine-modal-header">
                    <h2 class="datamachine-modal-title">
                        <?php
                        /* translators: %s: tool name */
                        echo esc_html(sprintf(__('Configure %s', 'data-machine'), ucwords(str_replace('_', ' ', $tool_id))));
                        ?>
                    </h2>
                    <button type="button" class="datamachine-modal-close" aria-label="<?php esc_attr_e('Close', 'data-machine'); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="datamachine-modal-body">
                    <?php
                    // Render tool config form
                    if (isset($all_tools[$tool_id])) {
                        $tool_class = $all_tools[$tool_id]['class'];

                        if (class_exists($tool_class) && method_exists($tool_class, 'get_config_fields')) {
                            $tool_instance = new $tool_class();
                            $config_fields = $tool_instance->get_config_fields();

                            if (!empty($config_fields)):
                                ?>
                                <div class="datamachine-tool-config-container">
                                    <form id="datamachine-<?php echo esc_attr($tool_id); ?>-config-form" data-tool-id="<?php echo esc_attr($tool_id); ?>">
                                        <table class="form-table">
                                            <tbody>
                                                <?php foreach ($config_fields as $field_name => $field_config): ?>
                                                    <tr class="form-field">
                                                        <th scope="row">
                                                            <label for="<?php echo esc_attr($tool_id . '_' . $field_name); ?>">
                                                                <?php echo esc_html($field_config['label']); ?>
                                                            </label>
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

                                    <div class="datamachine-tool-config-actions">
                                        <button type="button" class="button button-secondary datamachine-modal-close">
                                            <?php esc_html_e('Cancel', 'data-machine'); ?>
                                        </button>
                                        <button type="button"
                                                class="button button-primary datamachine-tool-config-save"
                                                data-tool-id="<?php echo esc_attr($tool_id); ?>">
                                            <?php esc_html_e('Save Configuration', 'data-machine'); ?>
                                        </button>
                                    </div>

                                    <div class="datamachine-settings-tool-notice">
                                        <p class="description">
                                            <?php esc_html_e('Once configured, this tool will be available for use in all AI steps across all pipelines.', 'data-machine'); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php
                            else:
                                ?>
                                <div class="datamachine-tool-config-container">
                                    <p><?php esc_html_e('This tool does not require any configuration.', 'data-machine'); ?></p>
                                </div>
                                <?php
                            endif;
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>