<?php
/**
 * Defines constants for the Data Machine plugin.
 *
 * @package Data_Machine
 */

if (!class_exists('Data_Machine_Constants')) {
    class Data_Machine_Constants {

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
         * Default AI model for the initial data processing/generation step.
         */
        const AI_MODEL_INITIAL = 'gpt-4.1-mini';

        /**
         * Default AI model for the fact-checking step.
         */
        const AI_MODEL_FACT_CHECK = 'gpt-4o-mini';

        /**
         * Default AI model for the finalization/refinement step.
         */
        const AI_MODEL_FINALIZE = 'gpt-4.1-mini';

        /**
         * The name of the constant used to define a custom encryption key in wp-config.php.
         */
        const ENCRYPTION_KEY_CONSTANT_NAME = 'DM_ENCRYPTION_KEY';

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
                error_log('Data Machine Security Warning: Required encryption key constant (' . $constant_name . ') is not defined or is empty in wp-config.php. Using wp_salt as a fallback. Encryption will be weak/predictable.');
            }

            if (empty($raw_key)) {
                // This case should be rare if WP salts are configured and the constant isn't empty.
                error_log('Data Machine Security Warning: Encrypted data will be weak/predictable. Encryption key is empty.');
                // Return a hash of a fallback string to avoid fatal errors, though encryption will be weak/predictable.
                $raw_key = 'fallback_key_for_empty_encryption_key';
            }

            // Ensure the key is exactly 32 bytes for AES-256 using SHA256 hash
            // The 'true' argument returns raw binary output.
            return hash('sha256', $raw_key, true);
        }
    }
} 