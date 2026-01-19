<?php
/**
 * Create Flow Tool
 *
 * Focused tool for creating flow instances from existing pipelines.
 * Automatically syncs pipeline steps to the new flow.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Services\FlowManager;
use DataMachine\Services\FlowStepManager;
use DataMachine\Services\HandlerService;
use DataMachine\Core\Database\Flows\Flows as FlowsDB;

class CreateFlow {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool( 'chat', 'create_flow', array( $this, 'getToolDefinition' ) );
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
			'description' => 'Create a new flow for a pipeline with optional step configurations. Query existing flows first to learn established patterns. Use pipeline_step_ids from the pipeline response for step_configs keys.',
			'parameters'  => array(
				'pipeline_id'       => array(
					'type'        => 'integer',
					'required'    => true,
					'description' => 'Pipeline ID',
				),
				'flow_name'         => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Flow name (defaults to "Flow")',
				),
				'scheduling_config' => array(
					'type'        => 'object',
					'required'    => false,
					'description' => 'Schedule: {interval: value}. Valid intervals:' . "\n" . SchedulingDocumentation::getIntervalsJson(),
				),
				'step_configs'      => array(
					'type'        => 'object',
					'required'    => false,
					'description' => 'Step configurations keyed by pipeline_step_id: {handler_slug?, handler_config?, user_message?}',
				),
			),
		);
	}

	public function handle_tool_call( array $parameters, array $tool_def = array() ): array {
		$pipeline_id = $parameters['pipeline_id'] ?? null;

		if ( ! is_numeric( $pipeline_id ) || (int) $pipeline_id <= 0 ) {
			return array(
				'success'   => false,
				'error'     => 'pipeline_id is required and must be a positive integer',
				'tool_name' => 'create_flow',
			);
		}

		$pipeline_id       = (int) $pipeline_id;
		$flow_name         = $parameters['flow_name'] ?? 'Flow';
		$scheduling_config = $parameters['scheduling_config'] ?? array( 'interval' => 'manual' );

		$validation = $this->validateSchedulingConfig( $scheduling_config );
		if ( true !== $validation ) {
			return array(
				'success'   => false,
				'error'     => $validation,
				'tool_name' => 'create_flow',
			);
		}

		$flows_db       = new FlowsDB();
		$existing_flows = $flows_db->get_flows_for_pipeline( $pipeline_id );

		foreach ( $existing_flows as $existing_flow ) {
			if ( strcasecmp( $existing_flow['flow_name'], $flow_name ) === 0 ) {
				$flow_config   = $existing_flow['flow_config'] ?? array();
				$flow_step_ids = array_keys( $flow_config );

				return array(
					'success'   => true,
					'data'      => array(
						'flow_id'        => $existing_flow['flow_id'],
						'flow_name'      => $existing_flow['flow_name'],
						'pipeline_id'    => $pipeline_id,
						'flow_step_ids'  => $flow_step_ids,
						'already_exists' => true,
						'message'        => "Flow '{$existing_flow['flow_name']}' already exists for this pipeline. Use configure_flow_steps to modify it, or specify a different flow_name to create a new flow.",
					),
					'tool_name' => 'create_flow',
				);
			}
		}

		$flow_manager = new FlowManager();
		$result       = $flow_manager->create(
			$pipeline_id,
			$flow_name,
			array(
				'scheduling_config' => $scheduling_config,
			)
		);

		if ( ! $result ) {
			return array(
				'success'   => false,
				'error'     => 'Failed to create flow. Verify the pipeline_id exists and you have sufficient permissions.',
				'tool_name' => 'create_flow',
			);
		}

		$flow_config   = $result['flow_data']['flow_config'] ?? array();
		$flow_step_ids = array_keys( $flow_config );

		// Apply step configurations if provided
		$config_results = array(
			'applied' => array(),
			'errors'  => array(),
		);
		if ( ! empty( $parameters['step_configs'] ) ) {
			$config_results = $this->applyStepConfigs( $result['flow_id'], $parameters['step_configs'] );
		}

		$response_data = array(
			'flow_id'       => $result['flow_id'],
			'flow_name'     => $result['flow_name'],
			'pipeline_id'   => $result['pipeline_id'],
			'synced_steps'  => $result['synced_steps'],
			'flow_step_ids' => $flow_step_ids,
			'scheduling'    => $scheduling_config['interval'],
		);

		if ( ! empty( $config_results['applied'] ) ) {
			$response_data['configured_steps'] = $config_results['applied'];
		}

		if ( ! empty( $config_results['errors'] ) ) {
			$response_data['configuration_errors'] = $config_results['errors'];
		}

		$response_data['message'] = empty( $parameters['step_configs'] )
			? 'Flow created. Use configure_flow_steps to set handler configurations.'
			: ( empty( $config_results['errors'] )
				? 'Flow created and configured.'
				: 'Flow created with some configuration errors.' );

		return array(
			'success'   => true,
			'data'      => $response_data,
			'tool_name' => 'create_flow',
		);
	}

	/**
	 * Apply step configurations to a newly created flow.
	 *
	 * @param int   $flow_id Flow ID
	 * @param array $step_configs Configs keyed by pipeline_step_id
	 * @return array{applied: array, errors: array}
	 */
	private function applyStepConfigs( int $flow_id, array $step_configs ): array {
		$flow_step_manager = new FlowStepManager();
		$handler_service   = new HandlerService();
		$applied           = array();
		$errors            = array();

		foreach ( $step_configs as $pipeline_step_id => $config ) {
			$flow_step_id = $pipeline_step_id . '_' . $flow_id;
			$step_applied = false;

			// Apply handler_slug + handler_config if provided
			if ( ! empty( $config['handler_slug'] ) ) {
				$validation = $handler_service->validate( $config['handler_slug'] );
				if ( ! $validation['valid'] ) {
					$errors[] = array(
						'pipeline_step_id' => $pipeline_step_id,
						'error'            => $validation['error'],
					);
					continue;
				}

				$handler_config = $config['handler_config'] ?? array();
				$success        = $flow_step_manager->updateHandler(
					$flow_step_id,
					$config['handler_slug'],
					$handler_config
				);

				if ( ! $success ) {
					$errors[] = array(
						'pipeline_step_id' => $pipeline_step_id,
						'error'            => 'Failed to update handler',
					);
					continue;
				}
				$step_applied = true;
			}

			// Apply user_message if provided
			if ( ! empty( $config['user_message'] ) ) {
				$success = $flow_step_manager->updateUserMessage(
					$flow_step_id,
					$config['user_message']
				);

				if ( ! $success ) {
					$errors[] = array(
						'pipeline_step_id' => $pipeline_step_id,
						'error'            => 'Failed to update user_message',
					);
					continue;
				}
				$step_applied = true;
			}

			if ( $step_applied ) {
				$applied[] = $flow_step_id;
			}
		}

		return array(
			'applied' => $applied,
			'errors'  => $errors,
		);
	}

	private function validateSchedulingConfig( array $config ): bool|string {
		if ( empty( $config ) ) {
			return true;
		}

		$interval = $config['interval'] ?? null;

		if ( null === $interval ) {
			return 'scheduling_config requires an interval property';
		}

		$intervals       = array_keys( apply_filters( 'datamachine_scheduler_intervals', array() ) );
		$valid_intervals = array_merge( array( 'manual', 'one_time' ), $intervals );
		if ( ! in_array( $interval, $valid_intervals, true ) ) {
			return 'Invalid interval. Must be one of: ' . implode( ', ', $valid_intervals );
		}

		if ( 'one_time' === $interval ) {
			$timestamp = $config['timestamp'] ?? null;
			if ( ! is_numeric( $timestamp ) || (int) $timestamp <= 0 ) {
				return 'one_time interval requires a valid unix timestamp';
			}
		}

		return true;
	}
}
