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
     * Service Locator instance.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      Data_Machine_Service_Locator    $locator    Service Locator instance.
     */
    private $locator;

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
     * The main Data_Machine_Admin_Page instance.
     * Needed to call the display methods for the pages.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      Data_Machine_Admin_Page $admin_page_handler
     */
    private $admin_page_handler;

    /**
     * Initialize the class and set its properties.
     *
     * @since    NEXT_VERSION
     * @param    string                             $version             The plugin version.
     * @param    Data_Machine_Service_Locator $locator             Service Locator instance.
     * @param    Data_Machine_Admin_Page    $admin_page_handler  The handler for page display callbacks.
     */
    public function __construct( $version, Data_Machine_Service_Locator $locator, Data_Machine_Admin_Page $admin_page_handler ) {
        $this->version = $version;
        $this->locator = $locator;
        $this->admin_page_handler = $admin_page_handler;
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
        add_menu_page(
            'Data Machine',
            'Data Machine', // Menu title
            'manage_options', // Capability
            'data-machine-admin-page', // Menu slug
            array( $this->admin_page_handler, 'display_admin_page' ), // Use handler for callback
            'dashicons-database-import', // Icon slug
            6 // Position
        );
        add_submenu_page(
            'data-machine-admin-page', // Parent slug
            'Project Dashboard', // Page title
            'Dashboard', // Menu title
            'manage_options', // Capability
            'data-machine-project-dashboard-page', // Menu slug
            array( $this->admin_page_handler, 'display_project_dashboard_page' ) // Use handler for callback
        );
        add_submenu_page(
            'data-machine-admin-page', // Parent slug
            'Settings', // Page title
            'Settings', // Menu title
            'manage_options', // Capability
            'data-machine-settings-page', // Menu slug
            array( $this->admin_page_handler, 'display_settings_page' ) // Use handler for callback
        );
      
        // Add Remote Locations submenu page
        $this->remote_locations_hook_suffix = add_submenu_page(
                  'data-machine-admin-page', // Parent slug
                  __('Manage Remote Locations', 'data-machine'), // Page title
                  __('Remote Locations', 'data-machine'), // Menu title
                  'manage_options', // Capability required
                  'adc-remote-locations', // Menu slug
                  array($this->admin_page_handler, 'display_remote_locations_page') // Use handler for callback
              );
      
        // Add API Keys submenu page
        $this->api_keys_hook_suffix = add_submenu_page(
            'data-machine-admin-page', // Parent slug
            __('API Keys', 'data-machine'), // Page title
            __('API Keys', 'data-machine'), // Menu title
            'manage_options', // Capability required
            'adc-api-keys', // Menu slug
            array($this->admin_page_handler, 'display_api_keys_page') // Use handler for callback
        );
    }

    /**
     * Enqueue admin assets (CSS and JS).
     *
     * @since    NEXT_VERSION
     * @param    string    $hook_suffix    The current admin page hook.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Define base paths and URLs
        $plugin_base_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) ); // Base plugin path - adjusted for utility directory
        $plugin_base_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );   // Base plugin URL - adjusted for utility directory

    	// Check if the current page is one of the plugin's pages
        // Note: Hook names are like 'toplevel_page_MENU_SLUG' or 'PARENT_SLUG_page_SUBMENU_SLUG'
    	$plugin_pages = [
    		'toplevel_page_data-machine-admin-page',
            'data-machine_page_data-machine-project-dashboard-page', // Matches parent slug 'data-machine-admin-page'
    		'data-machine_page_data-machine-settings-page', // Matches parent slug 'data-machine-admin-page'
    		$this->remote_locations_hook_suffix, // Hook suffix stored during menu creation
            $this->api_keys_hook_suffix // Hook suffix for API Keys page
    	];

    	if ( in_array($hook_suffix, $plugin_pages) ) {
            // Admin CSS
            $css_path = $plugin_base_path . 'assets/css/data-machine-admin.css';
            $css_url = $plugin_base_url . 'assets/css/data-machine-admin.css';
            $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
            wp_enqueue_style( 'data-machine-admin', $css_url, array(), $css_version, 'all' );
            
            // Main Admin Page JS
            if ('toplevel_page_data-machine-admin-page' === $hook_suffix) {
                $js_main_path = $plugin_base_path . 'assets/js/data-machine-main.js';
                $js_main_url = $plugin_base_url . 'assets/js/data-machine-main.js';
                $js_main_version = file_exists($js_main_path) ? filemtime($js_main_path) : $this->version;
                wp_enqueue_script( 'data-machine-main', $js_main_url, array( 'jquery' ), $js_main_version, false ); // Load in header

                // Get dependencies via locator
                $db_modules = $this->locator->get('database_modules');

                // Get current module settings for JS
                $user_id = get_current_user_id();
                $current_module_id = get_user_meta($user_id, 'Data_Machine_current_module', true);
                $current_module = $db_modules ? $db_modules->get_module($current_module_id, $user_id) : null;

                $params = array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'file_processing_nonce' => wp_create_nonce( 'file_processing_nonce' ),
                    'fact_check_nonce' => wp_create_nonce( 'fact_check_nonce' ),
                    'finalize_response_nonce' => wp_create_nonce( 'finalize_response_nonce' ),
                    'data_source_type' => $current_module && isset($current_module->data_source_type) ? $current_module->data_source_type : 'files', // Default 'files'
                    'output_type' => $current_module && isset($current_module->output_type) ? $current_module->output_type : 'data', // Default 'data'
                    'check_status_nonce' => wp_create_nonce( 'dm_check_status_nonce' ) // Nonce for checking job status
                   );
                wp_localize_script( 'data-machine-main', 'dm_ajax_params', $params );
            
            // Settings Page JS
            } elseif ('data-machine_page_data-machine-settings-page' === $hook_suffix) {
                $js_settings_path = $plugin_base_path . 'assets/js/data-machine-settings.js';
                $js_settings_url = $plugin_base_url . 'assets/js/data-machine-settings.js';
                $js_settings_version = file_exists($js_settings_path) ? filemtime($js_settings_path) : $this->version;
                wp_enqueue_script( 'data-machine-settings', $js_settings_url, array( 'jquery' ), $js_settings_version, true ); // Load in footer

                $settings_params = array(
                	'ajax_url' => admin_url( 'admin-ajax.php' ),
                	'get_module_nonce' => wp_create_nonce( 'dm_get_module_nonce' ),
                	'get_project_modules_nonce' => wp_create_nonce( 'dm_get_project_modules_nonce' ),
                	'create_project_nonce' => wp_create_nonce( 'dm_create_project_nonce' ),
                    'get_synced_info_nonce' => wp_create_nonce( 'dm_get_location_synced_info_nonce' ),
                    'nonce' => wp_create_nonce('dm_settings_nonce') // Add the general settings nonce
                );
                wp_localize_script( 'data-machine-settings', 'dm_settings_params', $settings_params );
            
            // Project Dashboard Page JS
            } elseif ('data-machine_page_data-machine-project-dashboard-page' === $hook_suffix) {
                 $js_dashboard_path = $plugin_base_path . 'assets/js/data-machine-dashboard.js';
                 $js_dashboard_url = $plugin_base_url . 'assets/js/data-machine-dashboard.js';
                 $js_dashboard_version = file_exists($js_dashboard_path) ? filemtime($js_dashboard_path) : $this->version;
                 wp_enqueue_script( 'data-machine-dashboard', $js_dashboard_url, array( 'jquery' ), $js_dashboard_version, true ); // Load in footer

                 $dashboard_params = array(
                     'ajax_url'                  => admin_url( 'admin-ajax.php' ),
                     'run_now_nonce'             => wp_create_nonce( 'dm_run_now_nonce' ),
                     'get_schedule_data_nonce'   => wp_create_nonce( 'dm_get_schedule_data_nonce' ),
                     'edit_schedule_nonce'       => wp_create_nonce( 'dm_edit_schedule_nonce' ),
                     'create_project_nonce'      => wp_create_nonce( 'dm_create_project_nonce' )
                 );
                 wp_localize_script( 'data-machine-dashboard', 'dm_dashboard_params', $dashboard_params );
            
            // Remote Locations Page JS
            } elseif ($hook_suffix === $this->remote_locations_hook_suffix) {
            	$js_remote_path = $plugin_base_path . 'assets/js/data-machine-remote-locations.js';
                $js_remote_url = $plugin_base_url . 'assets/js/data-machine-remote-locations.js';
                $js_remote_version = file_exists($js_remote_path) ? filemtime($js_remote_path) : $this->version; // Already uses filemtime
            	wp_enqueue_script( 'adc-remote-locations-admin-js', $js_remote_url, array('jquery'), $js_remote_version, true ); // Load in footer
         
            	$remote_locations_params = array(
            		'ajax_url' => admin_url('admin-ajax.php'),
            		'confirm_delete' => __('Are you sure you want to delete the location "%s"? This cannot be undone.', 'data-machine')
            	);
            	wp_localize_script('adc-remote-locations-admin-js', 'adcRemoteLocationsParams', $remote_locations_params);

            // API Keys Page JS (Add if needed in the future)
            // } elseif ($hook_suffix === $this->api_keys_hook_suffix) {
            //     // Enqueue specific JS for API Keys page
            // }
         
            }
        }
    }

} // End class 