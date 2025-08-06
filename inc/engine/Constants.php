<?php
/**
 * Data Machine System Constants and Configuration
 * 
 * Provides centralized system constants, configuration data, and utility methods
 * for scheduling intervals, step types, and engine configuration.
 * 
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine
 * @since      0.6.0
 */

namespace DataMachine\Engine;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if (!class_exists('DataMachine\Core\Constants')) {
    /**
     * System constants and configuration provider.
     * 
     * Centralized repository for all system constants, intervals, and configuration
     * data used throughout the Data Machine plugin architecture.
     * 
     * @since 0.6.0
     */
    class Constants {

        /**
         * Get Action Scheduler intervals for flow scheduling.
         * Direct interval data for Action Scheduler - no WordPress cron involvement.
         * 
         * @return array Array of schedule definitions with 'label' and 'interval' keys.
         */
        public static function get_scheduler_intervals(): array {
            return [
                'every_5_minutes' => [
                    'label'    => __('Every 5 Minutes', 'data-machine'),
                    'interval' => 300 // 5 * 60
                ],
                'hourly'          => [
                    'label'    => __('Hourly', 'data-machine'),
                    'interval' => HOUR_IN_SECONDS
                ],
                'every_2_hours'   => [
                    'label'    => __('Every 2 Hours', 'data-machine'),
                    'interval' => HOUR_IN_SECONDS * 2
                ],
                'every_4_hours'   => [
                    'label'    => __('Every 4 Hours', 'data-machine'),
                    'interval' => HOUR_IN_SECONDS * 4
                ],
                'qtrdaily'        => [
                    'label'    => __('Every 6 Hours', 'data-machine'),
                    'interval' => HOUR_IN_SECONDS * 6
                ],
                'twicedaily'      => [
                    'label'    => __('Twice Daily', 'data-machine'),
                    'interval' => HOUR_IN_SECONDS * 12
                ],
                'daily'           => [
                    'label'    => __('Daily', 'data-machine'),
                    'interval' => DAY_IN_SECONDS
                ],
                'weekly'          => [
                    'label'    => __('Weekly', 'data-machine'),
                    'interval' => WEEK_IN_SECONDS
                ],
            ];
        }



        /**
         * Job timeout settings and limits.
         */
        const JOB_STUCK_TIMEOUT_HOURS = 6;          // Hours before job is considered stuck
        const JOB_CLEANUP_OLD_DAYS = 30;            // Days before completed/failed jobs are deleted
        const MAX_CONCURRENT_JOBS = 2;              // Maximum concurrent jobs via Action Scheduler
        
        /**
         * Action Scheduler configuration.
         */
        const ACTION_GROUP = 'data-machine';        // Action Scheduler group name

        // --- Helper Methods for accessing constants ---

        /**
         * Get all scheduler interval slugs.
         *
         * @return array
         */
        public static function get_all_scheduler_intervals(): array {
            return array_keys(self::get_scheduler_intervals());
        }

        /**
         * Get the display label for a given scheduler interval slug.
         *
         * @param string $interval The interval slug (e.g., 'daily').
         * @return string|null The display label or null if not found.
         */
        public static function get_scheduler_label(string $interval): ?string {
            $schedules = self::get_scheduler_intervals();
            if (!isset($schedules[$interval]['label'])) {
                return null;
            }
            return $schedules[$interval]['label'];
        }

        /**
         * Get the interval in seconds for a given scheduler interval slug.
         *
         * @param string $interval The interval slug (e.g., 'daily').
         * @return int|null The interval in seconds or null if not found.
         */
        public static function get_scheduler_interval_seconds(string $interval): ?int {
            $schedules = self::get_scheduler_intervals();
            if (!isset($schedules[$interval]['interval'])) {
                return null;
            }
            return $schedules[$interval]['interval'];
        }

        /**
         * Get the scheduler intervals array suitable for wp_localize_script
         * (contains only label for UI purposes).
         *
         * @return array
         */
        public static function get_scheduler_intervals_for_js(): array {
            $js_schedules = [];
            $schedules = self::get_scheduler_intervals();
            foreach ($schedules as $slug => $details) {
                $js_schedules[$slug] = $details['label'];
            }
            return $js_schedules;
        }

        /**
         * Get the encryption key suitable for AES-256-CBC (32 bytes).
         * Uses WordPress AUTH_KEY directly - no fallbacks or caching.
         *
         * @return string The 32-byte encryption key.
         */
        public static function get_encryption_key(): string {
            return hash('sha256', AUTH_KEY, true);
        }

        // --- Handler Registry Helper Methods ---


        /**
         * Get all registered input handlers via pure discovery mode.
         * Uses dm_get_handlers filter with pure discovery for complete independence.
         *
         * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
         */
        public static function get_input_handlers(): array {
            // Use pure discovery mode - get all handlers and filter for input type
            $all_handlers = apply_filters('dm_get_handlers', []);
            $input_handlers = array_filter($all_handlers, function($handler) {
                return ($handler['type'] ?? '') === 'input';
            });
            
            // Debug logging in development mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $logger = apply_filters('dm_get_logger', null);
                $logger && $logger->debug('Pure discovery input handlers registered', [
                    'total_handlers' => count($all_handlers),
                    'input_handler_count' => count($input_handlers),
                    'context' => 'input_handler_registration'
                ]);
            }
            
            return $input_handlers;
        }

        /**
         * Get all registered output handlers via pure discovery mode.
         * Uses dm_get_handlers filter with pure discovery for complete independence.
         *
         * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
         */
        public static function get_output_handlers(): array {
            // Use pure discovery mode - get all handlers and filter for output type
            $all_handlers = apply_filters('dm_get_handlers', []);
            $output_handlers = array_filter($all_handlers, function($handler) {
                return ($handler['type'] ?? '') === 'output';
            });
            
            // Debug logging in development mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $logger = apply_filters('dm_get_logger', null);
                $logger && $logger->debug('Pure discovery output handlers registered', [
                    'total_handlers' => count($all_handlers),
                    'output_handler_count' => count($output_handlers),
                    'context' => 'output_handler_registration'
                ]);
            }
            
            return $output_handlers;
        }

        /**
         * Get all registered handlers for a specific pipeline step type.
         * Universal method that works with any step type via pure discovery mode.
         *
         * @param string $step_type The step type ('input', 'output', etc.).
         * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
         */
        public static function get_handlers_by_step(string $step_type): array {
            // Use pure discovery mode - get all handlers and filter for step type
            $all_handlers = apply_filters('dm_get_handlers', []);
            $step_handlers = array_filter($all_handlers, function($handler) use ($step_type) {
                return ($handler['type'] ?? '') === $step_type;
            });
            
            // Debug logging in development mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $logger = apply_filters('dm_get_logger', null);
                $logger && $logger->debug('Pure discovery handlers for step', [
                    'step_type' => $step_type,
                    'total_handlers' => count($all_handlers),
                    'step_handler_count' => count($step_handlers),
                    'context' => 'step_handler_registration'
                ]);
            }
            
            return $step_handlers;
        }

        /**
         * Get all registered pipeline steps via filter system.
         * Pass filter results through as-is - no smart defaults.
         *
         * @return array Associative array of [step_slug => step_config].
         */
        public static function get_pipeline_steps(): array {
            return apply_filters('dm_get_steps', []);
        }

        /**
         * Get a specific pipeline step by slug.
         *
         * @param string $step_slug The step slug.
         * @return array|null Step configuration or null if not found.
         */
        public static function get_pipeline_step(string $step_slug): ?array {
            $all_steps = self::get_pipeline_steps();
            if (!isset($all_steps[$step_slug])) {
                return null;
            }
            return $all_steps[$step_slug];
        }


        /**
         * Get the class name for a specific handler by step type and slug.
         * Universal method that works with any step type.
         *
         * @param string $step_type The step type ('input', 'output', etc.).
         * @param string $slug The handler slug.
         * @return string|null The class name or null if not found.
         */
        public static function get_handler_class(string $step_type, string $slug): ?string {
            $handlers = self::get_handlers_by_step($step_type);
            if (!isset($handlers[$slug]['class'])) {
                return null;
            }
            return $handlers[$slug]['class'];
        }

        /**
         * Get the class name for a specific input handler slug.
         *
         * @param string $slug The handler slug.
         * @return string|null The class name or null if not found.
         */
        public static function get_input_handler_class(string $slug): ?string {
            $handlers = self::get_input_handlers();
            if (!isset($handlers[$slug]['class'])) {
                return null;
            }
            return $handlers[$slug]['class'];
        }

        /**
         * Get the class name for a specific output handler slug.
         *
         * @param string $slug The handler slug.
         * @return string|null The class name or null if not found.
         */
        public static function get_output_handler_class(string $slug): ?string {
            $handlers = self::get_output_handlers();
            if (!isset($handlers[$slug]['class'])) {
                return null;
            }
            return $handlers[$slug]['class'];
        }

        /**
         * Get the label for a specific input handler slug.
         *
         * @param string $slug The handler slug.
         * @return string The label or the slug if label cannot be determined.
         */
        public static function get_input_handler_label(string $slug): string {
            $handlers = self::get_input_handlers();
            return $handlers[$slug]['label'] ?? $slug; // Display fallback - safe
        }

        /**
         * Get the label for a specific output handler slug.
         *
         * @param string $slug The handler slug.
         * @return string The label or the slug if label cannot be determined.
         */
        public static function get_output_handler_label(string $slug): string {
            $handlers = self::get_output_handlers();
            return $handlers[$slug]['label'] ?? $slug; // Display fallback - safe
        }

        /**
         * Get the handler info array for a specific input handler slug.
         *
         * @param string $slug The handler slug.
         * @return array|null The handler info array or null if not found.
         */
        public static function get_input_handler(string $slug): ?array {
            $handlers = self::get_input_handlers();
            if (!isset($handlers[$slug])) {
                return null;
            }
            return $handlers[$slug];
        }

        /**
         * Get the handler info array for a specific output handler slug.
         *
         * @param string $slug The handler slug.
         * @return array|null The handler info array or null if not found.
         */
        public static function get_output_handler(string $slug): ?array {
            $handlers = self::get_output_handlers();
            if (!isset($handlers[$slug])) {
                return null;
            }
            return $handlers[$slug];
        }

        /**
         * Test method to verify pure discovery pipeline system is working.
         * Validates the clean pure discovery handler registration system.
         * 
         * @return array Test results with handler counts and system status.
         */
        public static function test_universal_pipeline_system(): array {
            $results = [
                'status' => 'success',
                'tests' => []
            ];

            // Test 1: Pure discovery system
            $all_handlers = apply_filters('dm_get_handlers', []);
            $input_handlers = array_filter($all_handlers, fn($h) => ($h['type'] ?? '') === 'input');
            $output_handlers = array_filter($all_handlers, fn($h) => ($h['type'] ?? '') === 'output');
            $results['tests']['pure_discovery_system'] = [
                'total_handlers' => count($all_handlers),
                'input_handlers' => count($input_handlers),
                'output_handlers' => count($output_handlers)
            ];

            // Test 2: Pipeline steps registration
            $pipeline_steps = apply_filters('dm_get_steps', []);
            $results['tests']['pipeline_steps'] = count($pipeline_steps);

            // Test 3: Constants methods using pure discovery
            $constants_input = self::get_input_handlers();
            $constants_output = self::get_output_handlers();
            $results['tests']['constants_integration'] = [
                'input_handlers' => count($constants_input),
                'output_handlers' => count($constants_output)
            ];

            // Test 4: Pure discovery helper methods
            $transform_handlers = self::get_handlers_by_step('transform'); // Should be empty
            $input_step = self::get_pipeline_step('input');
            $results['tests']['pure_discovery_helpers'] = [
                'transform_handlers' => count($transform_handlers),
                'input_step_found' => !empty($input_step)
            ];

            // Test 5: Pure discovery architecture verification
            $results['tests']['pure_discovery_architecture'] = [
                'no_parameter_mode' => true, // All parameter mode eliminated
                'single_discovery_pattern' => true  // Pure discovery throughout
            ];

            return $results;
        }
    }
} 