<?php
/**
 * Handler Authentication Form Template
 *
 * Simple, always-accessible authentication form.
 * No overengineered states - just config form + connection status.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

$handler_slug = $context['handler_slug'] ?? ($handler_slug ?? null);
$step_type = $context['step_type'] ?? ($step_type ?? null);
$flow_step_id = $context['flow_step_id'] ?? ($flow_step_id ?? null);
$pipeline_id = $context['pipeline_id'] ?? ($pipeline_id ?? null);

$handler_label = $handler_config['label'] ?? ucfirst($handler_slug);

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
            $template_slug = $handler_slug;
            ?>
            <button type="button" class="button button-secondary dm-modal-open" 
                    data-template="handler-settings/<?php echo esc_attr($template_slug); ?>"
                    data-context='{"flow_step_id":"<?php echo esc_attr($flow_step_id); ?>","step_type":"<?php echo esc_attr($step_type); ?>","handler_slug":"<?php echo esc_attr($handler_slug); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>","flow_id":"<?php echo esc_attr($flow_id); ?>"}'>
                <?php esc_html_e('Back to Settings', 'data-machine'); ?>
            </button>
        </div>
    </div>
    <?php
    return;
}

// Get current configuration and authentication status
$current_config = apply_filters('dm_retrieve_oauth_keys', [], $handler_slug);
$is_authenticated = $auth_instance->is_authenticated();
$config_fields = method_exists($auth_instance, 'get_config_fields') ? $auth_instance->get_config_fields() : [];
$account_details = null;

if ($is_authenticated && method_exists($auth_instance, 'get_account_details')) {
    $account_details = $auth_instance->get_account_details();
}

// Detect if this provider uses OAuth flow vs simple credential storage
$uses_oauth = method_exists($auth_instance, 'get_authorization_url') || method_exists($auth_instance, 'handle_oauth_callback');

?>
<div class="dm-handler-auth-container">
    <div class="dm-handler-auth-header">
        <h3><?php echo esc_html(sprintf(__('%s Authentication', 'data-machine'), $handler_label)); ?></h3>
        <p><?php echo esc_html(sprintf(__('Configure API credentials and manage account connection.', 'data-machine'), $handler_label)); ?></p>
    </div>
    
    <?php if (!empty($config_fields)): ?>
    <!-- Configuration Form (always visible) -->
    <div class="dm-auth-config-section">
        <h4><?php esc_html_e('API Configuration', 'data-machine'); ?></h4>
        
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
                <button type="submit" class="button button-secondary">
                    <?php if ($uses_oauth): ?>
                        <?php esc_html_e('Save Configuration', 'data-machine'); ?>
                    <?php else: ?>
                        <?php esc_html_e('Save Credentials', 'data-machine'); ?>
                    <?php endif; ?>
                </button>
            </div>
        </form>
        
        <?php if ($uses_oauth): ?>
        <!-- Redirect URI Display for OAuth providers -->
        <div class="dm-redirect-uri-section">
            <h5><?php echo esc_html(sprintf(__('Redirect URI for %s App', 'data-machine'), ucfirst($handler_slug))); ?></h5>
            <p><?php esc_html_e('Copy this URL and paste it in your app settings under "redirect uri" or "callback URL":', 'data-machine'); ?></p>
            <code class="dm-redirect-uri-code">
                <?php echo esc_html(apply_filters('dm_get_oauth_url', '', $handler_slug)); ?>
            </code>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($uses_oauth): ?>
    <!-- Connection Status & Actions -->
    <div class="dm-auth-connection-section">
        <h4><?php esc_html_e('Account Connection', 'data-machine'); ?></h4>
        
        <div class="dm-auth-status <?php echo $is_authenticated ? 'dm-auth-status--connected' : 'dm-auth-status--disconnected'; ?>">
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
                    <?php if ($uses_oauth): ?>
                        <p><?php echo esc_html(sprintf(__('Connect your %s account to enable this handler.', 'data-machine'), $handler_label)); ?></p>
                    <?php else: ?>
                        <p><?php echo esc_html(sprintf(__('Configure your %s credentials to enable this handler.', 'data-machine'), $handler_label)); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Connection Actions -->
            <div class="dm-auth-actions">
                <?php if ($is_authenticated): ?>
                    <button type="button" class="button button-secondary dm-disconnect-account" 
                            data-handler="<?php echo esc_attr($handler_slug); ?>">
                        <?php esc_html_e('Disconnect', 'data-machine'); ?>
                    </button>
                <?php else: ?>
                    <?php if ($uses_oauth): ?>
                        <?php
                        // Get direct provider authorization URL - bare metal connection
                        $oauth_url = apply_filters('dm_get_oauth_auth_url', '', $handler_slug);
                        
                        // Handle errors from authorization URL generation
                        if (is_wp_error($oauth_url)) {
                            $oauth_url = '#';
                            $has_config = false; // Disable button if URL generation failed
                        } else {
                            $has_config = !empty($current_config);
                        }
                        ?>
                        <button type="button" class="button button-primary dm-connect-oauth" 
                                data-handler="<?php echo esc_attr($handler_slug); ?>"
                                data-oauth-url="<?php echo esc_attr($oauth_url); ?>"
                                <?php if (!$has_config): ?>disabled title="<?php esc_attr_e('Save configuration first', 'data-machine'); ?>"<?php endif; ?>>
                            <?php echo esc_html(sprintf(__('Connect %s', 'data-machine'), $handler_label)); ?>
                        </button>
                    <?php else: ?>
                        <!-- Simple credential providers just need config saved -->
                        <p class="dm-simple-auth-message">
                            <?php echo esc_html(sprintf(__('Save your %s credentials above to enable this handler.', 'data-machine'), $handler_label)); ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="dm-modal-navigation">
        <?php 
        // EXACT COPY FROM EDIT HANDLER BUTTON
        $template_slug = $handler_slug;
        ?>
        <button type="button" class="button button-secondary dm-modal-content" 
                data-template="handler-settings/<?php echo esc_attr($template_slug); ?>"
                data-context='{"flow_step_id":"<?php echo esc_attr($flow_step_id); ?>","step_type":"<?php echo esc_attr($step_type); ?>","handler_slug":"<?php echo esc_attr($handler_slug); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>","flow_id":"<?php echo esc_attr($flow_id); ?>"}'>
            <?php esc_html_e('Back to Settings', 'data-machine'); ?>
        </button>
    </div>
</div>