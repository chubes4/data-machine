<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin
 */

namespace DataMachine\Admin;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * The admin-specific functionality of the plugin.
 */
class AdminPage {

    // All services now accessed via method-level filter calls
    // Zero constructor dependencies for pure filter-based architecture




    // Remote locations functionality now handled directly by template


    /**
     * Initialize the class and set its properties.
     *
     * @since    0.1.0
     */
    /**
     * Initialize the class with zero constructor dependencies.
     * 
     * All services accessed via method-level filter calls for pure
     * filter-based architecture alignment.
     *
     * @since    0.1.0
     */
    public function __construct() {
        // All page processing is now handled through form actions or AJAX
        // No need for individual page load hooks with unified display_page() method
        
        // Admin post handlers for log management
        add_action( 'admin_post_dm_update_log_level', array( $this, 'handle_update_log_level' ) );
        add_action( 'admin_post_dm_clear_logs', array( $this, 'handle_clear_logs' ) );
        
        // AJAX handler for log refresh
        add_action( 'wp_ajax_dm_refresh_logs', array( $this, 'handle_refresh_logs_ajax' ) );
    }



    /**
     * Universal admin page display method.
     * 
     * Uses registered admin pages and their content renderers for clean architecture.
     * All admin pages use this single method with different content renderers.
     */
    public function display_page() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'data-machine'));
        }
        
        // Get current page slug
        $current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        
        // Extract page slug from WordPress menu format (remove 'dm-' prefix)
        $page_slug = $current_page;
        if (strpos($page_slug, 'dm-') === 0) {
            $page_slug = substr($page_slug, 3); // Remove 'dm-' prefix
        }
        
        // Get page configuration using collection-based registry
        $all_pages = apply_filters('dm_register_admin_pages', []);
        $page_config = $all_pages[$page_slug] ?? null;
        
        // If no page config found, show error
        if (!$page_config || !isset($page_config['callback'])) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Page configuration not found.', 'data-machine') . '</p></div>';
            return;
        }
        
        // Call the page callback directly - pages are self-sufficient via filters
        $callback = $page_config['callback'];
        if (is_callable($callback)) {
            call_user_func($callback);
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Page callback not callable.', 'data-machine') . '</p></div>';
        }
    }
    



    /**
     * Handle log level update form submission.
     */
    public function handle_update_log_level() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'data-machine'));
        }

        if (!wp_verify_nonce(isset($_POST['dm_log_level_nonce']) ? sanitize_text_field(wp_unslash($_POST['dm_log_level_nonce'])) : '', 'dm_update_log_level')) {
            wp_die(esc_html__('Security check failed.', 'data-machine'));
        }

        $new_log_level = sanitize_key($_POST['dm_log_level'] ?? 'info');
        
        $logger = apply_filters('dm_get_logger', null);
        $available_levels = $logger ? $logger->get_available_log_levels() : [];
        
        if (!array_key_exists($new_log_level, $available_levels)) {
            $logger->add_admin_error('Invalid log level selected.');
        } else {
            update_option('dm_log_level', $new_log_level);
            $logger->add_admin_success('Log level updated successfully to: ' . $available_levels[$new_log_level]);
        }

        // Redirect back to logs tab
        wp_redirect(admin_url('admin.php?page=dm-jobs&tab=logs'));
        exit;
    }

    /**
     * Handle clear logs form submission.
     */
    public function handle_clear_logs() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'data-machine'));
        }

        if (!wp_verify_nonce(isset($_POST['dm_clear_logs_nonce']) ? sanitize_text_field(wp_unslash($_POST['dm_clear_logs_nonce'])) : '', 'dm_clear_logs')) {
            wp_die(esc_html__('Security check failed.', 'data-machine'));
        }

        $logger = apply_filters('dm_get_logger', null);
        
        if ($logger->clear_logs()) {
            $logger->add_admin_success('Log files cleared successfully.');
        } else {
            $logger->add_admin_error('Failed to clear some log files.');
        }

        // Redirect back to logs tab
        wp_redirect(admin_url('admin.php?page=dm-jobs&tab=logs'));
        exit;
    }

    /**
     * Handle AJAX request to refresh logs.
     */
    public function handle_refresh_logs_ajax() {
        // Verify nonce using standard AJAX nonce verification
        check_ajax_referer( 'dm_refresh_logs', 'nonce' );
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'data-machine')]);
        }

        $logger = apply_filters('dm_get_logger', null);
        
        try {
            $recent_logs = $logger->get_recent_logs(100);
            wp_send_json_success(['logs' => $recent_logs]);
        } catch (Exception $e) {
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error('Exception caught in handle_refresh_logs_ajax', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
                'user_id' => get_current_user_id()
            ]);
            wp_send_json_error(['message' => 'Failed to retrieve logs: ' . $e->getMessage()]);
        }
    }
}
