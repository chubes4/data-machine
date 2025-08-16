<?php
/**
 * OAuth Error Modal Template
 *
 * Shows authentication error state within modal.
 * Provides options to retry or return to auth settings.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Extract context data consistently with template system
$handler_slug = $context['handler_slug'] ?? '';
$handler_label = $context['handler_label'] ?? ucfirst($handler_slug);
$error_code = $context['error_code'] ?? 'unknown_error';
$error_message = $context['error_message'] ?? '';
$step_type = $context['step_type'] ?? '';
$flow_step_id = $context['flow_step_id'] ?? '';
$pipeline_id = $context['pipeline_id'] ?? '';

// Define user-friendly error messages
$error_messages = [
    'access_denied' => __('You declined to authorize the application.', 'data-machine'),
    'missing_app_keys' => __('API keys are not configured. Please check your API configuration.', 'data-machine'),
    'request_token_failed' => __('Failed to get authorization from the provider. Please try again.', 'data-machine'),
    'access_token_failed' => __('Failed to complete authorization. Please try again.', 'data-machine'),
    'permission_denied' => __('You do not have permission to perform this action.', 'data-machine'),
    'missing_credentials' => __('API credentials are not configured properly.', 'data-machine'),
    'token_secret_expired' => __('The authorization session expired. Please try again.', 'data-machine'),
    'init_exception' => __('An error occurred while starting the authorization process.', 'data-machine'),
    'callback_exception' => __('An error occurred while completing the authorization.', 'data-machine'),
    'unknown_error' => __('An unknown error occurred during authentication.', 'data-machine')
];

$display_message = $error_message ?: ($error_messages[$error_code] ?? $error_messages['unknown_error']);

// Determine if error is retryable
$retryable_errors = ['request_token_failed', 'access_token_failed', 'token_secret_expired', 'init_exception', 'callback_exception', 'unknown_error'];
$is_retryable = in_array($error_code, $retryable_errors);

// Determine if configuration is needed
$config_errors = ['missing_app_keys', 'missing_credentials'];
$needs_config = in_array($error_code, $config_errors);

?>
<div class="dm-oauth-error-container">
    <div class="dm-oauth-error-header">
        <div class="dm-error-icon">
            <span class="dashicons dashicons-warning"></span>
        </div>
        <h3><?php echo esc_html(sprintf(__('%s Authentication Failed', 'data-machine'), $handler_label)); ?></h3>
        <p><?php esc_html_e('There was a problem connecting your account.', 'data-machine'); ?></p>
    </div>
    
    <div class="dm-oauth-error-content">
        <div class="dm-error-details">
            <h4><?php esc_html_e('Error Details', 'data-machine'); ?></h4>
            <div class="dm-error-message">
                <p><?php echo esc_html($display_message); ?></p>
                <?php if (!empty($error_code) && $error_code !== 'unknown_error'): ?>
                    <p class="dm-error-code"><small><?php echo esc_html(sprintf(__('Error Code: %s', 'data-machine'), $error_code)); ?></small></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dm-troubleshooting">
            <h4><?php esc_html_e('What can you do?', 'data-machine'); ?></h4>
            <ul>
                <?php if ($needs_config): ?>
                    <li><?php echo esc_html(sprintf(__('Check your %s API configuration below', 'data-machine'), $handler_label)); ?></li>
                    <li><?php esc_html_e('Ensure your API keys are entered correctly', 'data-machine'); ?></li>
                <?php elseif ($error_code === 'access_denied'): ?>
                    <li><?php esc_html_e('Try connecting again and authorize the application', 'data-machine'); ?></li>
                    <li><?php echo esc_html(sprintf(__('Make sure you have permission to authorize apps on your %s account', 'data-machine'), $handler_label)); ?></li>
                <?php else: ?>
                    <li><?php esc_html_e('Try connecting again in a few moments', 'data-machine'); ?></li>
                    <li><?php echo esc_html(sprintf(__('Check your %s account status', 'data-machine'), $handler_label)); ?></li>
                    <li><?php esc_html_e('Verify your internet connection is stable', 'data-machine'); ?></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    
    <div class="dm-oauth-error-actions">
        <?php
        // Determine correct handler settings template - WordPress needs fetch/publish distinction
        $template_slug = $handler_slug;
        if ($handler_slug === 'wordpress' && isset($step_type)) {
            $template_slug = ($step_type === 'fetch') ? 'wordpress_fetch' : 'wordpress_publish';
        }
        
        // Prepare context data for navigation
        $context_data = [
            'handler_slug' => $handler_slug,
            'step_type' => $step_type,
            'flow_step_id' => $flow_step_id,
            'pipeline_id' => $pipeline_id
        ];
        $context_json = htmlspecialchars(json_encode($context_data), ENT_QUOTES, 'UTF-8');
        ?>
        
        <?php if ($is_retryable): ?>
        <button type="button" class="button button-primary dm-connect-account" 
                data-handler="<?php echo esc_attr($handler_slug); ?>">
            <?php esc_html_e('Try Again', 'data-machine'); ?>
        </button>
        <?php endif; ?>
        
        <?php if ($needs_config): ?>
        <button type="button" class="button button-primary dm-modal-content" 
                data-template="handler-settings/<?php echo esc_attr($template_slug); ?>"
                data-context='<?php echo $context_json; ?>'>
            <?php esc_html_e('Check API Configuration', 'data-machine'); ?>
        </button>
        <?php endif; ?>
        
        <button type="button" class="button button-secondary dm-modal-content" 
                data-template="modal/handler-auth-form"
                data-context='<?php echo $context_json; ?>'>
            <?php esc_html_e('Back to Authentication', 'data-machine'); ?>
        </button>
        
        <button type="button" class="button button-secondary dm-modal-close">
            <?php esc_html_e('Close', 'data-machine'); ?>
        </button>
    </div>
</div>

<style>
.dm-oauth-error-container {
    text-align: center;
    padding: 2rem;
}

.dm-error-icon {
    font-size: 3rem;
    color: #dc3545;
    margin-bottom: 1rem;
}

.dm-error-icon .dashicons {
    width: 3rem;
    height: 3rem;
    font-size: 3rem;
}

.dm-oauth-error-header h3 {
    color: #dc3545;
    margin-bottom: 0.5rem;
}

.dm-oauth-error-content {
    margin: 2rem 0;
    text-align: left;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.dm-error-details {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.dm-error-details h4 {
    margin-top: 0;
    margin-bottom: 1rem;
    color: #721c24;
}

.dm-error-message {
    color: #721c24;
}

.dm-error-code {
    font-family: monospace;
    color: #6c757d;
}

.dm-troubleshooting h4 {
    margin-bottom: 1rem;
    color: #495057;
}

.dm-troubleshooting ul {
    padding-left: 1.5rem;
}

.dm-troubleshooting li {
    margin: 0.5rem 0;
}

.dm-oauth-error-actions {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #ddd;
    text-align: center;
}

.dm-oauth-error-actions .button {
    margin: 0 0.5rem;
}
</style>