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

$all_auth = apply_filters('datamachine_auth_providers', []);
$auth_instance = $all_auth[$handler_slug] ?? null;
$has_auth = ($auth_instance !== null);

if (!$has_auth) {
    ?>
    <div class="datamachine-handler-auth-container">
        <div class="datamachine-handler-auth-header">
            <?php /* translators: %s: Handler name/label */ ?>
            <h3><?php echo esc_html(sprintf(__('%s Authentication', 'datamachine'), $handler_label)); ?></h3>
        </div>
        <div class="datamachine-auth-not-available">
            <h4><?php esc_html_e('Authentication Not Required', 'datamachine'); ?></h4>
            <?php /* translators: %s: Handler name/label */ ?>
            <p><?php echo esc_html(sprintf(__('The %s handler does not require authentication to function.', 'datamachine'), $handler_label)); ?></p>
        </div>
        <div class="datamachine-auth-actions">
            <?php 
            $template_slug = $handler_slug;
            ?>
            <button type="button" class="button button-secondary datamachine-modal-open" 
                    data-template="handler-settings/<?php echo esc_attr($template_slug); ?>"
                    data-context='<?php echo esc_attr(wp_json_encode(['flow_step_id' => $flow_step_id, 'step_type' => $step_type, 'handler_slug' => $handler_slug, 'pipeline_id' => $pipeline_id, 'flow_id' => $flow_id])); ?>'>
                <?php esc_html_e('Back to Settings', 'datamachine'); ?>
            </button>
        </div>
    </div>
    <?php
    return;
}

// Detect if this provider uses OAuth flow vs simple credential storage
$uses_oauth = method_exists($auth_instance, 'get_authorization_url') || method_exists($auth_instance, 'handle_oauth_callback');

// Get current configuration and authentication status
$current_config = $uses_oauth
    ? apply_filters('datamachine_retrieve_oauth_keys', [], $handler_slug)
    : apply_filters('datamachine_retrieve_oauth_account', [], $handler_slug);
$is_authenticated = $auth_instance->is_authenticated();
$config_fields = method_exists($auth_instance, 'get_config_fields') ? $auth_instance->get_config_fields() : [];
$account_details = null;

if ($is_authenticated && method_exists($auth_instance, 'get_account_details')) {
    $account_details = $auth_instance->get_account_details();
}

?>
<div class="datamachine-handler-auth-container">
    <div class="datamachine-handler-auth-header">
        <?php /* translators: %s: Handler name/label */ ?>
        <h3><?php echo esc_html(sprintf(__('%s Authentication', 'datamachine'), $handler_label)); ?></h3>
        <p><?php echo esc_html(sprintf(__('Configure API credentials and manage account connection.', 'datamachine'), $handler_label)); ?></p>
    </div>
    
    <?php if (!empty($config_fields)): ?>
    <!-- Configuration Form (always visible) -->
    <div class="datamachine-auth-config-section">
        <h4><?php esc_html_e('API Configuration', 'datamachine'); ?></h4>
        
        <form class="datamachine-auth-config-form" data-handler="<?php echo esc_attr($handler_slug); ?>">
            <input type="hidden" name="handler_slug" value="<?php echo esc_attr($handler_slug); ?>" />
            
            <?php foreach ($config_fields as $field_name => $field_config): ?>
                <div class="datamachine-field-group">
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
            
            <div class="datamachine-config-actions">
                <button type="submit" class="button button-secondary">
                    <?php if ($uses_oauth): ?>
                        <?php esc_html_e('Save Configuration', 'datamachine'); ?>
                    <?php else: ?>
                        <?php esc_html_e('Save Credentials', 'datamachine'); ?>
                    <?php endif; ?>
                </button>
            </div>
        </form>
        
        <?php if ($uses_oauth): ?>
        <!-- Redirect URI Display for OAuth providers -->
        <div class="datamachine-redirect-uri-section">
            <?php /* translators: %s: Handler name (capitalized) */ ?>
            <h5><?php echo esc_html(sprintf(__('Redirect URI for %s App', 'datamachine'), ucfirst($handler_slug))); ?></h5>
            <p><?php esc_html_e('Copy this URL and paste it in your app settings under "redirect uri" or "callback URL":', 'datamachine'); ?></p>
            <code class="datamachine-redirect-uri-code">
                <?php echo esc_html(apply_filters('datamachine_oauth_callback', '', $handler_slug)); ?>
            </code>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Connection Status & Actions -->
    <div class="datamachine-auth-connection-section">
        <h4><?php esc_html_e('Account Connection', 'datamachine'); ?></h4>
        
        <div class="datamachine-auth-status <?php echo $is_authenticated ? 'datamachine-auth-status--connected' : 'datamachine-auth-status--disconnected'; ?>">
            <?php if ($is_authenticated): ?>
                <div class="datamachine-auth-connected">
                    <span class="datamachine-auth-indicator datamachine-auth-indicator--connected">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <strong><?php esc_html_e('Connected', 'datamachine'); ?></strong>
                    </span>
                    <?php if ($account_details): ?>
                        <div class="datamachine-auth-account-details">
                            <?php if (!empty($account_details['username'])): ?>
                                <p><strong><?php esc_html_e('Account:', 'datamachine'); ?></strong> <?php echo esc_html($account_details['username']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($account_details['name'])): ?>
                                <p><strong><?php esc_html_e('Name:', 'datamachine'); ?></strong> <?php echo esc_html($account_details['name']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($account_details['email'])): ?>
                                <p><strong><?php esc_html_e('Email:', 'datamachine'); ?></strong> <?php echo esc_html($account_details['email']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="datamachine-auth-disconnected">
                    <span class="datamachine-auth-indicator datamachine-auth-indicator--disconnected">
                        <span class="dashicons dashicons-warning"></span>
                        <strong><?php esc_html_e('Not Connected', 'datamachine'); ?></strong>
                    </span>
                    <?php if ($uses_oauth): ?>
                        <?php /* translators: %s: Handler name/label */ ?>
                        <p><?php echo esc_html(sprintf(__('Connect your %s account to enable this handler.', 'datamachine'), $handler_label)); ?></p>
                    <?php else: ?>
                        <?php /* translators: %s: Handler name/label */ ?>
                        <p><?php echo esc_html(sprintf(__('Configure your %s credentials to enable this handler.', 'datamachine'), $handler_label)); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Connection Actions -->
            <div class="datamachine-auth-actions">
                <?php if ($is_authenticated): ?>
                    <button type="button" class="button button-secondary datamachine-disconnect-account" 
                            data-handler="<?php echo esc_attr($handler_slug); ?>">
                        <?php esc_html_e('Disconnect', 'datamachine'); ?>
                    </button>
                <?php else: ?>
                    <?php if ($uses_oauth): ?>
                        <?php
                        // Get direct provider authorization URL - bare metal connection
                        $oauth_url = apply_filters('datamachine_oauth_url', '', $handler_slug);

                        // Handle errors from authorization URL generation
                        if (is_wp_error($oauth_url)) {
                            $oauth_url = '#';
                        }
                        ?>
                        <button type="button" class="button button-primary datamachine-connect-oauth"
                                data-handler="<?php echo esc_attr($handler_slug); ?>"
                                data-oauth-url="<?php echo esc_attr($oauth_url); ?>">
                            <?php /* translators: %s: Handler name/label */ ?>
                            <?php echo esc_html(sprintf(__('Connect %s', 'datamachine'), $handler_label)); ?>
                        </button>
                    <?php else: ?>
                        <!-- Simple credential providers just need config saved -->
                        <p class="datamachine-simple-auth-message">
                            <?php /* translators: %s: Handler name/label */ ?>
                            <?php echo esc_html(sprintf(__('Save your %s credentials above to enable this handler.', 'datamachine'), $handler_label)); ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="datamachine-modal-navigation">
        <?php 
        // EXACT COPY FROM EDIT HANDLER BUTTON
        $template_slug = $handler_slug;
        ?>
        <button type="button" class="button button-secondary datamachine-modal-content" 
                data-template="handler-settings/<?php echo esc_attr($template_slug); ?>"
                data-context='<?php echo esc_attr(wp_json_encode(['flow_step_id' => $flow_step_id, 'step_type' => $step_type, 'handler_slug' => $handler_slug, 'pipeline_id' => $pipeline_id, 'flow_id' => $flow_id])); ?>'>
            <?php esc_html_e('Back to Settings', 'datamachine'); ?>
        </button>
    </div>
</div>