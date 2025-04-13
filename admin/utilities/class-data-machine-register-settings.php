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
                'title' => __( 'General Settings', 'data-machine' ),
            ),
            'api' => array(
                'id'    => 'api',
                'title' => __( 'API Settings', 'data-machine' ),
            ),
            'scheduling' => array(
                'id'    => 'scheduling',
                'title' => __( 'Scheduling Settings', 'data-machine' ),
            ),
        );

        // Define default fields
        $this->fields = array(
            // General settings
            'enable_plugin' => array(
                'id'          => 'enable_plugin',
                'title'       => __( 'Enable Plugin', 'data-machine' ),
                'callback'    => array( $this, 'checkbox_field_callback' ),
                'section'     => 'general',
                'args'        => array(
                    'label_for' => 'enable_plugin',
                    'desc'      => __( 'Enable or disable the plugin functionality.', 'data-machine' ),
                ),
            ),
            'debug_mode' => array(
                'id'          => 'debug_mode',
                'title'       => __( 'Debug Mode', 'data-machine' ),
                'callback'    => array( $this, 'checkbox_field_callback' ),
                'section'     => 'general',
                'args'        => array(
                    'label_for' => 'debug_mode',
                    'desc'      => __( 'Enable debug mode for additional logging.', 'data-machine' ),
                ),
            ),
            
            // API settings
            'api_key' => array(
                'id'          => 'api_key',
                'title'       => __( 'API Key', 'data-machine' ),
                'callback'    => array( $this, 'text_field_callback' ),
                'section'     => 'api',
                'args'        => array(
                    'label_for' => 'api_key',
                    'desc'      => __( 'Enter your API key.', 'data-machine' ),
                    'class'     => 'regular-text',
                ),
            ),
            'api_endpoint' => array(
                'id'          => 'api_endpoint',
                'title'       => __( 'API Endpoint', 'data-machine' ),
                'callback'    => array( $this, 'text_field_callback' ),
                'section'     => 'api',
                'args'        => array(
                    'label_for' => 'api_endpoint',
                    'desc'      => __( 'Enter the API endpoint URL.', 'data-machine' ),
                    'class'     => 'regular-text',
                ),
            ),
            
            // Scheduling settings
            'enable_scheduling' => array(
                'id'          => 'enable_scheduling',
                'title'       => __( 'Enable Scheduling', 'data-machine' ),
                'callback'    => array( $this, 'checkbox_field_callback' ),
                'section'     => 'scheduling',
                'args'        => array(
                    'label_for' => 'enable_scheduling',
                    'desc'      => __( 'Enable scheduled data machine.', 'data-machine' ),
                ),
            ),
            'schedule_frequency' => array(
                'id'          => 'schedule_frequency',
                'title'       => __( 'Schedule Frequency', 'data-machine' ),
                'callback'    => array( $this, 'select_field_callback' ),
                'section'     => 'scheduling',
                'args'        => array(
                    'label_for' => 'schedule_frequency',
                    'desc'      => __( 'Select how often to run data machine.', 'data-machine' ),
                    'options'   => array(
                        'hourly'     => __( 'Hourly', 'data-machine' ),
                        'twicedaily' => __( 'Twice Daily', 'data-machine' ),
                        'daily'      => __( 'Daily', 'data-machine' ),
                        'weekly'     => __( 'Weekly', 'data-machine' ),
                    ),
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
            'Data_Machine_options',
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

        // Register Instagram OAuth settings for the API / Auth page
        register_setting(
            'dm_api_keys_group',
            'instagram_oauth_client_id'
        );
        register_setting(
            'dm_api_keys_group',
            'instagram_oauth_client_secret'
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
            echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
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
            echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
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
            echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
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
        $sanitized = array();

        if ( ! is_array( $input ) ) {
            return $sanitized;
        }

        // Handle global plugin options (booleans, API key, etc.)
        $global_keys = ['enable_plugin', 'debug_mode', 'enable_scheduling', 'api_key', 'api_endpoint', 'schedule_frequency'];
        foreach ($global_keys as $key) {
            if (isset($input[$key])) {
                switch ($key) {
                    case 'enable_plugin':
                    case 'debug_mode':
                    case 'enable_scheduling':
                        $sanitized[$key] = isset($input[$key]) ? 1 : 0;
                        break;
                    case 'api_key':
                    case 'api_endpoint':
                        $sanitized[$key] = sanitize_text_field($input[$key]);
                        break;
                    case 'schedule_frequency':
                        $valid_options = array('hourly', 'twicedaily', 'daily', 'weekly');
                        $sanitized[$key] = in_array($input[$key], $valid_options) ? $input[$key] : 'daily';
                        break;
                }
            }
        }

        // Sanitize input handler settings
        if (isset($input['data_source_type']) && isset($input['data_source_config'])) {
            $type = $input['data_source_type'];
            $config = $input['data_source_config'][$type] ?? [];
            $handler = $this->locator->get('handler_registry')->get_input_handler_instance($type);
            if ($handler && method_exists($handler, 'sanitize_settings')) {
                $sanitized['data_source_config'][$type] = $handler->sanitize_settings($config);
            }
        }

        // Sanitize output handler settings
        if (isset($input['output_type']) && isset($input['output_config'])) {
            $type = $input['output_type'];
            $config = $input['output_config'][$type] ?? [];
            $handler = $this->locator->get('handler_registry')->get_output_handler_instance($type);
            if ($handler && method_exists($handler, 'sanitize_settings')) {
                $sanitized['output_config'][$type] = $handler->sanitize_settings($config);
            }
        }
        return $sanitized;
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

} // End class 