<?php
/**
 * Delete Pipeline Step Tool
 *
 * Focused tool for removing steps from pipelines.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class DeletePipelineStep {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool( 'chat', 'delete_pipeline_step', array( $this, 'getToolDefinition' ) );
	}

	/**
	 * Get tool definition.
	 *
	 * @return array Tool definition array
	 */
	public function getToolDefinition(): array {
		return array(
			'class'       => self::class,
			'method'      => 'handle_tool_call',
			'description' => 'Remove a step from a pipeline. This removes the step from all flows on the pipeline.',
			'parameters'  => array(
				'pipeline_id'      => array(
					'type'        => 'integer',
					'required'    => true,
					'description' => 'ID of the pipeline containing the step',
				),
				'pipeline_step_id' => array(
					'type'        => 'string',
					'required'    => true,
					'description' => 'ID of the pipeline step to remove',
				),
			),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $parameters Tool call parameters
	 * @param array $tool_def Tool definition
	 * @return array Tool execution result
	 */
	public function handle_tool_call( array $parameters, array $tool_def = array() ): array {
		$pipeline_id      = $parameters['pipeline_id'] ?? null;
		$pipeline_step_id = $parameters['pipeline_step_id'] ?? null;

		if ( ! is_numeric( $pipeline_id ) || (int) $pipeline_id <= 0 ) {
			return array(
				'success'   => false,
				'error'     => 'pipeline_id is required and must be a positive integer',
				'tool_name' => 'delete_pipeline_step',
			);
		}

		if ( empty( $pipeline_step_id ) ) {
			return array(
				'success'   => false,
				'error'     => 'pipeline_step_id is required',
				'tool_name' => 'delete_pipeline_step',
			);
		}

		$pipeline_id      = (int) $pipeline_id;
		$pipeline_step_id = sanitize_text_field( $pipeline_step_id );

		$request  = new \WP_REST_Request( 'DELETE', '/datamachine/v1/pipelines/' . $pipeline_id . '/steps/' . $pipeline_step_id );
		$response = rest_do_request( $request );
		$data     = $response->get_data();
		$status   = $response->get_status();

		if ( $status >= 400 ) {
			return array(
				'success'   => false,
				'error'     => $data['message'] ?? 'Failed to delete pipeline step',
				'tool_name' => 'delete_pipeline_step',
			);
		}

		return array(
			'success'   => true,
			'data'      => array(
				'pipeline_id'      => $pipeline_id,
				'pipeline_step_id' => $pipeline_step_id,
				'message'          => 'Step removed from pipeline and all associated flows.',
			),
			'tool_name' => 'delete_pipeline_step',
		);
	}
}
