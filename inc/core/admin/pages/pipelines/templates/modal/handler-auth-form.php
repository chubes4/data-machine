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
$user_id = get_current_user_id();

// Authentication discovery via filter
$auth_instance = apply_filters('dm_get_auth', null, $handler_slug);
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
            <button type="button" class="button button-secondary dm-modal-trigger" 
                    data-template="handler-settings"
                    data-context='{"handler_slug":"<?php echo esc_attr($handler_slug); ?>","step_type":"<?php echo esc_attr($step_type ?? ''); ?>"}'>
                <?php esc_html_e('Back to Settings', 'data-machine'); ?>
            </button>
        </div>
    </div>
    <?php
    return;
}

// Get authentication status
$is_authenticated = $auth_instance->is_authenticated($user_id);
$account_details = null;

if ($is_authenticated && method_exists($auth_instance, 'get_account_details')) {
    $account_details = $auth_instance->get_account_details($user_id);
}

?>
<div class="dm-handler-auth-container">
    <div class="dm-handler-auth-header">
        <h3><?php echo esc_html(sprintf(__('%s Authentication', 'data-machine'), $handler_label)); ?></h3>
        <p><?php echo esc_html(sprintf(__('Manage your %s account connection below.', 'data-machine'), $handler_label)); ?></p>
    </div>
    
    <!-- Authentication Status -->
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
                    <p><?php echo esc_html(sprintf(__('Connect your %s account to enable this handler.', 'data-machine'), $handler_label)); ?></p>
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
                        data-handler="<?php echo esc_attr($handler_slug); ?>">
                    <?php echo esc_html(sprintf(__('Connect %s', 'data-machine'), $handler_label)); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Authentication Help -->
    <div class="dm-auth-help">
        <h4><?php esc_html_e('Authentication Information', 'data-machine'); ?></h4>
        <?php
        // Get handler-specific auth help text
        $help_text = '';
        if (method_exists($auth_instance, 'get_auth_help_text')) {
            $help_text = $auth_instance->get_auth_help_text();
        } else {
            // Fallback help text based on handler type
            switch ($handler_slug) {
                case 'twitter':
                    $help_text = __('Twitter uses OAuth 1.0a authentication. You will be redirected to Twitter to authorize the application, then redirected back to complete the connection.', 'data-machine');
                    break;
                case 'reddit':
                case 'facebook':
                case 'threads':
                case 'googlesheets':
                    $help_text = sprintf(__('%s uses OAuth 2.0 authentication. You will be redirected to authorize the application and grant necessary permissions.', 'data-machine'), $handler_label);
                    break;
                case 'bluesky':
                    $help_text = __('Bluesky uses App Password authentication. You will need to create an App Password in your Bluesky account settings and enter it here.', 'data-machine');
                    break;
                case 'wordpress':
                    $help_text = __('WordPress authentication may use API keys or basic authentication depending on your site configuration.', 'data-machine');
                    break;
                default:
                    $help_text = sprintf(__('This handler requires authentication to connect to your %s account.', 'data-machine'), $handler_label);
                    break;
            }
        }
        ?>
        <p><?php echo esc_html($help_text); ?></p>
    </div>
    
    <!-- Modal Navigation -->
    <div class="dm-modal-navigation">
        <button type="button" class="button button-secondary dm-modal-trigger" 
                data-template="handler-settings"
                data-context='{"handler_slug":"<?php echo esc_attr($handler_slug); ?>","step_type":"<?php echo esc_attr($step_type ?? ''); ?>"}'>
            <?php esc_html_e('Back to Settings', 'data-machine'); ?>
        </button>
    </div>
</div>