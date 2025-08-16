<?php
/**
 * OAuth Success Modal Template
 *
 * Shows successful authentication completion within modal.
 * Provides option to return to auth settings or continue.
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
$account_details = $context['account_details'] ?? [];
$step_type = $context['step_type'] ?? '';
$flow_step_id = $context['flow_step_id'] ?? '';
$pipeline_id = $context['pipeline_id'] ?? '';

?>
<div class="dm-oauth-success-container">
    <div class="dm-oauth-success-header">
        <div class="dm-success-icon">
            <span class="dashicons dashicons-yes-alt"></span>
        </div>
        <h3><?php echo esc_html(sprintf(__('%s Connected Successfully!', 'data-machine'), $handler_label)); ?></h3>
        <p><?php echo esc_html(sprintf(__('Your %s account has been connected and is ready to use.', 'data-machine'), $handler_label)); ?></p>
    </div>
    
    <div class="dm-oauth-success-content">
        <?php if (!empty($account_details)): ?>
        <div class="dm-account-info">
            <h4><?php esc_html_e('Connected Account', 'data-machine'); ?></h4>
            <div class="dm-account-details">
                <?php if (!empty($account_details['username'])): ?>
                    <p><strong><?php esc_html_e('Username:', 'data-machine'); ?></strong> <?php echo esc_html($account_details['username']); ?></p>
                <?php endif; ?>
                <?php if (!empty($account_details['name'])): ?>
                    <p><strong><?php esc_html_e('Name:', 'data-machine'); ?></strong> <?php echo esc_html($account_details['name']); ?></p>
                <?php endif; ?>
                <?php if (!empty($account_details['email'])): ?>
                    <p><strong><?php esc_html_e('Email:', 'data-machine'); ?></strong> <?php echo esc_html($account_details['email']); ?></p>
                <?php endif; ?>
                <?php if (!empty($account_details['screen_name'])): ?>
                    <p><strong><?php esc_html_e('Handle:', 'data-machine'); ?></strong> @<?php echo esc_html($account_details['screen_name']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="dm-next-steps">
            <h4><?php esc_html_e('What\'s Next?', 'data-machine'); ?></h4>
            <p><?php echo esc_html(sprintf(__('You can now use %s in your pipelines. Configure handler settings or return to your pipeline.', 'data-machine'), $handler_label)); ?></p>
        </div>
    </div>
    
    <div class="dm-oauth-success-actions">
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
        
        <button type="button" class="button button-secondary dm-modal-content" 
                data-template="handler-settings/<?php echo esc_attr($template_slug); ?>"
                data-context='<?php echo $context_json; ?>'>
            <?php esc_html_e('Handler Settings', 'data-machine'); ?>
        </button>
        
        <button type="button" class="button button-primary dm-modal-content" 
                data-template="modal/handler-auth-form"
                data-context='<?php echo $context_json; ?>'>
            <?php esc_html_e('View Authentication', 'data-machine'); ?>
        </button>
        
        <button type="button" class="button button-secondary dm-modal-close">
            <?php esc_html_e('Close', 'data-machine'); ?>
        </button>
    </div>
</div>

<style>
.dm-oauth-success-container {
    text-align: center;
    padding: 2rem;
}

.dm-success-icon {
    font-size: 3rem;
    color: #28a745;
    margin-bottom: 1rem;
}

.dm-success-icon .dashicons {
    width: 3rem;
    height: 3rem;
    font-size: 3rem;
}

.dm-oauth-success-header h3 {
    color: #28a745;
    margin-bottom: 0.5rem;
}

.dm-oauth-success-content {
    margin: 2rem 0;
    text-align: left;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.dm-account-info {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.dm-account-info h4 {
    margin-top: 0;
    margin-bottom: 1rem;
    color: #495057;
}

.dm-account-details p {
    margin: 0.5rem 0;
}

.dm-next-steps h4 {
    margin-bottom: 1rem;
    color: #495057;
}

.dm-oauth-success-actions {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #ddd;
    text-align: center;
}

.dm-oauth-success-actions .button {
    margin: 0 0.5rem;
}
</style>