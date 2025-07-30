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
        
        // Get all registered admin pages
        $registered_pages = apply_filters('dm_register_admin_pages', []);
        
        // Find the matching page configuration
        $page_config = null;
        foreach ($registered_pages as $page) {
            if (isset($page['menu_slug']) && $page['menu_slug'] === $current_page) {
                $page_config = $page;
                break;
            }
        }
        
        // If no page config found, show error
        if (!$page_config || !isset($page_config['content_renderer'])) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Page configuration not found.', 'data-machine') . '</p></div>';
            return;
        }
        
        // Prepare context data for templates
        $context = $this->prepare_page_context($current_page);
        
        // Call the content renderer
        $content_renderer = $page_config['content_renderer'];
        if (is_callable($content_renderer)) {
            echo call_user_func($content_renderer, $context);
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Content renderer not callable.', 'data-machine') . '</p></div>';
        }
    }
    
    /**
     * Prepare context data for page templates.
     * 
     * Uses filter-based service access for pure filter-based architecture.
     *
     * @param string $page_slug The current page slug
     * @return array Context data for the template
     */
    private function prepare_page_context($page_slug) {
        $context = [];
        
        // Add common context data via filter-based service access
        $context['db_projects'] = apply_filters('dm_get_database_service', null, 'projects');
        $context['db_modules'] = apply_filters('dm_get_database_service', null, 'modules');
        $context['logger'] = apply_filters('dm_get_logger', null);
        
        // Add page-specific context data
        switch ($page_slug) {
            case 'dm-project-management':
                // Projects page specific context
                break;
            case 'dm-jobs':
                // Jobs page specific context
                break;
            case 'dm-remote-locations':
                // Remote locations page specific context
                break;
        }
        
        return $context;
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
