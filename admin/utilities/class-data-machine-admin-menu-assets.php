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
        // Main Dashboard Page
        add_menu_page(
            'Data Machine',
            'Data Machine', // Menu title
            'manage_options', // Capability
            'data-machine-dashboard', // New main menu slug
            array( $this->admin_page_handler, 'display_dashboard_page' ), // Dashboard callback
            'dashicons-database-import', // Icon slug
            6 // Position
        );
        // Run One (Single Module) Page
        add_submenu_page(
            'data-machine-dashboard', // Parent slug
            'Run Single Module', // Page title
            'Run Single Module', // Menu title
            'manage_options', // Capability
            'dm-run-single-module', // Keep old slug for compatibility
            array( $this->admin_page_handler, 'display_admin_page' ) // Old callback
        );
        // Projects
        add_submenu_page(
            'data-machine-dashboard', // Parent slug
            'Projects',
            'Projects',
            'manage_options',
            'dm-project-management',
            array( $this->admin_page_handler, 'display_project_dashboard_page' )
        );
        // Modules
        add_submenu_page(
            'data-machine-dashboard', // Parent slug
            'Module Config',
            'Module Config',
            'manage_options',
            'dm-module-config',
            array( $this->admin_page_handler, 'display_settings_page' )
        );
        // Remote Locations
        $this->remote_locations_hook_suffix = add_submenu_page(
            'data-machine-dashboard',
            __('Manage Remote Locations', 'data-machine'),
            __('Remote Locations', 'data-machine'),
            'manage_options',
            'dm-remote-locations',
            array($this->admin_page_handler, 'display_remote_locations_page')
        );
        // API Keys
        $this->api_keys_hook_suffix = add_submenu_page(
            'data-machine-dashboard',
            __('API / Auth', 'data-machine'),
            __('API / Auth', 'data-machine'),
            'manage_options',
            'dm-api-keys',
            array($this->admin_page_handler, 'display_api_keys_page')
        );
        // Jobs
        add_submenu_page(
            'data-machine-dashboard',
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
        error_log('[Data Machine] enqueue_admin_assets hook_suffix: ' . $hook_suffix);

        // Define base paths and URLs
        $plugin_base_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) ); // Base plugin path - adjusted for utility directory
        $plugin_base_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );   // Base plugin URL - adjusted for utility directory

        // Dashboard page hook suffix
        $dashboard_hook = 'toplevel_page_data-machine-dashboard';
        $run_one_hooks = [
            'data-machine-dashboard_page_dm-run-single-module',
            'data-machine_page_dm-run-single-module', // Compatibility for legacy/alternate parent slug
        ];

        if ( $hook_suffix === $dashboard_hook ) {
            // Enqueue dashboard CSS
            $css_path = $plugin_base_path . 'assets/css/data-machine-admin.css';
            $css_url = $plugin_base_url . 'assets/css/data-machine-admin.css';
            $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
            wp_enqueue_style( 'data-machine-admin', $css_url, array(), $css_version, 'all' );

            // Enqueue dashboard JS
            $js_path = $plugin_base_path . 'assets/js/data-machine-dashboard.js';
            $js_url = $plugin_base_url . 'assets/js/data-machine-dashboard.js';
            $js_version = file_exists($js_path) ? filemtime($js_path) : $this->version;
            wp_enqueue_script( 'data-machine-dashboard-js', $js_url, array( 'jquery' ), $js_version, true );

            // Localize script with AJAX URL and nonce
            wp_localize_script( 'data-machine-dashboard-js', 'dm_dashboard_params', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'dm_dashboard_nonce' => wp_create_nonce( 'dm_dashboard_nonce' ),
            ) );
        }

        if ( in_array($hook_suffix, $run_one_hooks, true) ) {
            // Enqueue admin CSS
            $css_path = $plugin_base_path . 'assets/css/data-machine-admin.css';
            $css_url = $plugin_base_url . 'assets/css/data-machine-admin.css';
            $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
            wp_enqueue_style( 'data-machine-admin', $css_url, array(), $css_version, 'all' );

            // Enqueue Run One JS
            $js_main_path = $plugin_base_path . 'assets/js/run-single-module.js';
            $js_main_url = $plugin_base_url . 'assets/js/run-single-module.js';
            $js_main_version = file_exists($js_main_path) ? filemtime($js_main_path) : $this->version;
            wp_enqueue_script( 'dm-run-single-module', $js_main_url, array( 'jquery' ), $js_main_version, false );

            // Get dependencies via locator
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

    	// Check if the current page is one of the plugin's pages
        // Note: Hook names are like 'toplevel_page_MENU_SLUG' or 'PARENT_SLUG_page_SUBMENU_SLUG'
    	$plugin_pages = [
    		'toplevel_page_dm-run-single-module',
            'dm-run-single-module_page_dm-project-management', // Matches parent slug 'dm-run-single-module'
            'dm-run-single-module_page_dm-module-config', // Matches parent slug 'dm-run-single-module'
            'data-machine_page_dm-project-management', // Support alternate/legacy parent slug
            'data-machine_page_dm-module-config', // Support alternate/legacy parent slug
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
            if ('toplevel_page_dm-run-single-module' === $hook_suffix) {
                error_log('[Data Machine] Loading assets for: toplevel_page_dm-run-single-module');
                $js_main_path = $plugin_base_path . 'assets/js/run-single-module.js';
                $js_main_url = $plugin_base_url . 'assets/js/run-single-module.js';
                $js_main_version = file_exists($js_main_path) ? filemtime($js_main_path) : $this->version;
                wp_enqueue_script( 'dm-run-single-module', $js_main_url, array( 'jquery' ), $js_main_version, false ); // Load in header

                // Get dependencies via locator
                $db_modules = $this->db_modules;

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
                    'output_type' => $current_module && isset($current_module->output_type) ? $current_module->output_type : 'data_export', // Default 'data_export'
                    'check_status_nonce' => wp_create_nonce( 'dm_check_status_nonce' ) // Nonce for checking job status
                   );
                wp_localize_script( 'dm-run-single-module', 'dm_ajax_params', $params );
            
            // Remote Locations Page JS
            } elseif ($hook_suffix === $this->remote_locations_hook_suffix) {
            	$js_remote_path = $plugin_base_path . 'assets/js/data-machine-remote-locations.js';
                $js_remote_url = $plugin_base_url . 'assets/js/data-machine-remote-locations.js';
                $js_remote_version = file_exists($js_remote_path) ? filemtime($js_remote_path) : $this->version; // Already uses filemtime
            	wp_enqueue_script( 'dm-remote-locations-admin-js', $js_remote_url, array('jquery'), $js_remote_version, true ); // Load in footer
         
            	$remote_locations_params = array(
            		'ajax_url' => admin_url('admin-ajax.php'),
            		'confirm_delete' => __('Are you sure you want to delete the location "%s"? This cannot be undone.', 'data-machine')
            	);
            	wp_localize_script('dm-remote-locations-admin-js', 'dmRemoteLocationsParams', $remote_locations_params);

            // API Keys Page JS
            } elseif ($hook_suffix === $this->api_keys_hook_suffix) {
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
            } elseif ( in_array( $hook_suffix, array( 'data-machine-dashboard_page_dm-project-management', 'data-machine_page_dm-project-management' ), true ) ) {
                // Enqueue Project Management JS
                $js_project_management_path = $plugin_base_path . 'assets/js/data-machine-project-management.js';
                $js_project_management_url = $plugin_base_url . 'assets/js/data-machine-project-management.js';
                $js_project_management_version = file_exists($js_project_management_path) ? filemtime($js_project_management_path) : $this->version;
                wp_enqueue_script( 'data-machine-project-management-js', $js_project_management_url, array( 'jquery' ), $js_project_management_version, true );

                // Localize script with AJAX URL and nonces needed for project management
                wp_localize_script( 'data-machine-project-management-js', 'dm_dashboard_params', array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'create_project_nonce' => wp_create_nonce( 'dm_create_project_nonce' ),
                    'run_now_nonce' => wp_create_nonce( 'dm_run_now_nonce' ),
                    'get_schedule_data_nonce' => wp_create_nonce( 'dm_get_schedule_data_nonce' ),
                    'edit_schedule_nonce' => wp_create_nonce( 'dm_edit_schedule_nonce' ),
                    'cron_schedules' => wp_get_schedules(), // Pass available cron schedules
                ) );
            }
         
            // --- BEGIN: Add ES module support for module-config/js scripts ---
            add_filter('script_loader_tag', function($tag, $handle) {
                $module_handles = array(
                    'dm-module-config',
                    'dm-module-config-state',
                    'dm-module-config-ajax',
                    'dm-module-config-remote-locations',
                    'dm-module-config-ui-helpers',
                    'module-state-controller',
                    'project-module-selector',
                    'handler-template-manager',
                );
                if (in_array($handle, $module_handles)) {
                    // Only add type="module" if not already present
                    if (strpos($tag, 'type="module"') === false) {
                        $tag = str_replace('src=', 'type="module" src=', $tag);
                    }
                }
                return $tag;
            }, 10, 2);
            // --- END: Add ES module support ---
        }

        // --- BEGIN: Enqueue Module Config Assets (Consolidated) ---
        // Only enqueue on the module config page
        $screen = get_current_screen();
        if ( ! empty( $screen->id ) && strpos( $screen->id, 'dm-module-config' ) !== false ) {
            error_log('[Data Machine] Loading assets for: Module Config Page');

            $plugin_base_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) ); // Base plugin path
            $plugin_base_url  = plugin_dir_url( dirname( dirname( __FILE__ ) ) );   // Base plugin URL

            // Prepare settings params for localization
            $settings_params = array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                // Standardized nonce for all module config actions
                'module_config_nonce' => wp_create_nonce( 'dm_module_config_actions_nonce' ),
            );

            // Enqueue JS assets in dependency order
            $js_files = array(
                // Ensure module-config-ajax is enqueued FIRST or early
                'dm-module-config-ajax' => array('path' => 'module-config/js/module-config-ajax.js', 'deps' => array('jquery')),
                'dm-module-config-state' => array('path' => 'module-config/js/module-config-state.js', 'deps' => array('jquery')),
                'module-state-controller' => array('path' => 'module-config/js/module-state-controller.js', 'deps' => array('dm-module-config-state', 'dm-module-config-ajax')),
                'project-module-selector' => array('path' => 'module-config/js/project-module-selector.js', 'deps' => array('jquery', 'dm-module-config-ajax')),
                'handler-template-manager' => array('path' => 'module-config/js/handler-template-manager.js', 'deps' => array('jquery', 'dm-module-config-ajax')),
                // Ensure dm-module-config depends on the ajax handler
                'dm-module-config' => array('path' => 'module-config/js/dm-module-config.js', 'deps' => array('jquery', 'dm-module-config-ajax', 'dm-module-config-state', 'project-module-selector', 'handler-template-manager')),
                'dm-module-config-ui-helpers' => array('path' => 'module-config/js/dm-module-config-ui-helpers.js', 'deps' => array('jquery', 'dm-module-config')),
                // Ensure remote locations depends on the ajax handler
                'dm-module-config-remote-locations' => array('path' => 'module-config/js/dm-module-config-remote-locations.js', 'deps' => array('jquery', 'dm-module-config', 'dm-module-config-ajax')),
            );

            foreach ( $js_files as $handle => $info ) {
                $js_path = $plugin_base_path . $info['path'];
                $js_url  = $plugin_base_url . $info['path'];
                if ( file_exists( $js_path ) ) {
                    wp_enqueue_script( $handle, $js_url, $info['deps'], filemtime( $js_path ), true );
                }
            }

            // Localize params for the main AJAX handler script
            wp_localize_script( 'dm-module-config-ajax', 'dm_settings_params', $settings_params );

            // Add type="module" to all module-config scripts for ES module support
            // This filter is now applied only when module config assets are enqueued
            add_filter('script_loader_tag', function($tag, $handle) {
                $module_handles = array(
                    'dm-module-config',
                    'dm-module-config-state',
                    'dm-module-config-ajax',
                    'dm-module-config-remote-locations',
                    'dm-module-config-ui-helpers',
                    'module-state-controller',
                    'project-module-selector',
                    'handler-template-manager',
                );
                if (in_array($handle, $module_handles)) {
                    if (strpos($tag, 'type="module"') === false) {
                        $tag = str_replace('src=', 'type="module" src=', $tag);
                    }
                }
                return $tag;
            }, 10, 2);
        }
        // --- END: Enqueue Module Config Assets ---
    }
} // End class