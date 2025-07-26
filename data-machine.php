<?php
/**
 * Plugin Name:     Data Machine
 * Plugin URI:      https://wordpress.org/plugins/data-machine/
 * Description:     A powerful WordPress plugin that automatically collects data from various sources using OpenAI API, fact-checks it, and publishes the results to multiple platforms including WordPress, Twitter, Facebook, Threads, and Bluesky.
 * Version:         0.1.0
 * Author:          Chris Huber
 * Author URI:      https://chubes.net
 * Text Domain:     data-machine
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'DATA_MACHINE_VERSION', '0.1.0' );

/** Define plugin path constant */
define( 'DATA_MACHINE_PATH', plugin_dir_path( __FILE__ ) );

// Load Composer autoloader and dependencies (includes Action Scheduler)
require_once __DIR__ . '/vendor/autoload.php';

// Load AI HTTP Client library for unified multi-provider AI integration
require_once __DIR__ . '/lib/ai-http-client/ai-http-client.php';

// PSR-4 Autoloading - no manual includes needed
use DataMachine\{DataMachine, Constants, CoreHandlerRegistry};
use DataMachine\Admin\{AdminPage, AdminMenuAssets};
use DataMachine\Admin\OAuth\{Twitter as OAuthTwitter, Reddit as OAuthReddit, Threads as OAuthThreads, Facebook as OAuthFacebook, ApiAuthPage};
use DataMachine\Admin\Projects\{Scheduler, AjaxScheduler, ImportExport, FileUploadHandler, ProjectManagementAjax, PipelineManagementAjax, ModalConfigAjax};
use DataMachine\Admin\ModuleConfig\{RegisterSettings, SettingsFields, ModuleConfigHandler};
use DataMachine\Admin\ModuleConfig\Ajax\{ModuleConfigAjax, RemoteLocationsAjax};
use DataMachine\Admin\RemoteLocations\{RemoteLocationService, FormHandler as RemoteLocationsFormHandler, SyncRemoteLocations};
use DataMachine\Database\{Jobs as DatabaseJobs, Modules as DatabaseModules, Projects as DatabaseProjects, ProcessedItems as DatabaseProcessedItems, RemoteLocations as DatabaseRemoteLocations};
use DataMachine\Engine\{JobCreator, ProcessingOrchestrator, JobStatusManager, ProcessedItemsManager};
use DataMachine\Engine\Filters\{AiResponseParser, PromptBuilder, MarkdownConverter};
use DataMachine\Handlers\{HandlerFactory, HttpService};
use DataMachine\Handlers\Input\Files as InputFiles;
use DataMachine\Helpers\{Logger, MemoryGuard, EncryptionHelper, ActionScheduler, ProjectPromptsService, ProjectPipelineConfigService};
use DataMachine\Contracts\{LoggerInterface, ActionSchedulerInterface};



/**
 * Begins execution of the plugin.
 *
 * @since    0.1.0
 */
function run_data_machine() {
    // Initialize pure filter-based service registry
    \DataMachine\ServiceRegistry::init();
    
    // Admin setup
    if (is_admin()) {
        new ApiAuthPage();
        $logger = apply_filters('dm_get_service', null, 'logger');
        add_action('admin_notices', array($logger, 'display_admin_notices'));
    }

    // Initialize core handler auto-registration system
    CoreHandlerRegistry::init();

    // Register universal 3-step pipeline architecture
    add_filter('dm_register_pipeline_steps', function($steps) {
        return [
            'input' => [
                'class' => 'DataMachine\\Engine\\Steps\\InputStep',
                'next' => 'ai'
            ],
            'ai' => [
                'class' => 'DataMachine\\Engine\\Steps\\AIStep',
                'next' => 'output'
            ],
            'output' => [
                'class' => 'DataMachine\\Engine\\Steps\\OutputStep',
                'next' => null
            ]
        ];
    }, 5);


    // Admin initialization
    new AdminPage();

    // Module handler - uses filter-based service access
    $module_handler = new ModuleConfigHandler();
    $module_handler->init_hooks();


    // Import/export handler
    $import_export_handler = new ImportExport();

    // AJAX handlers
    $module_ajax_handler = new ModuleConfigAjax();
    $dashboard_ajax_handler = new ProjectManagementAjax();
    $ajax_scheduler = new AjaxScheduler();
    $ajax_auth = new \DataMachine\Admin\OAuth\AjaxAuth();
    $remote_locations_ajax_handler = new RemoteLocationsAjax();
    $file_upload_handler = new FileUploadHandler();
    $pipeline_management_ajax_handler = new PipelineManagementAjax();
    $modal_config_ajax_handler = new ModalConfigAjax();
    $modal_config_ajax_handler->init_hooks();

    // --- Instantiate Main Plugin ---
    $register_settings = new RegisterSettings(DATA_MACHINE_VERSION);
    $plugin = new DataMachine();

    // --- Run the Plugin ---
    $plugin->run();

    // Register hooks for AJAX handlers
    add_action( 'wp_ajax_dm_get_module_data', array( $module_ajax_handler, 'get_module_data_ajax_handler' ) );

    add_action( 'wp_ajax_dm_run_now', array( $dashboard_ajax_handler, 'handle_run_now' ) );
    add_action( 'wp_ajax_dm_edit_schedule', array( $dashboard_ajax_handler, 'handle_edit_schedule' ) );
    add_action( 'wp_ajax_dm_get_project_schedule_data', array( $dashboard_ajax_handler, 'handle_get_project_schedule_data' ) );

    // Register async pipeline step hooks dynamically
    $pipeline_steps = apply_filters( 'dm_register_pipeline_steps', [] );
    $orchestrator = apply_filters('dm_get_service', null, 'orchestrator');
    foreach ( $pipeline_steps as $step_name => $step_config ) {
        $hook_name = 'dm_' . $step_name . '_job_event';
        
        // All steps use the unified dynamic orchestrator
        add_action( $hook_name, function( $job_id ) use ( $orchestrator, $step_name ) {
            return $orchestrator->execute_step( $step_name, $job_id );
        }, 10, 1 );
    }

    // Initialize scheduler hooks
    $scheduler = apply_filters('dm_get_service', null, 'scheduler');
    if ($scheduler) {
        $scheduler->init_hooks();
    }

    // Example modal content providers (for demonstration and testing)
    add_filter('dm_get_modal_content', 'dm_register_example_modal_content', 10, 2);
}

/**
 * Example modal content provider for demonstration.
 * Shows how external plugins can register configuration interfaces.
 *
 * @param mixed $content Current content (null if none registered)
 * @param array $context Modal context with project_id, step_id, step_type, modal_type, user_id
 * @return array|mixed Modal content array or original content
 */
function dm_register_example_modal_content($content, $context) {
    if ($content !== null) {
        return $content; // Another provider already handled this
    }

    $step_type = $context['step_type'] ?? '';
    $modal_type = $context['modal_type'] ?? '';

    // Example AI step configuration
    if ($step_type === 'ai' && $modal_type === 'ai_config') {
        return [
            'content' => '
                <div class="dm-ai-config-form">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="ai-step-title">' . esc_html__('Step Title', 'data-machine') . '</label>
                                </th>
                                <td>
                                    <input type="text" id="ai-step-title" name="title" class="regular-text" 
                                           placeholder="' . esc_attr__('e.g., Content Summarizer', 'data-machine') . '" />
                                    <p class="description">' . esc_html__('A descriptive name for this AI processing step.', 'data-machine') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="ai-step-prompt">' . esc_html__('AI Prompt', 'data-machine') . '</label>
                                </th>
                                <td>
                                    <textarea id="ai-step-prompt" name="prompt" rows="6" class="large-text" 
                                              placeholder="' . esc_attr__('Enter the prompt instructions for the AI...', 'data-machine') . '"></textarea>
                                    <p class="description">' . esc_html__('The prompt that will be sent to the AI model for processing.', 'data-machine') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="ai-step-model">' . esc_html__('AI Model', 'data-machine') . '</label>
                                </th>
                                <td>
                                    <select id="ai-step-model" name="model">
                                        <option value="gpt-4">' . esc_html__('GPT-4 (Recommended)', 'data-machine') . '</option>
                                        <option value="gpt-3.5-turbo">' . esc_html__('GPT-3.5 Turbo (Faster)', 'data-machine') . '</option>
                                        <option value="claude-3-sonnet">' . esc_html__('Claude 3 Sonnet', 'data-machine') . '</option>
                                    </select>
                                    <p class="description">' . esc_html__('Select the AI model to use for this step.', 'data-machine') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="ai-step-temperature">' . esc_html__('Temperature', 'data-machine') . '</label>
                                </th>
                                <td>
                                    <input type="number" id="ai-step-temperature" name="temperature" 
                                           min="0" max="2" step="0.1" value="0.7" class="small-text" />
                                    <p class="description">' . esc_html__('Controls randomness: 0 = focused, 2 = creative.', 'data-machine') . '</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            ',
            'show_save_button' => true,
            'data' => [
                'step_type' => $step_type,
                'modal_type' => $modal_type
            ]
        ];
    }

    // Example input/output step configuration 
    if (in_array($step_type, ['input', 'output']) && $modal_type === 'handler_config') {
        $step_label = $step_type === 'input' ? __('Input', 'data-machine') : __('Output', 'data-machine');
        
        return [
            'content' => '
                <div class="dm-handler-config-form">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="handler-type">' . sprintf(esc_html__('%s Handler', 'data-machine'), $step_label) . '</label>
                                </th>
                                <td>
                                    <select id="handler-type" name="handler">
                                        <option value="">' . esc_html__('Select handler...', 'data-machine') . '</option>
                                        ' . ($step_type === 'input' ? '
                                        <option value="files">' . esc_html__('File Upload', 'data-machine') . '</option>
                                        <option value="rss">' . esc_html__('RSS Feed', 'data-machine') . '</option>
                                        <option value="twitter">' . esc_html__('Twitter', 'data-machine') . '</option>
                                        ' : '
                                        <option value="wordpress">' . esc_html__('WordPress Posts', 'data-machine') . '</option>
                                        <option value="twitter">' . esc_html__('Twitter', 'data-machine') . '</option>
                                        <option value="email">' . esc_html__('Email', 'data-machine') . '</option>
                                        ') . '
                                    </select>
                                    <p class="description">' . sprintf(esc_html__('Choose how data will be %s.', 'data-machine'), $step_type === 'input' ? 'collected' : 'output') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="handler-config">' . esc_html__('Configuration', 'data-machine') . '</label>
                                </th>
                                <td>
                                    <textarea id="handler-config" name="config" rows="4" class="large-text" 
                                              placeholder="' . esc_attr__('Handler-specific configuration will appear here...', 'data-machine') . '"></textarea>
                                    <p class="description">' . esc_html__('Configuration options will depend on the selected handler.', 'data-machine') . '</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            ',
            'show_save_button' => true,
            'data' => [
                'step_type' => $step_type,
                'modal_type' => $modal_type
            ]
        ];
    }

    return $content; // Return original if no match
}


// Initialize after plugins_loaded to ensure Action Scheduler is available
add_action('plugins_loaded', 'run_data_machine', 20);


/**
 * Allows JSON file uploads.
 */
function dm_allow_json_upload($mimes) {
    $mimes['json'] = 'application/json';
    return $mimes;
}
add_filter( 'upload_mimes', 'dm_allow_json_upload' );


// Activation and deactivation hooks (if needed)
register_activation_hook( __FILE__, 'activate_data_machine' );
register_deactivation_hook( __FILE__, array( '\\DataMachine\\DataMachine', 'deactivate' ) );

function activate_data_machine() {
	// Action Scheduler is now bundled as a library - no dependency check needed

	// Create/Update tables using static methods where available
	\DataMachine\Database\Projects::create_table();
	\DataMachine\Database\Modules::create_table();
	\DataMachine\Database\Jobs::create_table();
	\DataMachine\Database\RemoteLocations::create_table(); // Add table creation call

	// Instantiate and call instance method for Processed_Items
	$db_processed_items = new \DataMachine\Database\ProcessedItems();
	$db_processed_items->create_table();

	// Set a transient flag for first-time admin notice or setup wizard (optional)
	set_transient( 'dm_activation_notice', true, 5 * MINUTE_IN_SECONDS );
	
	// Clean up legacy active project/module user meta keys
	dm_cleanup_legacy_user_meta();
}

/**
 * Clean up orphaned user meta keys from the legacy active project/module system.
 * This removes the Data_Machine_current_project and Data_Machine_current_module
 * user meta keys that are no longer needed after removing the active selection feature.
 */
function dm_cleanup_legacy_user_meta() {
	global $wpdb;
	
	// Delete legacy user meta keys for active project/module selections
	$legacy_keys = [
		'Data_Machine_current_project',
		'Data_Machine_current_module',
		'auto_data_collection_current_project', // Old plugin name
		'auto_data_collection_current_module'   // Old plugin name
	];
	
	foreach ($legacy_keys as $meta_key) {
		$deleted = $wpdb->delete(
			$wpdb->usermeta,
			['meta_key' => $meta_key],
			['%s']
		);
		
		if ($deleted !== false && $deleted > 0) {
			error_log("Data Machine: Cleaned up {$deleted} orphaned user meta entries for key: {$meta_key}");
		}
	}
}