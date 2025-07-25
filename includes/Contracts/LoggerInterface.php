<?php
/**
 * Logger Interface
 *
 * Defines the contract for logging services within the Data Machine plugin.
 * Enables dependency inversion and makes logging services mockable for testing.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/Contracts
 * @since      0.6.1
 */

namespace DataMachine\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

interface LoggerInterface {

    /**
     * Log an informational message.
     *
     * @param string $message The log message.
     * @param array $context Additional context data.
     * @return void
     */
    public function info(string $message, array $context = []): void;

    /**
     * Log an error message.
     *
     * @param string $message The error message.
     * @param array $context Additional context data.
     * @return void
     */
    public function error(string $message, array $context = []): void;

    /**
     * Log a warning message.
     *
     * @param string $message The warning message.
     * @param array $context Additional context data.
     * @return void
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Log a debug message.
     *
     * @param string $message The debug message.
     * @param array $context Additional context data.
     * @return void
     */
    public function debug(string $message, array $context = []): void;
}