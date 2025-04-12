<?php
/**
 * Manages database operations for plugin modules.
 *
 * Handles creating the modules table and performing CRUD operations.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/database
 * @since      0.2.0
 */
class Data_Machine_Database_Modules {

    /**
     * The name of the modules database table.
     *
     * @since    0.2.0
     * @access   private
     * @var      string    $table_name    The name of the database table.
     */
    private $table_name;

    /** @var Data_Machine_Service_Locator */
    private $locator;

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.2.0
     */
    public function __construct(Data_Machine_Service_Locator $locator) {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_modules';
        $this->locator = $locator;
    }

    /**
     * Create the modules database table on plugin activation.
     *
     * @since    0.2.0
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dm_modules';
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $sql = "CREATE TABLE $table_name (
            module_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            module_name varchar(255) NOT NULL DEFAULT '',
            process_data_prompt longtext DEFAULT NULL,
            fact_check_prompt longtext DEFAULT NULL,
            finalize_response_prompt longtext DEFAULT NULL,
            data_source_type varchar(50) DEFAULT 'files' NOT NULL,
            data_source_config longtext DEFAULT NULL,
            output_type varchar(50) DEFAULT 'data_export' NOT NULL,
            output_config longtext DEFAULT NULL,
            schedule_interval varchar(50) NOT NULL DEFAULT 'project_schedule',
            schedule_status varchar(20) NOT NULL DEFAULT 'active',
            last_run_at datetime NULL DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (module_id),
            KEY project_id (project_id)
        ) $charset_collate;";

        dbDelta( $sql );

    }



    /**
     * Create a new module associated with a project.
     *
     * @since    0.2.0 (Refactored 0.12.0)
     * @param    int      $project_id   The ID of the project this module belongs to.
     * @param    array    $module_data  Associative array of module data.
     * @return   int|false              The ID of the newly created module or false on failure.
     */
    public function create_module( $project_id, $module_data ) {
        global $wpdb;

        // Ensure project_id is valid
        if ( empty( $project_id ) || ! is_numeric( $project_id ) ) {
        	// Use logger if available
        	if (class_exists('Data_Machine_Service_Locator') && isset($this->locator)) {
        	    $logger = $this->locator->get('logger');
        	    if ($logger) {
        	        $logger->error('Failed to create module: Invalid project_id', [
        	            'project_id' => $project_id,
        	            'module_data' => $module_data
        	        ]);
        	    }
        	}
        	// Fallback to error_log
        	error_log( 'Data Machine: Attempted to create module with invalid project_id: ' . $project_id );
        	return false;
        }
      
        $data = array(
        	'project_id' => absint( $project_id ), // Use project_id
        	// 'user_id' removed
            'module_name' => isset( $module_data['module_name'] ) ? sanitize_text_field( $module_data['module_name'] ) : 'New Module',
            'process_data_prompt' => isset( $module_data['process_data_prompt'] ) ? wp_kses_post( $module_data['process_data_prompt'] ) : '',
            'fact_check_prompt' => isset( $module_data['fact_check_prompt'] ) ? wp_kses_post( $module_data['fact_check_prompt'] ) : '',
            'finalize_response_prompt' => isset( $module_data['finalize_response_prompt'] ) ? wp_kses_post( $module_data['finalize_response_prompt'] ) : '',
            'data_source_type' => isset( $module_data['data_source_type'] ) ? sanitize_text_field( $module_data['data_source_type'] ) : 'files', // Default to files
            'data_source_config' => isset( $module_data['data_source_config'] ) ? wp_json_encode( $module_data['data_source_config'] ) : null, // Store config as JSON
            'output_type' => isset( $module_data['output_type'] ) ? sanitize_text_field( $module_data['output_type'] ) : 'data_export', // Default to data
            'output_config' => isset( $module_data['output_config'] ) ? wp_json_encode( $module_data['output_config'] ) : null, // Store config as JSON
            'schedule_interval' => isset( $module_data['schedule_interval'] ) ? sanitize_text_field( $module_data['schedule_interval'] ) : 'manual',
            'schedule_status' => isset( $module_data['schedule_status'] ) ? sanitize_text_field( $module_data['schedule_status'] ) : 'paused',
        );

        $format = array(
        	'%d', // project_id
        	// '%d', // user_id format removed
            '%s', // module_name
            '%s', // process_data_prompt
            '%s', // fact_check_prompt
            '%s', // finalize_response_prompt
            '%s', // data_source_type
            '%s', // data_source_config (JSON string)
            '%s', // output_type
            '%s', // output_config (JSON string)
            '%s', // schedule_interval
            '%s', // schedule_status
        );

        $result = $wpdb->insert( $this->table_name, $data, $format );

        if ( $result ) {
            return $wpdb->insert_id;
        } else {
            // --- Add Logging Here ---
            error_log( 'Data Machine: Failed to insert new module. DB Error: ' . $wpdb->last_error );
            // --- End Logging ---
            return false;
        }
    }

    /**
     * Retrieve all modules for a specific project, verifying user ownership.
     *
     * @since    0.2.0 (Refactored 0.12.0)
     * @param    int      $project_id   The ID of the project.
     * @param    int      $user_id      The ID of the user requesting the modules.
     * @return   array|null             An array of module objects or null if none found or user doesn't own project.
     */
    public function get_modules_for_project( $project_id, $user_id ) {
    	global $wpdb;
   
    	// First, verify the user owns the project
    	// Note: Assumes Database_Projects class is available/loaded.
    	// Ideally, this dependency would be injected or retrieved via locator.
    	if (!class_exists('Data_Machine_Database_Projects')) {
    		require_once __DIR__ . '/class-database-projects.php';
    	}
    	$db_projects = new Data_Machine_Database_Projects();
    	$project = $db_projects->get_project( $project_id, $user_id );
   
    	if ( ! $project ) {
    		return null; // Project not found or user doesn't own it
    	}
   
    	// User owns the project, now get the modules
    	$results = $wpdb->get_results( $wpdb->prepare(
    		"SELECT * FROM $this->table_name WHERE project_id = %d ORDER BY module_name ASC",
    		absint( $project_id )
    	) );
    	return $results;
    }

    /**
     * Retrieve a specific module by its ID.
     *
     * @since    0.2.0
     * @param    int      $module_id    The ID of the module.
     * @return   object|null            The module object or null if not found.
     */
    public function get_module( $module_id ) { // Removed $user_id parameter
     global $wpdb;
     // Query only by module_id
     $query = $wpdb->prepare( "SELECT * FROM $this->table_name WHERE module_id = %d", absint( $module_id ) );

        $module = $wpdb->get_row( $query );
        return $module;
    }

    /**
     * Update an existing module.
     *
     * @since    0.2.0 (Refactored 0.12.0)
     * @param    int      $module_id    The ID of the module to update.
     * @param    array    $module_data  Associative array of module data to update.
     * @param    int      $user_id      The ID of the user attempting the update (for ownership check).
     * @return   int|false              The number of rows updated or false on failure or ownership mismatch.
     */
    public function update_module( $module_id, $module_data, $user_id ) {
    	global $wpdb;
   
    	// Get the module first to find its project_id
    	$existing_module = $this->get_module( $module_id );
    	if ( ! $existing_module || ! isset( $existing_module->project_id ) ) {
    		return false; // Module not found or missing project ID
    	}
   
    	// Verify the user owns the project associated with the module
    	// Note: Assumes Database_Projects class is available/loaded.
    	if ( ! class_exists( 'Data_Machine_Database_Projects' ) ) {
    		require_once __DIR__ . '/class-database-projects.php';
    	}
    	$db_projects = new Data_Machine_Database_Projects();
    	$project     = $db_projects->get_project( $existing_module->project_id, $user_id );
   
    	if ( ! $project ) {
    		return false; // User doesn't own the project this module belongs to
    	}
   
    	// Prepare data for update
    	$data   = array();
    	$format = array();
   
    	$fields_to_update = [
    		'module_name'              => '%s',
    		'process_data_prompt'      => '%s',
    		'fact_check_prompt'        => '%s',
    		'finalize_response_prompt' => '%s',
    		'data_source_type'         => '%s',
    		'data_source_config'       => '%s', // Expects JSON encoded string
    		'output_type'              => '%s',
    		'output_config'            => '%s', // Expects JSON encoded string
    	];
   
    	foreach ( $fields_to_update as $field => $fmt ) {
    		if ( isset( $module_data[ $field ] ) ) {
    			$value = $module_data[ $field ];
    			// Sanitize based on field type
    			if ( in_array( $field, [ 'data_source_config', 'output_config' ] ) ) {
    				// Assume it's already a correctly structured array/object for encoding
    				$data[ $field ] = wp_json_encode( $value );
    			} elseif ( in_array( $field, [ 'process_data_prompt', 'fact_check_prompt', 'finalize_response_prompt' ] ) ) {
    				$data[ $field ] = wp_kses_post( $value );
    			} else {
    				$data[ $field ] = sanitize_text_field( $value );
    			}
    			$format[] = $fmt;
    		}
    	}
   
    	if ( empty( $data ) ) {
    		return 0; // Nothing to update, return 0 rows affected
    	}
   
    	// updated_at is handled automatically by the database
   
    	// Update based only on module_id, ownership already verified
    	$where        = array( 'module_id' => $module_id );
    	$where_format = array( '%d' );
   
    	$result = $wpdb->update( $this->table_name, $data, $where, $format, $where_format );
   
    	// Log errors on failure
    	if ( $result === false ) {
    		error_log( 'Data Machine: Failed to update module (ID: ' . $module_id . '). DB Error: ' . $wpdb->last_error );
    	}
   
    	return $result; // Returns number of rows affected or false on error
    }

    /**
     * Delete a module.
     *
     * @since    0.2.0 (Refactored 0.12.0)
     * @param    int      $module_id    The ID of the module to delete.
     * @param    int      $user_id      The ID of the user attempting the delete (for ownership check).
     * @return   int|false              The number of rows deleted or false on failure or ownership mismatch.
     */
    public function delete_module( $module_id, $user_id ) {
    	global $wpdb;
   
    	// Get the module first to find its project_id
    	$existing_module = $this->get_module( $module_id );
    	if ( ! $existing_module || ! isset( $existing_module->project_id ) ) {
    		return false; // Module not found or missing project ID
    	}
   
    	// Verify the user owns the project associated with the module
    	if ( ! class_exists( 'Data_Machine_Database_Projects' ) ) {
    		require_once __DIR__ . '/class-database-projects.php';
    	}
    	$db_projects = new Data_Machine_Database_Projects();
    	$project     = $db_projects->get_project( $existing_module->project_id, $user_id );
   
    	if ( ! $project ) {
    		return false; // User doesn't own the project this module belongs to
    	}
   
    	// Prevent deleting the last module for a user? Maybe add later.
   
    	// Delete based only on module_id, ownership already verified
    	$where        = array( 'module_id' => $module_id );
    	$where_format = array( '%d' );
   
    	$result = $wpdb->delete( $this->table_name, $where, $where_format );
   
    	// Log errors on failure
    	if ( $result === false ) {
    		error_log( 'Data Machine: Failed to delete module (ID: ' . $module_id . '). DB Error: ' . $wpdb->last_error );
    	}
   
    	return $result; // Returns number of rows affected or false on error
    }

    /**
     * Delete all modules associated with a specific project ID.
     *
     * @param int $project_id The ID of the project whose modules should be deleted.
     * @return int|false The number of rows deleted, or false on error.
     */
    public function delete_modules_for_project( $project_id ) {
        global $wpdb;

        $project_id = absint( $project_id );
        if ( empty( $project_id ) ) {
            error_log("Data Machine DB Modules: Invalid project_id provided to delete_modules_for_project.");
            return false;
        }

        // No ownership check here as it's assumed the calling function verified project ownership.
        $deleted = $wpdb->delete(
            $this->table_name,
            array( 'project_id' => $project_id ), // WHERE clause
            array( '%d' )                      // Format for WHERE clause
        );

        // $wpdb->delete returns number of rows affected or false on error.
        if ( false === $deleted ) {
             error_log( 'Data Machine DB Modules: Failed to delete modules for project ID: ' . $project_id . '. DB Error: ' . $wpdb->last_error );
        }

        return $deleted; 
    }

    /**
     * Update schedule settings for a specific module.
     *
     * @param int    $module_id  The ID of the module to update.
     * @param string $interval   The new schedule interval.
     * @param string $status     The new schedule status.
     * @param int    $user_id    The ID of the user performing the update (for ownership check).
     * @return int|false Number of rows updated or false on failure.
     */
    public function update_module_schedule($module_id, $interval, $status, $user_id) {
        global $wpdb;

        // Validate interval and status against allowed values if needed
        $allowed_intervals = ['project_schedule', 'manual', 'every_5_minutes', 'hourly', 'twicedaily', 'daily', 'weekly'];
        $allowed_statuses = ['active', 'paused'];
        if ( !in_array($interval, $allowed_intervals) || !in_array($status, $allowed_statuses) ) {
            error_log('Data Machine DB Modules: Invalid interval or status provided for update_module_schedule.');
            return false;
        }

        // Need to verify ownership before update. Get the project_id first.
        $module = $this->get_module( $module_id );
        if (!$module || !isset($module->project_id)) {
             error_log("Data Machine DB Modules: Cannot update schedule, module {$module_id} not found.");
             return false; // Module not found
        }
        // Check if user owns the project this module belongs to
        $db_projects = $this->locator->get('database_projects'); // Assumes locator is available (needs injection)
        if (!$db_projects || !$db_projects->get_project($module->project_id, $user_id)) {
             error_log("Data Machine DB Modules: Permission denied to update schedule for module {$module_id}.");
             return false; // Permission denied
        }

        // Perform the update
        $updated = $wpdb->update(
            $this->table_name,
            [
                'schedule_interval' => $interval,
                'schedule_status' => $status,
            ],
            ['module_id' => absint($module_id)],
            ['%s', '%s'], // Format for data
            ['%d']  // Format for WHERE
        );

        if ( false === $updated ) {
            error_log( 'Data Machine DB Modules: Failed to update schedule for module ID: ' . $module_id . '. DB Error: ' . $wpdb->last_error );
            return false;
        }

        return $updated;
    }

}