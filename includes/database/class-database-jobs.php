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
     * Initialize the class.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_jobs';
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
            error_log('Data Machine DB Jobs: Invalid parameters for create_job.');
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
            error_log( 'Data Machine DB Jobs: Failed to insert job. DB Error: ' . $wpdb->last_error );
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

    // TODO: Add methods for get_job, update_job_status etc. if needed elsewhere.

} 