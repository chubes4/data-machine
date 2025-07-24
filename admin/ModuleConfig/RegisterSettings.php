<?php
/**
 * Handles registration of WordPress settings for the Data Machine plugin.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/module-config
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin\ModuleConfig;

use DataMachine\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages settings registration.
 */
class RegisterSettings {

    /**
     * The plugin version.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      string    $version    The current plugin version.
     */
    private $version;

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
     * Admin notices.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      object    $admin_notices    Admin notices object.
     */
    private $admin_notices;

    /**
     * Initialize the class and set its properties.
     *
     * @since    NEXT_VERSION
     * @param    string                                $version    The plugin version.
     * @param    object                                $admin_notices    Admin notices object.
     */
    public function __construct( $version, $admin_notices = null ) {
        $this->version = $version;
        $this->admin_notices = $admin_notices;
        
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
                    'options'   => Constants::CRON_SCHEDULES,
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
            'dm_options',
            array( $this, 'sanitize_options' )
        );

        // Register sections
        foreach ( $this->sections as $section ) {
            add_settings_section(
                $section['id'],
                $section['title'],
                array( $this, 'section_callback' ),
                $this->settings_group
            );
        }

        // Register fields
        foreach ( $this->fields as $field ) {
            add_settings_field(
                $field['id'],
                $field['title'],
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


        // Register Threads App settings
        register_setting(
            'dm_api_keys_group',
            'threads_app_id',
            ['sanitize_callback' => 'sanitize_text_field']
        );
        register_setting(
            'dm_api_keys_group',
            'threads_app_secret',
            ['sanitize_callback' => 'sanitize_text_field']
        );

        // Register Facebook App settings
        register_setting(
            'dm_api_keys_group',
            'facebook_app_id',
            ['sanitize_callback' => 'sanitize_text_field']
        );
        register_setting(
            'dm_api_keys_group',
            'facebook_app_secret',
            ['sanitize_callback' => 'sanitize_text_field']
        );

        // Register Bluesky settings
        register_setting(
            'dm_api_keys_group',
            'bluesky_username',
            ['sanitize_callback' => 'sanitize_text_field']
        );
        register_setting(
            'dm_api_keys_group',
            'bluesky_app_password',
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
        $options = get_option( 'dm_options', array() );
        $id = $args['label_for'];
        $checked = isset( $options[$id] ) ? $options[$id] : 0;
        
        echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="Data_Machine_options[' . esc_attr( $id ) . ']" value="1" ' . checked( 1, $checked, false ) . '/>';
        
        if ( isset( $args['desc'] ) ) {
            echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>'; // Escape desc here
        }
    }

    /**
     * Text field callback.
     *
     * @since NEXT_VERSION
     * @param array $args Field arguments.
     */
    public function text_field_callback( $args ) {
        $options = get_option( 'dm_options', array() );
        $id = $args['label_for'];
        $value = isset( $options[$id] ) ? $options[$id] : '';
        $class = isset( $args['class'] ) ? $args['class'] : '';
        
        echo '<input type="text" id="' . esc_attr( $id ) . '" name="Data_Machine_options[' . esc_attr( $id ) . ']" value="' . esc_attr( $value ) . '" class="' . esc_attr( $class ) . '" />';
        
        if ( isset( $args['desc'] ) ) {
            echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>'; // Escape desc here
        }
    }

    /**
     * Select field callback.
     *
     * @since NEXT_VERSION
     * @param array $args Field arguments.
     */
    public function select_field_callback( $args ) {
        $options = get_option( 'dm_options', array() );
        $id = $args['label_for'];
        $selected = isset( $options[$id] ) ? $options[$id] : '';
        
        echo '<select id="' . esc_attr( $id ) . '" name="Data_Machine_options[' . esc_attr( $id ) . ']">';
        
        foreach ( $args['options'] as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $selected, false ) . '>' . esc_html( $label ) . '</option>';
        }
        
        echo '</select>';
        
        if ( isset( $args['desc'] ) ) {
            echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>'; // Escape desc here
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
        $options = get_option('dm_options', array());

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
                    $valid_options = Constants::get_project_cron_intervals(); // Use project intervals
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
        return get_option( 'dm_options', array() );
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

} // End class 