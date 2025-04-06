<?php
/**
 * Manages database operations for plugin modules.
 *
 * Handles creating the modules table and performing CRUD operations.
 *
 * @package    Auto_Data_Collection
 * @subpackage Auto_Data_Collection/includes
 * @since      0.2.0
 */
class Auto_Data_Collection_Database_Modules {

    /**
     * The name of the modules database table.
     *
     * @since    0.2.0
     * @access   private
     * @var      string    $table_name    The name of the database table.
     */
    private $table_name;

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.2.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'auto_data_collection_modules';
    }

    /**
     * Create the modules database table on plugin activation.
     *
     * @since    0.2.0
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            module_id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            module_name varchar(255) NOT NULL,
            process_data_prompt longtext DEFAULT NULL,
            fact_check_prompt longtext DEFAULT NULL,
            finalize_json_prompt longtext DEFAULT NULL,
            -- openai_api_key varchar(255) DEFAULT NULL, -- Removed API Key from module table
            created_at timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (module_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        // Add a default module if the table is newly created and empty
        $this->add_default_module_if_needed();
    }

    /**
     * Add a default module for all users if no modules exist.
     *
     * @since 0.2.0
     */
    private function add_default_module_if_needed() {
        global $wpdb;

        // Get current user ID
        $user_id = get_current_user_id();

        // Check if user already has a default module
        $existing_default = $wpdb->get_var( $wpdb->prepare(
            "SELECT module_id FROM $this->table_name WHERE user_id = %d AND module_name = %s",
            $user_id,
            'Default Module'
        ) );

        // Get the user's currently selected module ID *before* potentially creating a new one
        $current_module_id = get_user_meta($user_id, 'auto_data_collection_current_module', true);

        if ( ! $existing_default ) {
            // Create the default module
            $module_id = $this->create_module( $user_id, array(
                'module_name' => 'Default Module',
                'process_data_prompt' => 'The Frankenstein Prompt',
                'fact_check_prompt' => 'Please fact-check the following data:',
                'finalize_json_prompt' => 'Please finalize the JSON output:',
                // 'openai_api_key' => '', // Removed API Key
            ) );

            // If module creation was successful AND the user didn't already have a module selected,
            // set the newly created default module as current.
            if ( $module_id && ! $current_module_id ) {
                update_user_meta($user_id, 'auto_data_collection_current_module', $module_id);
            }
            
            // Set as current module for user
            update_user_meta($user_id, 'auto_data_collection_current_module', $wpdb->insert_id);
        }
    }


    /**
     * Create a new module for a specific user.
     *
     * @since    0.2.0
     * @param    int      $user_id      The ID of the user creating the module.
     * @param    array    $module_data  Associative array of module data.
     * @return   int|false              The ID of the newly created module or false on failure.
     */
    public function create_module( $user_id, $module_data ) {
        global $wpdb;

        $data = array(
            'user_id' => $user_id,
            'module_name' => isset( $module_data['module_name'] ) ? sanitize_text_field( $module_data['module_name'] ) : 'New Module',
            'process_data_prompt' => isset( $module_data['process_data_prompt'] ) ? wp_kses_post( $module_data['process_data_prompt'] ) : '',
            'fact_check_prompt' => isset( $module_data['fact_check_prompt'] ) ? wp_kses_post( $module_data['fact_check_prompt'] ) : '',
            'finalize_json_prompt' => isset( $module_data['finalize_json_prompt'] ) ? wp_kses_post( $module_data['finalize_json_prompt'] ) : '',
            // 'openai_api_key' => isset( $module_data['openai_api_key'] ) ? sanitize_text_field( $module_data['openai_api_key'] ) : '', // Removed API Key
            // created_at and updated_at are handled by the database
        );

        $format = array(
            '%d', // user_id
            '%s', // module_name
            '%s', // process_data_prompt
            '%s', // fact_check_prompt
            '%s', // finalize_json_prompt
            // '%s', // openai_api_key // Removed API Key
        );

        $result = $wpdb->insert( $this->table_name, $data, $format );

        if ( $result ) {
            return $wpdb->insert_id;
        } else {
            // Log error maybe?
            return false;
        }
    }

    /**
     * Retrieve all modules for a specific user.
     *
     * @since    0.2.0
     * @param    int      $user_id      The ID of the user.
     * @return   array|null             An array of module objects or null if none found.
     */
    public function get_modules_for_user( $user_id ) {
        global $wpdb;
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE user_id = %d ORDER BY module_name ASC",
            $user_id
        ) );
        return $results;
    }

    /**
     * Retrieve a specific module by its ID.
     *
     * @since    0.2.0
     * @param    int      $module_id    The ID of the module.
     * @param    int      $user_id      (Optional) The ID of the user to verify ownership.
     * @return   object|null            The module object or null if not found or ownership mismatch.
     */
    public function get_module( $module_id, $user_id = null ) {
        global $wpdb;
        $query = $wpdb->prepare( "SELECT * FROM $this->table_name WHERE module_id = %d", $module_id );

        // If user_id is provided, add it to the query to verify ownership
        if ( $user_id !== null ) {
            $query .= $wpdb->prepare( " AND user_id = %d", $user_id );
        }

        $module = $wpdb->get_row( $query );
        return $module;
    }

    /**
     * Update an existing module.
     *
     * @since    0.2.0
     * @param    int      $module_id    The ID of the module to update.
     * @param    array    $module_data  Associative array of module data to update.
     * @param    int      $user_id      The ID of the user to verify ownership.
     * @return   int|false              The number of rows updated or false on failure or ownership mismatch.
     */
    public function update_module( $module_id, $module_data, $user_id ) {
        global $wpdb;

        // Verify ownership first
        $existing_module = $this->get_module( $module_id, $user_id );
        if ( ! $existing_module ) {
            return false; // Module not found or user doesn't own it
        }

        $data = array();
        $format = array();

        if ( isset( $module_data['module_name'] ) ) {
            $data['module_name'] = sanitize_text_field( $module_data['module_name'] );
            $format[] = '%s';
        }
        if ( isset( $module_data['process_data_prompt'] ) ) {
            $data['process_data_prompt'] = wp_kses_post( $module_data['process_data_prompt'] );
            $format[] = '%s';
        }
        if ( isset( $module_data['fact_check_prompt'] ) ) {
            $data['fact_check_prompt'] = wp_kses_post( $module_data['fact_check_prompt'] );
            $format[] = '%s';
        }
        if ( isset( $module_data['finalize_json_prompt'] ) ) {
            $data['finalize_json_prompt'] = wp_kses_post( $module_data['finalize_json_prompt'] );
            $format[] = '%s';
        }
        // if ( isset( $module_data['openai_api_key'] ) ) { // Removed API Key
        //     $data['openai_api_key'] = sanitize_text_field( $module_data['openai_api_key'] );
        //     $format[] = '%s';
        // }

        if ( empty( $data ) ) {
            return false; // Nothing to update
        }

        // updated_at is handled automatically by the database

        $where = array( 'module_id' => $module_id, 'user_id' => $user_id );
        $where_format = array( '%d', '%d' );

        $result = $wpdb->update( $this->table_name, $data, $where, $format, $where_format );

        return $result;
    }

    /**
     * Delete a module.
     *
     * @since    0.2.0
     * @param    int      $module_id    The ID of the module to delete.
     * @param    int      $user_id      The ID of the user to verify ownership.
     * @return   int|false              The number of rows deleted or false on failure or ownership mismatch.
     */
    public function delete_module( $module_id, $user_id ) {
        global $wpdb;

        // Verify ownership first
        $existing_module = $this->get_module( $module_id, $user_id );
        if ( ! $existing_module ) {
            return false; // Module not found or user doesn't own it
        }

        // Prevent deleting the last module for a user? Maybe add later.

        $where = array( 'module_id' => $module_id, 'user_id' => $user_id );
        $where_format = array( '%d', '%d' );

        $result = $wpdb->delete( $this->table_name, $where, $where_format );

        return $result;
    }

}