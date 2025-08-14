<?php
/**
 * Handler Authentication Form Template
 *
 * Pure rendering template for handler authentication management.
 * Uses filter-based auth discovery for dynamic authentication interface.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

$handler_label = $handler_config['label'] ?? ucfirst($handler_slug);

// Authentication discovery via pure discovery mode
$all_auth = apply_filters('dm_auth_providers', []);
$auth_instance = $all_auth[$handler_slug] ?? null;
$has_auth = ($auth_instance !== null);

if (!$has_auth) {
    ?>
    <div class="dm-handler-auth-container">
        <div class="dm-handler-auth-header">
            <h3><?php echo esc_html(sprintf(__('%s Authentication', 'data-machine'), $handler_label)); ?></h3>
        </div>
        <div class="dm-auth-not-available">
            <h4><?php esc_html_e('Authentication Not Required', 'data-machine'); ?></h4>
            <p><?php echo esc_html(sprintf(__('The %s handler does not require authentication to function.', 'data-machine'), $handler_label)); ?></p>
        </div>
        <div class="dm-auth-actions">
            <?php 
            // Determine correct handler settings template - WordPress needs fetch/publish distinction
            $template_slug = $handler_slug;
            if ($handler_slug === 'wordpress' && isset($step_type)) {
                $template_slug = ($step_type === 'fetch') ? 'wordpress_fetch' : 'wordpress_publish';
            }
            ?>
            <button type="button" class="button button-secondary dm-modal-content" 
                    data-template="handler-settings/<?php echo esc_attr($template_slug); ?>"
                    data-context='{"handler_slug":"<?php echo esc_attr($handler_slug); ?>","step_type":"<?php echo esc_attr($step_type ?? ''); ?>"}'>
                <?php esc_html_e('Back to Settings', 'data-machine'); ?>
            </button>
        </div>
    </div>
    <?php
    return;
}

// Check configuration and authentication status
$is_configured = method_exists($auth_instance, 'is_configured') ? $auth_instance->is_configured() : false;
$is_authenticated = $auth_instance->is_authenticated();
$config_fields = method_exists($auth_instance, 'get_config_fields') ? $auth_instance->get_config_fields() : [];
$account_details = null;

if ($is_authenticated && method_exists($auth_instance, 'get_account_details')) {
    $account_details = $auth_instance->get_account_details();
}

// Get current configuration for populating form
$current_config = $is_configured ? apply_filters('dm_oauth', [], 'get_config', $handler_slug) : [];

?>
<div class="dm-handler-auth-container">
    <div class="dm-handler-auth-header">
        <h3><?php echo esc_html(sprintf(__('%s Authentication', 'data-machine'), $handler_label)); ?></h3>
        <p><?php echo esc_html(sprintf(__('Manage your %s account connection below.', 'data-machine'), $handler_label)); ?></p>
    </div>
    
    <?php if (!empty($config_fields) && !$is_configured): ?>
    <!-- Configuration Form (shown when not configured) -->
    <div class="dm-auth-config-section">
        <h4><?php esc_html_e('API Configuration', 'data-machine'); ?></h4>
        <p><?php echo esc_html(sprintf(__('Enter your %s API credentials to enable authentication.', 'data-machine'), $handler_label)); ?></p>
        
        <form class="dm-auth-config-form" data-handler="<?php echo esc_attr($handler_slug); ?>">
            <?php wp_nonce_field('dm_ajax_actions', 'nonce'); ?>
            <input type="hidden" name="handler_slug" value="<?php echo esc_attr($handler_slug); ?>" />
            
            <?php foreach ($config_fields as $field_name => $field_config): ?>
                <div class="dm-field-group">
                    <label for="<?php echo esc_attr($field_name); ?>">
                        <?php echo esc_html($field_config['label']); ?>
                        <?php if ($field_config['required'] ?? false): ?>
                            <span class="required">*</span>
                        <?php endif; ?>
                    </label>
                    
                    <?php
                    $field_type = $field_config['type'] ?? 'text';
                    $current_value = $current_config[$field_name] ?? '';
                    ?>
                    
                    <input type="<?php echo esc_attr($field_type); ?>" 
                           id="<?php echo esc_attr($field_name); ?>"
                           name="<?php echo esc_attr($field_name); ?>"
                           value="<?php echo esc_attr($current_value); ?>"
                           <?php echo ($field_config['required'] ?? false) ? 'required' : ''; ?>
                           class="regular-text" />
                    
                    <?php if (!empty($field_config['description'])): ?>
                        <p class="description"><?php echo esc_html($field_config['description']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <div class="dm-config-actions">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Save Configuration', 'data-machine'); ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Authentication Status (always shown) -->
    <div class="dm-auth-status <?php echo $is_authenticated ? 'dm-auth-status--connected' : 'dm-auth-status--disconnected'; ?>">
        <div class="dm-auth-status-info">
            <?php if ($is_authenticated): ?>
                <div class="dm-auth-connected">
                    <span class="dm-auth-indicator dm-auth-indicator--connected">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <strong><?php esc_html_e('Connected', 'data-machine'); ?></strong>
                    </span>
                    <?php if ($account_details): ?>
                        <div class="dm-auth-account-details">
                            <?php if (!empty($account_details['username'])): ?>
                                <p><strong><?php esc_html_e('Account:', 'data-machine'); ?></strong> <?php echo esc_html($account_details['username']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($account_details['name'])): ?>
                                <p><strong><?php esc_html_e('Name:', 'data-machine'); ?></strong> <?php echo esc_html($account_details['name']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($account_details['email'])): ?>
                                <p><strong><?php esc_html_e('Email:', 'data-machine'); ?></strong> <?php echo esc_html($account_details['email']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="dm-auth-disconnected">
                    <span class="dm-auth-indicator dm-auth-indicator--disconnected">
                        <span class="dashicons dashicons-warning"></span>
                        <strong><?php esc_html_e('Not Connected', 'data-machine'); ?></strong>
                    </span>
                    <?php if ($is_configured): ?>
                        <p><?php echo esc_html(sprintf(__('Connect your %s account to enable this handler.', 'data-machine'), $handler_label)); ?></p>
                    <?php else: ?>
                        <p><?php echo esc_html(sprintf(__('Configure your %s API credentials above, then connect your account.', 'data-machine'), $handler_label)); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Authentication Actions -->
        <div class="dm-auth-actions">
            <?php if ($is_authenticated): ?>
                <button type="button" class="button button-secondary dm-test-connection" 
                        data-handler="<?php echo esc_attr($handler_slug); ?>">
                    <?php esc_html_e('Test Connection', 'data-machine'); ?>
                </button>
                <button type="button" class="button button-secondary dm-disconnect-account" 
                        data-handler="<?php echo esc_attr($handler_slug); ?>">
                    <?php esc_html_e('Disconnect', 'data-machine'); ?>
                </button>
            <?php else: ?>
                <button type="button" class="button button-primary dm-connect-account" 
                        data-handler="<?php echo esc_attr($handler_slug); ?>"
                        <?php if (!$is_configured): ?>disabled title="<?php esc_attr_e('Configure API credentials first', 'data-machine'); ?>"<?php endif; ?>>
                    <?php echo esc_html(sprintf(__('Connect %s', 'data-machine'), $handler_label)); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    // Get handler-specific auth help text via method discovery only  
    $help_text = '';
    if (method_exists($auth_instance, 'get_auth_help_text')) {
        $help_text = $auth_instance->get_auth_help_text();
    }

    // Only display help section if auth provider provides help text
    if (!empty($help_text)): ?>
        <!-- Authentication Help -->
        <div class="dm-auth-help">
            <h4><?php esc_html_e('Authentication Information', 'data-machine'); ?></h4>
            <p><?php echo esc_html($help_text); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Modal Navigation -->
    <div class="dm-modal-navigation">
        <?php 
        // Determine correct handler settings template - WordPress needs fetch/publish distinction
        $template_slug = $handler_slug;
        if ($handler_slug === 'wordpress' && isset($step_type)) {
            $template_slug = ($step_type === 'fetch') ? 'wordpress_fetch' : 'wordpress_publish';
        }
        ?>
        <button type="button" class="button button-secondary dm-modal-content" 
                data-template="handler-settings/<?php echo esc_attr($template_slug); ?>"
                data-context='{"handler_slug":"<?php echo esc_attr($handler_slug); ?>","step_type":"<?php echo esc_attr($step_type ?? ''); ?>"}'>
            <?php esc_html_e('Back to Settings', 'data-machine'); ?>
        </button>
    </div>
</div>