<?php
/**
 * LogsManager Service
 *
 * Centralized service for log file operations including reading, clearing,
 * and configuration. Supports per-agent-type log files and levels.
 *
 * Note: Actual logging (write operations) uses the datamachine_log action hook
 * which remains in DataMachineActions.php for cross-cutting concern flexibility.
 *
 * @package DataMachine\Services
 * @since 0.1.0
 */

namespace DataMachine\Services;

use DataMachine\Engine\AI\AgentType;

if (!defined('WPINC')) {
    die;
}

class LogsManager {

    /**
     * Clear the log file for a specific agent type.
     *
     * @param string $agentType Agent type (pipeline, chat)
     * @return bool True on success
     */
    public static function clear(string $agentType): bool {
        return datamachine_clear_log_file($agentType);
    }

    /**
     * Clear all agent type log files.
     *
     * @return bool True if all files cleared successfully
     */
    public static function clearAll(): bool {
        return datamachine_clear_all_log_files();
    }

    /**
     * Set the log level for a specific agent type.
     *
     * @param string $agentType Agent type (pipeline, chat)
     * @param string $level Log level (debug, error, none)
     * @return bool True on success
     */
    public static function setLevel(string $agentType, string $level): bool {
        return datamachine_set_log_level($agentType, $level);
    }

    /**
     * Get the current log level for a specific agent type.
     *
     * @param string $agentType Agent type (pipeline, chat)
     * @return string Current log level
     */
    public static function getLevel(string $agentType): string {
        return datamachine_get_log_level($agentType);
    }

    /**
     * Get the log file path for a specific agent type.
     *
     * @param string $agentType Agent type (pipeline, chat)
     * @return string Absolute path to log file
     */
    public static function getFilePath(string $agentType): string {
        return datamachine_get_log_file_path($agentType);
    }

    /**
     * Get log file size in megabytes for a specific agent type.
     *
     * @param string $agentType Agent type (pipeline, chat)
     * @return float File size in MB, 0 if file doesn't exist
     */
    public static function getFileSize(string $agentType): float {
        return datamachine_get_log_file_size($agentType);
    }

    /**
     * Get log file content with optional filtering for a specific agent type.
     *
     * @param string $agentType Agent type (pipeline, chat)
     * @param string $mode Content mode: 'full' or 'recent'
     * @param int $limit Number of entries when mode is 'recent'
     * @param int|null $jobId Optional job ID to filter by
     * @param int|null $pipelineId Optional pipeline ID to filter by
     * @param int|null $flowId Optional flow ID to filter by
     * @return array Result array with content, metadata, and status
     */
    public static function getContent(string $agentType, string $mode = 'full', int $limit = 200, ?int $jobId = null, ?int $pipelineId = null, ?int $flowId = null): array {
        if (!AgentType::isValid($agentType)) {
            return [
                'success' => false,
                'error' => 'invalid_agent_type',
                'message' => __('Invalid agent type specified.', 'data-machine')
            ];
        }

        $log_file = self::getFilePath($agentType);

        if (!file_exists($log_file)) {
            return [
                'success' => true,
                'content' => '',
                'total_lines' => 0,
                'mode' => $mode,
                'agent_type' => $agentType,
                'message' => __('No log entries found.', 'data-machine')
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

        // Apply filters (AND logic - all filters must match)
        $has_filters = $jobId !== null || $pipelineId !== null || $flowId !== null;
        if ($jobId !== null) {
            $file_content = self::filterByJobId($file_content, $jobId);
        }
        if ($pipelineId !== null) {
            $file_content = self::filterByPipelineId($file_content, $pipelineId);
        }
        if ($flowId !== null) {
            $file_content = self::filterByFlowId($file_content, $flowId);
        }
        $filtered_lines = $has_filters ? count($file_content) : null;

        if ($mode === 'recent') {
            $file_content = array_slice($file_content, 0, $limit);
        }

        $content = implode("\n", $file_content);

        $response = [
            'success' => true,
            'content' => $content,
            'total_lines' => $total_lines,
            'mode' => $mode,
            'agent_type' => $agentType
        ];

        if ($filtered_lines !== null) {
            $response['filtered_lines'] = $filtered_lines;
            if ($jobId !== null) {
                $response['job_id'] = $jobId;
            }
            if ($pipelineId !== null) {
                $response['pipeline_id'] = $pipelineId;
            }
            if ($flowId !== null) {
                $response['flow_id'] = $flowId;
            }
        }

        if ($jobId !== null || $pipelineId !== null || $flowId !== null) {
            $filter_parts = [];
            if ($jobId !== null) {
                $filter_parts[] = sprintf('job %d', $jobId);
            }
            if ($pipelineId !== null) {
                $filter_parts[] = sprintf('pipeline %d', $pipelineId);
            }
            if ($flowId !== null) {
                $filter_parts[] = sprintf('flow %d', $flowId);
            }
            $response['message'] = sprintf(
                __('Retrieved %1$d log entries for %2$s.', 'data-machine'),
                count($file_content),
                implode(', ', $filter_parts)
            );
        } else {
            $response['message'] = sprintf(
                __('Loaded %1$d %2$s log entries.', 'data-machine'),
                count($file_content),
                $mode === 'recent' ? 'recent' : 'total'
            );
        }

        return $response;
    }

    /**
     * Get log file metadata and configuration for a specific agent type.
     *
     * @param string $agentType Agent type (pipeline, chat)
     * @return array Metadata including file info and configuration
     */
    public static function getMetadata(string $agentType): array {
        if (!AgentType::isValid($agentType)) {
            return [
                'success' => false,
                'error' => 'invalid_agent_type',
                'message' => __('Invalid agent type specified.', 'data-machine')
            ];
        }

        $log_file_path = self::getFilePath($agentType);
        $log_file_exists = file_exists($log_file_path);
        $log_file_size = $log_file_exists ? filesize($log_file_path) : 0;

        $size_formatted = $log_file_size > 0
            ? size_format($log_file_size, 2)
            : '0 bytes';

        $current_level = self::getLevel($agentType);
        $available_levels = datamachine_get_available_log_levels();

        $agent_types = AgentType::getAll();
        $agent_info = $agent_types[$agentType] ?? [];

        return [
            'success' => true,
            'agent_type' => $agentType,
            'agent_label' => $agent_info['label'] ?? ucfirst($agentType),
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
     * Get metadata for all agent types.
     *
     * @return array Array of metadata for each agent type
     */
    public static function getAllMetadata(): array {
        $all_metadata = [];

        foreach (AgentType::getAll() as $agentType => $info) {
            $all_metadata[$agentType] = self::getMetadata($agentType);
        }

        return [
            'success' => true,
            'agent_types' => $all_metadata
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

    /**
     * Filter log lines by pipeline ID.
     *
     * @param array $lines Log file lines
     * @param int $pipelineId Pipeline ID to filter by
     * @return array Filtered log lines
     */
    private static function filterByPipelineId(array $lines, int $pipelineId): array {
        $filtered = [];

        foreach ($lines as $line) {
            if (preg_match('/"pipeline_id"\s*:\s*' . preg_quote($pipelineId, '/') . '(?:[,\}])/', $line)) {
                $filtered[] = $line;
            }
        }

        return $filtered;
    }

    /**
     * Filter log lines by flow ID.
     *
     * @param array $lines Log file lines
     * @param int $flowId Flow ID to filter by
     * @return array Filtered log lines
     */
    private static function filterByFlowId(array $lines, int $flowId): array {
        $filtered = [];

        foreach ($lines as $line) {
            if (preg_match('/"flow_id"\s*:\s*' . preg_quote($flowId, '/') . '(?:[,\}])/', $line)) {
                $filtered[] = $line;
            }
        }

        return $filtered;
    }
}
