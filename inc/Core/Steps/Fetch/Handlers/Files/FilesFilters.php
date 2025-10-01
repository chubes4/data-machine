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
function dm_register_files_fetch_filters() {
    add_filter('dm_handlers_uncached', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $handlers['files'] = [
                'type' => 'fetch',
                'class' => Files::class,
                'label' => __('Files', 'data-machine'),
                'description' => __('Process local files and uploads', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);

    add_filter('dm_handler_settings', function($all_settings, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $all_settings['files'] = new FilesSettings();
        }
        return $all_settings;
    }, 10, 2);

    add_filter('dm_render_template', function($content, $template_name, $data = []) {
        if ($template_name === 'modal/handler-settings/files') {
            $template_path = dirname(__DIR__, 4) . '/admin/pages/pipelines/templates/modal/handler-settings/files.php';
            if (file_exists($template_path)) {
                $context = $data;
                ob_start();
                include $template_path;
                return ob_get_clean();
            }
        }
        return $content;
    }, 10, 3);
}

dm_register_files_fetch_filters();