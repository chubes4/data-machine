<?php
/**
 * Logs Admin Page Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as the Logs Admin Page's "main plugin file" - the complete
 * interface contract with the engine, demonstrating complete self-containment
 * and zero bootstrap dependencies.
 * 
 * @package DataMachine
 * @subpackage Core\Admin\Pages\Logs
 * @since 0.1.0
 */

namespace DataMachine\Core\Admin\Pages\Logs;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Logs Admin Page component filters
 *
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Logs Admin Page capabilities purely through filter-based discovery.
 *
 * @since 0.1.0
 */
function datamachine_register_logs_admin_page_filters() {

    // Form submission handler for log level updates
    add_action('admin_init', function() {
        // Only process on logs page
        if (!isset($_GET['page']) || $_GET['page'] !== 'datamachine-logs') {
            return;
        }

        // Check for form submission
        if (!isset($_POST['datamachine_logs_action']) || $_POST['datamachine_logs_action'] !== 'update_log_level') {
            return;
        }

        $nonce = isset($_POST['datamachine_logs_nonce']) ? sanitize_text_field(wp_unslash($_POST['datamachine_logs_nonce'])) : '';

        // Verify nonce
        if (!wp_verify_nonce($nonce, 'datamachine_logs_action')) {
            wp_die(esc_html__('Security check failed.', 'datamachine'));
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage logs.', 'datamachine'));
        }

        // Sanitize and validate log level
        $log_level = isset($_POST['log_level']) ? sanitize_text_field(wp_unslash($_POST['log_level'])) : '';
        $available_levels = array_keys(datamachine_get_available_log_levels());

        if (!in_array($log_level, $available_levels)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__('Invalid log level selected.', 'datamachine') . '</p></div>';
            });
            return;
        }

        // Update log level
        $updated = datamachine_set_log_level($log_level);

        if ($updated) {
            add_action('admin_notices', function() use ($log_level) {
                $level_display = datamachine_get_available_log_levels()[$log_level] ?? ucfirst($log_level);
                /* translators: %s: Selected log level. */
                $message = sprintf(esc_html__('Log level updated to %s.', 'datamachine'), esc_html($level_display));
                echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__('Failed to update log level.', 'datamachine') . '</p></div>';
            });
        }
    });

    // Pure discovery mode - matches actual system usage
    add_filter('datamachine_admin_pages', function($pages) {
        $pages['logs'] = [
            'page_title' => __('Logs', 'datamachine'),
            'menu_title' => __('Logs', 'datamachine'),
            'capability' => 'manage_options',
            'position' => 30,
            'templates' => __DIR__ . '/templates/',
            'assets' => [
                'css' => [
                    'datamachine-admin-logs' => [
                        'file' => 'inc/Core/Admin/Pages/Logs/assets/css/admin-logs.css',
                        'deps' => [],
                        'media' => 'all'
                    ]
                ],
                'js' => [
                    'datamachine-admin-logs' => [
                        'file' => 'inc/Core/Admin/Pages/Logs/assets/js/admin-logs.js',
                        'deps' => ['wp-api-fetch'],
                        'in_footer' => true
                    ]
                ]
            ]
        ];
        return $pages;
    });
}

// Auto-register when file loads - achieving complete self-containment
datamachine_register_logs_admin_page_filters();