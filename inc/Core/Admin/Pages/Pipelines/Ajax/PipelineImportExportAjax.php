<?php
/**
 * Pipeline Import/Export AJAX Handler
 *
 * Handles pipeline import and export AJAX operations.
 * Centralizes CSV import/export functionality with delegation to engine actions.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Ajax
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Ajax;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelineImportExportAjax
{
    /**
     * Register pipeline import/export AJAX handlers.
     */
    public static function register() {
        $instance = new self();

        // Import/Export AJAX actions
        add_action('wp_ajax_dm_export_pipelines', [$instance, 'handle_export_pipelines']);
        add_action('wp_ajax_dm_import_pipelines', [$instance, 'handle_import_pipelines']);
    }

    /**
     * Export selected pipelines
     */
    public function handle_export_pipelines() {
        // Security checks
        if (!check_ajax_referer('dm_ajax_actions', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $pipeline_ids = json_decode(sanitize_textarea_field(wp_unslash($_POST['pipeline_ids'] ?? '[]')), true);

        if (empty($pipeline_ids)) {
            wp_send_json_error(['message' => __('No pipelines selected', 'data-machine')]);
        }

        // Trigger export action
        do_action('dm_export', 'pipelines', $pipeline_ids);

        // Get result via filter
        $csv_content = apply_filters('dm_export_result', null);

        if ($csv_content) {
            wp_send_json_success(['csv' => $csv_content]);
        } else {
            wp_send_json_error(['message' => __('Export failed', 'data-machine')]);
        }
    }

    /**
     * Import pipelines from CSV
     */
    public function handle_import_pipelines() {
        // Security checks
        if (!check_ajax_referer('dm_ajax_actions', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $csv_content = sanitize_textarea_field(wp_unslash($_POST['csv_content'] ?? ''));

        if (empty($csv_content)) {
            wp_send_json_error(['message' => __('No CSV content provided', 'data-machine')]);
        }

        // Trigger import action
        do_action('dm_import', 'pipelines', $csv_content);

        // Get result via filter
        $result = apply_filters('dm_import_result', null);

        if ($result && isset($result['imported'])) {
            wp_send_json_success([
                /* translators: %d: Number of imported pipelines */
                'message' => sprintf(__('Successfully imported %d pipelines', 'data-machine'), count($result['imported'])),
                'pipeline_ids' => $result['imported']
            ]);
        } else {
            wp_send_json_error(['message' => __('Import failed', 'data-machine')]);
        }
    }
}