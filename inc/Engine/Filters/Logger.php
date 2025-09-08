<?php
/**
 * Data Machine Logger Functions and Filter Registration
 *
 * Pure function implementation of the Data Machine logging system.
 * Eliminates singleton pattern and class-based architecture in favor of
 * WordPress-native actions and filters.
 *
 * ARCHITECTURE:
 * - dm_log action (DataMachineActions.php): Operations that modify state (write, clear, cleanup, set_level)
 * - dm_log_file filter: Operations that get information (get_recent, get_size, get_path, get_level, get_available_levels)
 *
 * @package DataMachine
 * @subpackage Engine\Filters
 * @since 0.1.0
 */

// If this file is called directly, abort.
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
 * Registers dm_log_file filter for information retrieval operations.
 * Action operations (write, clear, cleanup, set_level) handled in DataMachineActions.php.
 *
 * @since 0.1.0
 */
function dm_register_logger_filters() {
    add_filter('dm_log_file', function($result, $operation, $param1 = null) {
        switch ($operation) {
            case 'get_recent':
                return dm_get_recent_logs($param1 ?? 100);
            case 'get_size':
                return dm_get_log_file_size();
            case 'get_path':
                return dm_get_log_file_path();
            case 'get_level':
                return dm_get_log_level();
            case 'get_available_levels':
                return dm_get_available_log_levels();
        }
        return $result;
    }, 10, 3);
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
function dm_get_monolog_instance($force_refresh = false) {
    static $monolog_instance = null;
    
    if ($monolog_instance === null || $force_refresh) {
        // Ensure log directory exists
        dm_ensure_log_directory();
        
        // Get log level from WordPress options
        $log_level_setting = get_option('dm_log_level', 'error');
        $log_level = dm_get_monolog_level($log_level_setting);
        
        // Create Monolog instance
        $monolog_instance = new MonologLogger('DataMachine');
        
        // Add handler only if logging is enabled (not 'none')
        if ($log_level !== null) {
            $log_file = dm_get_log_file_path();
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
function dm_get_monolog_level(string $level_string): ?Level {
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
 * Ensure log directory exists and is writable.
 * 
 * Uses static flag for performance to avoid repeated checks.
 *
 * @return bool True on success
 */
function dm_ensure_log_directory() {
    static $directory_created = false;
    
    if ($directory_created) {
        return true;
    }
    
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/data-machine-logs';
    
    // Create directory if needed
    if (!file_exists($log_dir) && !wp_mkdir_p($log_dir)) {
        wp_die('Data Machine: Cannot create log directory. Check filesystem permissions.');
    }
    
    // Verify writable using WP_Filesystem
    global $wp_filesystem;
    if (!$wp_filesystem) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }
    
    if (!$wp_filesystem->is_writable($log_dir)) {
        wp_die('Data Machine: Log directory not writable. Check filesystem permissions.');
    }
    
    $directory_created = true;
    return true;
}

/**
 * Log a message using Monolog.
 *
 * @param Level $level Monolog level
 * @param string|\Stringable $message Message to log
 * @param array $context Optional context data
 */
function dm_log_message(Level $level, string|\Stringable $message, array $context = []): void {
    try {
        dm_get_monolog_instance()->log($level, $message, $context);
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
function dm_log_error(string|\Stringable $message, array $context = []): void {
    dm_log_message(Level::Error, $message, $context);
}

/**
 * Log a warning message.
 *
 * @param string|\Stringable $message Warning message
 * @param array $context Optional context data
 */
function dm_log_warning(string|\Stringable $message, array $context = []): void {
    dm_log_message(Level::Warning, $message, $context);
}

/**
 * Log an informational message.
 *
 * @param string|\Stringable $message Info message
 * @param array $context Optional context data
 */
function dm_log_info(string|\Stringable $message, array $context = []): void {
    dm_log_message(Level::Info, $message, $context);
}

/**
 * Log a debug message.
 *
 * @param string|\Stringable $message Debug message
 * @param array $context Optional context data
 */
function dm_log_debug(string|\Stringable $message, array $context = []): void {
    dm_log_message(Level::Debug, $message, $context);
}

/**
 * Log a critical message.
 *
 * @param string|\Stringable $message Critical message
 * @param array $context Optional context data
 */
function dm_log_critical(string|\Stringable $message, array $context = []): void {
    dm_log_message(Level::Critical, $message, $context);
}


/**
 * Get log file path.
 *
 * @return string Log file path
 */
function dm_get_log_file_path(): string {
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/data-machine-logs';
    return $log_dir . '/data-machine.log';
}

/**
 * Get log file size in MB.
 *
 * @return float Log file size in MB
 */
function dm_get_log_file_size(): float {
    $log_file = dm_get_log_file_path();
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
function dm_get_recent_logs(int $lines = 100): array {
    $log_file = dm_get_log_file_path();
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
function dm_cleanup_log_files($max_size_mb = 10, $max_age_days = 30): bool {
    $log_file = dm_get_log_file_path();

    if (!file_exists($log_file)) {
        return false;
    }

    $max_size_bytes = $max_size_mb * 1024 * 1024;
    
    $size_exceeds = filesize($log_file) > $max_size_bytes;
    $age_exceeds = (time() - filemtime($log_file)) / DAY_IN_SECONDS > $max_age_days;

    if ($size_exceeds && $age_exceeds) {
        dm_log_debug("Log file cleanup triggered: Size and age limits exceeded");
        return dm_clear_log_files();
    }

    return false;
}


/**
 * Clear log file.
 *
 * @return bool True on success
 */
function dm_clear_log_files(): bool {
    $log_file = dm_get_log_file_path();
    
    // Clear main log file
    if (file_exists($log_file)) {
        
        $delete_result = wp_delete_file($log_file);
        
        
        if ($delete_result) {
            dm_log_debug('Log file cleared successfully.');
            return true;
        } else {
            dm_log_error('Failed to clear log file.');
            return false;
        }
    }
    
    return true;
}


/**
 * Get current log level setting.
 *
 * @return string Current log level
 */
function dm_get_log_level(): string {
    return get_option('dm_log_level', 'error');
}

/**
 * Set log level setting.
 *
 * @param string $level Log level to set
 * @return bool True on success
 */
function dm_set_log_level(string $level): bool {
    $available_levels = array_keys(dm_get_available_log_levels());
    if (!in_array($level, $available_levels)) {
        return false;
    }
    
    return update_option('dm_log_level', $level);
}

/**
 * Get available log levels.
 *
 * @return array Array of log levels with descriptions
 */
function dm_get_available_log_levels(): array {
    return [
        'debug' => 'Debug (full logging)',
        'error' => 'Error (problems only)',
        'none' => 'None (disable logging)'
    ];
}