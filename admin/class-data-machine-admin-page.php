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

/**
 * The admin-specific functionality of the plugin.
 */
class Data_Machine_Admin_Page {

    /**
     * The plugin version.
     *
     * @since    0.1.0
     * @access   private
     * @var      string    $version    The current plugin version.
     */
    private $version;

    /**
     * Database Modules class instance.
     *
     * @since    0.2.0
     * @access   private
     * @var      Data_Machine_Database_Modules    $db_modules    Database Modules class instance.
     */
    private $db_modules;

    /**
     * Database Projects class instance.
     *
     * @since    0.13.0
     * @access   private
     * @var      Data_Machine_Database_Projects   $db_projects   Database Projects class instance.
     */
    private $db_projects;

    /**
     * Service Locator instance.
     *
     * @since    0.14.0 // Or current version
     * @access   private
     * @var      Data_Machine_Service_Locator    $locator    Service Locator instance.
     */
    private $locator;

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.1.0
     * @param    string                                   $version         The plugin version.
     * @param    Data_Machine_Database_Modules    $db_modules Injected DB Modules instance.
     * @param    Data_Machine_Database_Projects   $db_projects Injected DB Projects instance.
     * @param    Data_Machine_Service_Locator     $locator     Injected Service Locator instance.
     */
    public function __construct( $version, Data_Machine_Database_Modules $db_modules, Data_Machine_Database_Projects $db_projects, Data_Machine_Service_Locator $locator ) {
        $this->version = $version;
        $this->db_modules = $db_modules;
        $this->db_projects = $db_projects;
        $this->locator = $locator; // Store locator

        // Hook the form processing function to the settings page load action
        add_action( 'load-data-machine_page_data-machine-settings-page', array( $this, 'process_settings_page_save' ) );
    }

    /**
     * Processes the form submission from the main settings page.
     * Hooked to 'load-data-machine_page_data-machine-settings-page'.
     *
     * @since NEXT_VERSION
     */
    public function process_settings_page_save() {
        // Only process if our form was submitted
        if ( ! isset( $_POST['dm_save_module_settings_submit'] ) ) {
            return;
        }

        // --- Security Checks ---
        // Nonce check (name matches wp_nonce_field in settings-page.php)
        check_admin_referer( 'dm_save_module_settings_action', '_wpnonce_dm_save_module' );

        // Capability check
        if ( ! current_user_can( 'manage_options' ) ) { // Adjust capability check if needed
            wp_die( __( 'Permission denied.', 'data-machine' ) );
        }

        // --- Get Dependencies ---
        $logger = $this->locator->get('logger');
        $db_modules = $this->locator->get('database_modules'); // Use injected instance
        $handler_registry = $this->locator->get('handler_registry');
        $user_id = get_current_user_id();

        // --- Get Submitted Data ---
        // Module ID ('new' or numeric)
        $submitted_module_id = isset($_POST['Data_Machine_current_module']) ? sanitize_text_field($_POST['Data_Machine_current_module']) : null;
        // Project ID (needed for creating new modules and associating updates)
        $project_id = isset($_POST['Data_Machine_current_project']) ? absint($_POST['Data_Machine_current_project']) : 0;
        // General Module Data
        $module_name = isset($_POST['module_name']) ? sanitize_text_field($_POST['module_name']) : '';
        $process_prompt = isset($_POST['process_data_prompt']) ? wp_kses_post(wp_unslash($_POST['process_data_prompt'])) : '';
        $fact_check_prompt = isset($_POST['fact_check_prompt']) ? wp_kses_post(wp_unslash($_POST['fact_check_prompt'])) : '';
        $finalize_prompt = isset($_POST['finalize_response_prompt']) ? wp_kses_post(wp_unslash($_POST['finalize_response_prompt'])) : '';
        // Selected Handler Slugs
        $data_source_type_slug = isset($_POST['data_source_type']) ? sanitize_key($_POST['data_source_type']) : 'files';
        $output_type_slug = isset($_POST['output_type']) ? sanitize_key($_POST['output_type']) : 'data_export';
        // Raw Config Data (entire arrays)
        $submitted_ds_config_all = $_POST['data_source_config'] ?? [];
        $submitted_output_config_all = $_POST['output_config'] ?? [];

        $logger->info('[Settings Page Save] Starting save process.', [
            'submitted_module_id' => $submitted_module_id,
            'project_id' => $project_id,
            'user_id' => $user_id
        ]);

        // --- Validate required fields ---
        if (empty($submitted_module_id)) {
            $logger->error('[Settings Page Save] Error: Missing module ID.');
            add_settings_error('Data_Machine_messages', 'missing_module_id', __('Module ID is missing.', 'data-machine'), 'error');
            return;
        }
        if ($submitted_module_id === 'new' && empty($project_id)) {
            $logger->error('[Settings Page Save] Error: Missing project ID for new module.');
            add_settings_error('Data_Machine_messages', 'missing_project_id_new', __('Project ID is required to create a new module.', 'data-machine'), 'error');
            return;
        }
        if (empty($module_name)) {
            $logger->error('[Settings Page Save] Error: Missing module name.');
            add_settings_error('Data_Machine_messages', 'missing_module_name', __('Module name is required.', 'data-machine'), 'error');
            return;
        }

        // --- Process Submitted Config Data (Sanitize using handlers) ---
        // We only care about the config for the *selected* handlers
        $sanitized_ds_config_selected = [];
        $sanitized_output_config_selected = [];

        // Process Selected Data Source Config
        if (isset($submitted_ds_config_all[$data_source_type_slug])) {
            $input_handler_class = $handler_registry->get_input_handler_class($data_source_type_slug);
            if ($input_handler_class) {
                try {
                    $input_handler_instance = $this->locator->get('input_' . $data_source_type_slug);
                    if ($input_handler_instance instanceof Data_Machine_Input_Handler_Interface && method_exists($input_handler_instance, 'sanitize_settings')) {
                        $current_handler_submitted_config = $submitted_ds_config_all[$data_source_type_slug] ?? [];
                        $sanitized_ds_config_selected = $input_handler_instance->sanitize_settings($current_handler_submitted_config);
                        $logger->debug('[Settings Page Save] Sanitized input config.', ['slug' => $data_source_type_slug]);
                    } else {
                        $logger->warning('[Settings Page Save] Input handler missing sanitize_settings or wrong type.', ['slug' => $data_source_type_slug]);
                        $sanitized_ds_config_selected = []; // Use empty array if sanitization fails/not possible
                    }
                } catch (\Exception $e) {
                     $logger->error('[Settings Page Save] Error getting/sanitizing input handler.', ['slug' => $data_source_type_slug, 'error' => $e->getMessage()]);
                     $sanitized_ds_config_selected = [];
                }
            } else {
                 $logger->warning('[Settings Page Save] Input handler class not found.', ['slug' => $data_source_type_slug]);
                 $sanitized_ds_config_selected = [];
            }
        } else {
             $logger->debug('[Settings Page Save] No config submitted for selected input handler.', ['slug' => $data_source_type_slug]);
             $sanitized_ds_config_selected = []; // Ensure it's an empty array if nothing was submitted for the selected handler
        }

        // Process Selected Output Config
        if (isset($submitted_output_config_all[$output_type_slug])) {
            $output_handler_class = $handler_registry->get_output_handler_class($output_type_slug);
            if ($output_handler_class) {
                 try {
                    $output_handler_instance = $this->locator->get('output_' . $output_type_slug);
                    if ($output_handler_instance instanceof Data_Machine_Output_Handler_Interface && method_exists($output_handler_instance, 'sanitize_settings')) {
                        $current_handler_submitted_config = $submitted_output_config_all[$output_type_slug] ?? [];
                        $sanitized_output_config_selected = $output_handler_instance->sanitize_settings($current_handler_submitted_config);
                        $logger->debug('[Settings Page Save] Sanitized output config.', ['slug' => $output_type_slug]);
                    } else {
                        $logger->warning('[Settings Page Save] Output handler missing sanitize_settings or wrong type.', ['slug' => $output_type_slug]);
                        $sanitized_output_config_selected = [];
                    }
                 } catch (\Exception $e) {
                      $logger->error('[Settings Page Save] Error getting/sanitizing output handler.', ['slug' => $output_type_slug, 'error' => $e->getMessage()]);
                      $sanitized_output_config_selected = [];
                 }
            } else {
                 $logger->warning('[Settings Page Save] Output handler class not found.', ['slug' => $output_type_slug]);
                 $sanitized_output_config_selected = [];
            }
        } else {
            $logger->debug('[Settings Page Save] No config submitted for selected output handler.', ['slug' => $output_type_slug]);
            $sanitized_output_config_selected = [];
        }

        // --- Build CLEAN Final Configs ---
        // These will ONLY contain the key for the selected handler and its sanitized data.
        $final_clean_ds_config = [ $data_source_type_slug => $sanitized_ds_config_selected ];
        $final_clean_output_config = [ $output_type_slug => $sanitized_output_config_selected ];

        // --- Handle Module Create / Update ---

        // Handle new module creation
        if ($submitted_module_id === 'new') {
            $module_data = array(
                'module_name' => $module_name,
                'process_data_prompt' => $process_prompt,
                'fact_check_prompt' => $fact_check_prompt,
                'finalize_response_prompt' => $finalize_prompt,
                'data_source_type' => $data_source_type_slug,
                'data_source_config' => $final_clean_ds_config, // Use the clean, single-entry array
                'output_type' => $output_type_slug,
                'output_config' => $final_clean_output_config, // Use the clean, single-entry array
            );

            $new_module_id = $db_modules->create_module($project_id, $module_data);

            if ($new_module_id) {
                $logger->info('[Settings Page Save] New module created successfully.', ['new_module_id' => $new_module_id, 'project_id' => $project_id]);
                // Update user meta for current module/project
                update_user_meta($user_id, 'Data_Machine_current_project', $project_id);
                update_user_meta($user_id, 'Data_Machine_current_module', $new_module_id);
                add_settings_error('Data_Machine_messages', 'module_created', __('New module created successfully.', 'data-machine'), 'success');

                // Redirect to the same page but with the new module selected
                // This prevents re-submission on refresh and loads the new module's data
                $redirect_url = add_query_arg( array(
                    'page' => 'data-machine-settings-page', // Ensure this matches the actual page slug
                    'dm_notice_id' => 'module_created' // Optional: pass notice ID for JS focus?
                ), admin_url( 'admin.php' ) );
                wp_safe_redirect( $redirect_url );
                exit;

            } else {
                $logger->error('[Settings Page Save] Failed to create new module in DB.', ['project_id' => $project_id]);
                add_settings_error('Data_Machine_messages', 'create_failed', __('Failed to create new module.', 'data-machine'), 'error');
            }
            return; // Stop execution here for 'new' module case unless redirecting
        }

        // Handle updating an existing module
        $module_id_to_update = absint($submitted_module_id);
        $existing_module = $db_modules->get_module($module_id_to_update, $user_id); // Verify ownership

        if ($existing_module) {
             // PRESERVE existing 'remote_site_info' if the output type is 'publish_remote' and it's not being explicitly changed
             if ($output_type_slug === 'publish_remote') {
                 $existing_output_config_for_check = json_decode($existing_module->output_config ?: '', true) ?: array();
                 if (isset($existing_output_config_for_check['publish_remote']['remote_site_info'])) {
                     // Ensure the key exists in the final config before adding the sub-key
                     if (!isset($final_clean_output_config['publish_remote'])) {
                         $final_clean_output_config['publish_remote'] = [];
                     }
                     // Check if the submitted data *already* included remote_site_info (unlikely, but for safety)
                     if (!isset($final_clean_output_config['publish_remote']['remote_site_info'])) {
                          $final_clean_output_config['publish_remote']['remote_site_info'] = $existing_output_config_for_check['publish_remote']['remote_site_info'];
                          $logger->debug('[Settings Page Save] Preserved existing remote_site_info for publish_remote.');
                     }
                 }
             }

            // Prepare data for potential update - compare submitted values to existing
            $update_data = array();
            if ($module_name !== $existing_module->module_name) $update_data['module_name'] = $module_name;
            if ($process_prompt !== $existing_module->process_data_prompt) $update_data['process_data_prompt'] = $process_prompt;
            if ($fact_check_prompt !== $existing_module->fact_check_prompt) $update_data['fact_check_prompt'] = $fact_check_prompt;
            if ($finalize_prompt !== $existing_module->finalize_response_prompt) $update_data['finalize_response_prompt'] = $finalize_prompt;
            if ($data_source_type_slug !== $existing_module->data_source_type) $update_data['data_source_type'] = $data_source_type_slug;
            if ($output_type_slug !== $existing_module->output_type) $update_data['output_type'] = $output_type_slug;

            // Compare CLEAN final configs (as JSON strings for reliable comparison) to existing configs
            $existing_ds_config_json = $existing_module->data_source_config ?: '{}';
            $existing_output_config_json = $existing_module->output_config ?: '{}';
            $final_clean_ds_config_json = wp_json_encode($final_clean_ds_config);
            $final_clean_output_config_json = wp_json_encode($final_clean_output_config);

             // Check for differences using JSON comparison
            if ($final_clean_ds_config_json !== $existing_ds_config_json) {
                 $update_data['data_source_config'] = $final_clean_ds_config; // Store the array, DB class handles encoding
                 $logger->debug('[Settings Page Save] Detected change in data_source_config.');
            }

            if ($final_clean_output_config_json !== $existing_output_config_json) {
                 $update_data['output_config'] = $final_clean_output_config; // Store the array
                 $logger->debug('[Settings Page Save] Detected change in output_config.');
            }

            // --- Update Database ---
            $updated = false;
            if (!empty($update_data)) {
                $logger->debug('[Settings Page Save] Attempting DB update.', ['update_data_keys' => array_keys($update_data)]);
                $updated = $db_modules->update_module($module_id_to_update, $update_data, $user_id);
                if ($updated === false) {
                    $logger->error('[Settings Page Save] Failed to update module in DB.', ['module_id' => $module_id_to_update]);
                    add_settings_error('Data_Machine_messages', 'update_failed', __('Failed to update module settings.', 'data-machine'), 'error');
                    // No return here, allow meta update below
                }
            }

            // Always update the current module selection in user meta if it was submitted
            // (The dropdown change already handles this via JS/AJAX, but let's be safe)
            if (isset($_POST['Data_Machine_current_module_selector'])) {
                 $selected_module_via_dropdown = sanitize_text_field($_POST['Data_Machine_current_module_selector']);
                 if (absint($selected_module_via_dropdown) === $module_id_to_update || $selected_module_via_dropdown === 'new') { // Ensure consistency
                    update_user_meta($user_id, 'Data_Machine_current_module', $module_id_to_update);
                 }
            }
            // Also update project if selected
             if (isset($_POST['Data_Machine_current_project_selector'])) {
                 update_user_meta($user_id, 'Data_Machine_current_project', $project_id);
             }


            // Add success/no changes notice if no error occurred during update
            if (!isset($update_data['db_error'])) { // Check if we didn't set an error flag
                $message = $updated ? __('Module settings updated successfully.', 'data-machine') : __('Module settings saved (no changes detected).', 'data-machine');
                $notice_type = $updated ? 'success' : 'info'; // Use 'info' for no changes
                add_settings_error('Data_Machine_messages', 'module_updated', $message, $notice_type);
                $logger->info('[Settings Page Save] Update process completed.', ['module_id' => $module_id_to_update, 'changes_made' => (bool)$updated]);
            }


        } else {
            // Invalid module selected or permission denied
            $logger->error('[Settings Page Save] Invalid module ID or permission denied.', ['module_id' => $module_id_to_update, 'user_id' => $user_id]);
            add_settings_error('Data_Machine_messages', 'invalid_module', __('Invalid module selection or permission denied.', 'data-machine'), 'error');
        }
    }

    /**
     * Display the main admin page content.
     */
    public function display_admin_page() {
        // Make locator available to the included template file
        $locator = $this->locator; 
        // Load the template file
        include_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/main-admin-page.php';
    }

    /**
     * Display the settings page content by including the template file.
     */
    public function display_settings_page() {
        // Make locator available to the included template file
        $locator = $this->locator;
        // Load the template file
        include_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/settings-page.php';
    }

    /**
     * Display the project dashboard page content.
     */
    public function display_project_dashboard_page() {
        // Make DB instances available to the included template file
        $db_projects = $this->db_projects;
        $db_modules = $this->db_modules;
        // Load the template file
        include_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/project-dashboard-page.php';
    }

    /**
     * Display the API Keys settings page.
     *
     * @since NEXT_VERSION
     */
    public function display_api_keys_page() {
        // Security check: Ensure user has capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Instagram OAuth flow logic (admin page safe)
        if (isset($_GET['instagram_oauth']) && !isset($_GET['code'])) {
            $client_id = get_option('instagram_oauth_client_id');
            $redirect_uri = admin_url('admin.php?page=dm-api-keys&callback_oauth=1');
            $scope = 'user_profile,user_media';
            $auth_url = 'https://api.instagram.com/oauth/authorize'
                . '?client_id=' . urlencode($client_id)
                . '&redirect_uri=' . urlencode($redirect_uri)
                . '&scope=' . urlencode($scope)
                . '&response_type=code';
            wp_redirect($auth_url);
            exit;
        }

        if (isset($_GET['callback_oauth']) && isset($_GET['code'])) {
            $client_id = get_option('instagram_oauth_client_id');
            $client_secret = get_option('instagram_oauth_client_secret');
            $redirect_uri = admin_url('admin.php?page=dm-api-keys&callback_oauth=1');
            $code = sanitize_text_field($_GET['code']);
            $response = wp_remote_post('https://api.instagram.com/oauth/access_token', [
                'body' => [
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirect_uri,
                    'code' => $code,
                ]
            ]);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!empty($data['access_token']) && !empty($data['user_id'])) {
                $access_token = $data['access_token'];
                $user_id_ig = $data['user_id'];
                // Fetch user info
                $user_info_response = wp_remote_get('https://graph.instagram.com/' . $user_id_ig . '?fields=id,username,account_type,media_count,profile_picture_url&access_token=' . urlencode($access_token));
                $user_info = json_decode(wp_remote_retrieve_body($user_info_response), true);

                if (!empty($user_info['id'])) {
                    $accounts = get_user_meta(get_current_user_id(), 'data_machine_instagram_accounts', true);
                    if (!is_array($accounts)) $accounts = [];
                    $accounts[] = [
                        'id' => $user_info['id'],
                        'username' => $user_info['username'],
                        'profile_pic' => $user_info['profile_picture_url'] ?? '',
                        'access_token' => $access_token,
                        'account_type' => $user_info['account_type'] ?? '',
                        'media_count' => $user_info['media_count'] ?? 0,
                        'expires_at' => isset($data['expires_in']) ? date('Y-m-d H:i:s', time() + intval($data['expires_in'])) : '',
                    ];
                    update_user_meta(get_current_user_id(), 'data_machine_instagram_accounts', $accounts);
                    // Redirect to remove query params
                    wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_success=1'));
                    exit;
                }
            }
            // On error, redirect with error param
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=1'));
            exit;
        }

        // Display the settings page content
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/api-keys-page.php';
    }

    /**
     * Renders the Remote Locations admin page content by loading templates.
     */
    public function display_remote_locations_page() {
        // Get or instantiate the Remote Locations handler
        $remote_locations_handler = $this->locator->get('remote_locations');
        
        // If the handler isn't in the service locator, instantiate it
        if (!$remote_locations_handler) {
            require_once plugin_dir_path(__FILE__) . 'class-data-machine-remote-locations.php';
            $remote_locations_handler = new Data_Machine_Remote_Locations($this->locator);
        }
        
        // Delegate the display logic to the specialized class
        $remote_locations_handler->display_page();
    }

    /**
     * Renders the Jobs List page.
     *
     * @since NEXT_VERSION
     */
    public function display_jobs_page() {
        // Security check
        if (!current_user_can('manage_options')) { // Adjust capability as needed
            wp_die(__( 'Permission denied.', 'data-machine' ));
        }

        // Ensure the List Table class file is loaded
        $list_table_file = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/class-data-machine-jobs-list-table.php';
        if (file_exists($list_table_file)) {
            require_once $list_table_file;
        } else {
            // Handle error - class file missing
            echo '<div class="error"><p>' . __( 'Error: Jobs List Table class file not found.', 'data-machine' ) . '</p></div>';
            return;
        }

        // Create an instance of our package class...
        $jobs_list_table = new Data_Machine_Jobs_List_Table();
        // Fetch, prepare, sort, and filter our data...
        $jobs_list_table->prepare_items();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form method="post">
                <?php // Maybe add nonce fields here if we add bulk actions later ?>
                <?php $jobs_list_table->display(); ?>
            </form>
        </div>
        <?php
    }
}
