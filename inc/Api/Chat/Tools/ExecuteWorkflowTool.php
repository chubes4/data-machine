<?php
/**
 * Execute Workflow Tool
 *
 * Chat tool for executing content automation workflows.
 * Passes workflow steps directly to the Execute API endpoint.
 *
 * @package DataMachine\Api\Chat\Tools
 * @since 0.3.0
 */

namespace DataMachine\Api\Chat\Tools;

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Services\StepTypeService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ExecuteWorkflowTool {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool( 'chat', 'execute_workflow', array( $this, 'getToolDefinition' ) );
	}

	/**
	 * Get tool definition.
	 * Called lazily when tool is first accessed to ensure translations are loaded.
	 *
	 * @return array Tool definition array
	 */
	public function getToolDefinition(): array {
		$step_type_service = new StepTypeService();
		$step_types        = $step_type_service->getAll();
		$type_slugs        = ! empty( $step_types ) ? array_keys( $step_types ) : array( 'fetch', 'ai', 'publish', 'update' );
		$types_list        = implode( '|', $type_slugs );

		$description = 'Execute an ephemeral workflow (not saved to database).

STEP FORMAT: {type: "' . $types_list . '", handler_slug, handler_config, user_message?, system_prompt?}

Use api_query GET /datamachine/v1/handlers/{slug} for handler_config fields.

EXAMPLE:
[
  {"type": "fetch", "handler_slug": "rss", "handler_config": {"feed_url": "..."}},
  {"type": "ai", "user_message": "Summarize for social media"},
  {"type": "publish", "handler_slug": "wordpress_publish", "handler_config": {"post_type": "post"}}
]';

		return array(
			'class'       => self::class,
			'method'      => 'handle_tool_call',
			'description' => $description,
			'parameters'  => array(
				'steps' => array(
					'type'        => 'array',
					'required'    => true,
					'description' => 'Step objects: {type, handler_slug, handler_config}. AI steps: {type: "ai", user_message}.',
				),
			),
		);
	}

	/**
	 * Execute the workflow.
	 *
	 * @param array $parameters Tool parameters containing steps
	 * @param array $tool_def Tool definition (unused)
	 * @return array Execution result
	 */
	public function handle_tool_call( array $parameters, array $tool_def = array() ): array {
		$steps = $parameters['steps'] ?? array();

		if ( empty( $steps ) ) {
			return array(
				'success'   => false,
				'error'     => 'Workflow must contain at least one step',
				'tool_name' => 'execute_workflow',
			);
		}

		$request = new \WP_REST_Request( 'POST', '/datamachine/v1/execute' );
		$request->set_body_params(
			array(
				'workflow' => array( 'steps' => $steps ),
			)
		);

		$response = rest_do_request( $request );
		$data     = $response->get_data();
		$status   = $response->get_status();

		if ( $response->is_error() ) {
			$error = $response->as_error();
			do_action(
				'datamachine_log',
				'error',
				'ExecuteWorkflowTool: REST request failed',
				array(
					'error' => $error->get_error_message(),
					'steps' => $steps,
				)
			);
			return array(
				'success'   => false,
				'error'     => $error->get_error_message(),
				'tool_name' => 'execute_workflow',
			);
		}

		if ( $status >= 400 ) {
			$error_message = $data['message'] ?? 'Execution failed';
			do_action(
				'datamachine_log',
				'error',
				'ExecuteWorkflowTool: Execution failed',
				array(
					'status' => $status,
					'error'  => $error_message,
					'data'   => $data,
				)
			);
			return array(
				'success'   => false,
				'error'     => $error_message,
				'tool_name' => 'execute_workflow',
			);
		}

		return array(
			'success'   => true,
			'data'      => $data,
			'tool_name' => 'execute_workflow',
		);
	}
}
