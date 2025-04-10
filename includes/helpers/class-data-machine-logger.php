<?php
/**
 * Handles logging for the Data Machine plugin.
 *
 * Provides methods for logging to the PHP error log and managing admin notices.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes
 * @since      NEXT_VERSION
 */

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
     * Logs a message to the standard PHP error log.
     *
     * @since NEXT_VERSION
     *
     * @param string $level   Log level (e.g., 'ERROR', 'WARNING', 'INFO', 'DEBUG').
     * @param string $message The message to log.
     * @param array  $context Optional context data.
     */
    public function log( $level, $message, $context = [] ) {
        $formatted_message = sprintf(
            '[Data Machine][%s] %s', 
            strtoupper($level),
            $message
        );
        if (!empty($context)) {
             // Use print_r for context as it handles arrays/objects well for logging
            $formatted_message .= ' | Context: ' . print_r($context, true);
        }
        error_log($formatted_message);
    }

    /**
     * Logs an error message.
     *
     * @since NEXT_VERSION
     * @param string $message The error message.
     * @param array  $context Optional context data.
     */
    public function error( $message, $context = [] ) {
        $this->log( 'ERROR', $message, $context );
    }

    /**
     * Logs a warning message.
     *
     * @since NEXT_VERSION
     * @param string $message The warning message.
     * @param array  $context Optional context data.
     */
    public function warning( $message, $context = [] ) {
        $this->log( 'WARNING', $message, $context );
    }

    /**
     * Logs an informational message.
     *
     * @since NEXT_VERSION
     * @param string $message The info message.
     * @param array  $context Optional context data.
     */
    public function info( $message, $context = [] ) {
        $this->log( 'INFO', $message, $context );
    }

    /**
     * Logs a debug message (consider adding a setting to enable/disable debug logging).
     *
     * @since NEXT_VERSION
     * @param string $message The debug message.
     * @param array  $context Optional context data.
     */
    public function debug( $message, $context = [] ) {
        // TODO: Add check for a debug mode setting if implemented
        $this->log( 'DEBUG', $message, $context );
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
     * @since NEXT_VERSION
     *
     * @return array An array of notice arrays.
     */
    public function get_pending_notices() {
        $notices = get_transient( self::TRANSIENT_NOTICES );
        delete_transient( self::TRANSIENT_NOTICES );
        return is_array( $notices ) ? $notices : [];
    }
} 