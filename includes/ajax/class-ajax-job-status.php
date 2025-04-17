<?php
/**
 * Handles AJAX job status polling and stepwise logging for Data Machine.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Data_Machine_Ajax_Job_Status {

    public function __construct() {
        add_action('wp_ajax_dm_check_job_status', array($this, 'dm_check_job_status_ajax_handler'));
    }

    /**
     * AJAX handler for polling job status and returning stepwise log.
     */
    public function dm_check_job_status_ajax_handler() {
        check_ajax_referer( 'dm_check_status_nonce', 'nonce' );

        $job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
        $user_id = get_current_user_id();

        if ( empty( $job_id ) || empty( $user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing job ID or user ID.', 'data-machine' ) ) );
            return;
        }

        global $wpdb;
        $jobs_table = $wpdb->prefix . 'dm_jobs';

        $job = $wpdb->get_row( $wpdb->prepare(
            "SELECT job_id, user_id, status, result_data, created_at, started_at, completed_at
             FROM $jobs_table WHERE job_id = %d",
            $job_id
        ) );

        if ( ! $job ) {
            wp_send_json_error( array( 'message' => __( 'Job not found.', 'data-machine' ) ) );
            return;
        }

        if ( $job->user_id != $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'data-machine' ) ) );
            return;
        }

        // Step logging removed - detailed logs are now in debug files.
        $job_steps_output = [];

        $response_data = array(
            'job_id' => $job->job_id,
            'status' => $job->status,
            'result' => null,
            'job_steps' => $job_steps_output
        );

        if ( $job->status === 'complete' || $job->status === 'failed' ) {
            $response_data['result'] = json_decode( wp_unslash( $job->result_data ), true );
        }

        wp_send_json_success( $response_data );
    }
}

// Register the handler if this file is included directly
if (defined('DOING_AJAX') && DOING_AJAX) {
    new Data_Machine_Ajax_Job_Status();
}