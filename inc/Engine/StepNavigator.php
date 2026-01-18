<?php
/**
 * Step Navigation Service
 *
 * Handles navigation logic for determining next/previous steps during flow execution.
 * Uses engine_data for optimal performance during execution.
 *
 * @package DataMachine\Engine
 * @since 0.2.1
 */

namespace DataMachine\Engine;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class StepNavigator {

	/**
	 * Load flow configuration from engine data storage.
	 */
	private function getFlowConfig( int $job_id ): array {
		if ( $job_id <= 0 ) {
			return array();
		}

		$engine_data = datamachine_get_engine_data( $job_id );
		return is_array( $engine_data['flow_config'] ?? null ) ? $engine_data['flow_config'] : array();
	}

	/**
	 * Get next flow step ID based on execution order
	 *
	 * Uses centralized engine data for execution context.
	 *
	 * @param string $flow_step_id Current flow step ID
	 * @param array  $context Context containing job_id
	 * @return string|null Next flow step ID or null if none
	 */
	public function get_next_flow_step_id( string $flow_step_id, array $context = array() ): ?string {
		$job_id = (int) ( $context['job_id'] ?? 0 );
		if ( $job_id <= 0 ) {
			return null;
		}

		$flow_config = $this->getFlowConfig( $job_id );

		$current_step = $flow_config[ $flow_step_id ] ?? null;
		if ( ! $current_step ) {
			return null;
		}

		$current_order = $current_step['execution_order'] ?? -1;
		$next_order    = $current_order + 1;

		foreach ( $flow_config as $step_id => $step ) {
			if ( ( $step['execution_order'] ?? -1 ) === $next_order ) {
				return $step_id;
			}
		}

		return null;
	}

	/**
	 * Get previous flow step ID based on execution order
	 *
	 * Uses centralized engine data for execution context.
	 *
	 * @param string $flow_step_id Current flow step ID
	 * @param array  $context Context containing job_id
	 * @return string|null Previous flow step ID or null if none
	 */
	public function get_previous_flow_step_id( string $flow_step_id, array $context = array() ): ?string {
		$job_id = (int) ( $context['job_id'] ?? 0 );
		if ( $job_id <= 0 ) {
			return null;
		}

		$flow_config = $this->getFlowConfig( $job_id );

		$current_step = $flow_config[ $flow_step_id ] ?? null;
		if ( ! $current_step ) {
			return null;
		}

		$current_order = $current_step['execution_order'] ?? -1;
		$prev_order    = $current_order - 1;

		foreach ( $flow_config as $step_id => $step ) {
			if ( ( $step['execution_order'] ?? -1 ) === $prev_order ) {
				return $step_id;
			}
		}

		return null;
	}
}
