<?php
/**
 * LogsManager Service
 *
 * Centralized service for log file operations including reading, clearing,
 * and configuration. Provides direct method access for REST API and internal use.
 *
 * Note: Actual logging (write operations) uses the datamachine_log action hook
 * which remains in DataMachineActions.php for cross-cutting concern flexibility.
 *
 * @package DataMachine\Services
 * @since 0.1.0
 */

namespace DataMachine\Services;

if (!defined('WPINC')) {
    die;
}

class LogsManager {

    /**
     * Clear the log file contents.
     *
     * @return bool True on success
     */
    public static function clear(): bool {
        $log_file = self::getFilePath();

        $log_dir = dirname($log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $result = file_put_contents($log_file, '');
        return $result !== false;
    }

    /**
     * Set the log level.
     *
     * @param string $level Log level (debug, error, none)
     * @return bool True on success
     */
    public static function setLevel(string $level): bool {
        $available_levels = array_keys(datamachine_get_available_log_levels());
        if (!in_array($level, $available_levels)) {
            return false;
        }

        return update_option('datamachine_log_level', $level);
    }

    /**
     * Get the current log level.
     *
     * @return string Current log level
     */
    public static function getLevel(): string {
        return get_option('datamachine_log_level', 'error');
    }

    /**
     * Get the log file path.
     *
     * @return string Absolute path to log file
     */
    public static function getFilePath(): string {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . DATAMACHINE_LOG_FILE;
    }

    /**
     * Get log file size in megabytes.
     *
     * @return float File size in MB, 0 if file doesn't exist
     */
    public static function getFileSize(): float {
        $log_file = self::getFilePath();
        if (!file_exists($log_file)) {
            return 0;
        }
        return round(filesize($log_file) / 1024 / 1024, 2);
    }

    /**
     * Get log file content with optional filtering.
     *
     * @param string $mode Content mode: 'full' or 'recent'
     * @param int $limit Number of entries when mode is 'recent'
     * @param int|null $jobId Optional job ID to filter by
     * @return array Result array with content, metadata, and status
     */
    public static function getContent(string $mode = 'full', int $limit = 200, ?int $jobId = null): array {
        $log_file = self::getFilePath();

        if (!file_exists($log_file)) {
            return [
                'success' => false,
                'error' => 'log_file_not_found',
                'message' => __('Log file does not exist.', 'data-machine')
            ];
        }

        $file_content = @file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($file_content === false) {
            return [
                'success' => false,
                'error' => 'log_file_read_error',
                'message' => __('Unable to read log file.', 'data-machine')
            ];
        }

        $file_content = array_reverse($file_content);
        $total_lines = count($file_content);

        $filtered_lines = null;
        if ($jobId !== null) {
            $file_content = self::filterByJobId($file_content, $jobId);
            $filtered_lines = count($file_content);
        }

        if ($mode === 'recent') {
            $file_content = array_slice($file_content, 0, $limit);
        }

        $content = implode("\n", $file_content);

        $response = [
            'success' => true,
            'content' => $content,
            'total_lines' => $total_lines,
            'mode' => $mode
        ];

        if ($filtered_lines !== null) {
            $response['filtered_lines'] = $filtered_lines;
            $response['job_id'] = $jobId;
        }

        if ($jobId !== null) {
            $response['message'] = sprintf(
                /* translators: 1: number of log entries, 2: job ID */
                __('Retrieved %1$d log entries for job %2$d.', 'data-machine'),
                count($file_content),
                $jobId
            );
        } else {
            $response['message'] = sprintf(
                /* translators: 1: number of log entries, 2: log set label */
                __('Loaded %1$d %2$s log entries.', 'data-machine'),
                count($file_content),
                $mode === 'recent' ? 'recent' : 'total'
            );
        }

        return $response;
    }

    /**
     * Get log file metadata and configuration.
     *
     * @return array Metadata including file info and configuration
     */
    public static function getMetadata(): array {
        $log_file_path = self::getFilePath();
        $log_file_exists = file_exists($log_file_path);
        $log_file_size = $log_file_exists ? filesize($log_file_path) : 0;

        $size_formatted = $log_file_size > 0
            ? size_format($log_file_size, 2)
            : '0 bytes';

        $current_level = self::getLevel();
        $available_levels = datamachine_get_available_log_levels();

        return [
            'success' => true,
            'log_file' => [
                'path' => $log_file_path,
                'exists' => $log_file_exists,
                'size' => $log_file_size,
                'size_formatted' => $size_formatted
            ],
            'configuration' => [
                'current_level' => $current_level,
                'available_levels' => $available_levels
            ]
        ];
    }

    /**
     * Filter log lines by job ID.
     *
     * @param array $lines Log file lines
     * @param int $jobId Job ID to filter by
     * @return array Filtered log lines
     */
    private static function filterByJobId(array $lines, int $jobId): array {
        $filtered = [];

        foreach ($lines as $line) {
            if (preg_match('/"job_id"\s*:\s*' . preg_quote($jobId, '/') . '(?:[,\}])/', $line)) {
                $filtered[] = $line;
            }
        }

        return $filtered;
    }
}
