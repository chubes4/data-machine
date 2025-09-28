<?php
/**
 * Logs Admin Page
 *
 * Simple log viewing interface for Data Machine featuring:
 * - Log level configuration with dropdown controls
 * - Direct log file reading and display
 * - Log file information (path, size)
 * - Clear logs and refresh functionality
 *
 * Follows the plugin's filter-based architecture with self-registration
 * and provides essential debugging tools for system monitoring.
 *
 * @package DataMachine\Core\Admin\Pages\Logs
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Logs;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Logs admin page implementation.
 *
 * Provides simple log viewing interface designed to help users monitor
 * Data Machine operations with direct file reading and log level management.
 */
class Logs
{
    /**
     * Number of recent log entries to display.
     */
    const RECENT_LOGS_COUNT = 200;

    /**
     * Log file path.
     */
    private $log_file_path;

    /**
     * Constructor - Registers the admin page via filter system.
     */
    public function __construct()
    {
        // Set log file path
        $upload_dir = wp_upload_dir();
        $this->log_file_path = $upload_dir['basedir'] . '/data-machine-logs/data-machine.log';
        
        // Admin page registration now handled by LogsFilters.php
        
        // Form handling moved to template - admin_init timing issues
        
        // AJAX handlers - dm_clear_logs and dm_load_full_logs are registered in LogsFilters.php
        add_action('wp_ajax_dm_update_log_level', [$this, 'ajax_update_log_level']);
    }


    /**
     * Render the main logs page content.
     */
    public function render_content()
    {
        // Get current log level setting
        $current_log_level = $this->get_current_log_level();
        
        // Get log file info
        $log_file_info = $this->get_log_file_info();
        
        // Get recent log entries
        $recent_logs = $this->get_recent_logs();

        // Use universal template system
        echo wp_kses(apply_filters('dm_render_template', '', 'page/logs-page', [
            'current_log_level' => $current_log_level,
            'log_file_info' => $log_file_info,
            'recent_logs' => $recent_logs,
            'log_file_path' => $this->log_file_path
        ]), dm_allowed_html());
    }

    /**
     * Get log file information.
     *
     * @return array Log file information
     */
    private function get_log_file_info()
    {
        $info = [
            'exists' => false,
            'size' => 0,
            'size_formatted' => '0 bytes'
        ];

        if (file_exists($this->log_file_path)) {
            $info['exists'] = true;
            $info['size'] = filesize($this->log_file_path);
            $info['size_formatted'] = $this->format_file_size($info['size']);
        }

        return $info;
    }

    /**
     * Get recent log entries from file.
     *
     * @return array Recent log entries
     */
    private function get_recent_logs()
    {
        if (!file_exists($this->log_file_path)) {
            return [];
        }

        // Read the file and get the last 50 lines
        $lines = file($this->log_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return [];
        }

        // Get the last 50 lines
        $recent_lines = array_slice($lines, -self::RECENT_LOGS_COUNT);
        
        // Reverse to show newest first
        return array_reverse($recent_lines);
    }

    /**
     * Format file size in human readable format.
     *
     * @param int $bytes File size in bytes
     * @return string Formatted file size
     */
    private function format_file_size($bytes)
    {
        if ($bytes == 0) {
            return '0 bytes';
        }

        $units = ['bytes', 'KB', 'MB', 'GB'];
        $unit_index = 0;
        
        while ($bytes >= 1024 && $unit_index < count($units) - 1) {
            $bytes /= 1024;
            $unit_index++;
        }

        return round($bytes, 2) . ' ' . $units[$unit_index];
    }

    /**
     * Get current log level setting.
     *
     * @return string Current log level
     */
    private function get_current_log_level()
    {
        return apply_filters('dm_log_file', 'error', 'get_level');
    }

    /**
     * Handle form submissions on admin_init.
     */
    public function handle_form_submissions()
    {
        // Delegate all form processing to handle_form_actions which has proper nonce verification
        $this->handle_form_actions();
    }

    /**
     * Handle form actions.
     */
    public function handle_form_actions()
    {
        $nonce = sanitize_text_field(wp_unslash($_POST['dm_logs_nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'dm_logs_action')) {
            return;
        }

        if (!isset($_POST['dm_logs_action'])) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['dm_logs_action']));

        switch ($action) {
            case 'clear_all':
                do_action('dm_log', 'clear_all');
                $this->add_admin_notice(
                    __('Logs cleared successfully.', 'data-machine'),
                    'success'
                );
                break;

            case 'update_log_level':
                $new_level = sanitize_text_field(wp_unslash($_POST['log_level'] ?? ''));
                $available_levels = apply_filters('dm_log_file', [], 'get_available_levels');
                if (array_key_exists($new_level, $available_levels)) {
                    do_action('dm_log', 'set_level', $new_level);
                    $this->add_admin_notice(
                        /* translators: %s: Log level name (e.g., debug, info, error) */
                        sprintf(esc_html__('Log level updated to %s.', 'data-machine'), ucfirst($new_level)),
                        'success'
                    );
                }
                break;
        }
    }

    /**
     * Add admin notice.
     *
     * @param string $message Notice message
     * @param string $type Notice type (success, error, warning, info)
     */
    private function add_admin_notice($message, $type = 'info')
    {
        add_action('admin_notices', function() use ($message, $type) {
            $class = 'notice-' . $type;
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        });
    }

    /**
     * AJAX: Clear logs - delegated to central dm_delete action
     */
    public function ajax_clear_logs()
    {
        $nonce = sanitize_text_field(wp_unslash($_POST['dm_logs_nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'dm_logs_action')) {
            wp_die(esc_html__('Security check failed.', 'data-machine'), 403);
        }

        // Delegate to central deletion system
        do_action('dm_delete', 'logs', null);
    }

    /**
     * AJAX: Update log level.
     */
    public function ajax_update_log_level()
    {
        $nonce = sanitize_text_field(wp_unslash($_POST['dm_logs_nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'dm_logs_action')) {
            wp_die(esc_html__('Security check failed.', 'data-machine'), 403);
        }

        $new_level = sanitize_text_field(wp_unslash($_POST['level'] ?? ''));
        
        $available_levels = apply_filters('dm_log_file', [], 'get_available_levels');
        if (!array_key_exists($new_level, $available_levels)) {
            wp_send_json_error(__('Invalid log level.', 'data-machine'));
        }

        do_action('dm_log', 'set_level', $new_level);
        /* translators: %s: Log level name (e.g., debug, info, error) */
        wp_send_json_success(sprintf(esc_html__('Log level updated to %s.', 'data-machine'), ucfirst($new_level)));
    }

    /**
     * AJAX: Load full log file content.
     */
    public function ajax_load_full_logs()
    {
        $nonce = sanitize_text_field(wp_unslash($_POST['dm_logs_nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'dm_logs_action')) {
            wp_send_json_error(__('Security check failed.', 'data-machine'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'data-machine'));
        }

        if (!file_exists($this->log_file_path)) {
            wp_send_json_error(__('Log file not found.', 'data-machine'));
        }

        $file_content = file_get_contents($this->log_file_path);
        if ($file_content === false) {
            wp_send_json_error(__('Unable to read log file.', 'data-machine'));
        }

        // Split into lines and reverse to show newest first
        $lines = explode("\n", $file_content);
        $lines = array_filter($lines, function($line) {
            return trim($line) !== '';
        });
        $lines = array_reverse($lines);

        $total_lines = count($lines);
        $full_content = implode("\n", $lines);

        wp_send_json_success([
            'content' => $full_content,
            'total_lines' => $total_lines,
            'message' => sprintf(
                /* translators: %d: Total number of log entries loaded */
                esc_html__('Loaded %d total log entries.', 'data-machine'),
                $total_lines
            )
        ]);
    }


}

// Instance creation handled by LogsFilters.php as needed