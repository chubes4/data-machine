<?php
/**
 * AJAX handler for Data Machine Dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Data_Machine_Dashboard_Ajax {
    /**
     * @var Data_Machine_Database_Dashboard
     */
    private $dashboard_db;

    public function __construct() {
        $this->dashboard_db = new Data_Machine_Database_Dashboard();
        add_action( 'wp_ajax_dm_dashboard_get_scheduled_runs', [ $this, 'handle_get_scheduled_runs' ] );
        add_action( 'wp_ajax_dm_dashboard_get_recent_successful_jobs', [ $this, 'handle_get_recent_successful_jobs' ] );
        add_action( 'wp_ajax_dm_dashboard_get_recent_failed_jobs', [ $this, 'handle_get_recent_failed_jobs' ] );
        add_action( 'wp_ajax_dm_dashboard_get_total_completed_jobs', [ $this, 'handle_get_total_completed_jobs' ] );
    }

    /**
     * Validate AJAX request (nonce and capability).
     */
    private function validate_ajax_request() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
        }
        $nonce = isset( $_POST['dm_dashboard_nonce'] ) ? sanitize_text_field( $_POST['dm_dashboard_nonce'] ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dm_dashboard_nonce' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce.' ], 403 );
        }
    }

    /**
     * Handle AJAX request for upcoming scheduled runs.
     */
    public function handle_get_scheduled_runs() {
        $this->validate_ajax_request();
        $project_id = isset( $_POST['project_id'] ) && $_POST['project_id'] !== 'all' ? intval( $_POST['project_id'] ) : null;
        $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 10;
        $result = $this->dashboard_db->get_upcoming_scheduled_runs( $limit, $project_id );
        wp_send_json_success( $result );
    }

    /**
     * Handle AJAX request for recent successful jobs.
     */
    public function handle_get_recent_successful_jobs() {
        $this->validate_ajax_request();
        $project_id = isset( $_POST['project_id'] ) && $_POST['project_id'] !== 'all' ? intval( $_POST['project_id'] ) : null;
        $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 10;
        $result = $this->dashboard_db->get_recent_successful_jobs( $limit, $project_id );
        wp_send_json_success( $result );
    }

    /**
     * Handle AJAX request for recent failed jobs.
     */
    public function handle_get_recent_failed_jobs() {
        $this->validate_ajax_request();
        $project_id = isset( $_POST['project_id'] ) && $_POST['project_id'] !== 'all' ? intval( $_POST['project_id'] ) : null;
        $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 10;
        $result = $this->dashboard_db->get_recent_failed_jobs( $limit, $project_id );
        wp_send_json_success( $result );
    }

    /**
     * Handle AJAX request for total completed jobs.
     */
    public function handle_get_total_completed_jobs() {
        $this->validate_ajax_request();
        $project_id = isset( $_POST['project_id'] ) && $_POST['project_id'] !== 'all' ? intval( $_POST['project_id'] ) : null;
        $result = $this->dashboard_db->get_total_completed_job_count( $project_id );
        wp_send_json_success( $result );
    }
} 