<?php
/**
 * Template for the Logs tab content.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get logger instance and current settings
$current_log_level = get_option('dm_log_level', 'info');
$log_file_path = $logger->get_log_file_path();
$log_file_size = $logger->get_log_file_size();
$recent_logs = $logger->get_recent_logs(50);
$available_levels = Data_Machine_Logger::get_available_log_levels();

?>
<div class="dm-logs-container">
    <!-- Log Level Configuration -->
    <div class="postbox" style="margin-top: 20px;">
        <h3 class="hndle">Log Configuration</h3>
        <div class="inside">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('dm_update_log_level', 'dm_log_level_nonce'); ?>
                <input type="hidden" name="action" value="dm_update_log_level">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Log Level</th>
                        <td>
                            <select name="dm_log_level" id="dm_log_level">
                                <?php foreach ($available_levels as $level => $description): ?>
                                    <option value="<?php echo esc_attr($level); ?>" <?php selected($current_log_level, $level); ?>>
                                        <?php echo esc_html($description); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                Controls what messages are written to the log file. Debug level creates the most verbose logs.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Update Log Level'); ?>
            </form>
        </div>
    </div>

    <!-- Log File Information -->
    <div class="postbox">
        <h3 class="hndle">Log File Information</h3>
        <div class="inside">
            <p><strong>File Location:</strong> <code><?php echo esc_html($log_file_path); ?></code></p>
            <p><strong>Current Size:</strong> <?php echo esc_html($log_file_size); ?> MB</p>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                <?php wp_nonce_field('dm_clear_logs', 'dm_clear_logs_nonce'); ?>
                <input type="hidden" name="action" value="dm_clear_logs">
                <input type="submit" class="button button-secondary" value="Clear All Logs" 
                       onclick="return confirm('Are you sure you want to clear all log files? This action cannot be undone.');">
            </form>
            
            <button type="button" class="button button-secondary" id="dm-refresh-logs">Refresh Logs</button>
        </div>
    </div>

    <!-- Recent Log Entries -->
    <div class="postbox">
        <h3 class="hndle">Recent Log Entries (Last 50)</h3>
        <div class="inside">
            <div id="dm-log-viewer" style="background: #f9f9f9; border: 1px solid #ddd; padding: 10px; height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap;">
                <?php echo esc_html(implode("\n", $recent_logs)); ?>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#dm-refresh-logs').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        $button.text('Refreshing...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'dm_refresh_logs',
                nonce: '<?php echo esc_js(wp_create_nonce('dm_refresh_logs')); ?>'
            },
            success: function(response) {
                if (response.success && response.data.logs) {
                    $('#dm-log-viewer').text(response.data.logs.join('\n'));
                } else {
                    alert('Failed to refresh logs: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('AJAX error: Failed to refresh logs.');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
});
</script>