<?php
/**
 * Defines constants for the Data Machine plugin.
 *
 * @package Data_Machine
 */

namespace DataMachine\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if (!class_exists('DataMachine\Constants')) {
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
         * The name of the constant used to define a custom encryption key in wp-config.php.
         */
        const ENCRYPTION_KEY_CONSTANT_NAME = 'DM_ENCRYPTION_KEY';

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
         * Prefers the DM_ENCRYPTION_KEY constant if defined, otherwise uses AUTH_KEY as a fallback.
         * The key is hashed using SHA256 and truncated to ensure it is 32 bytes long.
         *
         * @return string The 32-byte encryption key.
         */
        public static function get_encryption_key(): string {
            static $cached_key = null;
            
            // Cache the key to avoid repeated computation
            if ($cached_key !== null) {
                return $cached_key;
            }
            
            $constant_name = self::ENCRYPTION_KEY_CONSTANT_NAME;
            $raw_key = '';

            // Priority order: Custom constant > AUTH_KEY > wp_salt fallback
            if (defined($constant_name) && !empty(constant($constant_name))) {
                $raw_key = constant($constant_name);
            } elseif (defined('AUTH_KEY') && !empty(AUTH_KEY)) {
                $raw_key = AUTH_KEY;
            } else {
                // Final fallback - use wp_salt with site-specific data
                $raw_key = wp_salt('auth') . get_option('siteurl', 'fallback_site');
                
                // Log warning in debug mode only
                if (WP_DEBUG) {
                    error_log('Data Machine: Using fallback encryption key. Consider defining DM_ENCRYPTION_KEY in wp-config.php');
                }
            }

            if (empty($raw_key)) {
                // Extremely rare case - create deterministic fallback
                $raw_key = 'dm_fallback_' . get_option('siteurl', 'localhost') . '_key';
                
                if (WP_DEBUG) {
                    error_log('Data Machine: Emergency fallback encryption key used. This is insecure.');
                }
            }

            // Ensure the key is exactly 32 bytes for AES-256 using SHA256 hash
            $cached_key = hash('sha256', $raw_key, true);
            return $cached_key;
        }

        // --- Handler Registry Helper Methods ---


        /**
         * Get all registered input handlers via direct filter.
         * Uses dm_register_input_handlers filter for complete independence.
         *
         * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
         */
        public static function get_input_handlers(): array {
            $default_input_handlers = [];
            $handlers = apply_filters('dm_register_input_handlers', $default_input_handlers);
            
            // Debug logging in development mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Data Machine: Direct input handlers registered: ' . count($handlers));
            }
            
            return $handlers;
        }

        /**
         * Get all registered output handlers via direct filter.
         * Uses dm_register_output_handlers filter for complete independence.
         *
         * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
         */
        public static function get_output_handlers(): array {
            $default_output_handlers = [];
            $handlers = apply_filters('dm_register_output_handlers', $default_output_handlers);
            
            // Debug logging in development mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Data Machine: Direct output handlers registered: ' . count($handlers));
            }
            
            return $handlers;
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
    }
} 