<?php
/**
 * Logs Admin Page Template
 *
 * Template for the main logs administration page.
 * Data is provided by Logs::render_content() method.
 *
 * @package DataMachine\Core\Admin\Pages\Logs
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

// Handle form submissions directly in template (admin_init timing issues)
if (!empty($_POST) && isset($_POST['dm_logs_action'])) {
    // Verify nonce for security
    $nonce = sanitize_text_field(wp_unslash($_POST['dm_logs_nonce'] ?? ''));
    if (!wp_verify_nonce($nonce, 'dm_logs_action')) {
        wp_die(esc_html__('Security check failed.', 'data-machine'));
    }

    $action = sanitize_text_field(wp_unslash($_POST['dm_logs_action']));

    switch ($action) {
        case 'clear_all':
            do_action('dm_log', 'clear_all');
            break;

        case 'update_log_level':
            $new_level = sanitize_text_field(wp_unslash($_POST['log_level'] ?? ''));
            $available_levels = apply_filters('dm_log_file', [], 'get_available_levels');
            if (array_key_exists($new_level, $available_levels)) {
                do_action('dm_log', 'set_level', $new_level);
            }
            break;
    }
}

// Use data provided by Logs class render_content method
// Variables available: $current_log_level, $log_file_info, $recent_logs, $log_file_path
?>

<div class="dm-logs-page">
    
    <!-- Log Configuration Section -->
    <div class="dm-log-configuration">
        <h2><?php esc_html_e('Log Configuration', 'data-machine'); ?></h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('dm_logs_action', 'dm_logs_nonce'); ?>
            <input type="hidden" name="dm_logs_action" value="update_log_level">
            
            <div class="dm-log-level-field">
                <label for="log_level" class="dm-log-level-label">
                    <?php esc_html_e('Log Level', 'data-machine'); ?>
                </label>
                <select id="log_level" name="log_level" class="dm-log-level-select">
                    <?php 
                    $available_levels = apply_filters('dm_log_file', [], 'get_available_levels');
                    foreach ($available_levels as $level => $description): ?>
                        <option value="<?php echo esc_attr($level); ?>" <?php selected($current_log_level, $level); ?>>
                            <?php echo esc_html($description); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="dm-log-level-description">
                    <?php esc_html_e('Controls what messages are written to the log file. Debug level creates the most verbose logs.', 'data-machine'); ?>
                </p>
            </div>
            
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Update Log Level', 'data-machine'); ?>
            </button>
        </form>
    </div>

    <!-- Log File Information Section -->
    <div class="dm-log-file-info">
        <h2><?php esc_html_e('Log File Information', 'data-machine'); ?></h2>
        
        <p>
            <strong><?php esc_html_e('File Location:', 'data-machine'); ?></strong>
            <code class="dm-log-file-path"><?php echo esc_html($log_file_path); ?></code>
        </p>
        
        <p>
            <strong><?php esc_html_e('Current Size:', 'data-machine'); ?></strong>
            <?php echo esc_html($log_file_info['size_formatted']); ?>
        </p>
        
        <div class="dm-log-actions">
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=dm-logs')); ?>" class="dm-clear-logs-form" data-confirm-message="<?php esc_attr_e('Are you sure you want to clear all logs? This action cannot be undone.', 'data-machine'); ?>">
                <?php wp_nonce_field('dm_logs_action', 'dm_logs_nonce'); ?>
                <input type="hidden" name="dm_logs_action" value="clear_all">
                <button type="submit" class="button">
                    <?php esc_html_e('Clear All Logs', 'data-machine'); ?>
                </button>
            </form>
            
            <button type="button" class="button dm-refresh-logs">
                <?php esc_html_e('Refresh Logs', 'data-machine'); ?>
            </button>
            
            <button type="button" class="button dm-copy-logs" data-copy-target=".dm-log-viewer">
                <?php esc_html_e('Copy Logs', 'data-machine'); ?>
            </button>

            <button type="button" class="button dm-load-full-logs" id="dm-load-full-logs-btn" data-nonce="<?php echo esc_attr(wp_create_nonce('dm_logs_action')); ?>">
                <?php esc_html_e('Load Full Log', 'data-machine'); ?>
            </button>
        </div>
    </div>

    <!-- Log Entries Section -->
    <div class="dm-recent-logs">
        <h2 class="dm-log-section-title"><?php esc_html_e('Recent Log Entries (Last 200)', 'data-machine'); ?></h2>

        <div class="dm-log-status-message" style="display: none;"></div>

        <?php if (empty($recent_logs)): ?>
            <p class="dm-no-logs-message">
                <?php esc_html_e('No log entries found.', 'data-machine'); ?>
            </p>
        <?php else: ?>
            <div class="dm-log-viewer" data-current-mode="recent">
<?php echo esc_html(implode("\n", $recent_logs)); ?>
            </div>
        <?php endif; ?>
    </div>
    
</div>