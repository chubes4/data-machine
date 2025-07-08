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
     * @return array Associative array of [slug => ['class' => ClassName]].
     */
    private function scan_directory($sub_directory, $type) {
        $handlers = [];
        $directory = $this->plugin_path . 'includes/' . $sub_directory . '/';
        $pattern = $directory . 'class-data-machine-' . $type . '-*.php';

        $files = glob($pattern);
        if ($files === false) {
            // Handle error - glob() failed
        } else {
            // Optional: Log if no files found
        }

        foreach ($files as $file) {
            $filename = basename($file, '.php');
            // Expected format: class-data-machine-input-handler-slug or class-data-machine-output-handler-slug
            if (preg_match('/^class-data-machine-' . $type . '-([a-z0-9_-]+)$/', $filename, $matches)) {
                $slug_from_file = $matches[1];
                // Ensure the internal registry key always uses underscores.
                $slug = str_replace('-', '_', $slug_from_file); 
                // Construct class name using the filename directly (assuming it matches the class definition)
                // Note: Class name generation already correctly handles hyphens in the original filename.
                $class_name = str_replace('-', '_', ucwords(str_replace('class-', '', $filename), '-'));

                if (!class_exists($class_name)) {
                    require_once $file;
                }

                if (class_exists($class_name)) {
                    // Store only the class name, defer label retrieval
                    $handlers[$slug] = [
                        'class' => $class_name
                        // Removed label retrieval from here
                    ];
                } else {
                    // Log error: class not found after include
                }
            } else {
                 // Log error: filename pattern mismatch
            }
        }
        return $handlers;
    }

    /**
     * Gets all registered input handlers.
     *
     * @param bool $force_rediscover Force rediscovery even if cached.
     * @return array Associative array of [slug => ['class' => ClassName]].
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
     * @return array Associative array of [slug => ['class' => ClassName]].
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
     * @return string|null The label or the slug if label cannot be determined.
     */
    public function get_input_handler_label($slug) {
        $handlers = $this->get_input_handlers();
        if (isset($handlers[$slug]['class'])) {
            $class_name = $handlers[$slug]['class'];
            if (class_exists($class_name) && method_exists($class_name, 'get_label')) {
                // Call get_label dynamically when requested
                 // Ensure WordPress translation functions are ready now
                 if (did_action('init')) {
                    return call_user_func([$class_name, 'get_label']);
                 } else {
                     // Return slug if called before init (should ideally not happen for labels)
                     return $slug;
                 }
            }
        }
        return $slug; // Fallback to slug
    }

    /**
     * Gets the label for a specific output handler slug.
     *
     * @param string $slug The handler slug.
     * @return string|null The label or the slug if label cannot be determined.
     */
    public function get_output_handler_label($slug) {
        $handlers = $this->get_output_handlers();
         if (isset($handlers[$slug]['class'])) {
            $class_name = $handlers[$slug]['class'];
            if (class_exists($class_name) && method_exists($class_name, 'get_label')) {
                 // Call get_label dynamically when requested
                 // Ensure WordPress translation functions are ready now
                 if (did_action('init')) {
                    return call_user_func([$class_name, 'get_label']);
                 } else {
                     // Return slug if called before init (should ideally not happen for labels)
                     return $slug;
                 }
            }
        }
        return $slug; // Fallback to slug
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