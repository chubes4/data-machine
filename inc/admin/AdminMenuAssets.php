<?php
/**
 * Handles the admin menu registration and asset enqueueing for the Data Machine plugin.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/utilities
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin;

use DataMachine\Core\Constants;

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
     * Initialize the class with zero constructor dependencies.
     * 
     * Uses filter-based service access for pure filter-based architecture.
     *
     * @since    NEXT_VERSION
     */
    public function __construct() {
        $this->version = DATA_MACHINE_VERSION;
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
     * Register admin pages via filter system.
     * 
     * Uses dm_register_admin_pages filter to allow direct registration
     * from each admin page component and external plugin extensibility.
     *
     * @since    NEXT_VERSION
     */
    public function add_admin_menu() {
        // Get all registered admin pages via filter
        $admin_pages = apply_filters('dm_register_admin_pages', []);
        
        // Register each page with WordPress
        foreach ($admin_pages as $page) {
            // Use the callback from the page registration directly
            $callback = $page['callback'] ?? null;
            
            if (!$callback || !is_callable($callback)) {
                error_log('Data Machine: Invalid callback for admin page: ' . $page['menu_slug']);
                continue;
            }
            
            if ($page['type'] === 'menu') {
                $hook_suffix = add_menu_page(
                    $page['page_title'],
                    $page['menu_title'],
                    $page['capability'],
                    $page['menu_slug'],
                    $callback,
                    $page['icon_url'] ?? '',
                    $page['position'] ?? null
                );
            } else {
                $hook_suffix = add_submenu_page(
                    $page['parent_slug'],
                    $page['page_title'],
                    $page['menu_title'],
                    $page['capability'],
                    $page['menu_slug'],
                    $callback
                );
            }
            
            // Store hook suffixes for specific pages we need for asset loading
            if ($page['menu_slug'] === 'dm-remote-locations') {
                $this->remote_locations_hook_suffix = $hook_suffix;
            // API Keys page removed - replaced with handler-level configuration via universal modal system
            }
        }
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
        
        $jobs_hooks = [
            'data-machine_page_dm-jobs',
        ];
        
        // Module config hooks removed - page deprecated in favor of pipeline builder
        // Module configuration is now handled through the horizontal pipeline system

        if ( in_array($hook_suffix, $project_management_hooks, true) ) {
            $this->enqueue_project_management_assets();
        } elseif ( in_array($hook_suffix, $jobs_hooks, true) ) {
            $this->enqueue_jobs_assets();
        } elseif ( $hook_suffix === $this->remote_locations_hook_suffix ) {
            $this->enqueue_remote_locations_assets();
        // API Keys page asset enqueuing removed - replaced with handler-level configuration via universal modal system
        }
    }

    private function enqueue_project_management_assets() {
        $plugin_base_path = DATA_MACHINE_PATH;
        $plugin_base_url = plugins_url( '/', 'data-machine/data-machine.php' );
        
        // Enqueue main admin CSS
        $css_path = $plugin_base_path . 'assets/css/data-machine-admin.css';
        $css_url = $plugin_base_url . 'assets/css/data-machine-admin.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
        wp_enqueue_style( 'data-machine-admin', $css_url, array(), $css_version, 'all' );
        
        // Enqueue projects-specific CSS
        $css_projects_path = $plugin_base_path . 'assets/css/admin-projects.css';
        $css_projects_url = $plugin_base_url . 'assets/css/admin-projects.css';
        $css_projects_version = file_exists($css_projects_path) ? filemtime($css_projects_path) : $this->version;
        wp_enqueue_style( 'data-machine-admin-projects', $css_projects_url, array(), $css_projects_version, 'all' );
        $js_project_management_path = $plugin_base_path . 'assets/js/admin/core/data-machine-project-management.js';
        $js_project_management_url = $plugin_base_url . 'assets/js/admin/core/data-machine-project-management.js';
        $js_project_management_version = file_exists($js_project_management_path) ? filemtime($js_project_management_path) : $this->version;
        wp_enqueue_script( 'data-machine-project-management-js', $js_project_management_url, array( 'jquery' ), $js_project_management_version, true );
        
        // Enqueue project editing JavaScript
        $js_project_editing_path = $plugin_base_path . 'assets/js/admin/core/data-machine-project-editing.js';
        $js_project_editing_url = $plugin_base_url . 'assets/js/admin/core/data-machine-project-editing.js';
        $js_project_editing_version = file_exists($js_project_editing_path) ? filemtime($js_project_editing_path) : $this->version;
        wp_enqueue_script( 'data-machine-project-editing-js', $js_project_editing_url, array( 'jquery' ), $js_project_editing_version, true );
        
        // Enqueue pipeline builder JavaScript
        $js_pipeline_builder_path = $plugin_base_path . 'assets/js/admin/pipelines/project-pipeline-builder.js';
        $js_pipeline_builder_url = $plugin_base_url . 'assets/js/admin/pipelines/project-pipeline-builder.js';
        $js_pipeline_builder_version = file_exists($js_pipeline_builder_path) ? filemtime($js_pipeline_builder_path) : $this->version;
        wp_enqueue_script( 'data-machine-pipeline-builder-js', $js_pipeline_builder_url, array( 'jquery', 'jquery-ui-sortable' ), $js_pipeline_builder_version, true );

        // Enqueue pipeline modal JavaScript
        $js_pipeline_modal_path = $plugin_base_path . 'assets/js/admin/pipelines/pipeline-modal.js';
        $js_pipeline_modal_url = $plugin_base_url . 'assets/js/admin/pipelines/pipeline-modal.js';
        $js_pipeline_modal_version = file_exists($js_pipeline_modal_path) ? filemtime($js_pipeline_modal_path) : $this->version;
        wp_enqueue_script( 'data-machine-pipeline-modal-js', $js_pipeline_modal_url, array( 'jquery' ), $js_pipeline_modal_version, true );

        wp_localize_script( 'data-machine-project-management-js', 'dm_project_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'create_project_nonce' => wp_create_nonce( 'dm_create_project_nonce' ),
            'run_now_nonce' => wp_create_nonce( 'dm_run_now_nonce' ),
            'get_schedule_data_nonce' => wp_create_nonce( 'dm_get_schedule_data_nonce' ),
            'edit_schedule_nonce' => wp_create_nonce( 'dm_edit_schedule_nonce' ),
            // Pipeline system nonces and standard WordPress AJAX functionality
            'cron_schedules' => apply_filters('dm_get_constants', null)->get_cron_schedules_for_js(),
        ) );

        // Localize pipeline modal script
        wp_localize_script( 'data-machine-pipeline-modal-js', 'dmPipelineModal', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'get_modal_content_nonce' => wp_create_nonce( 'dm_get_modal_content_nonce' ),
            'save_modal_config_nonce' => wp_create_nonce( 'dm_save_modal_config_nonce' ),
            'strings' => array(
                'configureStep' => __( 'Configure Step', 'data-machine' ),
                'saving' => __( 'Saving...', 'data-machine' ),
                'save' => __( 'Save Configuration', 'data-machine' ),
                'cancel' => __( 'Cancel', 'data-machine' ),
                'close' => __( 'Close', 'data-machine' ),
                'errorLoading' => __( 'Error loading configuration', 'data-machine' ),
                'errorSaving' => __( 'Error saving configuration', 'data-machine' ),
                'configSaved' => __( 'Configuration saved successfully', 'data-machine' ),
            ),
        ) );

        // Localize pipeline builder script
        wp_localize_script( 'data-machine-pipeline-builder-js', 'dmPipelineBuilder', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'get_pipeline_steps_nonce' => wp_create_nonce( 'dm_get_pipeline_steps_nonce' ),
            'add_pipeline_step_nonce' => wp_create_nonce( 'dm_add_pipeline_step_nonce' ),
            'remove_pipeline_step_nonce' => wp_create_nonce( 'dm_remove_pipeline_step_nonce' ),
            'reorder_pipeline_steps_nonce' => wp_create_nonce( 'dm_reorder_pipeline_steps_nonce' ),
            'get_available_step_types_nonce' => wp_create_nonce( 'dm_get_available_step_types_nonce' ),
            'get_dynamic_next_steps_nonce' => wp_create_nonce( 'dm_get_dynamic_next_steps_nonce' ),
            'strings' => array(
                'pipelineSteps' => __( 'Pipeline Steps', 'data-machine' ),
                'loadingSteps' => __( 'Loading pipeline steps...', 'data-machine' ),
                'addStep' => __( 'Add Step', 'data-machine' ),
                'selectStepType' => __( 'Select step type...', 'data-machine' ),
                'inputStep' => __( 'Input Step', 'data-machine' ),
                'aiStep' => __( 'AI Step', 'data-machine' ),
                'outputStep' => __( 'Output Step', 'data-machine' ),
                'handler' => __( 'Handler', 'data-machine' ),
                'selectHandler' => __( 'Select handler...', 'data-machine' ),
                'noHandlerSelected' => __( 'No handler selected', 'data-machine' ),
                'noStepsConfigured' => __( 'No pipeline steps configured. Add a step to get started.', 'data-machine' ),
                'selectStepTypeFirst' => __( 'Please select a step type first.', 'data-machine' ),
                'confirmRemoveStep' => __( 'Are you sure you want to remove this step?', 'data-machine' ),
                'errorLoading' => __( 'Error loading pipeline steps', 'data-machine' ),
                'errorAddingStep' => __( 'Error adding pipeline step', 'data-machine' ),
                'errorRemovingStep' => __( 'Error removing pipeline step', 'data-machine' ),
                'errorReordering' => __( 'Error reordering pipeline steps', 'data-machine' ),
                'errorUpdatingConfig' => __( 'Error updating step configuration', 'data-machine' ),
                'handlerSelected' => __( 'Handler selected', 'data-machine' ),
            ),
        ) );
    }

    /**
     * Enqueue assets for the jobs page.
     *
     * @since NEXT_VERSION
     */
    private function enqueue_jobs_assets() {
        $plugin_base_path = DATA_MACHINE_PATH;
        $plugin_base_url = plugins_url( '/', 'data-machine/data-machine.php' );
        
        // Enqueue main admin CSS
        $css_path = $plugin_base_path . 'assets/css/data-machine-admin.css';
        $css_url = $plugin_base_url . 'assets/css/data-machine-admin.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
        wp_enqueue_style( 'data-machine-admin', $css_url, array(), $css_version, 'all' );
        
        // Enqueue jobs-specific CSS
        $css_jobs_path = $plugin_base_path . 'assets/css/admin-jobs.css';
        $css_jobs_url = $plugin_base_url . 'assets/css/admin-jobs.css';
        $css_jobs_version = file_exists($css_jobs_path) ? filemtime($css_jobs_path) : $this->version;
        wp_enqueue_style( 'data-machine-admin-jobs', $css_jobs_url, array(), $css_jobs_version, 'all' );
        
        // Enqueue jobs JavaScript
        $js_jobs_path = $plugin_base_path . 'assets/js/admin/core/data-machine-jobs.js';
        $js_jobs_url = $plugin_base_url . 'assets/js/admin/core/data-machine-jobs.js';
        $js_jobs_version = file_exists($js_jobs_path) ? filemtime($js_jobs_path) : $this->version;
        wp_enqueue_script( 'data-machine-jobs-js', $js_jobs_url, array( 'jquery' ), $js_jobs_version, true );
    }


    private function enqueue_remote_locations_assets() {
        $plugin_base_path = DATA_MACHINE_PATH;
        $plugin_base_url = plugins_url( '/', 'data-machine/data-machine.php' );
        $css_path = $plugin_base_path . 'assets/css/data-machine-admin.css';
        $css_url = $plugin_base_url . 'assets/css/data-machine-admin.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
        wp_enqueue_style( 'data-machine-admin', $css_url, array(), $css_version, 'all' );
        $js_remote_path = $plugin_base_path . 'assets/js/admin/core/data-machine-remote-locations.js';
        $js_remote_url = $plugin_base_url . 'assets/js/admin/core/data-machine-remote-locations.js';
        $js_remote_version = file_exists($js_remote_path) ? filemtime($js_remote_path) : $this->version;
        wp_enqueue_script( 'dm-remote-locations-admin-js', $js_remote_url, array('jquery'), $js_remote_version, true );
        $remote_locations_params = array(
            'ajax_url' => admin_url('admin-ajax.php')
        );
        wp_localize_script('dm-remote-locations-admin-js', 'dmRemoteLocationsParams', $remote_locations_params);
    }

    // API keys assets enqueuing method removed - replaced with handler-level configuration via universal modal system
} // End class