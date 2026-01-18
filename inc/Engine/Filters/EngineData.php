<?php
/**
 * Engine data snapshot helpers.
 *
 * @package DataMachine\Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persist a complete engine data snapshot for a job.
 */
function datamachine_set_engine_data( int $job_id, array $snapshot ): bool {
	if ( $job_id <= 0 ) {
		return false;
	}

	$db_jobs = new \DataMachine\Core\Database\Jobs\Jobs();
	$success = $db_jobs->store_engine_data( $job_id, $snapshot );

	if ( $success ) {
		wp_cache_set( $job_id, $snapshot, 'datamachine_engine_data' );
	}

	return $success;
}

/**
 * Merge new data into the stored engine snapshot.
 */
function datamachine_merge_engine_data( int $job_id, array $data ): bool {
	if ( $job_id <= 0 ) {
		return false;
	}

	$current = datamachine_get_engine_data( $job_id );
	$merged  = array_replace_recursive( $current, $data );

	return datamachine_set_engine_data( $job_id, $merged );
}

/**
 * Retrieve engine data snapshot for a job.
 */
function datamachine_get_engine_data( int $job_id ): array {
	if ( $job_id <= 0 ) {
		return array();
	}

	$cached = wp_cache_get( $job_id, 'datamachine_engine_data' );
	if ( $cached !== false ) {
		return is_array( $cached ) ? $cached : array();
	}

	$db_jobs     = new \DataMachine\Core\Database\Jobs\Jobs();
	$engine_data = $db_jobs->retrieve_engine_data( $job_id );

	if ( ! is_array( $engine_data ) ) {
		$engine_data = array();
	}

	wp_cache_set( $job_id, $engine_data, 'datamachine_engine_data' );

	return $engine_data;
}
