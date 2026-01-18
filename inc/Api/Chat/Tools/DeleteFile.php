<?php
/**
 * Delete File Tool
 *
 * Focused tool for deleting uploaded files.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class DeleteFile {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool( 'chat', 'delete_file', array( $this, 'getToolDefinition' ) );
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
			'description' => 'Delete an uploaded file.',
			'parameters'  => array(
				'filename' => array(
					'type'        => 'string',
					'required'    => true,
					'description' => 'Name of the file to delete',
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
		$filename = $parameters['filename'] ?? null;

		if ( empty( $filename ) ) {
			return array(
				'success'   => false,
				'error'     => 'filename is required',
				'tool_name' => 'delete_file',
			);
		}

		$filename = sanitize_file_name( $filename );

		$request  = new \WP_REST_Request( 'DELETE', '/datamachine/v1/files/' . $filename );
		$response = rest_do_request( $request );
		$data     = $response->get_data();
		$status   = $response->get_status();

		if ( $status >= 400 ) {
			return array(
				'success'   => false,
				'error'     => $data['message'] ?? 'Failed to delete file',
				'tool_name' => 'delete_file',
			);
		}

		return array(
			'success'   => true,
			'data'      => array(
				'filename' => $filename,
				'message'  => 'File deleted.',
			),
			'tool_name' => 'delete_file',
		);
	}
}
