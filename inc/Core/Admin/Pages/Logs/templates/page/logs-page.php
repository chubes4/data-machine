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

// REST API only - no form submissions

// Use data provided by Logs class render_content method
// Variables available: $datamachine_current_log_level, $datamachine_log_file_info, $datamachine_recent_logs, $datamachine_log_file_path
?>

<div class="datamachine-logs-page">
    
    <!-- Log Configuration Section -->
    <div class="datamachine-log-configuration">
        <h2><?php esc_html_e('Log Configuration', 'data-machine'); ?></h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('datamachine_logs_action', 'datamachine_logs_nonce'); ?>
            <input type="hidden" name="datamachine_logs_action" value="update_log_level">
            
            <div class="datamachine-log-level-field">
                <label for="log_level" class="datamachine-log-level-label">
                    <?php esc_html_e('Log Level', 'data-machine'); ?>
                </label>
                <select id="log_level" name="log_level" class="datamachine-log-level-select">
                    <?php 
                    $datamachine_available_levels = datamachine_get_available_log_levels();
                    foreach ($datamachine_available_levels as $datamachine_level => $datamachine_description): ?>
                        <option value="<?php echo esc_attr($datamachine_level); ?>" <?php selected($datamachine_current_log_level, $datamachine_level); ?>>
                            <?php echo esc_html($datamachine_description); ?>
                        </option>
                    <?php endforeach; ?>

                </select>
                <p class="datamachine-log-level-description">
                    <?php esc_html_e('Controls what messages are written to the log file. Debug level creates the most verbose logs.', 'data-machine'); ?>
                </p>
            </div>
            
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Update Log Level', 'data-machine'); ?>
            </button>
        </form>
    </div>

    <!-- Log File Information Section -->
    <div class="datamachine-log-file-info">
        <h2><?php esc_html_e('Log File Information', 'data-machine'); ?></h2>
        
        <p>
            <strong><?php esc_html_e('File Location:', 'data-machine'); ?></strong>
            <code class="datamachine-log-file-path"><?php echo esc_html($datamachine_log_file_path); ?></code>
        </p>
        
        <p>
            <strong><?php esc_html_e('Current Size:', 'data-machine'); ?></strong>
            <?php echo esc_html($datamachine_log_file_info['size_formatted']); ?>
        </p>
        
        <div class="datamachine-log-actions">
            <button type="button" class="button datamachine-clear-logs-btn" data-confirm-message="<?php esc_attr_e('Are you sure you want to clear all logs? This action cannot be undone.', 'data-machine'); ?>">
                <?php esc_html_e('Clear All Logs', 'data-machine'); ?>
            </button>
            
            <button type="button" class="button datamachine-refresh-logs">
                <?php esc_html_e('Refresh Logs', 'data-machine'); ?>
            </button>
            
            <button type="button" class="button datamachine-copy-logs" data-copy-target=".datamachine-log-viewer">
                <?php esc_html_e('Copy Logs', 'data-machine'); ?>
            </button>

            <button type="button" class="button datamachine-load-full-logs" id="datamachine-load-full-logs-btn" data-nonce="<?php echo esc_attr(wp_create_nonce('datamachine_logs_action')); ?>">
                <?php esc_html_e('Load Full Log', 'data-machine'); ?>
            </button>
        </div>
    </div>

    <!-- Log Entries Section -->
    <div class="datamachine-recent-logs">
        <h2 class="datamachine-log-section-title"><?php esc_html_e('Recent Log Entries (Last 200)', 'data-machine'); ?></h2>

        <div class="datamachine-log-status-message"></div>

        <?php if (empty($datamachine_recent_logs)): ?>
            <p class="datamachine-no-logs-message">
                <?php esc_html_e('No log entries found.', 'data-machine'); ?>
            </p>
        <?php else: ?>
            <div class="datamachine-log-viewer" data-current-mode="recent">
<?php echo esc_html(implode("\n", $datamachine_recent_logs)); ?>
            </div>
        <?php endif; ?>
    </div>
    
</div>
