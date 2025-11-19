<?php
/**
 * @package DataMachine\Core\Steps\Fetch\Handlers\GoogleSheets
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\GoogleSheets;

use DataMachine\Core\Steps\HandlerRegistrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Google Sheets fetch handler registration and configuration.
 *
 * Uses HandlerRegistrationTrait to provide standardized handler registration
 * for reading data from Google Sheets spreadsheets.
 *
 * @since 0.2.2
 */
class GoogleSheetsFetchFilters {
    use HandlerRegistrationTrait;

    /**
     * Register Google Sheets fetch handler with all required filters.
     */
    public static function register(): void {
        self::registerHandler(
            'googlesheets_fetch',
            'fetch',
            GoogleSheetsFetch::class,
            __('Google Sheets', 'datamachine'),
            __('Read data from Google Sheets spreadsheets', 'datamachine'),
            true,
            \DataMachine\Core\Steps\Publish\Handlers\GoogleSheets\GoogleSheetsAuth::class,
            GoogleSheetsFetchSettings::class,
            null
        );
    }
}

/**
 * Register Google Sheets fetch handler filters.
 *
 * @since 0.1.0
 */
function datamachine_register_googlesheets_fetch_filters() {
    GoogleSheetsFetchFilters::register();
}

datamachine_register_googlesheets_fetch_filters();