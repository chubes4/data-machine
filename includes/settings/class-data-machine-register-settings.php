<?php
/**
 * Handles registration of WordPress settings for the Data Machine plugin.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/utilities
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages settings registration.
 */
class Data_Machine_Register_Settings {

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
     * Settings group name.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      string    $settings_group    Settings group name.
     */
    private $settings_group = 'Data_Machine_settings_group';

    /**
     * Settings sections.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      array    $sections    Settings sections.
     */
    private $sections = array();

    /**
     * Settings fields.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      array    $fields    Settings fields.
     */
    private $fields = array();

    /**
     * Initialize the class and set its properties.
     *
     * @since    NEXT_VERSION
     * @param    string                                $version    The plugin version.
     * @param    Data_Machine_Service_Locator  $locator    Service Locator instance.
     */
    public function __construct( $version, Data_Machine_Service_Locator $locator ) {
        $this->version = $version;
        $this->locator = $locator;
        
        // Define default sections
        $this->sections = array(
            'general' => array(
                'id'    => 'general',
                'title' => 'General Settings',
            ),
            'api' => array(
                'id'    => 'api',
                'title' => 'API Settings',
            ),
            'scheduling' => array(
                'id'    => 'scheduling',
                'title' => 'Scheduling Settings',
            ),
        );

        // Define default fields
        $this->fields = array(
            // General settings
            'enable_plugin' => array(
                'id'          => 'enable_plugin',
                'title'       => 'Enable Plugin', // Translate later
                'callback'    => array( $this, 'checkbox_field_callback' ),
                'section'     => 'general',
                'args'        => array(
                    'label_for' => 'enable_plugin',
                    'desc'      => 'Enable or disable the plugin functionality.', // Translate later
                ),
            ),
            'debug_mode' => array(
                'id'          => 'debug_mode',
                'title'       => 'Debug Mode', // Translate later
                'callback'    => array( $this, 'checkbox_field_callback' ),
                'section'     => 'general',
                'args'        => array(
                    'label_for' => 'debug_mode',
                    'desc'      => 'Enable debug mode for additional logging.', // Translate later
                ),
            ),
            
            // API settings
            'api_key' => array(
                'id'          => 'api_key',
                'title'       => 'API Key', // Translate later
                'callback'    => array( $this, 'text_field_callback' ),
                'section'     => 'api',
                'args'        => array(
                    'label_for' => 'api_key',
                    'desc'      => 'Enter your API key.', // Translate later
                    'class'     => 'regular-text',
                ),
            ),
            'api_endpoint' => array(
                'id'          => 'api_endpoint',
                'title'       => 'API Endpoint', // Translate later
                'callback'    => array( $this, 'text_field_callback' ),
                'section'     => 'api',
                'args'        => array(
                    'label_for' => 'api_endpoint',
                    'desc'      => 'Enter the API endpoint URL.', // Translate later
                    'class'     => 'regular-text',
                ),
            ),
            
            // Scheduling settings
            'enable_scheduling' => array(
                'id'          => 'enable_scheduling',
                'title'       => 'Enable Scheduling', // Translate later
                'callback'    => array( $this, 'checkbox_field_callback' ),
                'section'     => 'scheduling',
                'args'        => array(
                    'label_for' => 'enable_scheduling',
                    'desc'      => 'Enable scheduled data machine.', // Translate later
                ),
            ),
            'schedule_frequency' => array(
                'id'          => 'schedule_frequency',
                'title'       => 'Schedule Frequency', // Translate later
                'callback'    => array( $this, 'select_field_callback' ),
                'section'     => 'scheduling',
                'args'        => array(
                    'label_for' => 'schedule_frequency',
                    'desc'      => 'Select how often to run data machine.', // Translate later
                    'options'   => Data_Machine_Constants::CRON_SCHEDULES,
                ),
            ),
        );
    }

    /**
     * Register hooks for settings.
     *
     * @since NEXT_VERSION
     */
    public function init_hooks() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'handle_api_keys_page_user_meta_save' ) );
    }

    /**
     * Register all settings.
     *
     * @since NEXT_VERSION
     */
    public function register_settings() {
        // Register setting
        register_setting(
            $this->settings_group,
            'Data_Machine_options',
            array( $this, 'sanitize_options' )
        );

        // Register sections
        foreach ( $this->sections as $section ) {
            add_settings_section(
                $section['id'],
                __( $section['title'], 'data-machine' ),
                array( $this, 'section_callback' ),
                $this->settings_group
            );
        }

        // Register fields
        foreach ( $this->fields as $field ) {
            add_settings_field(
                $field['id'],
                __( $field['title'], 'data-machine' ), // Translate title here
                $field['callback'],
                $this->settings_group,
                $field['section'],
                $field['args']
            );
        }
        
        // Register the global API key setting for the API Keys page
        register_setting(
            'dm_api_keys_group', // Option group for the page
            'openai_api_key',      // Option name 
            array( $this, 'sanitize_openai_api_key' ) // Sanitize callback
        );

        // Register Instagram OAuth settings for the API / Auth page
        register_setting(
            'dm_api_keys_group',
            'instagram_oauth_client_id'
        );
        register_setting(
            'dm_api_keys_group',
            'instagram_oauth_client_secret'
        );

        // Register Reddit OAuth settings for the API / Auth page (NEW)
        register_setting(
            'dm_api_keys_group',
            'reddit_oauth_client_id',
            ['sanitize_callback' => 'sanitize_text_field'] // Add basic sanitization
        );
        register_setting(
            'dm_api_keys_group',
            'reddit_oauth_client_secret',
            ['sanitize_callback' => 'sanitize_text_field'] // Add basic sanitization
        );
         register_setting(
            'dm_api_keys_group',
            'reddit_developer_username',
            ['sanitize_callback' => 'sanitize_text_field'] // Add basic sanitization
        );

        // Register Twitter App Keys settings
        register_setting(
            'dm_api_keys_group',
            'twitter_api_key',
            ['sanitize_callback' => 'sanitize_text_field']
        );
        register_setting(
            'dm_api_keys_group',
            'twitter_api_secret',
            ['sanitize_callback' => 'sanitize_text_field']
        );

        // Add settings section for API key on the API Keys page
        add_settings_section(
            'api_keys_section',   // ID
            'OpenAI API Key',     // Title
            array( $this, 'print_api_settings_section_info' ), // Callback for section description
            'dm-api-keys'        // Page slug for the page
        );

        // Add settings field for API key on the API Keys page
        add_settings_field(
            'openai_api_key',    // ID
            'API Key',           // Title (simpler as section gives context)
            array( $this, 'openai_api_key_callback' ), // Callback to render the field
            'dm-api-keys',      // Page slug for the page
            'api_keys_section'   // Section ID
        );
    }

    /**
     * Add custom sections.
     *
     * @since NEXT_VERSION
     * @param array $sections Array of sections to add.
     */
    public function add_sections( $sections ) {
        if ( is_array( $sections ) ) {
            $this->sections = array_merge( $this->sections, $sections );
        }
    }

    /**
     * Add custom fields.
     *
     * @since NEXT_VERSION
     * @param array $fields Array of fields to add.
     */
    public function add_fields( $fields ) {
        if ( is_array( $fields ) ) {
            $this->fields = array_merge( $this->fields, $fields );
        }
    }

    /**
     * Section callback.
     *
     * @since NEXT_VERSION
     * @param array $args Section arguments.
     */
    public function section_callback( $args ) {
        $section_id = $args['id'];
        
        // You can add custom content for each section here
        switch ( $section_id ) {
            case 'general':
                echo '<p>' . esc_html__( 'Configure general plugin settings.', 'data-machine' ) . '</p>';
                break;
            case 'api':
                echo '<p>' . esc_html__( 'Configure API connection settings.', 'data-machine' ) . '</p>';
                break;
            case 'scheduling':
                echo '<p>' . esc_html__( 'Configure data machine scheduling.', 'data-machine' ) . '</p>';
                break;
            default:
                break;
        }
    }

    /**
     * Checkbox field callback.
     *
     * @since NEXT_VERSION
     * @param array $args Field arguments.
     */
    public function checkbox_field_callback( $args ) {
        $options = get_option( 'Data_Machine_options', array() );
        $id = $args['label_for'];
        $checked = isset( $options[$id] ) ? $options[$id] : 0;
        
        echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="Data_Machine_options[' . esc_attr( $id ) . ']" value="1" ' . checked( 1, $checked, false ) . '/>';
        
        if ( isset( $args['desc'] ) ) {
            echo '<p class="description">' . esc_html__( $args['desc'], 'data-machine' ) . '</p>'; // Translate and escape desc here
        }
    }

    /**
     * Text field callback.
     *
     * @since NEXT_VERSION
     * @param array $args Field arguments.
     */
    public function text_field_callback( $args ) {
        $options = get_option( 'Data_Machine_options', array() );
        $id = $args['label_for'];
        $value = isset( $options[$id] ) ? $options[$id] : '';
        $class = isset( $args['class'] ) ? $args['class'] : '';
        
        echo '<input type="text" id="' . esc_attr( $id ) . '" name="Data_Machine_options[' . esc_attr( $id ) . ']" value="' . esc_attr( $value ) . '" class="' . esc_attr( $class ) . '" />';
        
        if ( isset( $args['desc'] ) ) {
            echo '<p class="description">' . esc_html__( $args['desc'], 'data-machine' ) . '</p>'; // Translate and escape desc here
        }
    }

    /**
     * Select field callback.
     *
     * @since NEXT_VERSION
     * @param array $args Field arguments.
     */
    public function select_field_callback( $args ) {
        $options = get_option( 'Data_Machine_options', array() );
        $id = $args['label_for'];
        $selected = isset( $options[$id] ) ? $options[$id] : '';
        
        echo '<select id="' . esc_attr( $id ) . '" name="Data_Machine_options[' . esc_attr( $id ) . ']">';
        
        foreach ( $args['options'] as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $selected, false ) . '>' . esc_html( $label ) . '</option>';
        }
        
        echo '</select>';
        
        if ( isset( $args['desc'] ) ) {
            echo '<p class="description">' . esc_html__( $args['desc'], 'data-machine' ) . '</p>'; // Translate and escape desc here
        }
    }

    /**
     * Sanitize options before saving.
     *
     * @since NEXT_VERSION
     * @param array $input The input options to sanitize.
     * @return array Sanitized options.
     */
    public function sanitize_options( $input ) {
        $sanitized_input = array();
        $options = get_option('Data_Machine_options', array());

        foreach ( $this->fields as $field ) {
            $field_id = $field['id'];
            $field_value = isset( $input[$field_id] ) ? $input[$field_id] : null;

            // Perform sanitization based on field type or specific logic
            switch ($field_id) {
                case 'enable_plugin':
                case 'debug_mode':
                case 'enable_scheduling':
                    $sanitized_input[$field_id] = ($field_value === 'on') ? 1 : 0;
                    break;
                case 'api_key':
                case 'api_endpoint':
                    $sanitized_input[$field_id] = sanitize_text_field($field_value);
                    break;
                case 'schedule_frequency':
                    // Sanitize against the allowed keys from constants
                    $valid_options = Data_Machine_Constants::get_project_cron_intervals(); // Use project intervals
                    if ( in_array($field_value, $valid_options) ) {
                        $sanitized_input[$field_id] = $field_value;
                    } else {
                        // Set to default or existing value if invalid
                        $sanitized_input[$field_id] = isset($options[$field_id]) ? $options[$field_id] : 'daily'; 
                    }
                    break;
                default:
                    // Handle other fields or provide a default sanitization
                    $sanitized_input[$field_id] = sanitize_text_field($field_value);
                    break;
            }
        }

        return $sanitized_input;
    }

    /**
     * Get all plugin options.
     *
     * @since NEXT_VERSION
     * @return array Plugin options.
     */
    public function get_options() {
        return get_option( 'Data_Machine_options', array() );
    }

    /**
     * Get a specific option.
     *
     * @since NEXT_VERSION
     * @param string $key     Option key.
     * @param mixed  $default Default value if option doesn't exist.
     * @return mixed Option value or default.
     */
    public function get_option( $key, $default = false ) {
        $options = $this->get_options();
        return isset( $options[$key] ) ? $options[$key] : $default;
    }

    /**
     * Sanitize the OpenAI API Key input.
     *
     * @since    NEXT_VERSION
     * @param    string    $input    The unsanitized input.
     * @return   string              The sanitized input.
     */
    public function sanitize_openai_api_key( $input ) {
        return sanitize_text_field( $input );
    }

    /**
     * Print the API Settings section information.
     *
     * @since    NEXT_VERSION
     */
    public function print_api_settings_section_info() {
        echo '<p>Enter your OpenAI API key below. This key is required for features utilizing OpenAI models.</p>';
    }

    /**
     * OpenAI API Key field callback.
     *
     * @since    NEXT_VERSION
     */
    public function openai_api_key_callback() {
        printf(
            '<input type="text" id="openai_api_key" name="openai_api_key" value="%s" class="regular-text" />',
            esc_attr( get_option( 'openai_api_key' ) )
        );
    }

    /**
     * Handle saving user-specific meta fields from the API Keys page.
     * Hooks into admin_init to catch the POST request before options.php processes it.
     *
     * @since NEXT_VERSION
     */
    public function handle_api_keys_page_user_meta_save() {
        // Check if this is a POST request for the API keys page
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ||
             !isset($_POST['option_page']) || 'dm_api_keys_group' !== $_POST['option_page'] ) {
            return; // Not our form submission
        }

        // Verify the nonce specific to user meta fields on this page
        if ( !isset($_POST['_wpnonce_dm_api_keys_user_meta']) || 
             !wp_verify_nonce($_POST['_wpnonce_dm_api_keys_user_meta'], 'dm_save_api_keys_user_meta') ) {
            // Nonce failed, log or add an error notice? Silently return for now.
            return;
        }

        // Check user capabilities
        if ( !current_user_can('manage_options') ) { // Assuming manage_options is the capability for this page
            return; 
        }

        $user_id = get_current_user_id();
        $updated = false;

        // Handle Bluesky Username
        if ( isset($_POST['bluesky_username']) ) {
            $bluesky_username = sanitize_text_field($_POST['bluesky_username']);
            update_user_meta($user_id, 'dm_bluesky_username', $bluesky_username);
            $updated = true;
        }

        // Handle Bluesky Password (only update if a new password was submitted)
        if ( isset($_POST['bluesky_app_password']) && !empty($_POST['bluesky_app_password']) ) {
            // Encrypt the password before saving
            // Note: Password is not sanitized beyond being non-empty
            try {
                $encrypted_password = Data_Machine_Encryption_Helper::encrypt($_POST['bluesky_app_password']);
                if ($encrypted_password === false) {
                    // Log error, maybe add admin notice
                    error_log('[Data Machine] Failed to encrypt Bluesky app password for user ' . $user_id . ' on API Keys page save.');
                    add_settings_error('Data_Machine_api_keys_messages', 'bluesky_encrypt_fail', __('Failed to encrypt Bluesky app password. It was not saved.', 'data-machine'), 'error');
                } else {
                    update_user_meta($user_id, 'dm_bluesky_app_password', $encrypted_password);
                    $updated = true;
                }
            } catch (\Exception $e) {
                error_log('[Data Machine] Exception encrypting Bluesky app password for user ' . $user_id . ': ' . $e->getMessage());
                 add_settings_error('Data_Machine_api_keys_messages', 'bluesky_encrypt_exception', __('An error occurred while encrypting the Bluesky app password. It was not saved.', 'data-machine'), 'error');
            }
        }

        // Add a success notice if something was updated
        // Note: This might appear alongside the standard "Settings saved." notice from options.php
        // if ($updated) {
        //     add_settings_error('Data_Machine_api_keys_messages', 'user_meta_saved', __('User-specific API settings updated.', 'data-machine'), 'updated');
        // }
    }

} // End class 