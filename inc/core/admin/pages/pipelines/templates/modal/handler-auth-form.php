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
$all_auth = apply_filters('dm_get_auth_providers', []);
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

// Get authentication status - admin-global authentication
$is_authenticated = $auth_instance->is_authenticated();
$account_details = null;

if ($is_authenticated && method_exists($auth_instance, 'get_account_details')) {
    $account_details = $auth_instance->get_account_details();
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