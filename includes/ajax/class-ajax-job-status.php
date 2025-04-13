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
            "SELECT job_id, user_id, status, result_data, started_at, completed_at,
                    step1_initial_request, step1_initial_response,
                    step2_factcheck_request, step2_factcheck_response,
                    step3_finalize_request, step3_finalize_response
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

        // Construct the job_steps array for the frontend
        $job_steps_output = [];
        if ($job->step1_initial_request || $job->step1_initial_response) {
            $job_steps_output[] = [
                'step' => 'initial_processing',
                'request' => $job->step1_initial_request ? json_decode(wp_unslash($job->step1_initial_request), true) : null,
                'response' => $job->step1_initial_response ? json_decode(wp_unslash($job->step1_initial_response), true) : null,
                'timestamp' => $job->started_at
            ];
        }
        if ($job->step2_factcheck_request || $job->step2_factcheck_response) {
            $step2_request = $job->step2_factcheck_request ? json_decode(wp_unslash($job->step2_factcheck_request), true) : null;
            $step2_response = $job->step2_factcheck_response ? json_decode(wp_unslash($job->step2_factcheck_response), true) : null;
            $job_steps_output[] = [
                'step' => 'fact_check',
                'request' => $step2_request,
                'response' => $step2_response,
                'timestamp' => $job->started_at
            ];
        }
        if ($job->step3_finalize_request || $job->step3_finalize_response) {
            $step3_request = $job->step3_finalize_request ? json_decode(wp_unslash($job->step3_finalize_request), true) : null;
            $step3_response = $job->step3_finalize_response ? json_decode(wp_unslash($job->step3_finalize_response), true) : null;
            $job_steps_output[] = [
                'step' => 'finalize',
                'request' => $step3_request,
                'response' => $step3_response,
                'timestamp' => $job->started_at
            ];
        }

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