<?php
/**
 * Manages database operations for processing jobs.
 *
 * Handles creating and potentially updating/fetching job records.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/database
 * @since      0.13.0 // Or appropriate version
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Data_Machine_Database_Jobs {

    /**
     * The name of the jobs database table.
     * @var string
     */
    private $table_name;

    /**
     * Optional reference to the projects database class.
     * @var Data_Machine_Database_Projects|null
     */
    private $db_projects;

    /**
     * Initialize the class.
     * @param Data_Machine_Database_Projects|null $db_projects Optional projects DB for updating last_run_at.
     */
    public function __construct($db_projects = null) {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_jobs';
        $this->db_projects = $db_projects;
    }

    /**
     * Create a new job record in the database.
     *
     * @param int    $module_id          The ID of the module this job is for.
     * @param int    $user_id            The ID of the user initiating the job.
     * @param string $module_config_json JSON string of the simplified module configuration for the job.
     * @param string|null $input_data_json JSON string of the input data packet, or null if not applicable initially.
     * @return int|false                 The ID of the newly created job or false on failure.
     */
    public function create_job( $module_id, $user_id, $module_config_json, $input_data_json ) {
        global $wpdb;

        // Basic validation
        if ( empty( $module_id ) || empty( $user_id ) || ! is_string( $module_config_json ) ) {
            		// Debug logging removed for production
            return false;
        }

        $data = array(
            'module_id' => absint( $module_id ),
            'user_id' => absint( $user_id ),
            'status' => 'pending',
            'module_config' => $module_config_json,
            'input_data' => is_string( $input_data_json ) ? $input_data_json : null, // Ensure input data is string or null
            'created_at' => current_time( 'mysql', 1 ), // GMT time
        );

        $format = array(
            '%d', // module_id
            '%d', // user_id
            '%s', // status
            '%s', // module_config
            '%s', // input_data
            '%s', // created_at
        );

        $inserted = $wpdb->insert( $this->table_name, $data, $format );

        if ( false === $inserted ) {
            		// Debug logging removed for production
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Create the jobs database table on plugin activation.
     *
     * @since 0.13.0 // Match the class version
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dm_jobs';
        $charset_collate = $wpdb->get_charset_collate();

        // We need dbDelta()
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $sql = "CREATE TABLE $table_name (
            job_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            module_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending', /* pending, processing, complete, failed */
            module_config longtext NULL, /* JSON config used for this specific job run */
            input_data longtext NULL, /* JSON input data used */
            result_data longtext NULL, /* JSON output/result/error */
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            started_at datetime NULL DEFAULT NULL,
            completed_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (job_id),
            KEY status (status),
            KEY module_id (module_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        dbDelta( $sql );

        // Note: We might not need to add default jobs here unlike default modules/projects.
    }

    /**
     * Get a specific job record by its ID.
     *
     * @param int $job_id The job ID.
     * @return object|null The job data as an object, or null if not found.
     */
    public function get_job( int $job_id ) {
        global $wpdb;
        if ( empty( $job_id ) ) {
            return null;
        }
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE job_id = %d",
            $job_id
        );
        $job = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $this->table_name is safe
        return $job;
    }

    /**
     * Update the status and started_at time for a job.
     *
     * @param int    $job_id The job ID.
     * @param string $status The new status (e.g., 'processing').
     * @return bool True on success, false on failure.
     */
    public function start_job( int $job_id, string $status = 'processing' ): bool {
        global $wpdb;
        if ( empty( $job_id ) ) {
            return false;
        }
        $updated = $wpdb->update(
            $this->table_name,
            [
                'status' => $status,
                'started_at' => current_time( 'mysql', 1 ), // GMT time
            ],
            ['job_id' => $job_id],
            ['%s', '%s'], // Format for data
            ['%d']  // Format for WHERE
        );
        return $updated !== false;
    }

    /**
     * Update the status, result_data, and completed_at time for a job.
     * Also updates the last_run_at field in the related project if possible.
     *
     * @param int    $job_id       The job ID.
     * @param string $status       The final status ('complete' or 'failed').
     * @param string|null $result_data JSON string of the result or error details.
     * @return bool True on success, false on failure.
     */
    public function complete_job( int $job_id, string $status, ?string $result_data ): bool {
        global $wpdb;
        // Update validation to include all final statuses
        $valid_statuses = ['completed', 'failed', 'completed_with_errors', 'completed_no_items'];

		// --- START: Add Detailed Logging Inside complete_job ---
		// Debug logging removed for production
		// --- END: Add Detailed Logging ---

        if ( empty( $job_id ) || !in_array( $status, $valid_statuses ) ) {
            // Debug logging removed for production
            return false;
        }
        $updated = $wpdb->update(
            $this->table_name,
            [
                'status' => $status,
                'result_data' => $result_data, // Store final result or error info
                'completed_at' => current_time( 'mysql', 1 ), // GMT time
            ],
            ['job_id' => $job_id],
            ['%s', '%s', '%s'], // Format for data
            ['%d']  // Format for WHERE
        );
         if ( false === $updated ) {
            // Debug logging removed for production
        } else {
            // --- Update last_run_at in the related project ---
            // 1. Get module_id for this job
            $job = $this->get_job($job_id);
            if ($job && !empty($job->module_id)) {
                // 2. Get project_id from modules table
                $modules_table = $wpdb->prefix . 'dm_modules';
                $project_id = $wpdb->get_var($wpdb->prepare("SELECT project_id FROM $modules_table WHERE module_id = %d", $job->module_id));
                if ($project_id) {
                    // 3. Update last_run_at in projects table
                    $projects_table = $wpdb->prefix . 'dm_projects';
                    $project_updated = $wpdb->update(
                        $projects_table,
                        ['last_run_at' => current_time('mysql', 1)],
                        ['project_id' => $project_id],
                        ['%s'],
                        ['%d']
                    );
                    if (false === $project_updated) {
                        // Debug logging removed for production
                    }
                }
            }
        }
        return $updated !== false;
    }

    /**
     * Updates the input_data for a specific job.
     * Used after fetching data for config-based jobs.
     *
     * @param int    $job_id          The job ID.
     * @param string $input_data_json JSON string of the processed input data.
     * @return bool True on success, false on failure.
     */
    public function update_job_input_data( int $job_id, string $input_data_json ): bool {
        global $wpdb;
        if ( empty( $job_id ) ) {
            return false;
        }
        $updated = $wpdb->update(
            $this->table_name,
            [
                'input_data' => $input_data_json,
            ],
            ['job_id' => $job_id],
            ['%s'], // Format for data
            ['%d']  // Format for WHERE
        );
        if ( false === $updated ) {
            // Debug logging removed for production
        }
        return $updated !== false;
    }

    // TODO: Add methods for get_job, update_job_status etc. if needed elsewhere.

} 