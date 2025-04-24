<?php
/**
 * Handles logging for the Data Machine plugin using Monolog.
 *
 * Provides methods for logging to a dedicated file and managing admin notices.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/helpers
 * @since      NEXT_VERSION
 */

// Declare upfront to ensure they are available
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\Handler\ErrorLogHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Data_Machine_Logger {

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
     * Gets the Monolog logger instance, initializing it if needed.
     *
     * @return MonologLogger
     */
    private function get_monolog(): MonologLogger {
        if ($this->monolog_instance === null) {
            // Define log path
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/data-machine-logs';

            // Attempt to create the directory if it doesn't exist
            if (!file_exists($log_dir)) {
                if (!wp_mkdir_p($log_dir)) {
                    error_log('Data Machine Logger Error: Could not create log directory: ' . $log_dir);
                    // Fallback to basic ErrorLogHandler
                    $this->monolog_instance = new MonologLogger('DataMachineFallback');
                    $this->monolog_instance->pushHandler(new ErrorLogHandler());
                    return $this->monolog_instance;
                 }
             }

            // Check if directory is writable
            if (!is_writable($log_dir)) {
                error_log('Data Machine Logger Warning: Log directory is not writable: ' . $log_dir);
            }

            $log_file = $log_dir . '/data-machine.log';

            // Create a logger channel
            $this->monolog_instance = new MonologLogger('DataMachine');

            try {
                // Create a handler (writing to a file)
                $handler = new StreamHandler($log_file, Level::Debug); // Log from DEBUG level up

                // Optional: Customize log format
                $formatter = new LineFormatter(
                    "[%datetime%] [%channel%.%level_name%]: %message% %context% %extra%\n",
                    "Y-m-d\TH:i:s.uP", // ISO 8601 format
                    true, // Allow inline line breaks
                    true  // Ignore empty context/extra
                );
                $handler->setFormatter($formatter);

                // Push the handler
                $this->monolog_instance->pushHandler($handler);

            } catch (\Exception $e) {
                 error_log('Data Machine Logger Error: Failed to initialize StreamHandler: ' . $e->getMessage());
                 // Fallback to ErrorLogHandler
                 $this->monolog_instance = new MonologLogger('DataMachineFallback');
                 $this->monolog_instance->pushHandler(new ErrorLogHandler());
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
             error_log('Data Machine Logger Failure: ' . $e->getMessage() . ' | Original Message: ' . $message);
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
                     echo '<pre style="margin-left: 2em; font-size: 0.9em;">' . esc_html( print_r( $notice['details'], true ) ) . '</pre>';
                }
            }
        }
    }

} // End class 