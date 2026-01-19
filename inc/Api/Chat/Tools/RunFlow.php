<?php
/**
 * Run Flow Tool
 *
 * Tool for executing existing flows immediately or scheduling delayed execution.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class RunFlow {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool( 'chat', 'run_flow', array( $this, 'getToolDefinition' ) );
	}

	/**
	 * Get tool definition.
	 * Called lazily when tool is first accessed to ensure translations are loaded.
	 *
	 * @return array Tool definition array
	 */
	public function getToolDefinition(): array {
		return array(
			'class'       => self::class,
			'method'      => 'handle_tool_call',
			'description' => 'Execute an existing flow immediately or schedule it for later. For IMMEDIATE execution: provide only flow_id (do NOT include timestamp). For SCHEDULED execution: provide flow_id AND a future Unix timestamp. Flows run asynchronously in the background. Use api_query with GET /datamachine/v1/jobs/{job_id} to check execution status.',
			'parameters'  => array(
				'flow_id'   => array(
					'type'        => 'integer',
					'required'    => true,
					'description' => 'Flow ID to execute',
				),
				'count'     => array(
					'type'        => 'integer',
					'required'    => false,
					'description' => 'Number of times to run the flow (1-10, default 1). Each run spawns an independent job. Use this to process multiple items from a source.',
				),
				'timestamp' => array(
					'type'        => 'integer',
					'required'    => false,
					'description' => 'ONLY for scheduled execution: a future Unix timestamp. OMIT this parameter entirely for immediate execution. Cannot be combined with count > 1.',
				),
			),
		);
	}

	public function handle_tool_call( array $parameters, array $tool_def = array() ): array {
		$flow_id = $parameters['flow_id'] ?? null;

		if ( ! is_numeric( $flow_id ) || (int) $flow_id <= 0 ) {
			return array(
				'success'   => false,
				'error'     => 'flow_id is required and must be a positive integer',
				'tool_name' => 'run_flow',
			);
		}

		$flow_id        = (int) $flow_id;
		$count          = $parameters['count'] ?? 1;
		$count          = max( 1, min( 10, (int) $count ) );
		$timestamp      = $parameters['timestamp'] ?? null;
		$execution_type = 'immediate';

		if ( ! empty( $timestamp ) && is_numeric( $timestamp ) && (int) $timestamp > time() ) {
			$timestamp      = (int) $timestamp;
			$execution_type = 'delayed';

			if ( $count > 1 ) {
				return array(
					'success'   => false,
					'error'     => 'Cannot schedule multiple runs with a timestamp. Use count only for immediate execution.',
					'tool_name' => 'run_flow',
				);
			}
		} else {
			$timestamp = null;
		}

		$jobs      = array();
		$flow_name = null;

		for ( $i = 0; $i < $count; $i++ ) {
			$body_params = array( 'flow_id' => $flow_id );
			if ( null !== $timestamp ) {
				$body_params['timestamp'] = $timestamp;
			}

			$request = new \WP_REST_Request( 'POST', '/datamachine/v1/execute' );
			$request->set_body_params( $body_params );

			$response = rest_do_request( $request );
			$data     = $response->get_data();
			$status   = $response->get_status();

			if ( $status >= 400 ) {
				$error_message = $data['message'] ?? 'Failed to execute flow';
				if ( empty( $jobs ) ) {
					return array(
						'success'   => false,
						'error'     => $error_message,
						'tool_name' => 'run_flow',
					);
				}
				break;
			}

			if ( isset( $data['data']['job_id'] ) ) {
				$jobs[] = $data['data']['job_id'];
			}
			if ( null === $flow_name && isset( $data['data']['flow_name'] ) ) {
				$flow_name = $data['data']['flow_name'];
			}
		}

		if ( 1 === $count ) {
			$response_data = array(
				'flow_id'        => $flow_id,
				'execution_type' => $execution_type,
				'message'        => 'immediate' === $execution_type
					? 'Flow queued for immediate background execution. It will start within seconds. Use job_id to check status.'
					: 'Flow scheduled for delayed background execution at the specified time.',
			);
			if ( ! empty( $jobs ) ) {
				$response_data['job_id'] = $jobs[0];
			}
			if ( null !== $flow_name ) {
				$response_data['flow_name'] = $flow_name;
			}
		} else {
			$response_data = array(
				'flow_id'        => $flow_id,
				'execution_type' => $execution_type,
				'count'          => count( $jobs ),
				'job_ids'        => $jobs,
				'message'        => sprintf(
					'Queued %d jobs for flow "%s". Each job will process one item independently.',
					count( $jobs ),
					$flow_name ?? "ID {$flow_id}"
				),
			);
			if ( null !== $flow_name ) {
				$response_data['flow_name'] = $flow_name;
			}
		}

		return array(
			'success'   => true,
			'data'      => $response_data,
			'tool_name' => 'run_flow',
		);
	}
}
