<?php
/**
 * Central registry for discovering and managing input and output handlers.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes
 * @since      NEXT_VERSION
 */

class Data_Machine_Handler_Registry {

    /**
     * Cached list of discovered input handlers.
     * @var array|null
     */
    private $input_handlers = null;

    /**
     * Cached list of discovered output handlers.
     * @var array|null
     */
    private $output_handlers = null;

    /**
     * Plugin base path.
     * @var string
     */
    private $plugin_path;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->plugin_path = DATA_MACHINE_PATH;
        // Optionally trigger discovery during instantiation, or lazily load
        $this->discover_handlers(); 
    }

    /**
     * Scans the handler directories and populates the handler lists.
     */
    private function discover_handlers() {
        $this->input_handlers = $this->scan_directory('input', 'input');
        $this->output_handlers = $this->scan_directory('output', 'output');
    }

    /**
     * Scans a specific directory for handler files.
     *
     * @param string $sub_directory The sub-directory within includes (e.g., 'input').
     * @param string $type 'input' or 'output'.
     * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
     */
    private function scan_directory($sub_directory, $type) {
        $handlers = [];
        $directory = $this->plugin_path . 'includes/' . $sub_directory . '/';
        $pattern = $directory . 'class-data-machine-' . $type . '-*.php';
        error_log("ADC Registry Scan: Type='$type', Pattern='$pattern'"); // DEBUG

        $files = glob($pattern);
        if ($files === false) {
             error_log("ADC Registry Scan: glob() returned false for pattern: $pattern"); // DEBUG
        } else {
             error_log("ADC Registry Scan: Type='$type', Files Found: " . print_r($files, true)); // DEBUG
        }

        foreach ($files as $file) {
            $filename = basename($file, '.php');
            error_log("ADC Registry Scan: Processing File: $file | Filename: $filename"); // DEBUG
            // Expected format: class-data-machine-input-handler-slug or class-data-machine-output-handler-slug
            if (preg_match('/^class-data-machine-' . $type . '-([a-z0-9_-]+)$/', $filename, $matches)) {
                $slug = $matches[1];
                // Construct class name using the filename directly (assuming it matches the class definition)
                $class_name = str_replace('-', '_', ucwords(str_replace('class-', '', $filename), '-'));
                error_log("ADC Registry Scan: Type='$type', Slug='$slug', Constructed Class='$class_name'"); // DEBUG

                if (!class_exists($class_name)) {
                    error_log("ADC Registry Scan: Class '$class_name' not found initially. Including file: $file"); // DEBUG
                    require_once $file;
                }

                if (class_exists($class_name)) {
                    error_log("ADC Registry Scan: Class '$class_name' exists after include."); // DEBUG
                    $label = $slug; // Default label is the slug
                    if (method_exists($class_name, 'get_label')) {
                        $label = call_user_func([$class_name, 'get_label']);
                    }
                    $handlers[$slug] = [
                        'class' => $class_name,
                        'label' => $label
                    ];
                     error_log("ADC Registry Scan: Successfully added handler: Type='$type', Slug='$slug'"); // DEBUG
                } else {
                    // Log error: class not found after include
                    error_log("ADC Registry Scan Error: Class {$class_name} not found in file {$file} AFTER include.");
                }
            } else {
                 error_log("ADC Registry Scan: Filename '$filename' did not match regex for type '$type'"); // DEBUG
            }
        }
        error_log("ADC Registry Scan: Completed scan for type '$type'. Handlers found: " . count($handlers)); // DEBUG
        return $handlers;
    }

    /**
     * Gets all registered input handlers.
     *
     * @param bool $force_rediscover Force rediscovery even if cached.
     * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
     */
    public function get_input_handlers($force_rediscover = false) {
        if ($this->input_handlers === null || $force_rediscover) {
            $this->discover_handlers();
        }
        return $this->input_handlers ?? [];
    }

    /**
     * Gets all registered output handlers.
     *
     * @param bool $force_rediscover Force rediscovery even if cached.
     * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
     */
    public function get_output_handlers($force_rediscover = false) {
        if ($this->output_handlers === null || $force_rediscover) {
            $this->discover_handlers();
        }
        return $this->output_handlers ?? [];
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
     * @return string|null The label or null if not found.
     */
    public function get_input_handler_label($slug) {
        $handlers = $this->get_input_handlers();
        return $handlers[$slug]['label'] ?? null;
    }

    /**
     * Gets the label for a specific output handler slug.
     *
     * @param string $slug The handler slug.
     * @return string|null The label or null if not found.
     */
    public function get_output_handler_label($slug) {
        $handlers = $this->get_output_handlers();
        return $handlers[$slug]['label'] ?? null;
    }
} 