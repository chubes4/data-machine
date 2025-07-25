<?php
/**
 * Defines constants for the Data Machine plugin.
 *
 * @package Data_Machine
 */

namespace DataMachine;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if (!class_exists('DataMachine\Constants')) {
    class Constants {

        /**
         * Allowed cron schedule intervals and their display names.
         * Used for validation, UI dropdowns, and scheduling logic.
         * Key: schedule slug
         * Value: array containing 'label' (display name) and 'interval' (seconds)
         */
        const CRON_SCHEDULES = [
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
         * Legacy AI model constants - these are deprecated.
         * AI models are now configured via the AI HTTP Client library 
         * through the admin interface per provider.
         */
        const AI_MODEL_INITIAL = ''; // Deprecated - models configured via AI HTTP Client
        const AI_MODEL_FACT_CHECK = ''; // Deprecated - models configured via AI HTTP Client  
        const AI_MODEL_FINALIZE = ''; // Deprecated - models configured via AI HTTP Client

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
            return array_keys(self::CRON_SCHEDULES);
        }

        /**
         * Get cron interval slugs allowed for Project-level scheduling.
         * Excludes intervals like 'every_5_minutes'.
         *
         * @return array
         */
        public static function get_project_cron_intervals(): array {
            $excluded = ['every_5_minutes'];
            return array_keys(array_diff_key(self::CRON_SCHEDULES, array_flip($excluded)));
        }

         /**
         * Get cron intervals allowed for Module-level scheduling (excluding project/manual).
         *
         * @return array
         */
        public static function get_module_cron_intervals(): array {
            // Currently all defined schedules are allowed for modules if not 'project_schedule' or 'manual'
            return array_keys(self::CRON_SCHEDULES);
        }

        /**
         * Get cron schedule intervals allowed for Module validation
         * (includes special values like 'project_schedule' and 'manual').
         *
         * @return array
         */
        public static function get_allowed_module_intervals_for_validation(): array {
            return array_merge(['project_schedule', 'manual'], array_keys(self::CRON_SCHEDULES));
        }

        /**
         * Get the display label for a given cron interval slug.
         *
         * @param string $interval The interval slug (e.g., 'daily').
         * @return string|null The display label or null if not found.
         */
        public static function get_cron_label(string $interval): ?string {
            return self::CRON_SCHEDULES[$interval]['label'] ?? null;
        }

        /**
         * Get the interval in seconds for a given cron interval slug.
         *
         * @param string $interval The interval slug (e.g., 'daily').
         * @return int|null The interval in seconds or null if not found.
         */
        public static function get_cron_interval_seconds(string $interval): ?int {
             return self::CRON_SCHEDULES[$interval]['interval'] ?? null;
        }

        /**
         * Get the CRON_SCHEDULES array suitable for wp_localize_script
         * (contains only label for UI purposes).
         *
         * @return array
         */
        public static function get_cron_schedules_for_js(): array {
            $js_schedules = [];
            foreach (self::CRON_SCHEDULES as $slug => $details) {
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
            $constant_name = self::ENCRYPTION_KEY_CONSTANT_NAME;
            $raw_key = '';

            if (defined($constant_name) && !empty(constant($constant_name))) {
                $raw_key = constant($constant_name);
            } elseif (defined(AUTH_KEY)) {
                $raw_key = AUTH_KEY;
            } else {
                // Final fallback if neither constant nor AUTH_KEY is defined
                // Using wp_salt here is okay as a last resort, but log an error.
                $raw_key = wp_salt();
                // Security warning logging removed for production
            }

            if (empty($raw_key)) {
                // This case should be rare if WP salts are configured and the constant isn't empty.
                // Security warning logging removed for production
                // Return a hash of a fallback string to avoid fatal errors, though encryption will be weak/predictable.
                $raw_key = 'fallback_key_for_empty_encryption_key';
            }

            // Ensure the key is exactly 32 bytes for AES-256 using SHA256 hash
            // The 'true' argument returns raw binary output.
            return hash('sha256', $raw_key, true);
        }

        // --- Handler Registry Helper Methods ---

        /**
         * Get all registered handlers via WordPress filter system.
         * Replaces HandlerRegistry functionality.
         *
         * @return array Array with 'input' and 'output' keys containing handler arrays.
         */
        public static function get_registered_handlers(): array {
            $default_handlers = [
                'input' => [],
                'output' => []
            ];
            
            return apply_filters('dm_register_handlers', $default_handlers);
        }

        /**
         * Get all registered input handlers.
         *
         * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
         */
        public static function get_input_handlers(): array {
            $handlers = self::get_registered_handlers();
            return $handlers['input'] ?? [];
        }

        /**
         * Get all registered output handlers.
         *
         * @return array Associative array of [slug => ['class' => ClassName, 'label' => Label]].
         */
        public static function get_output_handlers(): array {
            $handlers = self::get_registered_handlers();
            return $handlers['output'] ?? [];
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