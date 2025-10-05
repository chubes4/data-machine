<?php
/**
 * @package DataMachine\Core\Steps\Fetch\Handlers\GoogleSheets
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\GoogleSheets;

if (!defined('ABSPATH')) {
    exit;
}

function dm_register_googlesheets_fetch_filters() {
    add_filter('dm_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $handlers['googlesheets_fetch'] = [
                'type' => 'fetch',
                'class' => GoogleSheetsFetch::class,
                'label' => __('Google Sheets', 'data-machine'),
                'description' => __('Read data from Google Sheets spreadsheets', 'data-machine'),
                'requires_auth' => true
            ];
        }
        return $handlers;
    }, 10, 2);

    add_filter('dm_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'googlesheets_fetch') {
            $all_settings['googlesheets_fetch'] = new GoogleSheetsFetchSettings();
        }
        return $all_settings;
    }, 10, 2);

    add_filter('dm_auth_providers', function($providers, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $providers['googlesheets'] = new \DataMachine\Core\Steps\Publish\Handlers\GoogleSheets\GoogleSheetsAuth();
        }
        return $providers;
    }, 10, 2);

}

dm_register_googlesheets_fetch_filters();