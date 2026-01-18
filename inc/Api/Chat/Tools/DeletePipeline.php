<?php
/**
 * Delete Pipeline Tool
 *
 * Focused tool for deleting pipelines.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class DeletePipeline {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool( 'chat', 'delete_pipeline', array( $this, 'getToolDefinition' ) );
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
			'description' => 'Delete a pipeline and all its associated flows.',
			'parameters'  => array(
				'pipeline_id' => array(
					'type'        => 'integer',
					'required'    => true,
					'description' => 'ID of the pipeline to delete',
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
		$pipeline_id = $parameters['pipeline_id'] ?? null;

		if ( ! is_numeric( $pipeline_id ) || (int) $pipeline_id <= 0 ) {
			return array(
				'success'   => false,
				'error'     => 'pipeline_id is required and must be a positive integer',
				'tool_name' => 'delete_pipeline',
			);
		}

		$pipeline_id = (int) $pipeline_id;

		$request  = new \WP_REST_Request( 'DELETE', '/datamachine/v1/pipelines/' . $pipeline_id );
		$response = rest_do_request( $request );
		$data     = $response->get_data();
		$status   = $response->get_status();

		if ( $status >= 400 ) {
			return array(
				'success'   => false,
				'error'     => $data['message'] ?? 'Failed to delete pipeline',
				'tool_name' => 'delete_pipeline',
			);
		}

		return array(
			'success'   => true,
			'data'      => array(
				'pipeline_id' => $pipeline_id,
				'message'     => 'Pipeline and all associated flows deleted.',
			),
			'tool_name' => 'delete_pipeline',
		);
	}
}
