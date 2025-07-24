<?php
/**
 * Handles the admin menu registration and asset enqueueing for the Data Machine plugin.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/utilities
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin;

use DataMachine\Constants;
use DataMachine\Database\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages admin menus and assets.
 */
class AdminMenuAssets {

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
     * @var      AdminPage $admin_page_handler
     */
    private $admin_page_handler;

    /**
     * @var Modules
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
     * @param    AdminPage    $admin_page_handler  The handler for page display callbacks.
     * @param    Modules $db_modules          The database modules instance.
     */
    public function __construct( $version, AdminPage $admin_page_handler, Modules $db_modules ) {
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
        $project_management_hooks = [
            'toplevel_page_dm-project-management', // Main menu page
            'data-machine_page_dm-project-management',
        ];
        $module_config_hooks = [
            'data-machine_page_dm-module-config',
        ];

        if ( in_array($hook_suffix, $project_management_hooks, true) ) {
            $this->enqueue_project_management_assets();
        } elseif ( in_array($hook_suffix, $module_config_hooks, true) ) {
            $this->enqueue_module_config_assets();
        } elseif ( $hook_suffix === $this->remote_locations_hook_suffix ) {
            $this->enqueue_remote_locations_assets();
        } elseif ( $hook_suffix === $this->api_keys_hook_suffix ) {
            $this->enqueue_api_keys_assets();
        }
    }

    private function enqueue_project_management_assets() {
        $plugin_base_path = DATA_MACHINE_PATH;
        $plugin_base_url = plugins_url( '/', 'data-machine/data-machine.php' );
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
            'upload_files_nonce' => wp_create_nonce( 'dm_upload_files_nonce' ),
            'get_queue_status_nonce' => wp_create_nonce( 'dm_get_queue_status_nonce' ),
            'cron_schedules' => Constants::get_cron_schedules_for_js(),
        ) );
    }

    private function enqueue_module_config_assets() {
        $plugin_base_path = DATA_MACHINE_PATH;
        $plugin_base_url = plugins_url( '/', 'data-machine/data-machine.php' );
        $css_path = $plugin_base_path . 'assets/css/data-machine-admin.css';
        $css_url = $plugin_base_url . 'assets/css/data-machine-admin.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
        wp_enqueue_style( 'data-machine-admin', $css_url, array(), $css_version, 'all' );
        $settings_params = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'module_config_nonce' => wp_create_nonce( 'dm_module_config_actions_nonce' ),
        );
        $js_path = $plugin_base_path . 'admin/ModuleConfig/js/dm-module-config.js';
        $js_url  = $plugin_base_url . 'admin/ModuleConfig/js/dm-module-config.js';
        
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
        $plugin_base_path = DATA_MACHINE_PATH;
        $plugin_base_url = plugins_url( '/', 'data-machine/data-machine.php' );
        $css_path = $plugin_base_path . 'assets/css/data-machine-admin.css';
        $css_url = $plugin_base_url . 'assets/css/data-machine-admin.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
        wp_enqueue_style( 'data-machine-admin', $css_url, array(), $css_version, 'all' );
        $js_remote_path = $plugin_base_path . 'assets/js/data-machine-remote-locations.js';
        $js_remote_url = $plugin_base_url . 'assets/js/data-machine-remote-locations.js';
        $js_remote_version = file_exists($js_remote_path) ? filemtime($js_remote_path) : $this->version;
        wp_enqueue_script( 'dm-remote-locations-admin-js', $js_remote_url, array('jquery'), $js_remote_version, true );
        $remote_locations_params = array(
            'ajax_url' => admin_url('admin-ajax.php')
        );
        wp_localize_script('dm-remote-locations-admin-js', 'dmRemoteLocationsParams', $remote_locations_params);
    }

    private function enqueue_api_keys_assets() {
        $plugin_base_path = DATA_MACHINE_PATH;
        $plugin_base_url = plugins_url( '/', 'data-machine/data-machine.php' );
        $css_path = $plugin_base_path . 'assets/css/data-machine-admin.css';
        $css_url = $plugin_base_url . 'assets/css/data-machine-admin.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
        wp_enqueue_style( 'data-machine-admin', $css_url, array(), $css_version, 'all' );
        $js_api_keys_path = $plugin_base_path . 'assets/js/data-machine-api-keys.js';
        $js_api_keys_url = $plugin_base_url . 'assets/js/data-machine-api-keys.js';
        $js_api_keys_version = file_exists($js_api_keys_path) ? filemtime($js_api_keys_path) : $this->version;
        wp_enqueue_script('data-machine-api-keys', $js_api_keys_url, array('jquery'), $js_api_keys_version, true);

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