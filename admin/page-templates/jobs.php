<?php
/**
 * Complete Jobs admin page template.
 *
 * Handles both Jobs list and Logs management in a single tabbed interface.
 *
 * Expects:
 * - $logger (Logger) - Logger instance for logs tab
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/page-templates
 * @since      NEXT_VERSION
 */

use DataMachine\Helpers\Logger;

if (!defined('ABSPATH')) {
    exit;
}

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'jobs';

?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url('admin.php?page=dm-jobs&tab=jobs')); ?>" 
           class="nav-tab <?php echo $current_tab === 'jobs' ? 'nav-tab-active' : ''; ?>">
            Jobs
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=dm-jobs&tab=logs')); ?>" 
           class="nav-tab <?php echo $current_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
            Logs
        </a>
    </nav>

    <!-- Tab Content -->
    <?php if ($current_tab === 'jobs'): ?>
        <?php render_jobs_tab(); ?>
    <?php elseif ($current_tab === 'logs'): ?>
        <?php render_logs_tab($logger); ?>
    <?php endif; ?>
</div>

<?php
/**
 * Render the Jobs tab content with list table.
 */
function render_jobs_tab() {
    // Use the PSR-4 namespaced class
    if (!class_exists('DataMachine\Admin\JobsListTable')) {
        echo '<div class="error"><p>' . __('Error: Jobs List Table class not found. Please ensure the plugin is properly activated.', 'data-machine') . '</p></div>';
        return;
    }

    // Create and prepare list table
    $jobs_list_table = new DataMachine\Admin\JobsListTable();
    $jobs_list_table->prepare_items();
    ?>
    <form method="post">
        <?php $jobs_list_table->display(); ?>
    </form>
    <?php
}

/**
 * Render the Logs tab content with configuration and viewer.
 *
 * @param Logger $logger Logger instance
 */
function render_logs_tab($logger) {
    // Get logger data
    $current_log_level = get_option('dm_log_level', 'info');
    $log_file_path = $logger->get_log_file_path();
    $log_file_size = $logger->get_log_file_size();
    $recent_logs = $logger->get_recent_logs(50);
    $available_levels = Logger::get_available_log_levels();
    ?>
    <div class="dm-logs-container">
        <?php render_log_configuration($current_log_level, $available_levels); ?>
        <?php render_log_file_info($log_file_path, $log_file_size); ?>
        <?php render_log_viewer($recent_logs); ?>
    </div>
    <?php render_log_viewer_javascript(); ?>
    <?php
}

/**
 * Render the log level configuration section.
 *
 * @param string $current_log_level Current log level setting
 * @param array $available_levels Available log levels
 */
function render_log_configuration($current_log_level, $available_levels) {
    ?>
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
    <?php
}

/**
 * Render the log file information and management section.
 *
 * @param string $log_file_path Path to log file
 * @param string $log_file_size Log file size
 */
function render_log_file_info($log_file_path, $log_file_size) {
    ?>
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
    <?php
}

/**
 * Render the log viewer section.
 *
 * @param array $recent_logs Array of recent log entries
 */
function render_log_viewer($recent_logs) {
    ?>
    <div class="postbox">
        <h3 class="hndle">Recent Log Entries (Last 50)</h3>
        <div class="inside">
            <div id="dm-log-viewer" style="background: #f9f9f9; border: 1px solid #ddd; padding: 10px; height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap;">
                <?php echo esc_html(implode("\n", $recent_logs)); ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render the JavaScript for log viewer functionality.
 */
function render_log_viewer_javascript() {
    ?>
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
    <?php
}