<?php
/**
 * Settings Page Template with Tabbed Interface
 *
 * Tabbed settings interface with Admin, Agent, and AI Providers sections.
 * Uses WordPress native nav-tab-wrapper pattern for consistency.
 *
 * @package DataMachine\Core\Admin\Settings\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

// Validate nonce for tab switching
$datamachine_nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
if (empty($datamachine_nonce) || !wp_verify_nonce($datamachine_nonce, 'datamachine_settings_tab')) {
    $datamachine_active_tab = 'admin';
} else {
    // Get active tab from URL parameter or default to admin
    $datamachine_active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'admin';
}
$datamachine_valid_tabs = ['admin', 'agent', 'ai-providers'];
if (!in_array($datamachine_active_tab, $datamachine_valid_tabs, true)) {
    $datamachine_active_tab = 'admin';
}

?>
<div class="wrap datamachine-settings-page">
    <h1><?php echo esc_html($page_title ?? __('Data Machine Settings', 'data-machine')); ?></h1>
    
    <!-- Tab Navigation -->
    <h2 class="nav-tab-wrapper datamachine-nav-tab-wrapper">
        <?php $datamachine_tab_nonce = wp_create_nonce('datamachine_settings_tab'); ?>
        <a href="?page=datamachine-settings&_wpnonce=<?php echo esc_attr($datamachine_tab_nonce); ?>&tab=admin" 
           class="nav-tab <?php echo $datamachine_active_tab === 'admin' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Admin', 'data-machine'); ?>
        </a>
        <a href="?page=datamachine-settings&_wpnonce=<?php echo esc_attr($datamachine_tab_nonce); ?>&tab=agent"
           class="nav-tab <?php echo $datamachine_active_tab === 'agent' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Agent', 'data-machine'); ?>
        </a>
        <a href="?page=datamachine-settings&_wpnonce=<?php echo esc_attr($datamachine_tab_nonce); ?>&tab=ai-providers"
           class="nav-tab <?php echo $datamachine_active_tab === 'ai-providers' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('AI Providers', 'data-machine'); ?>
        </a>
    </h2>
    
    <!-- Tab Content -->
    <form method="post" action="options.php" class="datamachine-settings-form">
        <?php settings_fields('datamachine_settings'); ?>
        
        <div id="datamachine-tab-admin" class="datamachine-tab-content <?php echo $datamachine_active_tab === 'admin' ? 'active' : ''; ?>">
            <?php echo wp_kses(apply_filters('datamachine_render_template', '', 'page/admin-tab'), datamachine_allowed_html()); ?>
        </div>
        
        <div id="datamachine-tab-agent" class="datamachine-tab-content <?php echo $datamachine_active_tab === 'agent' ? 'active' : ''; ?>">
            <?php echo wp_kses(apply_filters('datamachine_render_template', '', 'page/agent-tab'), datamachine_allowed_html()); ?>
        </div>

        <div id="datamachine-tab-ai-providers" class="datamachine-tab-content <?php echo $datamachine_active_tab === 'ai-providers' ? 'active' : ''; ?>">
            <?php echo wp_kses(apply_filters('datamachine_render_template', '', 'page/ai-providers-tab'), datamachine_allowed_html()); ?>
        </div>
        
        <div class="datamachine-submit-container">
            <?php submit_button(); ?>
        </div>
    </form>

    <!-- Pre-rendered Tool Config Modals (no AJAX loading) -->
    <?php
    // Get all configurable tools
    $datamachine_all_tools = apply_filters('datamachine_global_tools', []);
    $datamachine_configurable_tools = [];
    foreach ($datamachine_all_tools as $datamachine_tool_name => $datamachine_tool_config) {
        if (!isset($datamachine_tool_config['handler']) && ($datamachine_tool_config['requires_config'] ?? false)) {
            $datamachine_configurable_tools[$datamachine_tool_name] = $datamachine_tool_config;
        }
    }

    // Pre-render modal for each configurable tool
    foreach ($datamachine_configurable_tools as $datamachine_tool_id => $datamachine_tool_info):
        $datamachine_tool_config = apply_filters('datamachine_get_tool_config', [], $datamachine_tool_id);
        ?>
        <div id="datamachine-modal-tool-config-<?php echo esc_attr($datamachine_tool_id); ?>"
             class="datamachine-modal"
             aria-hidden="true">
            <div class="datamachine-modal-overlay"></div>
            <div class="datamachine-modal-container">
                <div class="datamachine-modal-header">
                    <h2 class="datamachine-modal-title">
                        <?php
                        /* translators: %s: tool name */
                        echo esc_html(sprintf(__('Configure %s', 'data-machine'), ucwords(str_replace('_', ' ', $datamachine_tool_id))));
                        ?>
                    </h2>
                    <button type="button" class="datamachine-modal-close" aria-label="<?php esc_attr_e('Close', 'data-machine'); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="datamachine-modal-body">
                    <?php
                    // Render tool config form
                    if (isset($datamachine_all_tools[$datamachine_tool_id])) {
                        $datamachine_tool_class = $datamachine_all_tools[$datamachine_tool_id]['class'];

                        if (class_exists($datamachine_tool_class) && method_exists($datamachine_tool_class, 'get_config_fields')) {
                            $datamachine_tool_instance = new $datamachine_tool_class();
                            $datamachine_config_fields = $datamachine_tool_instance->get_config_fields();

                            if (!empty($datamachine_config_fields)):
                                ?>
                                <div class="datamachine-tool-config-container">
                                    <form id="datamachine-<?php echo esc_attr($datamachine_tool_id); ?>-config-form" data-tool-id="<?php echo esc_attr($datamachine_tool_id); ?>">
                                        <table class="form-table">
                                            <tbody>
                                                <?php foreach ($datamachine_config_fields as $datamachine_field_name => $datamachine_field_config): ?>
                                                    <tr class="form-field">
                                                        <th scope="row">
                                                            <label for="<?php echo esc_attr($datamachine_tool_id . '_' . $datamachine_field_name); ?>">
                                                                <?php echo esc_html($datamachine_field_config['label']); ?>
                                                            </label>
                                                        </th>
                                                        <td>
                                                            <?php
                                                            $datamachine_field_type = $datamachine_field_config['type'] ?? 'text';
                                                            $datamachine_field_value = $datamachine_tool_config[$datamachine_field_name] ?? '';
                                                            $datamachine_field_placeholder = $datamachine_field_config['placeholder'] ?? '';
                                                            $datamachine_field_required = !empty($datamachine_field_config['required']);
                                                            ?>
                                                            <input type="<?php echo esc_attr($datamachine_field_type); ?>"
                                                                   id="<?php echo esc_attr($datamachine_tool_id . '_' . $datamachine_field_name); ?>"
                                                                   name="<?php echo esc_attr($datamachine_field_name); ?>"
                                                                   value="<?php echo esc_attr($datamachine_field_value); ?>"
                                                                   class="regular-text"
                                                                   placeholder="<?php echo esc_attr($datamachine_field_placeholder); ?>"
                                                                   <?php echo $datamachine_field_required ? 'required' : ''; ?> />
                                                            <?php if (!empty($datamachine_field_config['description'])): ?>
                                                                <p class="description">
                                                                    <?php echo esc_html($datamachine_field_config['description']); ?>
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
                                                data-tool-id="<?php echo esc_attr($datamachine_tool_id); ?>">
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