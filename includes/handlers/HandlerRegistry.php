<?php
/**
 * Central registry for discovering and managing input and output handlers.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes
 * @since      NEXT_VERSION
 */

namespace DataMachine\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class HandlerRegistry {

    /**
     * Registered input handlers.
     * @var array
     */
    private $input_handlers = [];

    /**
     * Registered output handlers.
     * @var array
     */
    private $output_handlers = [];

    /**
     * Constructor.
     * 
     * Initializes handlers via WordPress filter system instead of filesystem scanning.
     */
    public function __construct() {
        // Initialize empty arrays
        $default_handlers = [
            'input' => [],
            'output' => []
        ];
        
        // Apply WordPress filter to allow registration of handlers
        $registered_handlers = apply_filters('dm_register_handlers', $default_handlers);
        
        // Store registered handlers
        $this->input_handlers = $registered_handlers['input'] ?? [];
        $this->output_handlers = $registered_handlers['output'] ?? [];
    }

    /**
     * Gets all registered input handlers.
     *
     * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
     */
    public function get_input_handlers() {
        return $this->input_handlers;
    }

    /**
     * Gets all registered output handlers.
     *
     * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
     */
    public function get_output_handlers() {
        return $this->output_handlers;
    }

    /**
     * Gets the class name for a specific input handler slug.
     *
     * @param string $slug The handler slug.
     * @return string|null The class name or null if not found.
     */
    public function get_input_handler_class($slug) {
        $handlers = $this->get_input_handlers();
        return $handlers[$slug]['class'] ?? null;
    }

    /**
     * Gets the class name for a specific output handler slug.
     *
     * @param string $slug The handler slug.
     * @return string|null The class name or null if not found.
     */
    public function get_output_handler_class($slug) {
        $handlers = $this->get_output_handlers();
        return $handlers[$slug]['class'] ?? null;
    }

    /**
     * Gets the label for a specific input handler slug.
     *
     * @param string $slug The handler slug.
     * @return string The label or the slug if label cannot be determined.
     */
    public function get_input_handler_label($slug) {
        $handlers = $this->get_input_handlers();
        return $handlers[$slug]['label'] ?? $slug;
    }

    /**
     * Gets the label for a specific output handler slug.
     *
     * @param string $slug The handler slug.
     * @return string The label or the slug if label cannot be determined.
     */
    public function get_output_handler_label($slug) {
        $handlers = $this->get_output_handlers();
        return $handlers[$slug]['label'] ?? $slug;
    }

    /**
     * Gets the handler info array for a specific input handler slug.
     *
     * @param string $slug The handler slug.
     * @return array|null The handler info array or null if not found.
     */
    public function get_input_handler($slug) {
        $handlers = $this->get_input_handlers();
        return $handlers[$slug] ?? null;
    }

    /**
     * Gets the handler info array for a specific output handler slug.
     *
     * @param string $slug The handler slug.
     * @return array|null The handler info array or null if not found.
     */
    public function get_output_handler($slug) {
        $handlers = $this->get_output_handlers();
        return $handlers[$slug] ?? null;
    }
} 