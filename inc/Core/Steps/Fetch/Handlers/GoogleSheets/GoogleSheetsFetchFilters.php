<?php
/**
 * Google Sheets Fetch Handler Registration
 *
 * @package DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\GoogleSheets
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\GoogleSheets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Google Sheets fetch handler filters.
 */
function dm_register_googlesheets_fetch_filters() {
    add_filter('dm_handlers', function($handlers) {
        $handlers['googlesheets_fetch'] = [
            'type' => 'fetch',
            'class' => GoogleSheetsFetch::class,
            'label' => __('Google Sheets', 'data-machine'),
            'description' => __('Read data from Google Sheets spreadsheets', 'data-machine')
        ];
        return $handlers;
    });

    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['googlesheets_fetch'] = new GoogleSheetsFetchSettings();
        return $all_settings;
    });

    add_filter('dm_auth_providers', function($providers) {
        $providers['googlesheets'] = new \DataMachine\Core\Steps\Publish\Handlers\GoogleSheets\GoogleSheetsAuth();
        return $providers;
    });
    
}

dm_register_googlesheets_fetch_filters();