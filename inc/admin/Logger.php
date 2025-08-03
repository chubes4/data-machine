<?php
/**
 * Handles logging for the Data Machine plugin using Monolog.
 *
 * Provides methods for logging to a dedicated file and managing admin notices.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/admin
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin;

// Declare upfront to ensure they are available
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\Handler\ErrorLogHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Logger {


    /**
     * Monolog instance.
     * @var MonologLogger|null
     */
    private $monolog_instance = null;

    /**
     * Convert string log level to Monolog Level.
     *
     * @param string $level_string Log level string
     * @return Level|null Monolog level constant, null for 'none'
     */
    private function get_monolog_level(string $level_string): ?Level {
        switch (strtolower($level_string)) {
            case 'debug':
                return Level::Debug;
            case 'info':
                return Level::Info;
            case 'error':
                return Level::Error; // This will include warnings per Monolog hierarchy
            case 'none':
                return null; // No logging
            default:
                return Level::Info;
        }
    }

    /**
     * Gets the Monolog logger instance, initializing it if needed.
     *
     * @return MonologLogger
     */
    private function get_monolog(): MonologLogger {
        if ($this->monolog_instance === null) {
            // Define log path
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/data-machine-logs';

            // Create the directory if it doesn't exist - fail fast if unable
            if (!file_exists($log_dir) && !wp_mkdir_p($log_dir)) {
                wp_die('Data Machine: Cannot create log directory. Check filesystem permissions.');
            }

            // Check if directory is writable using WP_Filesystem - fail fast if not
            global $wp_filesystem;
            if ( ! $wp_filesystem ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if (!$wp_filesystem->is_writable($log_dir)) {
                wp_die('Data Machine: Log directory not writable. Check filesystem permissions.');
            }

            $log_file = $log_dir . '/data-machine.log';

            // Create a logger channel
            $this->monolog_instance = new MonologLogger('DataMachine');

            try {
                // Get configurable log level from WordPress options
                $log_level_setting = get_option('dm_log_level', 'info');
                $log_level = $this->get_monolog_level($log_level_setting);
                
                // If log level is 'none', don't add any handlers (disables logging)
                if ($log_level !== null) {
                    // Create a handler (writing to a file)
                    $handler = new StreamHandler($log_file, $log_level);

                    // Customize log format
                    $formatter = new LineFormatter(
                        "[%datetime%] [%channel%.%level_name%]: %message% %context% %extra%\n",
                        "Y-m-d H:i:s", // Human-readable format
                        true, // Allow inline line breaks
                        true  // Ignore empty context/extra
                    );
                    $handler->setFormatter($formatter);

                    // Push the handler
                    $this->monolog_instance->pushHandler($handler);
                }

            } catch (\Exception $e) {
                wp_die('Data Machine: Failed to initialize logger. Error: ' . esc_html($e->getMessage()));
            }
        }
        return $this->monolog_instance;
    }

    /**
     * Logs a message using Monolog.
     *
     * @since NEXT_VERSION
     *
     * @param \Monolog\Level $level   Log level (use Monolog\Level constants).
     * @param string|\Stringable $message The message to log.
     * @param array $context Optional context data.
     */
    public function log(Level $level, string|\Stringable $message, array $context = [] ): void {
        try {
             $this->get_monolog()->log($level, $message, $context);
        } catch (\Exception $e) {
             // Prevent logging failures from crashing the application
        }
    }

    /**
     * Logs an error message.
     *
     * @since NEXT_VERSION
     * @param string|\Stringable $message The error message.
     * @param array $context Optional context data.
     */
    public function error( string|\Stringable $message, array $context = [] ): void {
        $this->log( Level::Error, $message, $context );
    }

    /**
     * Logs a warning message.
     *
     * @since NEXT_VERSION
     * @param string|\Stringable $message The warning message.
     * @param array $context Optional context data.
     */
    public function warning( string|\Stringable $message, array $context = [] ): void {
        $this->log( Level::Warning, $message, $context );
    }

    /**
     * Logs an informational message.
     *
     * @since NEXT_VERSION
     * @param string|\Stringable $message The info message.
     * @param array $context Optional context data.
     */
    public function info( string|\Stringable $message, array $context = [] ): void {
        $this->log( Level::Info, $message, $context );
    }

    /**
     * Logs a debug message.
     *
     * @since NEXT_VERSION
     * @param string|\Stringable $message The debug message.
     * @param array $context Optional context data.
     */
    public function debug( string|\Stringable $message, array $context = [] ): void {
        // Consider adding a check for WP_DEBUG or a custom constant
        // if (defined('WP_DEBUG') && WP_DEBUG) {
             $this->log( Level::Debug, $message, $context );
        // }
    }

    /**
     * Logs a critical message.
     *
     * @since NEXT_VERSION
     * @param string|\Stringable $message The critical message.
     * @param array $context Optional context data.
     */
    public function critical( string|\Stringable $message, array $context = [] ): void {
        $this->log( Level::Critical, $message, $context );
    }




    /**
     * Cleans up log files based on size or age criteria.
     * 
     * @param int $max_size_mb Maximum log file size in MB (default 10MB)
     * @param int $max_age_days Maximum log file age in days (default 30 days)
     * @return bool True if cleanup was performed, false otherwise
     */
    public function cleanup_log_files( $max_size_mb = 10, $max_age_days = 30 ) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/data-machine-logs';
        $log_file = $log_dir . '/data-machine.log';

        if ( ! file_exists( $log_file ) ) {
            return false;
        }

        $cleanup_needed = false;
        $max_size_bytes = $max_size_mb * 1024 * 1024;
        
        // Check file size
        if ( filesize( $log_file ) > $max_size_bytes ) {
            $cleanup_needed = true;
            $this->info( "Log file cleanup triggered: Size " . round( filesize( $log_file ) / 1024 / 1024, 2 ) . "MB exceeds limit of {$max_size_mb}MB" );
        }
        
        // Check file age
        $file_age_days = ( time() - filemtime( $log_file ) ) / DAY_IN_SECONDS;
        if ( $file_age_days > $max_age_days ) {
            $cleanup_needed = true;
            $this->info( "Log file cleanup triggered: Age " . round( $file_age_days, 1 ) . " days exceeds limit of {$max_age_days} days" );
        }

        if ( $cleanup_needed ) {
            return $this->rotate_log_file( $log_file );
        }

        return false;
    }

    /**
     * Rotates the log file by archiving the current file and starting fresh.
     * 
     * @param string $log_file Path to the log file
     * @return bool True on success, false on failure
     */
    private function rotate_log_file( $log_file ) {
        try {
            $backup_file = $log_file . '.old';
            
            // Initialize WP_Filesystem
            global $wp_filesystem;
            if ( ! $wp_filesystem ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            
            // Remove old backup if it exists
            if ( $wp_filesystem->exists( $backup_file ) ) {
                wp_delete_file( $backup_file );
            }
            
            // Move current log to backup
            if ( $wp_filesystem->move( $log_file, $backup_file ) ) {
                $this->info( "Log file rotated successfully. Old log archived as data-machine.log.old" );
                return true;
            } else {
                $this->error( "Failed to rotate log file" );
                return false;
            }
        } catch ( \Exception $e ) {
            $this->error( "Exception during log file rotation: " . $e->getMessage() );
            return false;
        }
    }

    /**
     * Get log file path.
     *
     * @return string Log file path
     */
    public function get_log_file_path(): string {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/data-machine-logs';
        return $log_dir . '/data-machine.log';
    }

    /**
     * Get log file size in MB.
     *
     * @return float Log file size in MB
     */
    public function get_log_file_size(): float {
        $log_file = $this->get_log_file_path();
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
    public function get_recent_logs(int $lines = 100): array {
        $log_file = $this->get_log_file_path();
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
     * Clear all log files.
     *
     * @return bool True on success, false on failure
     */
    public function clear_logs(): bool {
        $log_file = $this->get_log_file_path();
        $backup_file = $log_file . '.old';
        
        $success = true;
        
        // Initialize WP_Filesystem
        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        // Clear main log file
        if ($wp_filesystem->exists($log_file)) {
            if (!wp_delete_file($log_file)) {
                $success = false;
            }
        }
        
        // Clear backup log file
        if ($wp_filesystem->exists($backup_file)) {
            if (!wp_delete_file($backup_file)) {
                $success = false;
            }
        }
        
        if ($success) {
            $this->info('Log files cleared successfully.');
        } else {
            $this->error('Failed to clear some log files.');
        }
        
        return $success;
    }

    /**
     * Get available log levels.
     *
     * @return array Array of log levels
     */
    public static function get_available_log_levels(): array {
        return [
            'debug' => 'Debug (verbose logging)',
            'info' => 'Info (normal logging)',
            'error' => 'Error (errors and warnings only)',
            'none' => 'None (disable logging)'
        ];
    }

    /**
     * Get current log level setting.
     *
     * @return string Current log level
     */
    public function get_level(): string {
        return get_option('dm_log_level', 'info');
    }

    /**
     * Set log level setting.
     *
     * @param string $level Log level to set
     * @return bool True on success, false on failure
     */
    public function set_level(string $level): bool {
        $available_levels = array_keys(self::get_available_log_levels());
        if (!in_array($level, $available_levels)) {
            return false;
        }
        
        return update_option('dm_log_level', $level);
    }

} // End class


 