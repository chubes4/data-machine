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

namespace DataMachine\Core\Logger;

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

    const TRANSIENT_NOTICES = 'dm_admin_notices'; // Transient to store notices
    const NOTICE_ERROR      = 'error';
    const NOTICE_SUCCESS    = 'success';
    const NOTICE_WARNING    = 'warning';
    const NOTICE_INFO       = 'info';

    /**
     * Monolog instance.
     * @var MonologLogger|null
     */
    private $monolog_instance = null;

    /**
     * Convert string log level to Monolog Level.
     *
     * @param string $level_string Log level string
     * @return Level Monolog level constant
     */
    private function get_monolog_level(string $level_string): Level {
        switch (strtolower($level_string)) {
            case 'error':
                return Level::Error;
            case 'warning':
            case 'warn':
                return Level::Warning;
            case 'info':
                return Level::Info;
            case 'debug':
                return Level::Debug;
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

            // Check if directory is writable - fail fast if not
            if (!is_writable($log_dir)) {
                wp_die('Data Machine: Log directory not writable. Check filesystem permissions.');
            }

            $log_file = $log_dir . '/data-machine.log';

            // Create a logger channel
            $this->monolog_instance = new MonologLogger('DataMachine');

            try {
                // Get configurable log level from WordPress options
                $log_level_setting = get_option('dm_log_level', 'info');
                $log_level = $this->get_monolog_level($log_level_setting);
                
                // Create a handler (writing to a file)
                $handler = new StreamHandler($log_file, $log_level);

                // Optional: Customize log format
                $formatter = new LineFormatter(
                    "[%datetime%] [%channel%.%level_name%]: %message% %context% %extra%\n",
                    "Y-m-d H:i:s", // Human-readable format
                    true, // Allow inline line breaks
                    true  // Ignore empty context/extra
                );
                $handler->setFormatter($formatter);

                // Push the handler
                $this->monolog_instance->pushHandler($handler);

            } catch (\Exception $e) {
                wp_die('Data Machine: Failed to initialize logger. Error: ' . $e->getMessage());
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
     * Adds an admin notice to be displayed on the next admin page load.
     *
     * @since NEXT_VERSION
     *
     * @param string $type    Notice type (error, success, warning, info).
     * @param string $message The message to display.
     * @param bool   $is_dismissible Whether the notice should be dismissible.
     * @param array  $details Optional additional details (primarily for errors).
     */
    public function add_admin_notice( $type, $message, $is_dismissible = true, $details = [] ) {
        $notices = get_transient( self::TRANSIENT_NOTICES );
        if ( ! is_array( $notices ) ) {
            $notices = [];
        }

        $notices[] = [
            'type'           => $type,
            'message'        => $message,
            'is_dismissible' => $is_dismissible,
            'details'        => $details, // Store details if provided
            'time'           => time() // Add timestamp
        ];

        set_transient( self::TRANSIENT_NOTICES, $notices, 30 ); // Store for 30 seconds
    }

    /**
     * Adds an error admin notice.
     *
     * @since NEXT_VERSION
     *
     * @param string $message The error message.
     * @param array  $details Optional details to display with the error.
     */
    public function add_admin_error( $message, $details = [] ) {
        // Log the error internally as well
        $this->error( $message, $details ); 
        $this->add_admin_notice( self::NOTICE_ERROR, $message, true, $details );
    }

    /**
     * Adds a success admin notice.
     *
     * @since NEXT_VERSION
     *
     * @param string $message The success message.
     */
    public function add_admin_success( $message ) {
        // Optionally log success messages if needed
        // $this->info( $message ); 
        $this->add_admin_notice( self::NOTICE_SUCCESS, $message, true );
    }
    
    /**
     * Adds a warning admin notice.
     *
     * @since NEXT_VERSION
     *
     * @param string $message The warning message.
     */
    public function add_admin_warning( $message ) {
        $this->warning( $message ); 
        $this->add_admin_notice( self::NOTICE_WARNING, $message, true );
    }
    
    /**
     * Adds an info admin notice.
     *
     * @since NEXT_VERSION
     *
     * @param string $message The info message.
     */
    public function add_admin_info( $message ) {
        $this->info( $message ); 
        $this->add_admin_notice( self::NOTICE_INFO, $message, true );
    }

    /**
     * Retrieves all stored admin notices and clears the transient.
     * 
     * Important: Call this method *before* adding new notices for the current request 
     * if you intend to display notices from the *previous* request.
     * 
     * @since NEXT_VERSION
     * @return array The array of notice data, or an empty array if none found.
     */
    public function get_pending_notices() {
        $notices = get_transient( self::TRANSIENT_NOTICES );
        if ( is_array( $notices ) ) {
            delete_transient( self::TRANSIENT_NOTICES );
            return $notices;
        }
        return [];
    }

    /**
     * Displays pending admin notices stored in the transient.
     * Should be hooked to the 'admin_notices' action.
     *
     * @since NEXT_VERSION
     */
    public function display_admin_notices() {
        $notices = $this->get_pending_notices(); // Use the existing getter which also deletes

        if ( ! empty( $notices ) ) {
            foreach ( $notices as $notice ) {
                if ( empty( $notice['message'] ) || empty( $notice['type'] ) ) {
                    continue;
                }
                $type = sanitize_key( $notice['type'] );
                $is_dismissible = ! empty( $notice['is_dismissible'] );
                $class = 'notice notice-' . $type;
                if ( $is_dismissible ) {
                    $class .= ' is-dismissible';
                }
                
                // Basic message output
                printf(
                    '<div class="%s"><p>%s</p></div>',
                    esc_attr( $class ),
                    wp_kses_post( $notice['message'] ) // Allow basic HTML in message
                );
                
                // Optional: Output details for errors (consider formatting)
                if ($type === self::NOTICE_ERROR && !empty($notice['details'])) {
                    // Simple output for now, could be formatted better
                    echo '<pre style="margin-left: 2em; font-size: 0.9em;">' . esc_html( json_encode( $notice['details'], JSON_PRETTY_PRINT ) ) . '</pre>';
                }
            }
        }
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
            
            // Remove old backup if it exists
            if ( file_exists( $backup_file ) ) {
                unlink( $backup_file );
            }
            
            // Move current log to backup
            if ( rename( $log_file, $backup_file ) ) {
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
        
        // Clear main log file
        if (file_exists($log_file)) {
            if (!unlink($log_file)) {
                $success = false;
            }
        }
        
        // Clear backup log file
        if (file_exists($backup_file)) {
            if (!unlink($backup_file)) {
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
            'error' => 'Error (errors only)',
            'warning' => 'Warning (warnings and errors)',
            'info' => 'Info (normal logging)',
            'debug' => 'Debug (verbose logging)'
        ];
    }

} // End class 