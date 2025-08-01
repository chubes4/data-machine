<?php
/**
 * Data Machine Engine Testing Bootstrap
 *
 * Sets up the testing environment for Data Machine engine core testing.
 * Provides WordPress function mocks and loads necessary dependencies.
 *
 * @package DataMachine
 * @subpackage Tests
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../');
}

// Define plugin constants for testing
if (!defined('DATA_MACHINE_PATH')) {
    define('DATA_MACHINE_PATH', dirname(__FILE__) . '/../');
}

if (!defined('DATA_MACHINE_VERSION')) {
    define('DATA_MACHINE_VERSION', '1.0.0-test');
}

// Load Composer autoloader
require_once dirname(__FILE__) . '/../vendor/autoload.php';

/**
 * WordPress Function Mocks for Testing
 * 
 * These mock the essential WordPress functions needed for engine testing
 * without requiring a full WordPress installation.
 */

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return esc_html($text);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('apply_filters')) {
    /**
     * Mock WordPress apply_filters function for testing
     * 
     * This allows us to test the filter-based architecture in isolation.
     * During tests, we can register filters and verify they work properly.
     */
    function apply_filters($hook_name, $value, ...$args) {
        global $wp_filter;
        
        if (!isset($wp_filter[$hook_name])) {
            return $value;
        }
        
        foreach ($wp_filter[$hook_name] as $priority => $functions) {
            foreach ($functions as $function) {
                if (is_callable($function['function'])) {
                    $value = call_user_func_array($function['function'], array_merge([$value], $args));
                }
            }
        }
        
        return $value;
    }
}

if (!function_exists('add_filter')) {
    /**
     * Mock WordPress add_filter function for testing
     */
    function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        global $wp_filter;
        
        if (!isset($wp_filter[$hook_name])) {
            $wp_filter[$hook_name] = [];
        }
        
        if (!isset($wp_filter[$hook_name][$priority])) {
            $wp_filter[$hook_name][$priority] = [];
        }
        
        $wp_filter[$hook_name][$priority][] = [
            'function' => $callback,
            'accepted_args' => $accepted_args
        ];
        
        return true;
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        return new WP_Error('test_mode', 'HTTP requests disabled in test mode');
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        return new WP_Error('test_mode', 'HTTP requests disabled in test mode');
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return ($thing instanceof WP_Error);
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $test_options;
        return $test_options[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        global $test_options;
        $test_options[$option] = $value;
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        if ($type === 'timestamp') {
            return $gmt ? time() : time() + (get_option('gmt_offset') * HOUR_IN_SECONDS);
        }
        return date($type);
    }
}

if (!function_exists('gmdate')) {
    function gmdate($format, $timestamp = null) {
        return date($format, $timestamp ?: time());
    }
}

// Define WordPress constants for testing
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

/**
 * Simple WP_Error class for testing
 */
if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = [];
        public $error_data = [];
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }
        
        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            if (isset($this->errors[$code])) {
                return $this->errors[$code][0];
            }
            return '';
        }
        
        public function get_error_code() {
            $codes = array_keys($this->errors);
            return $codes[0] ?? '';
        }
    }
}

// Initialize global variables for testing
global $wp_filter, $test_options;
$wp_filter = [];
$test_options = [];

// Load plugin autoloader and core files
require_once dirname(__FILE__) . '/../data-machine.php';

// Load test utilities and mocks AFTER WordPress functions are defined
require_once __DIR__ . '/Mock/MockServices.php';
require_once __DIR__ . '/Mock/MockHandlers.php';
require_once __DIR__ . '/Mock/TestDataFixtures.php';

echo "Data Machine Engine Testing Bootstrap Loaded\n";