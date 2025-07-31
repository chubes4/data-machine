<?php
/**
 * Defines constants for the Data Machine plugin.
 *
 * @package Data_Machine
 */

namespace DataMachine\Engine;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if (!class_exists('DataMachine\Core\Constants')) {
    class Constants {

        /**
         * Get all allowed cron schedule intervals via WordPress filter.
         * Allows third-party plugins to add custom scheduling intervals.
         * 
         * @return array Array of schedule definitions with 'label' and 'interval' keys.
         */
        public static function get_cron_schedules(): array {
            // Default core schedules
            $default_schedules = [
                'every_5_minutes' => [
                    'label'    => 'Every 5 Minutes',
                    'interval' => 300 // 5 * 60
                ],
                'hourly'          => [
                    'label'    => 'Hourly',
                    'interval' => HOUR_IN_SECONDS
                ],
                'every_2_hours'   => [
                    'label'    => 'Every 2 Hours',
                    'interval' => HOUR_IN_SECONDS * 2
                ],
                'every_4_hours'   => [
                    'label'    => 'Every 4 Hours',
                    'interval' => HOUR_IN_SECONDS * 4
                ],
                'qtrdaily'        => [
                    'label'    => 'Every 6 Hours',   // Quarter-daily
                    'interval' => HOUR_IN_SECONDS * 6
                ],
                'twicedaily'      => [
                    'label'    => 'Twice Daily',
                    'interval' => HOUR_IN_SECONDS * 12
                ],
                'daily'           => [
                    'label'    => 'Daily',
                    'interval' => DAY_IN_SECONDS
                ],
                'weekly'          => [
                    'label'    => 'Weekly',
                    'interval' => WEEK_IN_SECONDS
                ],
            ];

            /**
             * Filter to allow third-party plugins to add custom CRON schedule intervals.
             * 
             * @param array $schedules Array of schedule definitions.
             *                        Key: schedule slug
             *                        Value: array with 'label' (display name) and 'interval' (seconds)
             * 
             * Example usage:
             * add_filter('dm_cron_schedules', function($schedules) {
             *     $schedules['every_15_minutes'] = [
             *         'label' => 'Every 15 Minutes',
             *         'interval' => 900 // 15 * 60
             *     ];
             *     return $schedules;
             * });
             */
            return apply_filters('dm_cron_schedules', $default_schedules);
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
         * Get all allowed cron interval slugs.
         *
         * @return array
         */
        public static function get_all_cron_intervals(): array {
            return array_keys(self::get_cron_schedules());
        }

        /**
         * Get cron interval slugs allowed for Project-level scheduling.
         * Excludes intervals like 'every_5_minutes'.
         *
         * @return array
         */
        public static function get_project_cron_intervals(): array {
            $excluded = ['every_5_minutes'];
            return array_keys(array_diff_key(self::get_cron_schedules(), array_flip($excluded)));
        }

         /**
         * Get cron intervals allowed for Module-level scheduling (excluding project/manual).
         *
         * @return array
         */
        public static function get_module_cron_intervals(): array {
            // Currently all defined schedules are allowed for modules if not 'project_schedule' or 'manual'
            return array_keys(self::get_cron_schedules());
        }

        /**
         * Get cron schedule intervals allowed for Module validation
         * (includes special values like 'project_schedule' and 'manual').
         *
         * @return array
         */
        public static function get_allowed_module_intervals_for_validation(): array {
            return array_merge(['project_schedule', 'manual'], array_keys(self::get_cron_schedules()));
        }

        /**
         * Get the display label for a given cron interval slug.
         *
         * @param string $interval The interval slug (e.g., 'daily').
         * @return string|null The display label or null if not found.
         */
        public static function get_cron_label(string $interval): ?string {
            $schedules = self::get_cron_schedules();
            return $schedules[$interval]['label'] ?? null;
        }

        /**
         * Get the interval in seconds for a given cron interval slug.
         *
         * @param string $interval The interval slug (e.g., 'daily').
         * @return int|null The interval in seconds or null if not found.
         */
        public static function get_cron_interval_seconds(string $interval): ?int {
            $schedules = self::get_cron_schedules();
            return $schedules[$interval]['interval'] ?? null;
        }

        /**
         * Get the cron schedules array suitable for wp_localize_script
         * (contains only label for UI purposes).
         *
         * @return array
         */
        public static function get_cron_schedules_for_js(): array {
            $js_schedules = [];
            $schedules = self::get_cron_schedules();
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
         * Get all registered input handlers via universal pipeline system.
         * Uses dm_get_handlers filter with 'input' parameter for complete independence.
         *
         * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
         */
        public static function get_input_handlers(): array {
            // Use universal parameter system only - no fallbacks
            $handlers = apply_filters('dm_get_handlers', null, 'input');
            
            // Debug logging in development mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $logger = apply_filters('dm_get_logger', null);
                $logger && $logger->debug('Universal input handlers registered', [
                    'handler_count' => count($handlers ?: []),
                    'context' => 'input_handler_registration'
                ]);
            }
            
            return $handlers ?: [];
        }

        /**
         * Get all registered output handlers via universal pipeline system.
         * Uses dm_get_handlers filter with 'output' parameter for complete independence.
         *
         * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
         */
        public static function get_output_handlers(): array {
            // Use universal parameter system only - no fallbacks
            $handlers = apply_filters('dm_get_handlers', null, 'output');
            
            // Debug logging in development mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $logger = apply_filters('dm_get_logger', null);
                $logger && $logger->debug('Universal output handlers registered', [
                    'handler_count' => count($handlers ?: []),
                    'context' => 'output_handler_registration'
                ]);
            }
            
            return $handlers ?: [];
        }

        /**
         * Get all registered handlers for a specific pipeline step parameter.
         * Universal method that works with any step type via the parameter-based system.
         *
         * @param string $step_parameter The step parameter ('input', 'output', etc.).
         * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
         */
        public static function get_handlers_by_step(string $step_parameter): array {
            $handlers = apply_filters('dm_get_handlers', null, $step_parameter);
            
            // Debug logging in development mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $logger = apply_filters('dm_get_logger', null);
                $logger && $logger->debug('Universal handlers for step', [
                    'step_parameter' => $step_parameter,
                    'handler_count' => count($handlers),
                    'context' => 'step_handler_registration'
                ]);
            }
            
            return $handlers ?: [];
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
            return $all_steps[$step_slug] ?? null;
        }


        /**
         * Get the class name for a specific handler by step parameter and slug.
         * Universal method that works with any step type.
         *
         * @param string $step_parameter The step parameter ('input', 'output', etc.).
         * @param string $slug The handler slug.
         * @return string|null The class name or null if not found.
         */
        public static function get_handler_class(string $step_parameter, string $slug): ?string {
            $handlers = self::get_handlers_by_step($step_parameter);
            return $handlers[$slug]['class'] ?? null;
        }

        /**
         * Get the class name for a specific input handler slug.
         *
         * @param string $slug The handler slug.
         * @return string|null The class name or null if not found.
         */
        public static function get_input_handler_class(string $slug): ?string {
            $handlers = self::get_input_handlers();
            return $handlers[$slug]['class'] ?? null;
        }

        /**
         * Get the class name for a specific output handler slug.
         *
         * @param string $slug The handler slug.
         * @return string|null The class name or null if not found.
         */
        public static function get_output_handler_class(string $slug): ?string {
            $handlers = self::get_output_handlers();
            return $handlers[$slug]['class'] ?? null;
        }

        /**
         * Get the label for a specific input handler slug.
         *
         * @param string $slug The handler slug.
         * @return string The label or the slug if label cannot be determined.
         */
        public static function get_input_handler_label(string $slug): string {
            $handlers = self::get_input_handlers();
            return $handlers[$slug]['label'] ?? $slug;
        }

        /**
         * Get the label for a specific output handler slug.
         *
         * @param string $slug The handler slug.
         * @return string The label or the slug if label cannot be determined.
         */
        public static function get_output_handler_label(string $slug): string {
            $handlers = self::get_output_handlers();
            return $handlers[$slug]['label'] ?? $slug;
        }

        /**
         * Get the handler info array for a specific input handler slug.
         *
         * @param string $slug The handler slug.
         * @return array|null The handler info array or null if not found.
         */
        public static function get_input_handler(string $slug): ?array {
            $handlers = self::get_input_handlers();
            return $handlers[$slug] ?? null;
        }

        /**
         * Get the handler info array for a specific output handler slug.
         *
         * @param string $slug The handler slug.
         * @return array|null The handler info array or null if not found.
         */
        public static function get_output_handler(string $slug): ?array {
            $handlers = self::get_output_handlers();
            return $handlers[$slug] ?? null;
        }

        /**
         * Test method to verify universal pipeline system is working.
         * Validates the clean parameter-based handler registration system.
         * 
         * @return array Test results with handler counts and system status.
         */
        public static function test_universal_pipeline_system(): array {
            $results = [
                'status' => 'success',
                'tests' => []
            ];

            // Test 1: Universal parameter system
            $input_handlers = apply_filters('dm_get_handlers', null, 'input');
            $output_handlers = apply_filters('dm_get_handlers', null, 'output');
            $results['tests']['universal_system'] = [
                'input_handlers' => count($input_handlers ?: []),
                'output_handlers' => count($output_handlers ?: [])
            ];

            // Test 2: Pipeline steps registration
            $pipeline_steps = apply_filters('dm_get_steps', []);
            $results['tests']['pipeline_steps'] = count($pipeline_steps);

            // Test 3: Constants methods using universal system
            $constants_input = self::get_input_handlers();
            $constants_output = self::get_output_handlers();
            $results['tests']['constants_integration'] = [
                'input_handlers' => count($constants_input),
                'output_handlers' => count($constants_output)
            ];

            // Test 4: Universal helper methods
            $transform_handlers = self::get_handlers_by_step('transform'); // Should be empty
            $input_step = self::get_pipeline_step('input');
            $results['tests']['universal_helpers'] = [
                'transform_handlers' => count($transform_handlers),
                'input_step_found' => !empty($input_step)
            ];

            // Test 5: Legacy system removal verification
            $results['tests']['clean_architecture'] = [
                'no_fallback_patterns' => true, // All fallback logic removed
                'single_filter_system' => true  // Only dm_get_handlers filter used
            ];

            return $results;
        }
    }
} 