<?php
/**
 * Handles the admin menu registration and asset enqueueing for the Data Machine plugin.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/utilities
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages admin menus and assets.
 */
class Data_Machine_Admin_Menu_Assets {

    /**
     * The plugin version.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      string    $version    The current plugin version.
     */
    private $version;

    /**
     * The main Data_Machine_Admin_Page instance.
     * Needed to call the display methods for the pages.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      Data_Machine_Admin_Page $admin_page_handler
     */
    private $admin_page_handler;

    /**
     * @var Data_Machine_Database_Modules
     */
    private $db_modules;

    /**
     * Hook suffix for the Remote Locations page. Used for conditional asset loading.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      string|false
     */
    private $remote_locations_hook_suffix = false;
    
    /**
     * Hook suffix for the API Keys page. Used for conditional asset loading.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      string|false
     */
    private $api_keys_hook_suffix = false; // Added property for API keys page

    /**
     * Initialize the class and set its properties.
     *
     * @since    NEXT_VERSION
     * @param    string                             $version             The plugin version.
     * @param    Data_Machine_Admin_Page    $admin_page_handler  The handler for page display callbacks.
     * @param    Data_Machine_Database_Modules $db_modules          The database modules instance.
     */
    public function __construct( $version, Data_Machine_Admin_Page $admin_page_handler, Data_Machine_Database_Modules $db_modules ) {
        $this->version = $version;
        $this->admin_page_handler = $admin_page_handler;
        $this->db_modules = $db_modules;
    }

    /**
     * Register hooks for admin menu and assets.
     *
     * @since NEXT_VERSION
     */
    public function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Add admin menu for the plugin.
     *
     * @since    NEXT_VERSION
     */
    public function add_admin_menu() {
        // Main menu now points to Projects (most logical starting point)
        add_menu_page(
            'Data Machine',
            'Data Machine', // Menu title
            'manage_options', // Capability
            'dm-project-management', // Main menu now points to Projects
            array( $this->admin_page_handler, 'display_project_management_page' ), // Projects callback
            'dashicons-database-import', // Icon slug
            6 // Position
        );
        // Projects (will be the default/main page)
        add_submenu_page(
            'dm-project-management', // Parent slug
            'Projects',
            'Projects',
            'manage_options',
            'dm-project-management', // Same slug as parent for clean URLs
            array( $this->admin_page_handler, 'display_project_management_page' )
        );
        // Modules
        add_submenu_page(
            'dm-project-management', // Parent slug
            'Module Config',
            'Module Config',
            'manage_options',
            'dm-module-config',
            array( $this->admin_page_handler, 'display_settings_page' )
        );
        // Run One (Single Module) Page
        add_submenu_page(
            'dm-project-management', // Parent slug
            'Run Single Module', // Page title
            'Run Single Module', // Menu title
            'manage_options', // Capability
            'dm-run-single-module', // Keep old slug for compatibility
            array( $this->admin_page_handler, 'display_admin_page' ) // Old callback
        );
        // Remote Locations
        $this->remote_locations_hook_suffix = add_submenu_page(
            'dm-project-management',
            __('Manage Remote Locations', 'data-machine'),
            __('Remote Locations', 'data-machine'),
            'manage_options',
            'dm-remote-locations',
            array($this->admin_page_handler, 'display_remote_locations_page')
        );
        // API Keys
        $this->api_keys_hook_suffix = add_submenu_page(
            'dm-project-management',
            __('API / Auth', 'data-machine'),
            __('API / Auth', 'data-machine'),
            'manage_options',
            'dm-api-keys',
            array($this->admin_page_handler, 'display_api_keys_page')
        );
        // Jobs
        add_submenu_page(
            'dm-project-management',
            __('Jobs', 'data-machine'),
            __('Jobs', 'data-machine'),
            'manage_options',
            'dm-jobs',
            array($this->admin_page_handler, 'display_jobs_page')
        );
    }

    /**
     * Enqueue admin assets (CSS and JS).
     *
     * @since    NEXT_VERSION
     * @param    string    $hook_suffix    The current admin page hook.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        $run_one_hooks = [
            'data-machine_page_dm-run-single-module',
        ];
        $project_management_hooks = [
            'toplevel_page_dm-project-management', // Main menu page
            'data-machine_page_dm-project-management',
        ];
        $module_config_hooks = [
            'data-machine_page_dm-module-config',
        ];

        if ( in_array($hook_suffix, $run_one_hooks, true) ) {
            $this->enqueue_run_single_module_assets();
        } elseif ( in_array($hook_suffix, $project_management_hooks, true) ) {
            $this->enqueue_project_management_assets();
        } elseif ( in_array($hook_suffix, $module_config_hooks, true) ) {
            $this->enqueue_module_config_assets();
        } elseif ( $hook_suffix === $this->remote_locations_hook_suffix ) {
            $this->enqueue_remote_locations_assets();
        } elseif ( $hook_suffix === $this->api_keys_hook_suffix ) {
            $this->enqueue_api_keys_assets();
        }
    }



    private function enqueue_run_single_module_assets() {
        $plugin_base_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) );
        $plugin_base_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
        $css_path = $plugin_base_path . 'assets/css/data-machine-admin.css';
        $css_url = $plugin_base_url . 'assets/css/data-machine-admin.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
        wp_enqueue_style( 'data-machine-admin', $css_url, array(), $css_version, 'all' );
        $js_main_path = $plugin_base_path . 'assets/js/run-single-module.js';
        $js_main_url = $plugin_base_url . 'assets/js/run-single-module.js';
        $js_main_version = file_exists($js_main_path) ? filemtime($js_main_path) : $this->version;
        wp_enqueue_script( 'dm-run-single-module', $js_main_url, array( 'jquery' ), $js_main_version, false );
        $db_modules = $this->db_modules;
        $user_id = get_current_user_id();
        $current_module_id = get_user_meta($user_id, 'Data_Machine_current_module', true);
        $current_module = $db_modules ? $db_modules->get_module($current_module_id, $user_id) : null;
        $params = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'file_processing_nonce' => wp_create_nonce( 'file_processing_nonce' ),
            'fact_check_nonce' => wp_create_nonce( 'fact_check_nonce' ),
            'finalize_response_nonce' => wp_create_nonce( 'finalize_response_nonce' ),
            'data_source_type' => $current_module && isset($current_module->data_source_type) ? $current_module->data_source_type : 'files',
            'output_type' => $current_module && isset($current_module->output_type) ? $current_module->output_type : 'data_export',
            'check_status_nonce' => wp_create_nonce( 'dm_check_status_nonce' )
        );
        wp_localize_script( 'dm-run-single-module', 'dm_ajax_params', $params );
    }

    private function enqueue_project_management_assets() {
        $plugin_base_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) );
        $plugin_base_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
        $css_path = $plugin_base_path . 'assets/css/data-machine-admin.css';
        $css_url = $plugin_base_url . 'assets/css/data-machine-admin.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
        wp_enqueue_style( 'data-machine-admin', $css_url, array(), $css_version, 'all' );
        $js_project_management_path = $plugin_base_path . 'assets/js/data-machine-project-management.js';
        $js_project_management_url = $plugin_base_url . 'assets/js/data-machine-project-management.js';
        $js_project_management_version = file_exists($js_project_management_path) ? filemtime($js_project_management_path) : $this->version;
        wp_enqueue_script( 'data-machine-project-management-js', $js_project_management_url, array( 'jquery' ), $js_project_management_version, true );
        wp_localize_script( 'data-machine-project-management-js', 'dm_project_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'create_project_nonce' => wp_create_nonce( 'dm_create_project_nonce' ),
            'run_now_nonce' => wp_create_nonce( 'dm_run_now_nonce' ),
            'get_schedule_data_nonce' => wp_create_nonce( 'dm_get_schedule_data_nonce' ),
            'edit_schedule_nonce' => wp_create_nonce( 'dm_edit_schedule_nonce' ),
            'cron_schedules' => \Data_Machine_Constants::get_cron_schedules_for_js(),
        ) );
    }

    private function enqueue_module_config_assets() {
        $plugin_base_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) );
        $plugin_base_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
        $css_path = $plugin_base_path . 'assets/css/data-machine-admin.css';
        $css_url = $plugin_base_url . 'assets/css/data-machine-admin.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
        wp_enqueue_style( 'data-machine-admin', $css_url, array(), $css_version, 'all' );
        $settings_params = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'module_config_nonce' => wp_create_nonce( 'dm_module_config_actions_nonce' ),
        );
        $js_path = $plugin_base_path . 'module-config/js/dm-module-config.js';
        $js_url  = $plugin_base_url . 'module-config/js/dm-module-config.js';
        if ( file_exists( $js_path ) ) {
            $js_version = filemtime( $js_path );
            wp_enqueue_script( 'dm-module-config', $js_url, array(), $js_version, true );
            wp_localize_script( 'dm-module-config', 'dm_settings_params', $settings_params );
        }
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'dm-module-config') {
                if (strpos($tag, 'type="module"') === false) {
                    $tag = str_replace('src=', 'type="module" src=', $tag);
                }
            }
            return $tag;
        }, 10, 2);
    }

    private function enqueue_remote_locations_assets() {
        $plugin_base_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) );
        $plugin_base_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
        $css_path = $plugin_base_path . 'assets/css/data-machine-admin.css';
        $css_url = $plugin_base_url . 'assets/css/data-machine-admin.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
        wp_enqueue_style( 'data-machine-admin', $css_url, array(), $css_version, 'all' );
        $js_remote_path = $plugin_base_path . 'assets/js/data-machine-remote-locations.js';
        $js_remote_url = $plugin_base_url . 'assets/js/data-machine-remote-locations.js';
        $js_remote_version = file_exists($js_remote_path) ? filemtime($js_remote_path) : $this->version;
        wp_enqueue_script( 'dm-remote-locations-admin-js', $js_remote_url, array('jquery'), $js_remote_version, true );
        $remote_locations_params = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'confirm_delete' => __('Are you sure you want to delete the location "%s"? This cannot be undone.', 'data-machine')
        );
        wp_localize_script('dm-remote-locations-admin-js', 'dmRemoteLocationsParams', $remote_locations_params);
    }

    private function enqueue_api_keys_assets() {
        $plugin_base_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) );
        $plugin_base_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
        $css_path = $plugin_base_path . 'assets/css/data-machine-admin.css';
        $css_url = $plugin_base_url . 'assets/css/data-machine-admin.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
        wp_enqueue_style( 'data-machine-admin', $css_url, array(), $css_version, 'all' );
        $js_api_keys_path = $plugin_base_path . 'assets/js/data-machine-api-keys.js';
        $js_api_keys_url = $plugin_base_url . 'assets/js/data-machine-api-keys.js';
        $js_api_keys_version = file_exists($js_api_keys_path) ? filemtime($js_api_keys_path) : $this->version;
        wp_enqueue_script('data-machine-api-keys', $js_api_keys_url, array('jquery'), $js_api_keys_version, true);
        $api_keys_params = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('dm_instagram_auth_nonce'),
            'oauth_url' => admin_url('admin-ajax.php?action=dm_instagram_oauth_start')
        );
        wp_localize_script('data-machine-api-keys', 'dmInstagramAuthParams', $api_keys_params);

        // Add localization for Reddit and Twitter (dmApiKeysParams)
        $dm_api_keys_params = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            // Reddit OAuth
            'reddit_oauth_url' => admin_url('admin-ajax.php?action=dm_reddit_oauth_start'),
            'reddit_oauth_nonce' => wp_create_nonce('dm_reddit_oauth_nonce'),
            // Twitter OAuth
            'twitter_oauth_url' => admin_url('admin-post.php?action=dm_twitter_oauth_init'),
            // Twitter nonce is generated dynamically via AJAX in JS
        );
        wp_localize_script('data-machine-api-keys', 'dmApiKeysParams', $dm_api_keys_params);
    }
} // End class