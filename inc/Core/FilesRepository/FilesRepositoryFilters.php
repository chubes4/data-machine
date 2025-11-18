<?php
/**
 * Files Repository service discovery and registration.
 *
 * Self-registration following established WordPress filter-based architecture.
 * Provides filter-based access to all FilesRepository utilities.
 *
 * @package DataMachine\Core\FilesRepository
 */

namespace DataMachine\Core\FilesRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register FilesRepository utilities for filter-based service discovery
 */
function datamachine_register_files_repository_filters() {
    // Register DirectoryManager
    add_filter('datamachine_get_directory_manager', function() {
        return new DirectoryManager();
    });

    // Register FileStorage
    add_filter('datamachine_get_file_storage', function() {
        return new FileStorage();
    });

    // Register RemoteFileDownloader
    add_filter('datamachine_get_remote_downloader', function() {
        return new RemoteFileDownloader();
    });

    // Register FileCleanup
    add_filter('datamachine_get_file_cleanup', function() {
        return new FileCleanup();
    });

    // Register cleanup action
    add_action('datamachine_cleanup_old_files', function() {
        $file_cleanup = apply_filters('datamachine_get_file_cleanup', null);

        if ($file_cleanup) {
            $settings = datamachine_get_datamachine_settings();
            $retention_days = $settings['file_retention_days'] ?? 7;

            $deleted_count = $file_cleanup->cleanup_old_files($retention_days);

            do_action('datamachine_log', 'debug', 'FilesRepository: Cleanup completed', [
                'files_deleted' => $deleted_count,
                'retention_days' => $retention_days
            ]);
        }
    });

    // Schedule cleanup on WordPress init
    add_action('init', function() {
        if (datamachine_files_should_schedule_cleanup() && !as_next_scheduled_action('datamachine_cleanup_old_files')) {
            as_schedule_recurring_action(
                time() + WEEK_IN_SECONDS,
                WEEK_IN_SECONDS,
                'datamachine_cleanup_old_files',
                [],
                'datamachine-files'
            );
        }
    });
}

/**
 * Check if cleanup should be scheduled
 *
 * @return bool True if cleanup should be scheduled
 */
function datamachine_files_should_schedule_cleanup(): bool {
    return true;
}

// Auto-execute registration
datamachine_register_files_repository_filters();
