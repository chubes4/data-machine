<?php
/**
 * Manages database operations for plugin projects.
 *
 * Handles creating the projects table and performing CRUD operations.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/database
 * @since      0.12.0 // Or next version
 */
class Data_Machine_Database_Projects {

    /**
     * The name of the projects database table.
     * @var string
     */
    private $table_name;

    /**
     * Initialize the class.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_projects';
    }

    /**
     * Create a new project for a user.
     *
     * @since 0.13.0 // or appropriate version
     * @param int    $user_id       The ID of the user creating the project.
     * @param string $project_name  The name of the project.
     * @param array  $project_data Optional. Additional data for the project (e.g., schedule settings).
     * @return int|false The ID of the newly created project or false on failure.
     */
    public function create_project( $user_id, $project_name, $project_data = array() ) {
        global $wpdb;

        if ( empty( $user_id ) || empty( $project_name ) ) {
            		// Debug logging removed for production
            return false;
        }

        // Prepare data for insertion
        $data = array(
            'user_id'      => absint( $user_id ),
            'project_name' => sanitize_text_field( $project_name ),
            // Use provided schedule data or fall back to defaults
            'schedule_interval' => isset( $project_data['schedule_interval'] ) ? sanitize_text_field( $project_data['schedule_interval'] ) : 'daily',
            'schedule_status'   => isset( $project_data['schedule_status'] ) ? sanitize_text_field( $project_data['schedule_status'] ) : 'paused',
            'created_at'   => current_time( 'mysql', 1 ), // GMT time
            'updated_at'   => current_time( 'mysql', 1 ), // GMT time
        );

        // Define formats for the data
        $format = array(
            '%d', // user_id
            '%s', // project_name
            '%s', // schedule_interval
            '%s', // schedule_status
            '%s', // created_at
            '%s', // updated_at
        );

        // Insert the project into the database
        $inserted = $wpdb->insert( $this->table_name, $data, $format );

        if ( false === $inserted ) {
            			// Debug logging removed for production
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Retrieve all projects for a specific user.
     *
     * @param int $user_id The ID of the user.
     * @return array|null An array of project objects or null if none found.
     */
    public function get_projects_for_user( $user_id ) {
        global $wpdb;
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE user_id = %d ORDER BY project_name ASC",
            absint( $user_id )
        ) );
        return $results;
    }

    /**
     * Retrieve a specific project by its ID.
     *
     * @param int      $project_id The ID of the project.
     * @param int|null $user_id    (Optional) The ID of the user to verify ownership.
     * @return object|null         The project object or null if not found or ownership mismatch.
     */
    public function get_project( $project_id, $user_id = null ) {
        global $wpdb;
        $query = $wpdb->prepare( "SELECT * FROM $this->table_name WHERE project_id = %d", absint( $project_id ) );

        if ( $user_id !== null ) {
            $query .= $wpdb->prepare( " AND user_id = %d", absint( $user_id ) );
        }

        $project = $wpdb->get_row( $query );
        return $project;
    }

    /**
     * Update the schedule interval and status for a specific project.
     *
     * @param int    $project_id The ID of the project to update.
     * @param string $interval   The new schedule interval (e.g., 'hourly', 'manual').
     * @param string $status     The new schedule status ('active', 'paused').
     * @param int    $user_id    The ID of the user making the change (for ownership check).
     * @return bool|int          Number of rows updated (usually 1) or false on failure/permission error.
     */
    public function update_project_schedule( $project_id, $interval, $status, $user_id ) {
        global $wpdb;

        // +++ DETAILED DEBUG LOGGING +++
        		// Debug logging removed for production

        // Validate interval and status against allowed values if needed
        $allowed_intervals = Data_Machine_Constants::get_project_cron_intervals();
        $allowed_statuses = ['active', 'paused'];
        $is_valid_interval = in_array($interval, $allowed_intervals);
        $is_valid_status = in_array($status, $allowed_statuses);
        		// Debug logging removed for production
        // +++ END DETAILED DEBUG LOGGING +++

        if ( !$is_valid_interval || !$is_valid_status ) {
            		// Debug logging removed for production
            return false;
        }

        // Update the project WHERE project_id matches AND user_id matches (ensures ownership)
        $updated = $wpdb->update(
            $this->table_name,
            array( // Data to update
                'schedule_interval' => $interval,
                'schedule_status' => $status,
                // updated_at is handled by DB trigger/default
            ),
            array( // WHERE clause
                'project_id' => absint( $project_id ),
                'user_id' => absint( $user_id )
            ),
            array( // Data format
                '%s', // interval
                '%s'  // status
            ),
            array( // WHERE format
                '%d',
                '%d'
            )
        );

        // +++ DETAILED DEBUG LOGGING +++
        		// Debug logging removed for production
        // +++ END DETAILED DEBUG LOGGING +++

        if ( false === $updated ) {
            		// Debug logging removed for production
            return false;
        }

        		// Debug logging removed for production
        return $updated; // Returns number of rows affected (0 or 1 usually)
    }

    /**
     * Update the last run timestamp for a project.
     *
     * @param int $project_id The ID of the project.
     * @return bool True on success, false on failure.
     */
    public function update_project_last_run( $project_id ) {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table_name,
            array(
                'last_run_at' => current_time( 'mysql', 1 ) // Use GMT time
            ),
            array(
                'project_id' => absint( $project_id )
            ),
            array('%s'), // Format for data
            array('%d')  // Format for WHERE
        );

        if ( false === $updated ) {
            		// Debug logging removed for production
            return false;
        }
        return true;
    }

    /**
     * Create or update the projects database table on plugin activation.
     *
     * @since 0.13.0 // Version where this method was added
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dm_projects';
        $charset_collate = $wpdb->get_charset_collate();

        // We need dbDelta()
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // Define the SQL for the projects table
        // Comments are kept outside the SQL string itself
        $sql = "CREATE TABLE $table_name (
            project_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            project_name varchar(255) NOT NULL DEFAULT '',
            project_prompt text NULL,
            schedule_interval varchar(50) NOT NULL DEFAULT 'manual',
            schedule_status varchar(20) NOT NULL DEFAULT 'paused',
            last_run_at datetime NULL DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (project_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Execute the SQL query using dbDelta
        dbDelta( $sql );
    }

    /**
     * Delete a specific project, verifying user ownership.
     *
     * @param int $project_id The ID of the project to delete.
     * @param int $user_id    The ID of the user attempting the deletion.
     * @return int|false The number of rows deleted (should be 1) or false on failure/permission error.
     */
    public function delete_project( $project_id, $user_id ) {
        global $wpdb;

        $project_id = absint( $project_id );
        $user_id = absint( $user_id );

        if ( empty( $project_id ) || empty( $user_id ) ) {
            		// Debug logging removed for production
            return false;
        }

        // Delete the project WHERE project_id and user_id match
        $deleted = $wpdb->delete(
            $this->table_name,
            array( 
                'project_id' => $project_id,
                'user_id'    => $user_id
            ), // WHERE clause
            array( 
                '%d', // Format for project_id
                '%d'  // Format for user_id
            ) // Format for WHERE clause
        );

        // $wpdb->delete returns number of rows affected or false on error.
        if ( false === $deleted ) {
            		// Debug logging removed for production
        } elseif ( $deleted === 0 ) {
            // This might happen if the project existed but didn't belong to the user, or was already deleted.
		// Debug logging removed for production;
             // We might still return false here as the intended action didn't complete as expected.
             return false; 
        }

        return $deleted; // Returns 1 on success, 0 if no match, false on error
    }

} // End class