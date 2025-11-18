<?php
/**
 * Data Machine Logger Functions and Filter Registration
 *
 * Pure function implementation of the Data Machine logging system.
 * Eliminates singleton pattern and class-based architecture in favor of
 * WordPress-native actions and filters.
 *
 * ARCHITECTURE:
 * - datamachine_log action (DataMachineActions.php): Operations that modify state (write, clear, cleanup, set_level)
 * - datamachine_log_file filter: Operations that get configuration information (get_level, get_available_levels)
 *
 * @package DataMachine
 * @subpackage Engine\Filters
 * @since 0.1.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Monolog dependencies
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;

/**
 * Register logger-related filters.
 * 
 * Registers datamachine_log_file filter for configuration retrieval operations.
 * Action operations (write, clear, cleanup, set_level) handled in DataMachineActions.php.
 *
 * @since 0.1.0
 */
function datamachine_register_logger_filters() {
}


/**
 * Get Monolog instance with request-level caching.
 * 
 * Replaces singleton pattern with static variable for performance
 * while maintaining pure function architecture.
 *
 * @param bool $force_refresh Force recreation of Monolog instance
 * @return MonologLogger Configured Monolog instance
 */
function datamachine_get_monolog_instance($force_refresh = false) {
    static $monolog_instance = null;

    if ($monolog_instance === null || $force_refresh) {
        // Get log level from WordPress options
        $log_level_setting = get_option('datamachine_log_level', 'error');
        $log_level = datamachine_get_monolog_level($log_level_setting);

        // Create Monolog instance
        $monolog_instance = new MonologLogger('DataMachine');

        // Add handler only if logging is enabled (not 'none')
        if ($log_level !== null) {
            $log_file = datamachine_get_log_file_path();
            $handler = new StreamHandler($log_file, $log_level);

            // Configure formatter
            $formatter = new LineFormatter(
                "[%datetime%] [%channel%.%level_name%]: %message% %context% %extra%\n",
                "Y-m-d H:i:s",
                true, // Allow inline line breaks
                true  // Ignore empty context/extra
            );
            $handler->setFormatter($formatter);
            $monolog_instance->pushHandler($handler);
        }
    }

    return $monolog_instance;
}

/**
 * Convert string log level to Monolog Level.
 *
 * @param string $level_string Log level string (debug, error, none)
 * @return Level|null Monolog level constant, null for 'none'
 */
function datamachine_get_monolog_level(string $level_string): ?Level {
    switch (strtolower($level_string)) {
        case 'debug':
            return Level::Debug;
        case 'error':
            return Level::Error;
        case 'none':
            return null; // No logging
        default:
            return Level::Debug; // Default to full logging if invalid level
    }
}

/**
 * Log a message using Monolog.
 *
 * @param Level $level Monolog level
 * @param string|\Stringable $message Message to log
 * @param array $context Optional context data
 */
function datamachine_log_message(Level $level, string|\Stringable $message, array $context = []): void {
    try {
        datamachine_get_monolog_instance()->log($level, $message, $context);
    } catch (\Exception $e) {
        // Prevent logging failures from crashing the application
    }
}

/**
 * Log an error message.
 *
 * @param string|\Stringable $message Error message
 * @param array $context Optional context data
 */
function datamachine_log_error(string|\Stringable $message, array $context = []): void {
    datamachine_log_message(Level::Error, $message, $context);
}

/**
 * Log a warning message.
 *
 * @param string|\Stringable $message Warning message
 * @param array $context Optional context data
 */
function datamachine_log_warning(string|\Stringable $message, array $context = []): void {
    datamachine_log_message(Level::Warning, $message, $context);
}

/**
 * Log an informational message.
 *
 * @param string|\Stringable $message Info message
 * @param array $context Optional context data
 */
function datamachine_log_info(string|\Stringable $message, array $context = []): void {
    datamachine_log_message(Level::Info, $message, $context);
}

/**
 * Log a debug message.
 *
 * @param string|\Stringable $message Debug message
 * @param array $context Optional context data
 */
function datamachine_log_debug(string|\Stringable $message, array $context = []): void {
    datamachine_log_message(Level::Debug, $message, $context);
}

/**
 * Log a critical message.
 *
 * @param string|\Stringable $message Critical message
 * @param array $context Optional context data
 */
function datamachine_log_critical(string|\Stringable $message, array $context = []): void {
    datamachine_log_message(Level::Critical, $message, $context);
}


function datamachine_get_log_file_path(): string {
    $upload_dir = wp_upload_dir();
    return $upload_dir['basedir'] . DATAMACHINE_LOG_FILE;
}

function datamachine_get_log_file_size(): float {
    $log_file = datamachine_get_log_file_path();
    if (!file_exists($log_file)) {
        return 0;
    }
    return round(filesize($log_file) / 1024 / 1024, 2);
}

/**
 * Get recent log entries.
 *
 * @param int $lines Number of lines to retrieve
 * @return array Array of log lines
 */
function datamachine_get_recent_logs(int $lines = 100): array {
    $log_file = datamachine_get_log_file_path();
    if (!file_exists($log_file)) {
        return ['No log file found.'];
    }

    // Read entire file and get last lines
    $file_content = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($file_content === false) {
        return ['Unable to read log file.'];
    }

    return array_slice($file_content, -$lines);
}

/**
 * Clean up log files based on size or age criteria.
 *
 * @param int $max_size_mb Maximum log file size in MB
 * @param int $max_age_days Maximum log file age in days
 * @return bool True if cleanup was performed
 */
function datamachine_cleanup_log_files($max_size_mb = 10, $max_age_days = 30): bool {
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . DATAMACHINE_LOG_DIR;

    if (!file_exists($log_dir)) {
        return false;
    }

    $log_file = datamachine_get_log_file_path();

    if (!file_exists($log_file)) {
        return false;
    }

    $max_size_bytes = $max_size_mb * 1024 * 1024;

    $size_exceeds = filesize($log_file) > $max_size_bytes;
    $age_exceeds = (time() - filemtime($log_file)) / DAY_IN_SECONDS > $max_age_days;

    if ($size_exceeds && $age_exceeds) {
        datamachine_log_debug("Log file cleanup triggered: Size and age limits exceeded");
        return datamachine_clear_log_files();
    }

    return false;
}


/**
 * Clear log file.
 *
 * @return bool True on success
 */
function datamachine_clear_log_files(): bool {
    $log_file = datamachine_get_log_file_path();

    // Ensure directory exists
    $log_dir = dirname($log_file);
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
    }

    // Clear log file contents (creates file if it doesn't exist)
    $clear_result = file_put_contents($log_file, '');

    if ($clear_result !== false) {
        datamachine_log_debug('Log file cleared successfully.');
        return true;
    } else {
        datamachine_log_error('Failed to clear log file.');
        return false;
    }
}


function datamachine_get_log_level(): string {
    return get_option('datamachine_log_level', 'error');
}

function datamachine_set_log_level(string $level): bool {
    $available_levels = array_keys(datamachine_get_available_log_levels());
    if (!in_array($level, $available_levels)) {
        return false;
    }
    
    return update_option('datamachine_log_level', $level);
}

/**
 * Get all valid log levels that can be used for logging operations.
 *
 * @return array Array of valid log level strings
 */
function datamachine_get_valid_log_levels(): array {
    return ['debug', 'info', 'warning', 'error', 'critical'];
}

/**
 * Get user-configurable log levels for admin interface.
 *
 * @return array Array of log levels with descriptions for user selection
 */
function datamachine_get_available_log_levels(): array {
    return [
        'debug' => 'Debug (full logging)',
        'error' => 'Error (problems only)',
        'none' => 'None (disable logging)'
    ];
}