<?php
/**
 * Files Fetch Handler Registration
 *
 * @package DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\Files
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Files;

use DataMachine\Core\Steps\HandlerRegistrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Files handler registration and configuration.
 *
 * Uses HandlerRegistrationTrait to provide standardized handler registration
 * for processing local files and uploads.
 *
 * @since 0.2.2
 */
class FilesFilters {
    use HandlerRegistrationTrait;

    /**
     * Register Files fetch handler with all required filters.
     */
    public static function register(): void {
        self::registerHandler(
            'files',
            'fetch',
            Files::class,
            __('Files', 'datamachine'),
            __('Process local files and uploads', 'datamachine'),
            false,
            null,
            FilesSettings::class,
            null
        );
    }
}

/**
 * Register files fetch handler filters.
 *
 * @since 0.1.0
 */
function datamachine_register_files_fetch_filters() {
    FilesFilters::register();
}

datamachine_register_files_fetch_filters();