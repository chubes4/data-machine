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

    public function __construct()
    {
        // Set log file path
        $upload_dir = wp_upload_dir();
        $this->log_file_path = $upload_dir['basedir'] . DATAMACHINE_LOG_FILE;

        // Admin page registration now handled by LogsFilters.php

        // Form handling moved to template - admin_init timing issues

        // AJAX replaced by REST API - see /inc/Api/Logs.php
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
        echo wp_kses(apply_filters('datamachine_render_template', '', 'page/logs-page', [
            'current_log_level' => $current_log_level,
            'log_file_info' => $log_file_info,
            'recent_logs' => $recent_logs,
            'log_file_path' => $this->log_file_path
        ]), datamachine_allowed_html());
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
        return apply_filters('datamachine_log_file', 'error', 'get_level');
    }


}

// Instance creation handled by LogsFilters.php as needed