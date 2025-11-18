<?php
/**
 * Files Fetch Handler Registration
 *
 * @package DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\Files
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Files;

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Register files fetch handler filters.
 */
function datamachine_register_files_fetch_filters() {
    add_filter('datamachine_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $handlers['files'] = [
                'type' => 'fetch',
                'class' => Files::class,
                'label' => __('Files', 'datamachine'),
                'description' => __('Process local files and uploads', 'datamachine')
            ];
        }
        return $handlers;
    }, 10, 2);

    add_filter('datamachine_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'files') {
            $all_settings['files'] = new FilesSettings();
        }
        return $all_settings;
    }, 10, 2);


}

datamachine_register_files_fetch_filters();